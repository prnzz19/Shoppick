<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
class SalesController extends Controller { public function index(){ $base=SellerOrder::where('store_id',auth()->user()->store->id); $stats=['today'=>(clone $base)->where('status','completed')->whereDate('completed_at',today())->sum('seller_total'),'month'=>(clone $base)->where('status','completed')->whereBetween('completed_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('seller_total'),'completed'=>(clone $base)->where('status','completed')->sum('seller_total'),'pending'=>(clone $base)->whereNotIn('status',['completed','cancelled'])->sum('seller_total'),'commission'=>(clone $base)->where('status','completed')->sum('commission_amount')]; $orders=$base->with('order')->latest()->paginate(20); return view('seller.sales.index',compact('stats','orders')); } }
