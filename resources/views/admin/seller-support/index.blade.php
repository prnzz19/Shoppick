@extends('layouts.admin')
@section('title','Seller Support')
@section('content')
<div class="mx-auto max-w-6xl"><div class="mb-5"><h1 class="text-2xl font-black text-navy-800">Seller Support</h1><p class="mt-1 text-sm text-slate-500">Reply to SHOPPICK Sellers without mixing support messages with customer chats.</p></div><x-marketplace-chat mode="admin" :embedded="true" /></div>
@endsection
