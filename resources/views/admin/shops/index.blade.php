@extends('layouts.admin')
@section('title', 'Manage Shops')
@section('content')
@php
    $viewer = auth()->user();
    $can = fn ($permission) => $viewer->hasPermissionTo($permission);
    $tabs = ['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'restricted' => 'Restricted', 'suspended' => 'Suspended'];
@endphp
<div>
    <p class="text-sm font-semibold text-brand-600">Marketplace</p>
    <h1 class="text-2xl font-extrabold text-navy-900">Manage Shops</h1>
    <p class="mt-1 text-sm text-slate-500">Monitor approved shops, products, activity, and marketplace status.</p>
</div>
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(['total' => ['Total Shops', 'text-navy-900'], 'active' => ['Active Shops', 'text-leaf-600'], 'restricted' => ['Restricted', 'text-orange-700'], 'suspended' => ['Suspended', 'text-rose-700']] as $key => [$label, $color])
        <div class="card border border-slate-200 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-1 text-2xl font-black {{ $color }}">{{ $summary[$key] }}</p></div>
    @endforeach
</div>
<nav class="mt-6 flex gap-5 overflow-x-auto border-b border-slate-200" aria-label="Shop status filters">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.shops.index', array_filter(['status' => $key, 'q' => request('q'), 'sort' => request('sort')])) }}" class="flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-semibold {{ request('status', '') === $key ? 'border-brand-500 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-navy-800' }}">{{ $label }} <span class="rounded-full px-2 py-0.5 text-xs {{ request('status', '') === $key ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$key] }}</span></a>
    @endforeach
</nav>
<form method="GET" class="mt-5 flex flex-wrap gap-3">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input class="input !w-72" name="q" value="{{ request('q') }}" placeholder="Search shop, seller, or email">
    <select class="input !w-auto" name="sort" onchange="this.form.submit()"><option value="">Newest</option><option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option><option value="products" @selected(request('sort') === 'products')>Most Products</option><option value="orders" @selected(request('sort') === 'orders')>Most Orders</option></select>
    <button class="btn-primary" type="submit">Filter</button>
</form>
<div class="card mt-5 overflow-hidden"><div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Shop</th><th class="px-4 py-3">Seller</th><th class="px-4 py-3">Products</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Rating</th><th class="px-4 py-3">Reports</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Joined</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($shops as $shop)
    @php($application = $shop->user->sellerApplications->sortByDesc('created_at')->first())
    <tr class="transition hover:bg-slate-50"><td class="px-4 py-4"><a href="{{ route('admin.shops.show', $shop) }}" class="flex items-center gap-3"><x-shop-avatar :shop="$shop" size="h-11 w-11" /><span><strong class="block text-navy-900">{{ $shop->name }}</strong><small class="text-slate-500">{{ $shop->status === 'active' ? 'Active shop' : str($shop->status)->headline().' shop' }}</small></span></a></td><td class="px-4 py-4"><span class="font-medium text-navy-800">{{ $shop->user->name }}</span><span class="block text-xs text-slate-500">{{ $shop->user->email }}</span></td><td class="px-4 py-4 font-semibold text-navy-800">{{ $shop->products_count }}</td><td class="px-4 py-4 font-semibold text-navy-800">{{ $shop->seller_orders_count }}</td><td class="px-4 py-4">{{ $shop->rating_count ? number_format($shop->rating_avg, 1) : '—' }}</td><td class="px-4 py-4">{{ $shop->reports_count }}</td><td class="px-4 py-4"><x-admin.status-badge :status="$shop->status" /></td><td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $shop->created_at->format('M d, Y') }}</td><td class="px-4 py-4"><div class="flex min-w-[170px] justify-end gap-2"><a class="font-semibold text-brand-600 hover:text-brand-800" href="{{ route('admin.shops.show', $shop) }}">View Details</a>@if($shop->status === 'pending' && in_array($application?->status, ['pending', 'escalated', 'awaiting_final_review'], true) && $can('approve_shops'))<form method="POST" action="{{ route('admin.shops.status', $shop) }}">@csrf<input type="hidden" name="action" value="approve"><button class="font-semibold text-leaf-600">Approve</button></form>@endif @if($shop->status === 'pending' && in_array($application?->status, ['pending', 'escalated', 'awaiting_final_review'], true) && $can('reject_shops'))<form method="POST" action="{{ route('admin.shops.status', $shop) }}" data-confirm-title="Reject this application?" data-confirm-action="Reject" data-confirm-type="danger" data-confirm-reason="true">@csrf<input type="hidden" name="action" value="reject"><button class="font-semibold text-rose-600">Reject</button></form>@endif</div></td></tr>
@empty
    <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">No shops found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $shops->links('components.pagination') }}</div>
@endsection
