@extends('layouts.admin')

@section('title', $user->name)

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-4">
        <div class="h-16 w-16 overflow-hidden rounded-full bg-brand-50">
            @if($user->avatar_url)<img src="{{ $user->avatar_url }}" class="h-full w-full object-cover">@endif
        </div>
        <div>
            <h1 class="text-2xl font-bold text-navy-800">{{ $user->name }}</h1>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('superadmin.users.edit', $user->id) }}" class="btn-outline btn-sm">Edit</a>
        <a href="{{ route('superadmin.users.reset-password', $user->id) }}" class="btn-accent btn-sm">Reset Password</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Orders</h3>
            @if($user->orders->isEmpty())
                <p class="text-sm text-slate-400">No orders placed.</p>
            @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr><th class="table-th">Order</th><th class="table-th">Date</th><th class="table-th">Total</th><th class="table-th">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($user->orders->take(10) as $o)
                            <tr>
                                <td class="table-td font-mono font-semibold text-navy-800">{{ $o->order_number }}</td>
                                <td class="table-td text-sm text-slate-500">{{ $o->created_at->format('M d, Y') }}</td>
                                <td class="table-td font-bold">₱{{ number_format($o->total, 2) }}</td>
                                <td class="table-td"><x-admin.status-badge :status="$o->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Roles</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($user->roles as $role)
                    <span class="badge {{ $role->slug === 'super_admin' ? 'bg-accent-100 text-accent-600' : ($role->slug === 'admin' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600') }}">{{ $role->name }}</span>
                @endforeach
                @if($user->roles->isEmpty())<span class="text-sm text-slate-400">No roles assigned</span>@endif
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-sm">
                <div><dt class="text-slate-500">Phone</dt><dd class="font-medium text-navy-800">{{ $user->phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Status</dt><dd class="font-medium text-navy-800">{{ $user->is_active ? 'Active' : 'Inactive' }}</dd></div>
                <div><dt class="text-slate-500">Joined</dt><dd class="font-medium text-navy-800">{{ $user->created_at->format('M d, Y') }}</dd></div>
                <div><dt class="text-slate-500">Last Updated</dt><dd class="font-medium text-navy-800">{{ $user->updated_at->format('M d, Y') }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Activity Log</h3>
        <div class="space-y-3">
            @forelse($activity as $log)
                <div class="border-l-2 border-brand-200 pl-3">
                    <p class="text-sm font-medium text-navy-800">{{ str_replace('.', ' ', ucwords($log->action, '.')) }}</p>
                    <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-400">No activity recorded.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
