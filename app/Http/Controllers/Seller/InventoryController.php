<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $products=$request->user()->store->products()->with('variants')->when($request->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%{$v}%")->orWhere('sku','like',"%{$v}%")))->orderBy('stock')->paginate(20)->withQueryString();
        return view('seller.inventory.index',compact('products'));
    }
    public function update(Request $request, Product $product)
    {
        abort_unless($product->store_id===$request->user()->store->id,403);
        $data=$request->validate(['stock'=>['required','integer','min:0'],'low_stock_threshold'=>['required','integer','min:0'],'variants'=>['nullable','array'],'variants.*'=>['integer','min:0']]);
        DB::transaction(function()use($product,$data){ Product::whereKey($product->id)->lockForUpdate()->update(['stock'=>$data['stock'],'low_stock_threshold'=>$data['low_stock_threshold']]); foreach($data['variants']??[] as $id=>$stock) ProductVariant::where('product_id',$product->id)->whereKey($id)->update(['stock'=>$stock]); });
        return back()->with('success','Inventory updated.');
    }
}
