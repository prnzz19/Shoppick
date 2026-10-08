<div class="card overflow-x-auto"><table class="w-full"><thead><tr>@foreach(['Seller','Shop','Orders','Sales','Status','Joined','Actions'] as $label)<th class="table-th">{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($sellers as $seller)
<tr>
<td class="table-td"><span class="font-semibold text-navy-900">{{ $seller->name }}</span><p class="text-xs text-slate-500 break-all">{{ $seller->email }}</p>@if($archived)<span class="badge bg-accent-50 text-accent-600">Archived</span><p class="text-xs text-slate-500">{{ $seller->sellerProfile->archived_at->format('M d, Y') }}</p>@endif</td>
<td class="table-td"><div class="flex items-center gap-2"><span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-50 font-semibold text-brand-700">@if($seller->store?->logo)<img class="h-full w-full object-cover" src="{{ asset('storage/'.$seller->store->logo) }}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span hidden>{{ mb_substr($seller->store->name,0,1) }}</span>@else{{ mb_substr($seller->store?->name ?? 'Shop',0,1) }}@endif</span><span>{{ $seller->store?->name ?? 'No shop' }}</span></div></td>
<td class="table-td tabular-nums">{{ number_format($seller->orders_count) }}</td>
<td class="table-td whitespace-nowrap"><span class="font-semibold text-navy-900">₱{{ number_format($seller->sales_total,2) }}</span>@if($seller->orders_count == 0)<p class="text-xs text-slate-500">No sales in period</p>@endif</td>
<td class="table-td"><x-admin.status-badge :status="$seller->sellerProfile->status"/>@if($seller->store)<p class="mt-1 text-xs text-slate-500">Shop: {{ str($seller->store->status)->headline() }}{{ $seller->store->archived_at ? ' · Archived' : '' }}</p>@endif</td>
<td class="table-td whitespace-nowrap">{{ $seller->created_at->format('M d, Y') }}</td>
<td class="table-td">@if($seller->store && in_array($seller->sellerProfile->status, ['approved','suspended'], true))<a class="btn-outline btn-sm" href="{{ route('admin.sellers.show',$seller) }}">View</a>@endif
@include('admin.sellers._archive-actions', ['record'=>$seller->sellerProfile, 'prefix'=>'admin.sellers', 'label'=>'Seller'])</td>
</tr>
@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">No sellers match your filters.@if($hasFilters)<a data-sellers-clear class="mt-2 block text-brand-700" href="{{ route('admin.sellers.index',$archived ? ['tab'=>'archived'] : []) }}">Clear Filters</a>@endif</td></tr>@endforelse
</tbody></table></div>
