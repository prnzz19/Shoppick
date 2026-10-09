<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Review;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
class CenterController extends Controller {
 public function products(Request $r)
 {
     $filters = $r->validate([
         'q' => ['nullable','string','max:100'],
         'category' => ['nullable','integer'],
         'status' => ['nullable','in:active,draft,inactive,archived'],
         'moderation' => ['nullable','in:clear,review_required,under_review,rejected'],
     ]);
     $status = $filters['status'] ?? '';
     $base = $r->user()->store->products()->withTrashed();
     if (filled($filters['q'] ?? null)) {
         $term = '%'.trim($filters['q']).'%';
         $base->where(fn ($query) => $query->where('name','like',$term)->orWhere('sku','like',$term));
     }
     if (filled($filters['category'] ?? null)) $base->where('category_id',$filters['category']);
     match ($filters['moderation'] ?? '') {
         'clear' => $base->whereIn('moderation_status',['clean','approved']),
         'review_required' => $base->whereIn('moderation_status',['scan_failed','under_review','flagged'])->where('publication_status','!=','draft'),
         'under_review' => $base->whereIn('moderation_status',['pending_scan','scanning'])->where('publication_status','!=','draft'),
         'rejected' => $base->where('moderation_status','rejected')->where('publication_status','!=','draft'),
         default => null,
     };
     $applyTab = function ($query, $tab) {
         if ($tab === 'archived') return $query->onlyTrashed();
         $query->withoutTrashed();
         return match ($tab) {
             'active' => $query->where('is_active',true),
             'draft' => $query->where('publication_status','draft'),
             'inactive' => $query->where('is_active',false)->where('publication_status','!=','draft'),
             default => $query,
         };
     };
     $counts = [];
     foreach (['','active','draft','inactive','archived'] as $tab) $counts[$tab] = $applyTab(clone $base,$tab)->count();
     $q = $applyTab(clone $base,$status)->with(['category','images','store'])->withCount('orderItems');
     $products = $q->orderByDesc($status === 'archived' ? 'deleted_at' : 'created_at')->orderByDesc('id')->paginate(10)->withQueryString();
     $categories = Category::where(fn ($query) => $query->where('is_active',true)
         ->orWhereHas('products',fn ($products) => $products->withTrashed()->where('store_id',$r->user()->store->id)))
         ->orderBy('sort_order')->orderBy('name')->get();
     return view('seller.products.status-index',compact('products','categories','counts'));
 }
 public function orders(Request $r){$storeId=$r->user()->store->id;$q=SellerOrder::where('store_id',$storeId);$tab=$r->status?:'all';if($tab==='to_pay')$q->whereHas('order',fn($o)=>$o->whereIn('payment_status',['unpaid','pending'])->where('payment_method','!=','cod'));elseif(isset(SellerOrder::TAB_GROUPS[$tab]))$q->whereIn('status',SellerOrder::TAB_GROUPS[$tab]);if($r->q)$q->where(fn($x)=>$x->where('seller_order_number','like','%'.$r->q.'%')->orWhereHas('order',fn($o)=>$o->where('buyer_name','like','%'.$r->q.'%')));$orders=$q->with(['order.user','items'])->latest()->paginate(20)->withQueryString();$base=SellerOrder::where('store_id',$storeId);$counts=['all'=>(clone $base)->count(),'to_pay'=>(clone $base)->whereHas('order',fn($o)=>$o->whereIn('payment_status',['unpaid','pending'])->where('payment_method','!=','cod'))->count()];foreach(SellerOrder::TAB_GROUPS as $key=>$statuses)$counts[$key]=(clone $base)->whereIn('status',$statuses)->count();return view('seller.orders._center',compact('orders','counts','tab'));}
 public function reviews(Request $r){$store=$r->user()->store;$base=Review::visible()->whereHas('product',fn($q)=>$q->where('store_id',$store->id));$average=(clone $base)->avg('rating')??0;$reviews=$base->with(['product','user','reply'])->latest()->paginate(20);return view('seller.reviews._center',compact('reviews','average'));}
 public function sales(Request $r){$base=SellerOrder::where('store_id',$r->user()->store->id);$stats=['today'=>(clone $base)->where('status','completed')->whereDate('completed_at',today())->sum('seller_total'),'month'=>(clone $base)->where('status','completed')->whereBetween('completed_at',[now()->startOfMonth(),now()->endOfMonth()])->sum('seller_total'),'completed'=>(clone $base)->where('status','completed')->sum('seller_total'),'pending'=>(clone $base)->whereNotIn('status',['completed','cancelled'])->sum('seller_total'),'commission'=>(clone $base)->where('status','completed')->sum('commission_amount')];$orders=$base->with('order')->latest()->paginate(20);return view('seller.sales._center',compact('stats','orders'));}
}
