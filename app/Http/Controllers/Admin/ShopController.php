<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Review;
use App\Models\Store;
use App\Services\ShopManagementService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(protected ShopManagementService $shops) {}

    public function index(Request $request)
    {
        $query=Store::with(['user.sellerApplications'])->withCount(['products','sellerOrders','reports','violations'=>fn($q)=>$q->where('status','confirmed')]);
        if($request->q)$query->where(fn($q)=>$q->where('name','like','%'.$request->q.'%')->orWhereHas('user',fn($u)=>$u->where('name','like','%'.$request->q.'%')->orWhere('email','like','%'.$request->q.'%')));
        if($request->status==='escalated')$query->where('status','pending')->whereHas('user.sellerApplications',fn($applications)=>$applications->where('status','escalated'));
        elseif($request->status==='pending')$query->where('status','pending')->whereHas('user.sellerApplications',fn($applications)=>$applications->where('status','pending'));
        elseif($request->status)$query->where('status',$request->status);
        if($request->reported)$query->has('reports');
        if($request->violations)$query->whereHas('violations',fn($q)=>$q->where('status','confirmed'));
        $shops=$query->latest()->paginate(20)->withQueryString();
        return view('admin.shops.index',compact('shops'));
    }

    public function show(Store $shop)
    {
        $shop->load(['user.sellerProfile','user.sellerApplications.adminReviewer','user.sellerApplications.escalator','user.sellerApplications.reviewer','products.category','products.images','products.moderationScans','sellerOrders','reports','violations'=>fn($q)=>$q->where('status','confirmed'),'statusChangedBy.roles'])
            ->loadCount(['products','sellerOrders','reports','violations'=>fn($q)=>$q->where('status','confirmed')]);
        $stats=['completed_orders'=>$shop->sellerOrders->where('status','completed')->count(),'cancelled_orders'=>$shop->sellerOrders->where('status','cancelled')->count(),'sales'=>$shop->sellerOrders->where('status','completed')->sum('seller_total'),'reviews'=>Review::whereHas('product',fn($q)=>$q->where('store_id',$shop->id))->count()];
        $activity=AdminActivityLog::with('user')->where('target_type',Store::class)->where('target_id',$shop->id)->latest()->limit(20)->get();
        $application=$shop->user->sellerApplications->sortByDesc('created_at')->first();
        return view('admin.shops.show',compact('shop','stats','activity','application'));
    }

    public function status(Request $request, Store $shop)
    {
        $data=$request->validate(['action'=>['required','in:approve,reject,restrict,suspend,reactivate'],'reason'=>['nullable','string','max:1000']]);
        $this->shops->apply($shop,$request->user(),$data['action'],$data['reason']??null);
        return back()->with('success','Shop status updated.');
    }

    public function note(Request $request, Store $shop)
    {
        $data=$request->validate(['note'=>['required','string','max:1500']]);$this->shops->addNote($shop,$request->user(),$data['note']);return back()->with('success','Administrative note added.');
    }

    public function escalate(Request $request, Store $shop)
    {
        $data=$request->validate(['reason'=>['required','string','max:1000']]);$this->shops->escalate($shop,$request->user(),$data['reason']);return back()->with('success','Shop case escalated to Super Admin.');
    }
}
