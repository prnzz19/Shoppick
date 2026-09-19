<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\SellerShopApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SellerApplicationController extends Controller
{
    public function __construct(protected SellerShopApprovalService $approvals) {}
    public function index(Request $request)
    {
        $tab=in_array($request->tab,['all','buyers','sellers'],true)?$request->tab:'all';
        $status=$request->input('status');$search=$request->input('q');
        $buyers=User::with(['roles','addresses','socialAccounts','registrationReviewer'])->where('registration_type','buyer')->when($status,fn($q,$s)=>$q->where('registration_status',$s))->when($search,fn($q,$s)=>$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('email','like',"%$s%")))->latest()->get();
        $sellers=SellerApplication::with(['user.addresses','user.socialAccounts','category','reviewer'])->when($status,fn($q,$s)=>$q->where('status',$s))->when($search,fn($q,$s)=>$q->where(fn($x)=>$x->where('store_name','like',"%$s%")->orWhereHas('user',fn($u)=>$u->where('name','like',"%$s%")->orWhere('email','like',"%$s%"))))->latest()->get();
        return view('admin.sellers.index', compact('buyers','sellers','tab'));
    }

    public function reviewBuyer(Request $request, User $user)
    {
        abort_unless($user->registration_type==='buyer' && $user->registration_status==='pending', 404);
        $data=$request->validate(['status'=>'required|in:approved,rejected','review_notes'=>'nullable|string|max:2000']);
        if($data['status']==='rejected' && blank($data['review_notes']??null))throw ValidationException::withMessages(['review_notes'=>'A rejection reason is required.']);
        $user->update(['registration_status'=>$data['status'],'is_active'=>$data['status']==='approved','registration_review_notes'=>$data['review_notes']??null,'registration_reviewed_by'=>$request->user()->id,'registration_reviewed_at'=>now()]);
        \App\Services\NotificationService::registrationDecision($user,$data['status']==='approved'?'Your SHOPPICK Buyer account has been approved.':'Your SHOPPICK Buyer registration was rejected.',$data['status']==='approved'?'You may now log in and use your Buyer account.':'Reason: '.$data['review_notes'],route('login'),['decision'=>$data['status']]);
        return back()->with('success','Buyer registration decision saved.');
    }

    public function buyerDocument(User $user)
    {
        abort_unless($user->registration_type==='buyer' && $user->valid_id_path,404);
        return Storage::disk('local')->download($user->valid_id_path);
    }

    public function sellerDocument(SellerApplication $application, string $document)
    {
        abort_unless(in_array($document,['valid-id','business-permit'],true),404);
        $path=$document==='valid-id'?$application->valid_id_path:$application->business_permit_path;
        abort_unless($path,404);
        return Storage::disk('local')->download($path);
    }

    public function review(Request $request, SellerApplication $application)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $this->approvals->review($application,$request->user(),$data['status'],$data['review_notes']??null);
        return back()->with('success', 'Seller application decision saved.');
    }

}
