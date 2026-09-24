@extends('layouts.admin')
@section('title', 'Rider Monitoring')
@section('content')
<h1 class="text-2xl font-bold text-navy-900">Riders</h1><p class="mt-1 mb-6 text-sm text-slate-500">Monitor rider activity. Account information and roles remain under Users &rarr; Logistics &rarr; Riders.</p>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr>
@foreach(['Rider','Email','Status','Active Deliveries','Completed Deliveries','Last Activity','Action'] as $label)<th class="table-th">{{ $label }}</th>@endforeach
</tr></thead><tbody>
@forelse($riders as $rider)
<tr class="border-t border-slate-100"><td class="table-td font-medium">{{ $rider->name }}</td><td class="table-td">{{ $rider->email }}</td><td class="table-td"><x-admin.status-badge :status="$rider->is_active ? ($rider->riderProfile?->account_status ?? 'active') : 'inactive'" /></td><td class="table-td">{{ $rider->current_assignments }}</td><td class="table-td">{{ $rider->completed_deliveries }}</td><td class="table-td whitespace-nowrap">{{ $rider->last_activity_at ? \Illuminate\Support\Carbon::parse($rider->last_activity_at)->format('M j, Y H:i') : 'No activity recorded' }}</td><td class="table-td"><a href="{{ route('admin.logistics.riders.show', $rider) }}" class="font-semibold text-brand-700 hover:underline">View Activity</a></td></tr>
@empty
<tr><td colspan="7" class="p-8 text-center text-slate-500">No riders found.</td></tr>
@endforelse
</tbody></table></div>
<p class="mt-3 text-xs text-slate-500">Active deliveries include delivery work and pickups awaiting handover. Completed deliveries include delivered and buyer-confirmed shipments.</p>
<div class="mt-4">{{ $riders->links() }}</div>
@endsection
