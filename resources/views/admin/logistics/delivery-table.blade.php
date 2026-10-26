<div class="overflow-x-auto">
<table class="w-full text-left text-sm">
<thead><tr>
<th class="table-th">Tracking{{ ($compact ?? false) ? '' : ' Code' }}</th><th class="table-th">Order</th>
<th class="table-th">Shop</th>@if(!($compact ?? false))<th class="table-th">Buyer</th><th class="table-th">Logistics Provider</th>@endif
<th class="table-th">Rider</th><th class="table-th">Status</th><th class="table-th whitespace-nowrap">Last Updated</th><th class="table-th">Action</th>
</tr></thead>
<tbody>
@forelse($shipments as $shipment)
<tr class="border-t border-slate-100">
<td class="table-td font-medium text-navy-900">{{ $shipment->shipment_number }}@if($shipment->parcel_code)<span class="block text-xs font-normal text-slate-500">{{ $shipment->parcel_code }}</span>@endif</td>
<td class="table-td">{{ $shipment->order?->order_number ?? 'Not recorded' }}<span class="block text-xs text-slate-500">ID: {{ $shipment->order_id ?? '—' }}</span></td>
<td class="table-td">{{ $shipment->store?->name ?? 'Not recorded' }}</td>
@if(!($compact ?? false))
<td class="table-td">{{ $shipment->order?->user?->name ?? $shipment->order?->buyer_name ?? 'Not recorded' }}</td>
<td class="table-td text-slate-500">Not recorded</td>
@endif
<td class="table-td">{{ $shipment->rider?->name ?? 'Unassigned' }}@if($shipment->pickupRider)<span class="block text-xs text-slate-500">Pickup: {{ $shipment->pickupRider->name }}</span>@endif</td>
<td class="table-td"><x-admin.status-badge :status="$shipment->status" /></td>
<td class="table-td whitespace-nowrap">{{ $shipment->updated_at?->format('M j, Y H:i') ?? '—' }}</td>
<td class="table-td"><a class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-extrabold text-brand-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-200" href="{{ route('admin.logistics.deliveries.show', $shipment) }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.4-5 9.5-5 9.5 5 9.5 5-3.4 5-9.5 5-9.5-5-9.5-5z"/><circle cx="12" cy="12" r="2.3" stroke-width="1.8"/></svg>View Details<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 18 6-6-6-6"/></svg></a></td>
</tr>
@empty
<tr><td colspan="{{ ($compact ?? false) ? 7 : 9 }}" class="p-8 text-center text-slate-500">No deliveries found.</td></tr>
@endforelse
</tbody>
</table>
</div>
