@extends('layouts.logistics')
@section('title','Account')
@section('content')
<div class="log-page-head"><div><h1>Logistics Account</h1><p>Manage the authenticated Sorting Center account and security settings.</p></div></div>
<div class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px]">
<form method="POST" enctype="multipart/form-data" action="{{ route('account.update') }}" class="log-card p-6">@csrf<h2 class="font-bold">Profile Information</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="label">Name<input class="input mt-1" name="name" value="{{ old('name',auth()->user()->name) }}" required></label><label class="label">Email<input class="input mt-1" type="email" name="email" value="{{ old('email',auth()->user()->email) }}" required></label><label class="label">Contact<input class="input mt-1" name="phone" value="{{ old('phone',auth()->user()->phone) }}"></label><label class="label">Profile photo<input class="input mt-1" type="file" name="avatar" accept="image/*"></label></div><button class="btn-primary mt-5">Save Profile</button></form>
<aside class="log-card p-6"><p class="text-xs font-bold uppercase tracking-wide text-brand-600">Sorting Center Account</p><h2 class="mt-2 text-lg font-bold">SHOPPICK Logistics</h2><p class="mt-1 text-sm text-slate-500">Business / unit: SHOPPICK Sorting Center</p><p class="mt-4 text-sm">Account status: <b>{{ auth()->user()->is_active ? 'Active' : 'Inactive' }}</b></p><a class="btn-outline mt-5 block text-center" href="{{ route('account.password') }}">Change Password</a></aside>
</div>
@endsection
