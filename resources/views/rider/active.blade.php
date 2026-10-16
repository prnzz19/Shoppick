@extends('layouts.rider')
@section('title','Active Delivery')
@section('content')
<section class="rider-card border-dashed p-10 text-center"><h1 class="text-xl font-black">No Active Delivery</h1><p class="mt-3 text-sm text-slate-500">You don't have an active delivery right now.</p><a class="btn-primary mt-6" href="{{ route('rider.jobs') }}">View My Deliveries</a></section>
@endsection
