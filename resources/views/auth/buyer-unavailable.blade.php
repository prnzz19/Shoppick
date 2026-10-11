@extends('layouts.auth')
@section('title','Temporarily Unavailable')
@section('auth-card')
<section class="card max-w-md p-8"><h1 class="text-xl font-bold">Unable to complete your request.</h1><p class="mt-3 text-sm leading-6 text-slate-500">Please try again. If you already created an account, sign in to continue email verification instead of registering again.</p><a class="btn-primary mt-6" href="{{ route('login') }}">Back to sign in</a></section>
@endsection
