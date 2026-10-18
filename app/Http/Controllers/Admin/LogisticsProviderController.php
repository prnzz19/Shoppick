<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{LogisticsProvider,Shipment};
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
class LogisticsProviderController extends Controller
{
    public function index(Request $request) { $providers=LogisticsProvider::with('owner')->when($request->status,fn($q,$v)=>$q->where('status',$v))->latest()->paginate(20)->withQueryString(); return view('admin/logistics/providers',compact('providers')); }
    public function document(LogisticsProvider $provider, string $document)
    {
        $paths=['permit'=>$provider->permit_path,'business-registration'=>$provider->business_registration_path,'representative-id'=>$provider->representative_id_path,'tax-registration'=>$provider->tax_registration_path];
        abort_unless(array_key_exists($document,$paths),404);
        $path=$paths[$document];
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path);
    }
    public function review(Request $request, LogisticsProvider $provider)
    {
        $data=$request->validate(['decision'=>['required','in:approved,rejected'],'reason'=>['nullable','string','max:2000']]);
        if ($data['decision'] === 'approved') abort_unless($provider->owner && $provider->owner->email_verified_at,422,'The provider owner must verify their email before approval.');
        DB::transaction(function()use($request,$provider,$data){$approved=$data['decision']==='approved';$provider->update(['status'=>$approved?'approved':'rejected','is_active'=>$approved,'review_notes'=>$data['reason']??null,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);$provider->owner?->forceFill(['registration_status'=>$approved?'approved':'rejected','is_active'=>$approved,'registration_reviewed_by'=>$request->user()->id,'registration_reviewed_at'=>now(),'registration_review_notes'=>$data['reason']??null])->save();AdminActivityLog::record('logistics_provider.reviewed',LogisticsProvider::class,$provider->id,['decision'=>$data['decision']]);});
        return back()->with('success','Logistics Provider application updated.');
    }
    public function status(Request $request, LogisticsProvider $provider)
    {
        $data=$request->validate(['status'=>['required','in:suspended,approved,deactivated'],'reason'=>['nullable','string','max:2000']]);$active=$data['status']==='approved';
        DB::transaction(function()use($request,$provider,$data,$active){
            $provider=LogisticsProvider::query()->whereKey($provider->id)->lockForUpdate()->firstOrFail();
            if (in_array($data['status'], ['suspended','deactivated'], true) && $provider->shipments()->activeNonTerminal()->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['status'=>'PROVIDER HAS ACTIVE SHIPMENTS. Finish existing shipments before changing this Provider status.']);
            }
            $provider->update(['status'=>$data['status'],'is_active'=>$active,'review_notes'=>$data['reason']??$provider->review_notes,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);$ownerActive=$data['status']==='suspended'?true:$active;$provider->owner?->forceFill(['registration_status'=>$active?'approved':$data['status'],'is_active'=>$ownerActive,'registration_reviewed_by'=>$request->user()->id,'registration_reviewed_at'=>now(),'registration_review_notes'=>$data['reason']??$provider->review_notes])->save();AdminActivityLog::record('logistics_provider.status_changed',LogisticsProvider::class,$provider->id,['status'=>$data['status']]);
        });
        return back()->with('success','Logistics Provider status updated.');
    }
}
