@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-navy-800">Users</h1>
    <a href="{{ route('superadmin.users.create') }}" class="btn-primary">+ Add User</a>
</div>

@php
    $userTabs = [
        'all' => 'All Users',
        'buyers' => 'Buyers',
        'sellers' => 'Sellers',
        'other' => 'Other Roles',
    ];
@endphp
<nav class="mb-5 overflow-x-auto border-b border-slate-200" aria-label="User type filters">
    <div class="flex min-w-max gap-1">
        @foreach($userTabs as $key => $label)
            <a href="{{ route('superadmin.users.index', array_merge(request()->except(['tab', 'page']), $key === 'all' ? [] : ['tab' => $key])) }}"
               class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === $key ? 'border-brand-500 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-navy-800' }}"
               @if($tab === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $tab === $key ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500' }}">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </div>
</nav>

<form method="GET" action="{{ route('superadmin.users.index') }}" class="mb-5 flex flex-wrap gap-3">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email..." class="input !w-64">
    <select name="role" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All roles</option>
        @foreach($roles as $role)
            <option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>
        @endforeach
    </select>
    <select name="status" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All status</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
</form>

<div class="card overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-4 py-3 text-sm text-slate-500">
        Showing: <span class="font-semibold text-navy-800">{{ $userTabs[$tab] }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">User</th>
                    <th class="table-th">Roles</th>
                    <th class="table-th">Joined</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <tr>
                        <td class="table-td">
                            <a href="{{ route('superadmin.users.show', $user->id) }}" class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-brand-50">
                                    @if($user->avatar_url)<img src="{{ $user->avatar_url }}" class="h-full w-full object-cover">@endif
                                </div>
                                <div>
                                    <p class="font-medium text-navy-800">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                </div>
                            </a>
                        </td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <span class="badge {{ $role->slug === 'super_admin' ? 'bg-accent-100 text-accent-600' : ($role->slug === 'admin' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600') }}">{{ $role->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="table-td text-sm text-slate-500">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="table-td">
                            @if($user->id === auth()->id())
                                <span class="badge bg-leaf-100 text-leaf-500">You</span>
                            @else
                                <form method="POST" action="{{ route('superadmin.users.toggle', $user->id) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $user->is_active ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</button>
                                </form>
                            @endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('superadmin.users.edit', $user->id) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                @unless($user->hasRole('super_admin'))
                                <form method="POST" action="{{ route('superadmin.users.destroy', $user->id) }}" data-confirm-title="Delete this user?" data-confirm-message="This action may permanently remove the selected user." data-confirm-action="Delete" data-confirm-type="danger">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-td py-10 text-center text-slate-400">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $users->links('components.pagination') }}</div>
@endsection
