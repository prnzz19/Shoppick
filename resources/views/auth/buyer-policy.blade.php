@extends('layouts.auth')
@section('title',$title)
@section('auth-card')
<section class="card w-full max-w-2xl p-6 sm:p-8"><h1 class="text-2xl font-bold">{{ $title }}</h1>@if(filled($content))<div class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $content }}</div>@else<p class="mt-5 text-sm leading-7 text-slate-600">The current SHOPPICK {{ $title }} text has not been published here. Please contact SHOPPICK support before accepting it.</p>@endif<a class="btn-outline mt-6" href="{{ route('register') }}">Back to registration</a></section>
@endsection
