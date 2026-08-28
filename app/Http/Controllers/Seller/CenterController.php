<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Review;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
class CenterController extends Controller {
 public function products(Request $r){$q=$r->user()->store->products()->with(['category','images']); if($r->q)$q->where('name','like','%'.$r->q.'%'); if($r->category)$q->where('category_id',$r->category); if($r->status==='active')$q->where('is_active',true); if($r->status==='draft')$q->where('is_active',false); if($r->status==='low')$q->whereColumn('stock','<=','low_stock_threshold')->where('stock','>',0); if($r->status==='out')$q->where('stock',0); $products=$q->latest()->paginate(15)->withQueryString();$categories=Category::active()->get();return view('seller.products._center',compact('products','categories'));}
 public function orders(Request $r){$q=SellerOrder::where('store_id',$r->user()->store->id);$groups=['new'=>['pending'],'processing'=>['confirmed','processing','packed'],'to_ship'=>['ready_to_ship'],'shipped'=>['shipped','delivered'],'completed'=>['completed'],'cancelled'=>['cancelled']];if($r->status&&isset($groups[$r->status]))$q->whereIn('status',$groups[$r->status]);if($r->q)$q->where(fn($x)=>$x->where('seller_order_number','like','%'.$r->q.'%')->orWhereHas('order',fn($o)=>$o->where('buyer_name','like','%'.$r->q.'%')));$orders=$q->with(['order.user','items'])->latest()->paginate(20)->withQueryString();return view('seller.orders._center',compact('orders'));}
 public function reviews(Request $r){$store=$r->user()->store;$base=Review::visible()->whereHas('product',fn($q)=>$q->where('store_id',$store->id));$average=(clone $base)->avg('rating')??0;$reviews=$base->with(['product','user','reply'])->latest()->paginate(20);return view('seller.reviews._center',compact('reviews','average'));}
 public function sales(Request $r){$base=SellerOrder::where('store_id',$r->user()->store->id);$stats=['today'=>(clone $base)->where('status','completed')->whereDate('completed_at',today())->sum('seller_total'),'month'=>(clone $base)->where('status','completed')->whereBetween('completed_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('seller_total'),'completed'=>(clone $base)->where('status','completed')->sum('seller_total'),'pending'=>(clone $base)->whereNotIn('status',['completed','cancelled'])->sum('seller_total'),'commission'=>(clone $base)->where('status','completed')->sum('commission_amount')];$orders=$base->with('order')->latest()->paginate(20);return view('seller.sales._center',compact('stats','orders'));}
}
