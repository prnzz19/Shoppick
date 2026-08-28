@extends('layouts.admin')

@section('title', 'Edit '.$user->name)

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Edit User</h1>
    <p class="text-sm text-slate-500">{{ $user->email }}</p>
</div>

<form method="POST" action="{{ route('superadmin.users.update', $user->id) }}" class="card max-w-2xl p-6">
    @csrf @method('PUT')
    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
            </div>
            <div>
                <label class="label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="input">
            </div>
            <div>
                <label class="label">New Password <span class="text-xs text-slate-400">(leave blank to keep)</span></label>
                <input type="password" name="password" class="input">
            </div>
            <div>
                <label class="label">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="input">
            </div>
        </div>
        <div>
            <label class="label">Roles</label>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach($roles as $role)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-navy-700">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id)) class="h-4 w-4 rounded border-slate-300 text-brand-500"> {{ $role->name }}
                    </label>
                @endforeach
            </div>
            @error('roles')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" name="is_active" value="1" @checked($user->is_active) class="h-4 w-4 rounded border-slate-300 text-brand-500"> Active
        </label>
        @error('is_active')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn-primary">Update User</button>
        <a href="{{ route('superadmin.users.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
