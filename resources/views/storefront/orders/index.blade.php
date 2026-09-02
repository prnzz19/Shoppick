@extends('layouts.storefront')
@section('title', 'My Orders')
@section('content')
<div class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8">
    <h1 class="mb-6 text-2xl font-bold text-navy-800">My Orders</h1>
    @php
        $tabs=['all'=>'All','to_pay'=>'To Pay','to_ship'=>'To Ship','to_receive'=>'To Receive','completed'=>'Completed','cancelled'=>'Cancelled','history'=>'Purchase History'];
        $empty=['to_ship'=>['No orders are currently waiting to ship.','Orders being prepared by sellers will appear here.'],'completed'=>['No completed orders yet.','Delivered purchases will appear here after completion.'],'cancelled'=>['No cancelled orders.','Cancelled purchases will appear here.'],'history'=>["You don't have any purchase history yet.",'Completed, cancelled, and refunded orders will appear here.']];
        [$emptyTitle,$emptyCopy]=$empty[$tab]??['No orders here yet','When you place an order, it will appear here.'];
        $badges=['pending'=>'bg-sun-100 text-sun-600','confirmed'=>'bg-blue-100 text-blue-700','processing'=>'bg-brand-100 text-brand-700','packed'=>'bg-brand-100 text-brand-700','ready_to_ship'=>'bg-brand-100 text-brand-700','shipped'=>'bg-brand-100 text-brand-700','delivered'=>'bg-leaf-100 text-leaf-600','completed'=>'bg-leaf-100 text-leaf-600','cancelled'=>'bg-rose-100 text-rose-600','refunded'=>'bg-indigo-100 text-indigo-700'];
    @endphp

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Order status">
        @foreach($tabs as $key=>$label)
            <a href="{{ route('orders.index',['tab'=>$key]) }}" @if($tab===$key) aria-current="page" @endif class="group inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold {{ $tab===$key?'border-brand-500 text-brand-600':'border-transparent text-slate-500 hover:text-brand-700' }}">
                @switch($key)
                    @case('all')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z"/></svg>
                        @break
                    @case('to_pay')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7.5h18m-16.5-3h15A1.5 1.5 0 0 1 21 6v12a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V6a1.5 1.5 0 0 1 1.5-1.5ZM7 15h3"/></svg>
                        @break
                    @case('to_ship')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7m-8 4v10"/></svg>
                        @break
                    @case('to_receive')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16V6h11v10H3Zm11-7h3l4 4v3h-7V9ZM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                        @break
                    @case('completed')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 1 1-4.1-7.55M8 12l2.7 2.7L21 4.5"/></svg>
                        @break
                    @case('cancelled')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 9 9 15m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        @break
                    @case('history')
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2"/></svg>
                        @break
                @endswitch
                <span>{{ $label }}</span><span class="text-xs font-medium opacity-70">({{ $tabCounts[$key] }})</span>
            </a>
        @endforeach
    </nav>

    @if($tab==='history')
        <div class="card mb-6 p-4">
            <div class="mb-4 flex gap-2 overflow-x-auto">
                @foreach(['all'=>'All History','completed'=>'Completed','cancelled'=>'Cancelled','refunded'=>'Refunded'] as $key=>$label)
                    <a href="{{ route('orders.index',array_merge(request()->except('page','history_status'),['tab'=>'history','history_status'=>$key])) }}" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold {{ request('history_status','all')===$key?'bg-brand-600 text-white':'bg-slate-100 text-slate-600 hover:bg-brand-50 hover:text-brand-700' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('orders.index') }}" class="grid gap-3 md:grid-cols-[minmax(220px,1fr)_180px_170px_auto]">
                <input type="hidden" name="tab" value="history"><input type="hidden" name="history_status" value="{{ request('history_status','all') }}">
                <input name="q" value="{{ request('q') }}" class="input" placeholder="Search past orders..." aria-label="Search purchase history">
                <select name="date" class="input">@foreach(['all'=>'All Time','30_days'=>'Last 30 Days','3_months'=>'Last 3 Months','6_months'=>'Last 6 Months','this_year'=>'This Year'] as $key=>$label)<option value="{{ $key }}" @selected(request('date','all')===$key)>{{ $label }}</option>@endforeach</select>
                <select name="sort" class="input">@foreach(['newest'=>'Newest First','oldest'=>'Oldest First','total_high'=>'Highest Total','total_low'=>'Lowest Total'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','newest')===$key)>{{ $label }}</option>@endforeach</select>
                <button class="btn-primary justify-center">Apply</button>
            </form>
        </div>
    @endif

    @if($orders->isEmpty())
        <div class="card flex flex-col items-center justify-center p-12 text-center sm:p-16">
            <svg class="h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-navy-800">{{ $emptyTitle }}</h3><p class="mt-1 text-sm text-slate-500">{{ $emptyCopy }}</p><a href="{{ route('products.index') }}" class="btn-primary mt-4">Continue Shopping</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                @php
                    $shops=$order->sellerOrders->pluck('store.name')->filter()->unique()->values(); $payment=$order->payments->sortByDesc('created_at')->first(); $firstItem=$order->items->first();
                    $reviewItem=$order->status==='completed'?$order->items->first(fn($item)=>$item->product_id&&!$order->reviews->contains('product_id',$item->product_id)):null;
                    $activeStatuses=['pending','confirmed','processing','packed','shipped','delivered'];
                @endphp
                <article class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 lg:px-6">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1"><span class="font-mono text-sm font-bold text-navy-800">{{ $order->order_number }}</span><span class="hidden text-slate-300 sm:inline">•</span><time class="text-sm text-slate-500">{{ $order->created_at->format('M d, Y · h:i A') }}</time></div>
                        <div class="flex items-center gap-2"><span class="badge {{ $badges[$order->status]??'bg-slate-100 text-slate-600' }}">{{ str($order->status)->replace('_',' ')->title() }}</span>@if($unreadOrderIds->contains($order->id))<span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600"><span class="h-2 w-2 rounded-full bg-brand-500"></span>New update</span>@endif</div>
                    </div>
                    <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(210px,.7fr)_minmax(190px,.55fr)] lg:items-center lg:p-6">
                        <div class="min-w-0">
                            <div class="mb-4">
                                <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-slate-400">{{ $shops->count()===1?'Shop':$shops->count().' Shops' }}</p>
                                <div class="mt-1.5 flex min-w-0 items-center gap-2 text-brand-600">
                                    <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 10v9h16v-9M3 10l2-6h14l2 6m-18 0a3 3 0 0 0 6 0m0 0a3 3 0 0 0 6 0m0 0a3 3 0 0 0 6 0M8 19v-5h4v5"/></svg>
                                    <p class="min-w-0 truncate text-base font-semibold text-navy-800" title="{{ $shops->isNotEmpty()?$shops->join(', '):'SHOPPICK Marketplace' }}">{{ $shops->isNotEmpty()?$shops->join(', '):'SHOPPICK Marketplace' }}</p>
                                </div>
                            </div>
                            <div class="space-y-3">@foreach($order->items->take(3) as $item)<div class="flex min-w-0 items-center gap-3"><div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">@if($item->product_image)<img src="{{ asset('storage/'.$item->product_image) }}" alt="" class="h-full w-full object-cover">@endif</div><div class="min-w-0"><p class="truncate text-sm font-semibold text-navy-800">{{ $item->product_name }}</p>@if($item->variant_label)<p class="truncate text-xs text-slate-500">Variant: {{ $item->variant_label }}</p>@endif<p class="text-xs text-slate-500">Qty: {{ $item->quantity }}</p></div></div>@endforeach @if($order->items->count()>3)<p class="pl-[4.25rem] text-xs font-semibold text-brand-600">+{{ $order->items->count()-3 }} more product(s)</p>@endif</div>
                        </div>
                        <dl class="grid grid-cols-2 gap-4 border-y border-slate-100 py-4 text-sm lg:grid-cols-1 lg:border-y-0 lg:border-l lg:py-0 lg:pl-6">
                            <div><dt class="text-xs text-slate-400">Payment Method</dt><dd class="mt-1 font-semibold text-navy-800">{{ $order->paymentMethodLabel() }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Payment Status</dt><dd class="mt-1 font-semibold {{ $order->effectivePaymentStatus()==='paid'?'text-leaf-600':($order->effectivePaymentStatus()==='failed'?'text-rose-600':'text-sun-600') }}">{{ $order->paymentStatusLabel() }}</dd>@if($order->payment_method==='cod'&&!in_array($order->effectivePaymentStatus(),['paid','cod_collected'],true))<p class="mt-1 text-xs text-slate-400">Pay ₱{{ number_format($order->total,2) }} when your order arrives.</p>@endif</div>
                            <div class="col-span-2 lg:col-span-1"><dt class="text-xs text-slate-400">Fulfillment</dt><dd class="mt-1 flex flex-wrap gap-1.5">@forelse($order->sellerOrders as $sellerOrder)<span class="badge {{ $badges[$sellerOrder->status]??'bg-slate-100 text-slate-600' }}">{{ str($sellerOrder->status)->replace('_',' ')->title() }}</span>@empty<span class="font-semibold text-navy-800">{{ str($order->status)->title() }}</span>@endforelse</dd></div>
                            @if($tab==='history'&&$order->completed_at)<div><dt class="text-xs text-slate-400">Completed</dt><dd class="mt-1 font-semibold text-navy-800">{{ $order->completed_at->format('M d, Y') }}</dd></div>@endif
                        </dl>
                        <div class="flex flex-col gap-3 lg:items-end lg:border-l lg:border-slate-100 lg:pl-6 lg:text-right">
                            <div><p class="text-sm text-slate-500">{{ $order->items->sum('quantity') }} {{ str('item')->plural($order->items->sum('quantity')) }}</p><p class="text-xl font-bold text-navy-800">₱{{ number_format($order->total,2) }}</p></div>
                            <div class="flex w-full flex-wrap gap-2 lg:justify-end"><a href="{{ route('orders.show',$order->order_number) }}" class="btn-primary btn-sm justify-center">View Order</a>@if(in_array($order->status,$activeStatuses,true))<a href="{{ route('orders.show',$order->order_number) }}" class="btn-secondary btn-sm justify-center">Track Order</a>@endif @if(in_array($order->status,['completed','cancelled','refunded'],true)&&$firstItem)<form method="POST" action="{{ route('orders.buy-again',[$order->order_number,$firstItem]) }}">@csrf<button class="btn-secondary btn-sm">Buy Again</button></form>@endif @if($reviewItem)<a href="{{ route('review.create',[$order->order_number,$reviewItem->product_id]) }}" class="btn-accent btn-sm justify-center">Review Product</a>@endif</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div><div class="mt-6">{{ $orders->links('components.pagination') }}</div>
    @endif
</div>
@endsection
