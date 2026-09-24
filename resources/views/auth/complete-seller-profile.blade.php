@extends('layouts.auth')
@section('title','Become a Seller')
@section('content')
<h1 class="text-2xl font-bold">Start with your Buyer account</h1><p class="mt-3">Create a Buyer account, then submit your seller application from the same account.</p><a class="btn-primary mt-5" href="{{ auth()->check()?route('seller.apply'):route('register') }}">{{ auth()->check()?'Become a Seller':'Create Buyer Account' }}</a>
@endsection
