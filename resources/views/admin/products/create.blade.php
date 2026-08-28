@extends('layouts.admin')

@section('title', 'Add Product')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Add Product</h1>
</div>
<form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
    @include('admin.products._form', ['product' => null])
</form>
@endsection
