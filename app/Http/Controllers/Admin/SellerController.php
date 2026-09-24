<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
class SellerController extends Controller
{
    public function index(Request $request)
    {
        $sellers = User::whereHas('roles', fn($q)=>$q->where('slug','seller'))->whereHas('sellerProfile', fn($q)=>$q->where('status','approved'))
            ->whereHas('store')->with(['sellerProfile','store'=>fn($q)=>$q->withCount('products')])
            ->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$request->q.'%')->orWhere('email','like','%'.$request->q.'%')->orWhereHas('store',fn($s)=>$s->where('name','like','%'.$request->q.'%'))))
            ->latest()->paginate(15)->withQueryString();
        return view('admin.sellers.active', compact('sellers'));
    }
    public function show(User $user)
    {
        abort_unless($user->hasRole('seller') && $user->sellerProfile?->status==='approved' && $user->store,404);
        $user->load(['store.products','sellerApplications.reviewer']);
        $orderCount=$user->store->sellerOrders()->count();
        $sales=$user->store->sellerOrders()->where('status','completed')->sum('seller_total');
        return view('admin.sellers.show',compact('user','orderCount','sales'));
    }
}
