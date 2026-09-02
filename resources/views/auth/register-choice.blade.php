@extends('layouts.auth')
@section('title','Create Account')
@section('auth-card')
<div class="w-full max-w-4xl"><div class="text-center"><h1 class="text-3xl font-extrabold text-navy-900">Create your SHOPPICK account</h1><p class="mt-2 text-slate-500">What would you like to do on SHOPPICK?</p></div>
<div class="mt-8 grid gap-5 md:grid-cols-2">
<article class="card flex flex-col border-2 border-transparent p-7 transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-lg"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-3xl">🛍️</span><h2 class="mt-5 text-xl font-bold text-navy-900">Shop on SHOPPICK</h2><p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Discover products and shop from your favorite stores.</p><a href="{{ route('register.buyer') }}" class="btn-primary mt-6 w-full">Register as Buyer</a></article>
<article class="card flex flex-col border-2 border-transparent p-7 transition hover:-translate-y-1 hover:border-accent-200 hover:shadow-lg"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-50 text-3xl">🏪</span><h2 class="mt-5 text-xl font-bold text-navy-900">Sell on SHOPPICK</h2><p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Create your store and start selling your products.</p><a href="{{ route('register.seller') }}" class="btn-accent mt-6 w-full text-center">Register as Seller</a></article>
</div><p class="mt-6 text-center text-sm text-slate-600">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Login</a></p></div>
@endsection
