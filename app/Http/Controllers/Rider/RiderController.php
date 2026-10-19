<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\{NotificationModel, Payment, ProofOfDelivery, RiderMessage, Shipment};
use App\Services\{CodCollectionService, NotificationService, RiderAssignmentDecisionService, RiderProofOfDeliveryService};
use App\Services\RiderPortalActionService;
use App\Support\RiderPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RiderController extends Controller
{
    public function dashboard(Request $request)
    {
        $id=$request->user()->id;
        $active=RiderPortal::actionable($id);
        $current=(clone $active)->with(['store','order','deliveryArea'])->orderByRaw("CASE WHEN status = 'out_for_delivery' THEN 0 ELSE 1 END")->oldest('assigned_at')->first();
        $tasks=(clone $active)->with(['store','order','deliveryArea'])->where(fn($q)=>$q->whereDate('assigned_at',today())->orWhereDate('delivery_assigned_at',today()))->oldest('assigned_at')->limit(6)->get();
        $summary=['assigned'=>(clone $active)->whereIn('status',['pickup_assigned','assigned_to_rider','assigned'])->count(),
            'pickup'=>Shipment::where('pickup_rider_id',$id)->whereIn('status',['pickup_assigned','pickup_accepted'])->count(),
            'active'=>Shipment::where('rider_id',$id)->whereIn('status',['in_transit','out_for_delivery'])->count(),
            'completed'=>Shipment::where('rider_id',$id)->whereIn('status',['delivered','completed'])->count(),
            'attention'=>$this->owned($request)->whereIn('status',['delivery_failed','delivery_attempted','exception'])->count()];
        return view('rider.dashboard',compact('current','summary','tasks'));
    }

    public function jobs(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:100','status'=>'nullable|in:'.implode(',',array_merge(Shipment::STATUSES,['active','completed','failed'])),'type'=>'nullable|in:pickup,delivery','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($request->filled('from')?'|after_or_equal:from':'')]);
        $type=$request->route('type')?:$request->type;
        $history=$request->routeIs('rider.history');
        $tracking=$request->routeIs('rider.tracking');
        $shipments=$this->owned($request)->with(['store','order','deliveryArea'])
            ->when($type==='pickup',fn($q)=>$q->where('pickup_rider_id',$request->user()->id))
            ->when($type==='delivery',fn($q)=>$q->where('rider_id',$request->user()->id))
            ->when($history,fn($q)=>$q->where(fn($h)=>$h->whereIn('status',RiderPortal::HISTORY)->orWhere(fn($p)=>$p->where('pickup_rider_id',$request->user()->id)->whereNotNull('pickup_arrived_at'))))
            ->when($request->filled('q'),fn($q)=>$q->where(fn($s)=>$s->where('shipment_number','like','%'.$request->q.'%')->orWhere('parcel_code','like','%'.$request->q.'%')->orWhereHas('order',fn($o)=>$o->where('order_number','like','%'.$request->q.'%'))))
            ->when($request->filled('from'),fn($q)=>$q->whereDate($history?'updated_at':'assigned_at','>=',$request->from))
            ->when($request->filled('to'),fn($q)=>$q->whereDate($history?'updated_at':'assigned_at','<=',$request->to))
            ->when($request->status==='assigned',fn($q)=>$q->whereIn('status',['pickup_assigned','assigned_to_rider','assigned']))
            ->when($request->status==='active',fn($q)=>$q->whereIn('status',['in_transit','out_for_delivery']))
            ->when($request->status==='completed',fn($q)=>$q->whereIn('status',['delivered','completed']))
            ->when($request->status==='failed',fn($q)=>$q->whereIn('status',['delivery_failed','delivery_attempted','exception']))
            ->when($request->filled('status')&&!in_array($request->status,['assigned','active','completed','failed']),fn($q)=>$q->where('status',$request->status))
            ->latest('updated_at')->paginate(12)->withQueryString();
        return view('rider.jobs',compact('shipments','type','history','tracking'));
    }

    public function show(Request $request,Shipment $shipment)
    {
        $this->authorizeShipment($request,$shipment);
        $shipment->load(['store.user','order.payments','sellerOrder.items','vehicle','currentHub','deliveryArea','proofOfDelivery']);
        $shipment->setRelation('events',$shipment->events()->when($shipment->rider_id!==$request->user()->id,fn($q)=>$q->whereIn('status',['ready_for_pickup','pickup_assigned','pickup_accepted','picked_up','pickup_arrived','at_sorting_center']))->oldest('id')->get());
        $actions=RiderPortal::actions($shipment,$request->user()->id);
        $version=RiderPortal::version($shipment);
        return view('rider.show',compact('shipment','actions','version'));
    }

    public function active(Request $request)
    {
        $shipment=RiderPortal::actionable($request->user()->id)->orderByRaw("CASE WHEN status = 'out_for_delivery' THEN 0 ELSE 1 END")->oldest('assigned_at')->first();
        return $shipment?redirect()->route('rider.shipments.show',$shipment):view('rider.active');
    }
    public function tracking(Request $request){return $this->jobs($request);}
    public function track(Request $request,Shipment $shipment){return $this->show($request,$shipment);}

    public function availability(Request $request)
    {
        $data=$request->validate(['availability'=>'required|in:available,off_duty']);$profile=$request->user()->riderProfile;
        abort_unless($profile&&$profile->account_status==='active'&&$request->user()->is_active,403);
        if(RiderPortal::actionable($request->user()->id)->exists())throw ValidationException::withMessages(['availability'=>'Finish your current assignment before changing availability.']);
        $profile->update($data);return back()->with('success','Availability updated.');
    }

    public function decline(Request $request,Shipment $shipment, RiderAssignmentDecisionService $decision)
    {
        $this->authorizeShipment($request,$shipment);$data=$request->validate(['version'=>'nullable|string|size:64','reason'=>'required|in:vehicle_issue,unavailable,emergency,other','details'=>'nullable|required_if:reason,other|string|max:500']);
        $decision->decline($shipment, $request->user(), $data);
        return redirect()->route('rider.jobs')->with('success','Logistics was notified and the Parcel was returned for reassignment.');
    }

    public function parcelAction(Request $request,Shipment $shipment,RiderPortalActionService $service)
    {
        $this->authorizeShipment($request,$shipment);
        $data=$request->validate(['action'=>'required|in:accept_pickup,confirm_pickup,arrive_sorting,accept_delivery,start_delivery,delivered,failed,legacy_pickup,legacy_transit,legacy_out','version'=>'nullable|string|size:64','parcel_code'=>'nullable|required_if:action,confirm_pickup|string|max:100','reason'=>'nullable|required_if:action,failed|in:recipient_unavailable,incorrect_address,reschedule_requested,parcel_issue,payment_issue,other','details'=>'nullable|required_if:reason,other|string|max:500']);
        $service->perform($shipment,$request->user(),$data);
        $message=match($data['action']){'accept_pickup'=>'Pickup accepted successfully.','confirm_pickup','legacy_pickup'=>'Parcel marked as Picked Up.','arrive_sorting'=>'Sorting Center arrival recorded.','accept_delivery'=>'Delivery assignment accepted successfully.','start_delivery','legacy_out'=>'Delivery is now Out for Delivery.','legacy_transit'=>'Parcel is now In Transit.','delivered'=>'Delivery completed successfully.','failed'=>'Delivery issue was reported.'};
        return back()->with('success',$message);
    }

    public function collectCod(Request $request,Shipment $shipment,CodCollectionService $service,RiderPortalActionService $portal)
    {
        $this->authorizeShipment($request,$shipment);
        $request->validate(['version'=>'nullable|string|size:64']);
        $portal->locked($shipment,$request->user(),$request->input('version'),fn($fresh,$user)=>$service->collect($fresh,$user));
        return back()->with('success','COD collection recorded for Logistics remittance confirmation.');
    }

    public function pod(Request $request,Shipment $shipment,RiderPortalActionService $portal, RiderProofOfDeliveryService $proof)
    {
        $this->authorizeShipment($request,$shipment);
        $request->validate(['version'=>'nullable|string|size:64']);
        $proof->submit($request, $shipment, $request->user(), $request->input('version'));
        return back()->with('success','Proof of Delivery submitted. You can now complete the delivery.');
    }
    public function podFile(Request $request,ProofOfDelivery $pod){abort_unless($pod->shipment()->where('rider_id',$request->user()->id)->exists(),403);abort_unless($pod->photo_path&&Storage::disk('local')->exists($pod->photo_path),404);return Storage::disk('local')->download($pod->photo_path);}
    public function licenseFile(Request $request,string $side){abort_unless(in_array($side,['front','back'],true),404);$profile=$request->user()->riderProfile;$path=$side==='front'?$profile?->driver_license_front_path:$profile?->driver_license_back_path;abort_unless($path&&Storage::disk('local')->exists($path),404);return Storage::disk('local')->download($path);}
    public function history(Request $request){return $this->jobs($request);}
    public function pods(Request $request){$pods=ProofOfDelivery::with('shipment.store')->where('submitted_by',$request->user()->id)->whereHas('shipment',fn($q)=>$q->where('rider_id',$request->user()->id))->latest()->paginate(15);return view('rider.pods',compact('pods'));}
    public function cod(Request $request){$payments=Payment::with('order')->where('collected_by',$request->user()->id)->latest('collected_at')->paginate(15);$pending=Payment::where('collected_by',$request->user()->id)->where('remittance_status','pending')->sum('amount');return view('rider.cod',compact('payments','pending'));}
    public function notifications(Request $request){$items=$request->user()->notificationsData()->latest()->paginate(20);return view('rider.notifications',compact('items'));}
    public function openNotification(Request $request,NotificationModel $notification)
    {
        abort_unless($notification->user_id===$request->user()->id,404);
        if($request->isMethod('post'))$notification->markAsRead();
        $link=$notification->link;
        $safe=$link&&str_starts_with($link,url('/rider').'/');
        if($safe&&preg_match('~/shipments/(\d+)~',parse_url($link,PHP_URL_PATH)??'',$matches))$safe=$this->owned($request)->whereKey($matches[1])->exists();
        return redirect($safe?$link:route('rider.notifications'));
    }
    public function readAllNotifications(Request $request){$request->user()->notificationsData()->unread()->update(['read_at'=>now()]);return back()->with('success','Notifications marked as read.');}
    public function messages(Request $request){RiderMessage::where('rider_id',$request->user()->id)->where('sender_id','!=',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);$messages=RiderMessage::with('sender')->where('rider_id',$request->user()->id)->latest()->paginate(30);return view('rider.messages',compact('messages'));}
    public function sendMessage(Request $request){$data=$request->validate(['message'=>'required|string|max:1000']);RiderMessage::create(['rider_id'=>$request->user()->id,'sender_id'=>$request->user()->id,'message'=>$data['message']]);foreach(\App\Models\User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->get() as $manager)NotificationService::send($manager->id,'Rider support message',str($data['message'])->limit(140),'rider_message',route('logistics.riders',['view'=>$request->user()->riderProfile->id]),['rider_id'=>$request->user()->id],'message');return back()->with('success','Message sent to SHOPPICK Logistics.');}
    public function profile(Request $request){$user=$request->user()->load(['riderProfile.hub','riderProfile.vehicle','addresses']);return view('rider.profile',compact('user'));}
    public function updateProfile(Request $request){$data=$request->validate(['phone'=>'required|string|max:30','emergency_contact_name'=>'nullable|string|max:100','emergency_contact_phone'=>'nullable|string|max:30']);$request->user()->update(['phone'=>$data['phone']]);$request->user()->riderProfile->update(collect($data)->except('phone')->all());return back()->with('success','Profile contact information updated.');}
    public function settings(Request $request){return view('rider.settings',['preferences'=>$request->user()->riderProfile->notification_preferences??[]]);}
    public function updateSettings(Request $request){$request->user()->riderProfile->update(['notification_preferences'=>['assignments'=>$request->boolean('assignments'),'status'=>$request->boolean('status'),'support'=>$request->boolean('support')]]);return back()->with('success','Notification preferences saved.');}
    public function password(Request $request){$data=$request->validate(['current_password'=>'required','password'=>['required','confirmed',Password::defaults()]]);if(!Hash::check($data['current_password'],$request->user()->password))throw ValidationException::withMessages(['current_password'=>'The current password is incorrect.']);$request->user()->update(['password'=>Hash::make($data['password'])]);return back()->with('success','Password changed.');}
    private function owned(Request $request){return Shipment::query()->where(fn($q)=>$q->where('pickup_rider_id',$request->user()->id)->orWhere('rider_id',$request->user()->id));}
    private function authorizeShipment(Request $request,Shipment $shipment):void{abort_unless(in_array($request->user()->id,[$shipment->pickup_rider_id,$shipment->rider_id],true),403);}
}
