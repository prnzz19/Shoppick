@extends('layouts.admin')

@section('title', 'Roles')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-navy-800">Roles & Permissions</h1>
    <button type="button" onclick="openRoleModal()" class="btn-primary">+ Create Role</button>
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
                @if($role->slug !== 'super_admin')
                <div class="flex gap-1">
                    <a href="{{ route('superadmin.roles.edit', $role->id) }}" class="p-2 text-slate-400 hover:text-brand-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                    <form method="POST" action="{{ route('superadmin.roles.destroy', $role->id) }}" onsubmit="return confirm('Delete this role?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-2 text-slate-400 hover:text-rose-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                    </form>
                </div>
                @endif
            </div>
            @if($role->slug === 'super_admin')
                <p class="mt-3 text-xs text-accent-500">Full access — cannot be edited or deleted.</p>
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

{{-- Modal --}}
<div id="role-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-navy-800">Create Role</h3>
            <button type="button" onclick="closeRoleModal()" class="text-slate-400 hover:text-navy-800"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('superadmin.roles.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="label">Role Name</label>
                    <input type="text" name="name" required class="input" placeholder="e.g. Support Staff">
                </div>
                <div>
                    <label class="label">Description</label>
                    <input type="text" name="description" class="input">
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn-primary flex-1">Create</button>
                <button type="button" onclick="closeRoleModal()" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openRoleModal() {
        const modal = document.getElementById('role-modal');
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeRoleModal() {
        const modal = document.getElementById('role-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }
</script>
@endpush
