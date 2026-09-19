<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\{NotificationModel, Payment, ProofOfDelivery, RiderMessage, Shipment};
use App\Services\{CodCollectionService, NotificationService, ParcelWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RiderController extends Controller
{
    private const COMPLETE = ['delivered','returned','completed'];

    public function dashboard(Request $request)
    {
        $base=$this->owned($request)->with(['store','order.payments','deliveryArea','currentHub']);
        $current=(clone $base)->whereNotIn('status',self::COMPLETE)->oldest('assigned_at')->first();
        $today=(clone $base)->whereDate('assigned_at',today());
        $summary=['assigned'=>(clone $today)->count(),'completed'=>(clone $today)->whereIn('status',self::COMPLETE)->count(),'attention'=>(clone $base)->whereIn('status',['delivery_failed','exception'])->count(),'cod'=>Payment::where('collected_by',$request->user()->id)->where('remittance_status','pending')->sum('amount')];
        return view('rider.dashboard',compact('current','summary'));
    }

    public function jobs(Request $request)
    {
        $type=$request->route('type')?:$request->type;
        $shipments=$this->owned($request)->with(['store','order.payments','deliveryArea'])
            ->when($type==='pickup',fn($q)=>$q->where('pickup_rider_id',$request->user()->id))
            ->when($type==='delivery',fn($q)=>$q->where('rider_id',$request->user()->id))
            ->when($request->status==='assigned',fn($q)=>$q->whereIn('status',['pickup_assigned','assigned_to_rider']))
            ->when($request->status==='active',fn($q)=>$q->whereNotIn('status',array_merge(self::COMPLETE,['pickup_assigned','assigned_to_rider'])))
            ->when($request->status==='completed',fn($q)=>$q->whereIn('status',self::COMPLETE))
            ->latest('updated_at')->paginate(12)->withQueryString();
        return view('rider.jobs',compact('shipments','type'));
    }

    public function show(Request $request,Shipment $shipment)
    {
        $this->authorizeShipment($request,$shipment);
        $shipment->load(['store.user','order.user','order.payments','sellerOrder.items','vehicle','currentHub','deliveryArea','events.actor','proofOfDelivery']);
        return view('rider.show',compact('shipment'));
    }

    public function availability(Request $request)
    {
        $data=$request->validate(['availability'=>'required|in:available,off_duty']);$profile=$request->user()->riderProfile;
        abort_unless($profile&&$profile->account_status==='active'&&$request->user()->is_active,403);
        if($this->owned($request)->whereNotIn('status',self::COMPLETE)->exists())throw ValidationException::withMessages(['availability'=>'Finish your current assignment before changing availability.']);
        $profile->update($data);return back()->with('success','Availability updated.');
    }

    public function decline(Request $request,Shipment $shipment)
    {
        $this->authorizeShipment($request,$shipment);$data=$request->validate(['reason'=>'required|in:vehicle_issue,unavailable,emergency,other','details'=>'nullable|required_if:reason,other|string|max:500']);
        DB::transaction(function()use($request,$shipment,$data){$shipment=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();$pickup=$shipment->pickup_rider_id===$request->user()->id&&$shipment->status==='pickup_assigned';$delivery=$shipment->rider_id===$request->user()->id&&$shipment->status==='assigned_to_rider'&&!$shipment->delivery_accepted_at;if(!$pickup&&!$delivery)throw ValidationException::withMessages(['reason'=>'This assignment can no longer be declined.']);$reason=str($data['reason'])->replace('_',' ')->headline().(isset($data['details'])?': '.$data['details']:'');$shipment->update($pickup?['pickup_rider_id'=>null,'pickup_assigned_by'=>null,'assigned_at'=>null,'status'=>'ready_for_pickup']:['rider_id'=>null,'delivery_assigned_by'=>null,'delivery_assigned_at'=>null,'assigned_at'=>null,'status'=>'sorted']);$shipment->events()->create(['actor_id'=>$request->user()->id,'status'=>'assignment_declined','note'=>$reason]);$request->user()->riderProfile->update(['availability'=>'available']);foreach(\App\Models\User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->get() as $manager)NotificationService::send($manager->id,'Rider declined assignment',"{$shipment->parcel_code}: {$reason}",'logistics',route($pickup?'logistics.pickup-requests':'logistics.delivery-assignment'),['shipment_id'=>$shipment->id],'package');});
        return redirect()->route('rider.jobs')->with('success','Logistics was notified and the Parcel was returned for reassignment.');
    }

    public function parcelAction(Request $request,Shipment $shipment,ParcelWorkflowService $workflow)
    {
        $data=$request->validate(['action'=>'required|in:accept_pickup,confirm_pickup,arrive_sorting,accept_delivery,start_delivery,delivered,failed','parcel_code'=>'nullable|string|max:100','reason'=>'nullable|required_if:action,failed|in:recipient_unavailable,incorrect_address,reschedule_requested,parcel_issue,payment_issue,other','details'=>'nullable|required_if:reason,other|string|max:500']);
        $reason=isset($data['reason'])?str($data['reason'])->replace('_',' ')->headline().(isset($data['details'])?': '.$data['details']:''):null;
        match($data['action']){'accept_pickup'=>$workflow->acceptPickup($shipment,$request->user()),'confirm_pickup'=>$workflow->confirmPickup($shipment,$request->user(),$data['parcel_code']??''),'arrive_sorting'=>$workflow->arriveSortingCenter($shipment,$request->user()),'accept_delivery'=>$workflow->acceptDelivery($shipment,$request->user()),'start_delivery'=>$workflow->startDelivery($shipment,$request->user()),'delivered'=>$workflow->deliver($shipment,$request->user()),'failed'=>$workflow->fail($shipment,$request->user(),$reason??'Delivery attempt failed.')};
        return back()->with('success','Parcel status updated.');
    }

    public function collectCod(Request $request,Shipment $shipment,CodCollectionService $service){$service->collect($shipment,$request->user());return back()->with('success','COD collection recorded for Logistics remittance confirmation.');}
    public function pod(Request $request,Shipment $shipment){abort_unless($shipment->rider_id===$request->user()->id&&$shipment->status==='out_for_delivery',403);$existing=$shipment->proofOfDelivery;$data=$request->validate(['recipient_name'=>'required|string|max:100','photo'=>[$existing?'nullable':'required','image','mimes:jpg,jpeg,png,webp','max:5120'],'notes'=>'nullable|string|max:1000']);$pod=DB::transaction(function()use($request,$shipment,$data,$existing){$path=$request->file('photo')?->store('pod')??$existing?->photo_path;return ProofOfDelivery::updateOrCreate(['shipment_id'=>$shipment->id],['submitted_by'=>$request->user()->id,'recipient_name'=>$data['recipient_name'],'photo_path'=>$path,'notes'=>$data['notes']??null,'status'=>'pending','reviewed_by'=>null,'review_notes'=>null,'submitted_at'=>now(),'reviewed_at'=>null]);});foreach(\App\Models\User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->get() as $user)NotificationService::send($user->id,'Proof of Delivery submitted.',"{$shipment->shipment_number} requires POD review.",'logistics',route('logistics.pod',['view'=>$pod->id]),['pod_id'=>$pod->id],'package');return back()->with('success','Proof of Delivery submitted. You can now complete the delivery.');}
    public function podFile(Request $request,ProofOfDelivery $pod){abort_unless($pod->shipment()->where('rider_id',$request->user()->id)->exists(),403);abort_unless($pod->photo_path&&Storage::disk('local')->exists($pod->photo_path),404);return Storage::disk('local')->download($pod->photo_path);}
    public function licenseFile(Request $request,string $side){abort_unless(in_array($side,['front','back'],true),404);$profile=$request->user()->riderProfile;$path=$side==='front'?$profile?->driver_license_front_path:$profile?->driver_license_back_path;abort_unless($path&&Storage::disk('local')->exists($path),404);return Storage::disk('local')->download($path);}
    public function history(Request $request){$request->merge(['status'=>$request->status?:'completed']);return $this->jobs($request);}
    public function pods(Request $request){$pods=ProofOfDelivery::with('shipment.store')->where('submitted_by',$request->user()->id)->latest()->paginate(15);return view('rider.pods',compact('pods'));}
    public function cod(Request $request){$payments=Payment::with('order')->where('collected_by',$request->user()->id)->latest('collected_at')->paginate(15);$pending=Payment::where('collected_by',$request->user()->id)->where('remittance_status','pending')->sum('amount');return view('rider.cod',compact('payments','pending'));}
    public function notifications(Request $request){$items=$request->user()->notificationsData()->latest()->paginate(20);return view('rider.notifications',compact('items'));}
    public function openNotification(Request $request,NotificationModel $notification){abort_unless($notification->user_id===$request->user()->id,404);$notification->markAsRead();return redirect($notification->link?:route('rider.notifications'));}
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
