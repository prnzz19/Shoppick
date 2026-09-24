<?php

namespace App\Http\Controllers;

use App\Models\{Category, Order, Product, SellerApplication, User};
use App\Services\{CartService, OrderService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

class MobileApiController extends Controller
{
    private function productData(Product $p): array
    {
        $p->loadMissing(['images', 'category', 'store', 'variants']);
        return ['id'=>$p->id,'name'=>$p->name,'slug'=>$p->slug,'description'=>$p->description,'price'=>(float)$p->salePrice(),'original_price'=>(float)$p->originalPrice(),'discount'=>(float)$p->discount,'stock'=>$p->stock,'image'=>$p->images->firstWhere('is_primary',true)?->url ?? $p->images->first()?->url,'images'=>$p->images->map->url,'category'=>$p->category?->name,'shop'=>$p->store?->name,'variants'=>$p->variants->map(fn($v)=>['id'=>$v->id,'type'=>$v->type,'value'=>$v->value,'price'=>$v->price,'stock'=>$v->stock])];
    }

    public function login(Request $r)
    {
        $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
        $user=User::where('email',strtolower($data['email']))->first();
        if(!$user || !Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['email'=>['The provided credentials are incorrect.']]);
        if(!$user->is_active) return response()->json(['message'=>'Your account is awaiting administrator approval.'],403);
        $plain=bin2hex(random_bytes(32)); $id=DB::table('mobile_api_tokens')->insertGetId(['user_id'=>$user->id,'name'=>'mobile','token_hash'=>hash('sha256',$plain),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['token'=>$plain,'user'=>$this->userData($user)],201);
    }
    public function register(Request $r)
    {
        $d=$r->validate(['first_name'=>'required|string|max:100','middle_initial'=>'nullable|string|max:5','last_name'=>'required|string|max:100','sex'=>'required|in:male,female','birthday'=>'required|date|before_or_equal:today','email'=>'required|email|max:255|unique:users,email','phone'=>'required|string|max:30','address_line'=>'required|string|max:255','barangay'=>'required|string|max:100','city'=>'required|string|max:100','region'=>'nullable|string|max:100','region_code'=>'nullable|string|max:20','province'=>'nullable|string|max:100','province_code'=>'nullable|string|max:20','city_code'=>'nullable|string|max:20','barangay_code'=>'nullable|string|max:20','postal_code'=>'required|string|max:20','country'=>'required|string|size:2','terms'=>'accepted','valid_id'=>'required|file|mimes:jpg,jpeg,png,pdf|max:5120','password'=>'required|string|min:8|confirmed']);
        $user=DB::transaction(function()use($d,$r){$name=trim($d['first_name'].' '.(!empty($d['middle_initial'])?$d['middle_initial'].'. ':'').$d['last_name']);$user=User::create(['name'=>$name,'first_name'=>$d['first_name'],'middle_initial'=>$d['middle_initial']??null,'last_name'=>$d['last_name'],'sex'=>$d['sex'],'birthday'=>$d['birthday'],'email'=>strtolower($d['email']),'phone'=>$d['phone'],'valid_id_path'=>$r->file('valid_id')->store('registration-documents'),'registration_type'=>'buyer','registration_status'=>'pending','password'=>Hash::make($d['password']),'is_active'=>false]);$user->assignRole('buyer');$user->addresses()->create(['full_name'=>$name,'phone'=>$d['phone'],'address_line'=>$d['address_line'],'region'=>$d['region']??null,'region_code'=>$d['region_code']??null,'barangay'=>$d['barangay'],'barangay_code'=>$d['barangay_code']??null,'city'=>$d['city'],'city_code'=>$d['city_code']??null,'province'=>$d['province']??'','province_code'=>$d['province_code']??null,'postal_code'=>$d['postal_code'],'country'=>strtoupper($d['country']),'label'=>'Home','is_default'=>true]);return $user;});
        return response()->json(['message'=>'Registration submitted. Your Buyer account is waiting for administrator approval.','user'=>['name'=>$user->name,'email'=>$user->email]],201);
    }
    private function userData(User $u): array { $a=$u->sellerApplications()->latest()->first(); return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone,'roles'=>$u->roles()->pluck('slug'),'seller_application'=>$a?['status'=>$a->status,'notes'=>$a->review_notes]:null,'is_seller'=>$u->isSeller()]; }
    public function logout(Request $r) { DB::table('mobile_api_tokens')->where('token_hash',hash('sha256',$r->bearerToken()))->delete(); return response()->json(['message'=>'Logged out']); }
    public function profile(Request $r) { return response()->json(['user'=>$this->userData($r->user()),'addresses'=>$r->user()->addresses]); }
    public function categories() { return Category::active()->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(['id','name','slug','image'])->values(); }
    public function products(Request $r) { $q=Product::active()->with(['images','category','store'])->when($r->filled('category'),fn($q)=>$q->where('category_id',$r->integer('category')))->when($r->filled('q'),fn($q)=>$q->where('name','like','%'.$r->string('q').'%'))->latest(); return response()->json(['data'=>$q->paginate(20)->through(fn($p)=>$this->productData($p))]); }
    public function product(Product $product) { abort_unless(Product::active()->whereKey($product->id)->exists(),404); return response()->json($this->productData($product)); }
    public function home() { return response()->json(['categories'=>$this->categories(),'featured'=>Product::active()->where('is_featured',true)->with(['images','category','store'])->take(8)->get()->map(fn($p)=>$this->productData($p)),'latest'=>Product::active()->with(['images','category','store'])->latest()->take(12)->get()->map(fn($p)=>$this->productData($p)),'deals'=>Product::active()->where('discount','>',0)->with(['images','category','store'])->take(8)->get()->map(fn($p)=>$this->productData($p))]); }
    public function cart(Request $r, CartService $cart) { return $this->cartResponse($r->user(),$cart); }
    private function cartResponse(User $u, CartService $cart) { $items=$cart->items($u->id); return response()->json(['items'=>$items->map(fn($i)=>['id'=>$i->id,'quantity'=>$i->quantity,'selected'=>$i->selected,'unit_price'=>(float)$i->unitPrice(),'line_total'=>(float)$i->lineTotal(),'product'=>$this->productData($i->product),'variant'=>$i->variant?->label]),'subtotal'=>(float)$items->filter->selected->sum(fn($i)=>$i->lineTotal()),'cart_count'=>$cart->count($u->id)]); }
    public function addCart(Request $r, CartService $cart) { $d=$r->validate(['product_id'=>'required|integer','product_variant_id'=>'nullable|integer','quantity'=>'required|integer|min:1|max:50']); $cart->add($r->user()->id,$d['product_id'],$d['product_variant_id']??null,$d['quantity']); return $this->cartResponse($r->user(),$cart); }
    public function updateCart(Request $r, int $item, CartService $cart) { $d=$r->validate(['quantity'=>'required|integer|min:1|max:50']); $cart->updateQuantity($r->user()->id,$item,$d['quantity']); return $this->cartResponse($r->user(),$cart); }
    public function removeCart(Request $r, int $item, CartService $cart) { $cart->remove($r->user()->id,$item); return $this->cartResponse($r->user(),$cart); }
    public function checkout(Request $r, OrderService $orders) { $d=$r->validate(['address_id'=>'required|integer','payment_method'=>'required|in:cod,gcash,maya,card','note'=>'nullable|string|max:500','voucher_codes'=>'nullable|array']); if(!$r->user()->isBuyer()) abort(403,'Buyer access required.'); $order=$orders->placeOrder($r->user()->id,$d)['order']; return response()->json(['order'=>$order->load('items')],201); }
    public function orders(Request $r) { return $r->user()->orders()->with('items')->latest()->paginate(20); }
    public function order(Request $r, string $order) { return $r->user()->orders()->with('items')->where('order_number',$order)->firstOrFail(); }
    public function sellerApplication(Request $r) { return response()->json(['application'=>$r->user()->sellerApplications()->latest()->first(),'is_seller'=>$r->user()->isSeller(),'categories'=>Category::active()->orderBy('name')->get(['id','name'])]); }
    public function submitSellerApplication(Request $r) { abort_if($r->user()->isSeller(),422,'You already have seller access.'); $d=$r->validate(['store_name'=>'required|string|max:120','store_description'=>'nullable|string|max:2000','phone'=>'required|string|max:30','address'=>'required|string|max:1000','category_id'=>'required|exists:categories,id','business_information'=>'nullable|string|max:2000','valid_id'=>'required|file|mimes:jpg,jpeg,png,pdf|max:5120','business_permit'=>'required|file|mimes:jpg,jpeg,png,pdf|max:5120','logo'=>'nullable|image|max:2048','banner'=>'nullable|image|max:4096']); foreach(['logo','banner'] as $f) if($r->hasFile($f)) $d[$f]=$r->file($f)->store('stores','public'); $d['valid_id_path']=$r->file('valid_id')->store('registration-documents'); $d['business_permit_path']=$r->file('business_permit')->store('registration-documents'); $d['address_line']=$d['address']; $d['same_address']=true; app(\App\Services\SellerRegistrationService::class)->submit($r->user(),$d); return response()->json(['message'=>'Seller application submitted.','application'=>$r->user()->sellerApplications()->latest()->first()],201); }
}
