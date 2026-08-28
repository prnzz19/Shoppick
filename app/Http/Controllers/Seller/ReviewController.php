<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\Review;
class ReviewController extends Controller { public function index(){ $store=auth()->user()->store; $reviews=Review::visible()->whereHas('product',fn($q)=>$q->where('store_id',$store->id))->with(['product','user','reply'])->latest()->paginate(20); $average=Review::visible()->whereHas('product',fn($q)=>$q->where('store_id',$store->id))->avg('rating')??0; return view('seller.reviews.index',compact('reviews','average')); }
 public function reply(\Illuminate\Http\Request $request, Review $review){ abort_unless($review->product()->where('store_id',$request->user()->store->id)->exists(),403); $data=$request->validate(['reply'=>['required','string','max:1000']]); $review->reply()->updateOrCreate([],['user_id'=>$request->user()->id,'reply'=>$data['reply']]); return back()->with('success','Reply posted.'); } }
