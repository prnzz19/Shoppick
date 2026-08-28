@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Orders</h1>

    {{-- Status filter chips --}}
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.orders.index') }}" class="chip {{ !request('status') ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">All ({{ array_sum($statusCounts->toArray()) }})</a>
        @foreach($statusCounts as $status => $count)
            <a href="{{ route('admin.orders.index', ['status' => $status]) }}" class="chip {{ request('status') === $status ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">{{ ucfirst($status) }} ({{ $count }})</a>
        @endforeach
    </div>
</div>

<form method="GET" action="{{ route('admin.orders.index') }}" class="mb-5 flex gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search order # or buyer..." class="input !w-72">
    <button type="submit" class="btn-primary">Search</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Order</th>
                    <th class="table-th">Buyer</th>
                    <th class="table-th">Date</th>
                    <th class="table-th">Total</th>
                    <th class="table-th">Payment</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($orders as $order)
                    <tr>
                        <td class="table-td font-mono font-semibold text-navy-800">{{ $order->order_number }}</td>
                        <td class="table-td">{{ $order->buyer_name }}</td>
                        <td class="table-td">{{ $order->created_at->format('M d, Y') }}</td>
                        <td class="table-td font-bold">₱{{ number_format($order->total, 2) }}</td>
                        <td class="table-td">
                            <span class="capitalize text-slate-600">{{ $order->payment_method }}</span><br>
                            <x-admin.status-badge :status="$order->payment_status" />
                        </td>
                        <td class="table-td"><x-admin.status-badge :status="$order->status" /></td>
                        <td class="table-td text-right">
                            <a href="{{ route('admin.orders.show', $order->id) }}" class="btn-outline btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-td py-10 text-center text-slate-400">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $orders->links('components.pagination') }}</div>
@endsection
