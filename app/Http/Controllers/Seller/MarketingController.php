<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
class MarketingController extends Controller {
 public function index(){ $vouchers=auth()->user()->store->vouchers()->latest()->paginate(15); return view('seller.marketing.index',compact('vouchers')); }
 public function store(Request $request){ $data=$request->validate(['code'=>['required','alpha_dash','max:30','unique:vouchers,code'],'title'=>['required','string','max:120'],'type'=>['required','in:percent,fixed'],'value'=>['required','numeric','min:0.01'],'min_purchase'=>['nullable','numeric','min:0'],'usage_limit'=>['nullable','integer','min:1'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after_or_equal:starts_at']]); $data['code']=strtoupper($data['code']); $data['status']='active'; auth()->user()->store->vouchers()->create($data); return back()->with('success','Voucher created successfully.'); }
 public function toggle(Voucher $voucher){ abort_unless($voucher->store_id===auth()->user()->store->id,403); $voucher->update(['status'=>$voucher->status==='active'?'inactive':'active']); return back()->with('success','Voucher status updated.'); }
}
