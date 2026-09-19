@extends('layouts.admin')

@section('title', 'Roles')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-navy-800">Roles & Permissions</h1>
    <span class="text-sm text-slate-500">Five protected SHOPPICK roles</span>
</div>

<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    @foreach($roles as $role)
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-bold text-navy-800">{{ $role->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ $role->description }}</p>
                    <div class="mt-3 flex gap-2">
                        <span class="badge bg-slate-100 text-slate-600">{{ $role->users_count }} users</span>
                        <span class="badge bg-slate-100 text-slate-600">{{ $role->permissions_count }} permissions</span>
                    </div>
                </div>
                @if($role->slug !== 'admin')
                <div class="flex gap-1">
                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="p-2 text-slate-400 hover:text-brand-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                </div>
                @endif
            </div>
            @if($role->slug === 'admin')
                <p class="mt-3 text-xs text-accent-500">Full system access — protected.</p>
            @endif
            @if($role->permissions->isNotEmpty())
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach($role->permissions->take(6) as $perm)
                            <span class="badge bg-brand-50 text-brand-700 text-[10px]">{{ $perm->name }}</span>
                        @endforeach
                        @if($role->permissions_count > 6)<span class="badge bg-slate-100 text-slate-500 text-[10px]">+{{ $role->permissions_count - 6 }}</span>@endif
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>

@endsection
