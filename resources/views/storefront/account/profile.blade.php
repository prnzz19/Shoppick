@extends('layouts.account')

@section('title', 'My Profile')

@section('account-content')
<div class="card p-6">
    <h1 class="text-xl font-bold text-navy-800">My Profile</h1>

    <form method="POST" action="{{ route('account.update') }}" enctype="multipart/form-data" class="mt-6">
        @csrf
        <div class="mb-6 flex items-center gap-4">
            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-600">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover">
                @else
                    <span class="text-3xl font-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>
                @endif
            </div>
            <label class="cursor-pointer">
                <input type="file" name="avatar" accept="image/*" class="hidden">
                <span class="btn-outline btn-sm">Upload Photo</span>
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="input">
            </div>
            <div>
                <label class="label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="input">
            </div>
            <div>
                <label class="label">Member Since</label>
                <input type="text" value="{{ auth()->user()->created_at->format('M d, Y') }}" class="input" disabled>
            </div>
        </div>

        <button type="submit" class="btn-primary mt-6">Save Changes</button>
    </form>
</div>

{{-- Recent orders --}}
<div class="card mt-6 p-6">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-navy-800">Recent Orders</h2>
        <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">View all</a>
    </div>
    @if($orders->isEmpty())
        <p class="text-sm text-slate-500">No orders yet.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach($orders as $order)
                <a href="{{ route('orders.show', $order->order_number) }}" class="flex items-center justify-between py-3 text-sm hover:bg-slate-50">
                    <div>
                        <p class="font-mono font-semibold text-navy-800">{{ $order->order_number }}</p>
                        <p class="text-xs text-slate-500">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-navy-800">₱{{ number_format($order->total, 2) }}</p>
                        <p class="text-xs capitalize text-slate-500">{{ $order->status }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
