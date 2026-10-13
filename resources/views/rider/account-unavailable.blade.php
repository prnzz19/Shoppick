@extends('layouts.auth')
@section('title','Rider Access Unavailable')
@section('auth-card')
<section class="card w-full max-w-lg p-7 sm:p-9"><p class="text-xs font-bold uppercase tracking-widest text-brand-700">SHOPPICK Rider</p><h1 class="mt-4 text-2xl font-black text-navy-900">Rider access unavailable</h1><p class="mt-3 text-sm leading-7 text-slate-600">Your Rider account is currently unavailable. Please contact SHOPPICK Logistics to review an inactive or suspended account.</p><p class="mt-3 text-sm leading-7 text-slate-600">You cannot access assigned deliveries until your account is active.</p>@auth<form class="mt-6" method="POST" action="{{ route('rider.logout') }}">@csrf<button class="btn-outline min-h-11 w-full">Sign out</button></form>@else<a class="btn-outline mt-6 min-h-11 w-full" href="{{ route('rider.login') }}">Back to Rider Login</a>@endauth</section>
@endsection
