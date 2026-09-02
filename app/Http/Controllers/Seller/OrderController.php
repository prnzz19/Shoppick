<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Services\SellerOrderStatusService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected SellerOrderStatusService $statusService) {}
    public function index(Request $request) { $query=SellerOrder::where('store_id',auth()->user()->store->id); $groups=['new'=>['pending'],'processing'=>['confirmed','processing','packed'],'to_ship'=>['ready_to_ship'],'shipped'=>['shipped','delivered'],'completed'=>['completed'],'cancelled'=>['cancelled']]; if($request->status&&isset($groups[$request->status]))$query->whereIn('status',$groups[$request->status]); if($request->q)$query->where(fn($q)=>$q->where('seller_order_number','like','%'.$request->q.'%')->orWhereHas('order',fn($o)=>$o->where('buyer_name','like','%'.$request->q.'%'))); $orders=$query->with(['order.user','items'])->latest()->paginate(20)->withQueryString(); return view('seller.orders.index',compact('orders')); }
    public function show(Request $request, SellerOrder $sellerOrder) { abort_unless($sellerOrder->store_id===$request->user()->store->id,403); $sellerOrder->load(['order.user','items.product','items.variant','histories.changedBy.roles','shipment.rider','shipment.vehicle','shipment.proofOfDelivery','shipment.events']); return view('seller.orders.show',compact('sellerOrder')); }
    public function update(Request $request, SellerOrder $sellerOrder) {
        abort_unless($sellerOrder->store_id === $request->user()->store->id, 403);
        $data=$request->validate(['status'=>['required','in:'.implode(',',SellerOrder::STATUSES)],'note'=>['nullable','string','max:500']]);
        $this->statusService->transition($sellerOrder, $request->user(), $data['status'], $data['note'] ?? null);
        return back()->with('success','Order status updated.'); }
}
