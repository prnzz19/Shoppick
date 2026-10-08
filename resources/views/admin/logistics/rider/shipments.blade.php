@if($shipments->isEmpty())
<div class="rd-empty">{{ $totalAssigned ? 'No assigned shipments match your filters.' : 'No shipments have been assigned to this rider yet.' }}</div>
@else
<div class="rd-table-wrap"><table class="rd-table"><thead><tr>@foreach(['Tracking','Order','Shop','Assignment','Status','Last Updated','Action'] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($shipments as $shipment)
<tr><td class="font-semibold text-navy-900">{{ $shipment->shipment_number }}@if($shipment->parcel_code)<p class="rd-muted">{{ $shipment->parcel_code }}</p>@endif</td><td>{{ $shipment->order?->order_number ?? 'Not recorded' }}</td><td>{{ $shipment->store?->name ?? 'Not recorded' }}</td><td>{{ $shipment->rider?->name ?? 'Unassigned' }}@if($shipment->pickupRider)<p class="rd-muted">Pickup: {{ $shipment->pickupRider->name }}</p>@endif</td><td>@include('admin.logistics.rider.badge',['status'=>$shipment->status])</td><td class="whitespace-nowrap">{{ $shipment->updated_at?->format('M j, Y H:i') ?? 'Not recorded' }}</td><td><a class="rd-action" href="{{ route('admin.logistics.deliveries.show',$shipment) }}" aria-label="View delivery {{ $shipment->shipment_number }}">View <span aria-hidden="true">→</span></a></td></tr>
@empty<tr><td colspan="7" class="rd-empty">{{ $totalAssigned ? 'No assigned shipments match your filters.' : 'No shipments have been assigned to this rider yet.' }}</td></tr>@endforelse
</tbody></table></div>
@endif
@include('admin.logistics.rider.pagination',['paginator'=>$shipments,'unit'=>'shipments'])
