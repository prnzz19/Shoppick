@extends('layouts.admin')

@section('title', 'Admin Management')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Admin Management</h1>
    <p class="text-sm text-slate-500">Accounts with admin or super admin roles</p>
</div>

<form method="GET" action="{{ route('superadmin.admins.index') }}" class="mb-5 flex gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email..." class="input !w-64">
    <button type="submit" class="btn-primary">Search</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Admin</th>
                    <th class="table-th">Roles</th>
                    <th class="table-th">Joined</th>
                    <th class="table-th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($admins as $admin)
                    <tr>
                        <td class="table-td">
                            <a href="{{ route('superadmin.users.show', $admin->id) }}" class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-brand-50">
                                    @if($admin->avatar_url)<img src="{{ $admin->avatar_url }}" class="h-full w-full object-cover">@endif
                                </div>
                                <div>
                                    <p class="font-medium text-navy-800">{{ $admin->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $admin->email }}</p>
                                </div>
                            </a>
                        </td>
                        <td class="table-td">
                            @foreach($admin->roles as $role)
                                <span class="badge {{ $role->slug === 'super_admin' ? 'bg-accent-100 text-accent-600' : 'bg-brand-100 text-brand-700' }}">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td class="table-td text-sm text-slate-500">{{ $admin->created_at->format('M d, Y') }}</td>
                        <td class="table-td text-right">
                            <a href="{{ route('superadmin.users.edit', $admin->id) }}" class="btn-outline btn-sm">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-td py-10 text-center text-slate-400">No admins found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $admins->links('components.pagination') }}</div>
@endsection
