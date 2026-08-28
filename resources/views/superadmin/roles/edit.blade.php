@extends('layouts.admin')

@section('title', 'Edit Role')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Edit Role: {{ $role->name }}</h1>
    <p class="text-sm text-slate-500">{{ $role->description }}</p>
</div>

<form method="POST" action="{{ route('superadmin.roles.update', $role->id) }}" class="card max-w-3xl p-6">
    @csrf @method('PUT')
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Role Name</label>
            <input type="text" name="name" value="{{ old('name', $role->name) }}" required class="input">
        </div>
        <div>
            <label class="label">Description</label>
            <input type="text" name="description" value="{{ old('description', $role->description) }}" class="input">
        </div>
    </div>

    <h3 class="mb-3 mt-6 text-sm font-bold uppercase tracking-wide text-navy-800">Permissions</h3>
    <div class="space-y-5">
        @foreach($permissions as $group => $perms)
            <div>
                <p class="mb-2 text-sm font-semibold capitalize text-navy-700">{{ $group }}</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($perms as $perm)
                        <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-navy-700">
                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked($role->permissions->contains('id', $perm->id)) class="h-4 w-4 rounded border-slate-300 text-brand-500"> {{ $perm->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn-primary">Save Role</button>
        <a href="{{ route('superadmin.roles.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
