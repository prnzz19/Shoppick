@extends('layouts.admin')

@section('title', 'Add User')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Add User</h1>
</div>

<form method="POST" action="{{ route('superadmin.users.store') }}" class="card max-w-2xl p-6">
    @csrf
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="input">
            </div>
            <div>
                <label class="label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="input">
            </div>
            <div>
                <label class="label">Password</label>
                <input type="password" name="password" required class="input">
            </div>
            <div>
                <label class="label">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="input">
            </div>
        </div>
        <div>
            <label class="label">Roles</label>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach($roles as $role)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-navy-700">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->slug, ['buyer'])) class="h-4 w-4 rounded border-slate-300 text-brand-500"> {{ $role->name }}
                    </label>
                @endforeach
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-500"> Active
        </label>
    </div>
    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn-primary">Create User</button>
        <a href="{{ route('superadmin.users.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
