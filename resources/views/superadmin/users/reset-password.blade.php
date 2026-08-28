@extends('layouts.admin')

@section('title', 'Reset Password')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Reset Password</h1>
    <p class="text-sm text-slate-500">For {{ $user->name }} ({{ $user->email }})</p>
</div>

<form method="POST" action="{{ route('superadmin.users.reset-password.store', $user->id) }}" class="card max-w-md p-6">
    @csrf
    <div class="space-y-4">
        <div>
            <label class="label">New Password</label>
            <input type="password" name="password" required class="input">
        </div>
        <div>
            <label class="label">Confirm Password</label>
            <input type="password" name="password_confirmation" required class="input">
        </div>
    </div>
    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn-accent">Reset Password</button>
        <a href="{{ route('superadmin.users.show', $user->id) }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
