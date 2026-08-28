<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const TRANSITIONS = ['pending'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['packed'],'packed'=>['ready_to_ship'],'ready_to_ship'=>['shipped'],'shipped'=>['delivered'],'delivered'=>['completed']];
    public function index(Request $request) { $query=SellerOrder::where('store_id',auth()->user()->store->id); $groups=['new'=>['pending'],'processing'=>['confirmed','processing','packed'],'to_ship'=>['ready_to_ship'],'shipped'=>['shipped','delivered'],'completed'=>['completed'],'cancelled'=>['cancelled']]; if($request->status&&isset($groups[$request->status]))$query->whereIn('status',$groups[$request->status]); if($request->q)$query->where(fn($q)=>$q->where('seller_order_number','like','%'.$request->q.'%')->orWhereHas('order',fn($o)=>$o->where('buyer_name','like','%'.$request->q.'%'))); $orders=$query->with(['order.user','items'])->latest()->paginate(20)->withQueryString(); return view('seller.orders.index',compact('orders')); }
    public function show(Request $request, SellerOrder $sellerOrder) { abort_unless($sellerOrder->store_id===$request->user()->store->id,403); $sellerOrder->load(['order.user','items.product','items.variant','histories.changedBy']); return view('seller.orders.show',compact('sellerOrder')); }
    public function update(Request $request, SellerOrder $sellerOrder) {
        abort_unless($sellerOrder->store_id === $request->user()->store->id, 403);
        $data=$request->validate(['status'=>['required','in:'.implode(',',SellerOrder::STATUSES)],'note'=>['nullable','string','max:500']]);
        abort_unless(in_array($data['status'], self::TRANSITIONS[$sellerOrder->status] ?? [], true), 422, 'That status transition is not allowed.');
        DB::transaction(function() use($sellerOrder,$data,$request){ $updates=['status'=>$data['status']]; if(in_array($data['status'],['completed','cancelled'])) $updates[$data['status'].'_at']=now(); $sellerOrder->update($updates);
            $sellerOrder->histories()->create(['order_id'=>$sellerOrder->order_id,'changed_by'=>$request->user()->id,'status'=>$data['status'],'note'=>$data['note']??null]);
            $statuses=$sellerOrder->order->sellerOrders()->pluck('status'); if($statuses->every(fn($s)=>$s==='completed')) $sellerOrder->order->update(['status'=>'completed','completed_at'=>now()]); elseif($statuses->every(fn($s)=>$s==='cancelled')) $sellerOrder->order->update(['status'=>'cancelled','cancelled_at'=>now()]); else $sellerOrder->order->update(['status'=>$data['status']]);
            NotificationService::send($sellerOrder->order->user_id,'Order status updated',"{$sellerOrder->seller_order_number} is now ".str_replace('_',' ',$data['status']).'.','order',route('orders.show',$sellerOrder->order->order_number)); });
        return back()->with('success','Order status updated.'); }
}
