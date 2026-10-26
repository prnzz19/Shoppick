@extends('layouts.logistics')
@section('title','Sorting')
@section('content')
@php
    $summary = $summary ?? ['total' => 0, 'awaiting_scan' => 0, 'ready_to_sort' => 0, 'sorted' => 0];
    $data = $data ?? request()->only(['q', 'status', 'area_id', 'hub_id', 'region_code', 'province_code', 'municipality_code', 'barangay_code']);
    $hubs = $hubs ?? collect();
    $hub = $hub ?? null;
    $sortingMunicipalities = $sortingMunicipalities ?? collect();
    $sortingBarangays = $sortingBarangays ?? collect();
    $coveredBarangayIds = $coveredBarangayIds ?? collect();
    $sortingRegions = $sortingRegions ?? collect();
    $sortingProvinces = $sortingProvinces ?? collect();
    $activeAreas = $areas->where('is_active', true);
    $statusLabels = ['awaiting_scan' => 'Awaiting scan', 'ready_to_sort' => 'Ready to sort', 'sorted' => 'Sorted'];
    $geoDetails = $geoDetails ?? collect();
    $initialSortingBarangays = $sortingBarangays->map(function ($row) use ($coveredBarangayIds) {
        return [
            'code' => $row->code,
            'name' => $row->name,
            'municipality' => $row->municipality,
            'municipality_code' => $row->municipality_code,
            'coverage_status' => $coveredBarangayIds->contains((int) $row->id) ? 'covered' : 'unconfigured',
        ];
    })->values();
@endphp
<div class="sorting-page"><div class="log-page-head"><div><p class="log-eyebrow">LOGISTICS OPERATIONS</p><h1>Parcel Sorting</h1><p>Confirm parcel scans, match delivery coverage, and route each load to the correct Delivery Area.</p></div><a class="btn-outline" href="{{ route('logistics.incoming') }}">View incoming parcels</a></div>
<div class="log-kpis sorting-summary" aria-label="Sorting summary">
    <article class="log-kpi"><span class="sorting-kpi-icon">+</span><div><b>{{ $summary['awaiting_scan'] }}</b><p>Awaiting scan</p></div></article>
    <article class="log-kpi"><span class="sorting-kpi-icon">o</span><div><b>{{ $summary['ready_to_sort'] }}</b><p>Ready to sort</p></div></article>
    <article class="log-kpi"><span class="sorting-kpi-icon">OK</span><div><b>{{ $summary['sorted'] }}</b><p>Sorted parcels</p></div></article>
    <article class="log-kpi"><span class="sorting-kpi-icon sorting-kpi-icon--total">=</span><div><b>{{ $summary['total'] }}</b><p>Total relevant parcels</p></div></article>
</div>
<div class="log-card sorting-guidance"><strong>Workflow guardrails</strong><p>Sorting confirms the Delivery Area only. Rider assignment remains controlled by the shipment workflow after a valid destination arrival; this page never assigns a Rider directly.</p></div>
<div class="log-card sorting-guidance"><strong>Need an inter-hub route?</strong><p>After a Parcel is sorted and its origin arrival is verified, choose an active destination Center and create the transfer from the Hub Transfers queue.</p><a class="btn-outline btn-sm" href="{{ route('logistics.hub-transfers') }}">Open Hub Transfers</a></div>
<div class="log-card sorting-guidance"><strong>Sorting Center context</strong><p>{{ $hub ? 'Showing parcels physically recorded at '.$hub->name.'. Queue results never use the buyer address to infer the current facility.' : 'No active Sorting Center is available for your account. Ask a Logistics manager to assign or activate a provider-owned center.' }}</p></div>
@if($sortingRegions->isEmpty() && $sortingProvinces->isEmpty() && $sortingMunicipalities->isEmpty())
<div class="log-card sorting-guidance" role="status"><strong>Geographic data has not been loaded.</strong><p>The national PSGC reference catalog is unavailable. Sorting Center coverage is separate and cannot populate the national geographic list.</p></div>
@endif
{{-- Delivery coverage is managed from each Sorting Center's Service Coverage settings. --}}
<form method="GET" action="{{ route('logistics.sorting') }}" class="log-card log-filters sorting-filters" aria-label="Filter sorting queue" data-sorting-filter-form data-areas-url="{{ route('logistics.api.sorting-delivery-areas') }}" data-summary-url="{{ route('logistics.api.sorting-summary') }}"><label class="sr-only" for="sorting-hub">Sorting Center</label><select class="input" id="sorting-hub" name="hub_id" aria-label="Sorting Center" data-sorting-hub @disabled($hubs->isEmpty())>@foreach($hubs as $sortingHub)<option value="{{ $sortingHub->id }}" @selected((string)($data['hub_id'] ?? '') === (string)$sortingHub->id)>{{ $sortingHub->name }}{{ $sortingHub->municipality ? ' · '.$sortingHub->municipality : '' }}</option>@endforeach</select><input class="input" type="search" name="q" value="{{ $data['q'] ?? '' }}" placeholder="Search parcel, shipment, or store" aria-label="Search parcels" @disabled(!$hub)><select class="input" name="status" aria-label="Filter by sorting status" @disabled(!$hub)><option value="">All queue statuses</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($data['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select><select class="input" name="area_id" aria-label="Filter by delivery area" data-sorting-area @disabled(!$hub)><option value="">All delivery areas</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected((string)($data['area_id'] ?? '') === (string)$area->id)>{{ $area->name }}{{ $area->is_active ? '' : ' (inactive)' }}</option>@endforeach</select><button class="btn-primary" type="submit" @disabled(!$hub)>Apply filters</button>@if(($data['q'] ?? null) || ($data['status'] ?? null) || ($data['area_id'] ?? null))<a class="btn-outline" href="{{ route('logistics.sorting', ['hub_id' => $hub?->id]) }}">Clear</a>@endif</form>
<div class="log-card sorting-queue"><div class="sorting-queue-head"><div><h2>Sorting queue</h2><p>{{ $shipments->total() }} parcels match the current filters.</p></div><span>Provider-owned shipments only</span></div><div class="sorting-table-wrap"><table class="log-table sorting-table"><thead><tr><th>Parcel</th><th>Destination</th><th>Scan status</th><th>Delivery area</th><th>Next action</th></tr></thead><tbody>
@forelse($shipments as $shipment)
@php($detected=$detectedAreas[$shipment->id]??null) @php($geo=$geoDetails[$shipment->id]??[]) @php($address=collect((array)$shipment->delivery_address)->filter()->implode(', ')) @php($parcelCode=$shipment->parcel_code?:$shipment->shipment_number)
<tr><td><strong>{{ $parcelCode }}</strong><small>{{ $shipment->store?->name ?: 'Store unavailable' }}</small><span class="status-pill">{{ $shipment->status === 'sorted' ? 'Sorted' : 'At Sorting Center' }}</span></td><td><span class="sorting-address">{{ data_get($geo,'municipality',data_get($shipment->delivery_address,'municipality',data_get($shipment->delivery_address,'city','Municipality unavailable'))) }} / {{ data_get($geo,'barangay',data_get($shipment->delivery_address,'barangay','Barangay unavailable')) }}</span><small>{{ data_get($geo,'verified') === true ? 'PSGC verified · '.data_get($geo,'psgc') : 'Destination not PSGC-verified' }}</small></td><td><span class="sorting-scan {{ $shipment->parcel_scanned_at ? 'is-confirmed' : '' }}">{{ $shipment->parcel_scanned_at ? 'Code confirmed' : 'Awaiting parcel scan' }}</span><small>{{ $shipment->parcel_scanned_at?->format('M d, g:i A') ?: 'Confirm code at Incoming Parcels' }}</small></td><td>@if($shipment->status === 'sorted')<strong>{{ $shipment->deliveryArea?->name ?: 'Area recorded' }}</strong>@else<span>{{ $detected?->name ?: 'Select an area / zone' }}</span>@endif</td><td>@if($shipment->status === 'sorted')<span class="sorting-complete">Ready for delivery workflow</span>@elseif($shipment->parcel_scanned_at)<form method="POST" action="{{ route('logistics.sorting.update',$shipment) }}" class="sorting-action-form">@csrf<select class="input" name="delivery_area_id" required aria-label="Delivery area for {{ $parcelCode }}"><option value="">Choose area</option>@foreach($activeAreas as $area)<option value="{{ $area->id }}" @selected($detected?->id === $area->id)>{{ $area->name }}</option>@endforeach</select><button class="btn-primary" type="submit">Confirm sorting</button></form>@else<span class="sorting-pending">Pending parcel confirmation</span>@endif</td></tr>
@empty<tr><td colspan="5" class="sorting-empty">No parcels match the current sorting filters.</td></tr>@endforelse
</tbody></table></div><div class="sorting-mobile-list">
@forelse($shipments as $shipment)
@php($detected=$detectedAreas[$shipment->id]??null) @php($geo=$geoDetails[$shipment->id]??[]) @php($address=collect((array)$shipment->delivery_address)->filter()->implode(', ')) @php($parcelCode=$shipment->parcel_code?:$shipment->shipment_number)
<article class="sorting-mobile-card"><div class="sorting-mobile-card-head"><div><strong>{{ $parcelCode }}</strong><small>{{ $shipment->store?->name ?: 'Store unavailable' }}</small></div><span class="status-pill">{{ $shipment->status === 'sorted' ? 'Sorted' : 'At Sorting Center' }}</span></div><dl><div><dt>Destination</dt><dd>{{ data_get($geo,'municipality',data_get($shipment->delivery_address,'municipality',data_get($shipment->delivery_address,'city','Municipality unavailable'))) }} / {{ data_get($geo,'barangay',data_get($shipment->delivery_address,'barangay','Barangay unavailable')) }}</dd></div><div><dt>PSGC verification</dt><dd>{{ data_get($geo,'verified') === true ? 'Verified' : 'Not verified / catalog unavailable' }}</dd></div><div><dt>Detected area / zone</dt><dd>{{ $detected?->name ?: 'No matching coverage' }}</dd></div><div><dt>Scan status</dt><dd class="{{ $shipment->parcel_scanned_at ? 'sorting-scan is-confirmed' : 'sorting-pending' }}">{{ $shipment->parcel_scanned_at ? 'Code confirmed' : 'Awaiting parcel scan' }}</dd></div><div><dt>Delivery area</dt><dd>{{ $shipment->deliveryArea?->name ?: 'Not sorted' }}</dd></div></dl>@if($shipment->status === 'sorted')<p class="sorting-complete">Ready for delivery workflow</p>@elseif($shipment->parcel_scanned_at)<form method="POST" action="{{ route('logistics.sorting.update',$shipment) }}" class="sorting-action-form">@csrf<select class="input" name="delivery_area_id" required><option value="">Choose area</option>@foreach($activeAreas as $area)<option value="{{ $area->id }}" @selected($detected?->id === $area->id)>{{ $area->name }}</option>@endforeach</select><button class="btn-primary" type="submit">Confirm sorting</button></form>@else<p class="sorting-pending">Confirm the parcel code before sorting.</p>@endif</article>
@empty<p class="sorting-empty">No parcels match the current sorting filters.</p>@endforelse
</div>@if($shipments->hasPages())<div class="sorting-pagination">{{ $shipments->links() }}</div>@endif</div></div>
@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-sorting-filter-form]');
    const hub = document.querySelector('[data-sorting-hub]');
    const area = document.querySelector('[data-sorting-area]');
    if (!form || !hub) return;
    if (area) { area.hidden = true; area.disabled = true; area.setAttribute('aria-hidden', 'true'); }
    const initialBarangays = @json($initialSortingBarangays);
    const insertBefore = form.querySelector('input[name="q"]');
    const barangay = document.createElement('select');
    barangay.id = 'sorting-barangay'; barangay.className = 'input'; barangay.name = 'barangay_code'; barangay.setAttribute('aria-label', 'Barangay');
    if (insertBefore) form.insertBefore(barangay, insertBefore);
    const selectedBarangay = @json($data['barangay_code'] ?? '');
    const renderBarangays = (rows, selected = '') => {
        const visibleRows = rows || [];
        const municipalities = new Set(visibleRows.map((row) => String(row.municipality_code || row.municipality || '')).filter(Boolean));
        barangay.disabled = !hub.value;
        barangay.innerHTML = '<option value="">' + (visibleRows.length ? 'All official barangays' : 'No configured barangays') + '</option>';
        visibleRows.forEach((row) => {
            const suffix = row.coverage_status === 'unconfigured' ? ' - coverage not configured' : '';
            const municipality = municipalities.size > 1 && row.municipality ? `  -  ${row.municipality}` : '';
            barangay.add(new Option(`${row.name}${municipality}${suffix}`, row.code, false, String(row.code) === String(selected)));
        });
    };
    const setBarangayState = (message, disabled = true) => { barangay.disabled = disabled; barangay.innerHTML = `<option value="">${message}</option>`; };
    const loadBarangays = async () => {
        if (!hub.value) { setBarangayState('Select a Sorting Center first'); return; }
        setBarangayState('Loading official barangays...');
        const url = `{{ route('logistics.api.sorting-barangays') }}?hub_id=${encodeURIComponent(hub.value)}`;
        const response = await fetch(url, {headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Unable to load official barangays.');
        const payload = await response.json();
        renderBarangays(payload.data || []);
    };
    renderBarangays(initialBarangays, selectedBarangay);
    hub.addEventListener('change', async () => { try { await loadBarangays(); form.submit(); } catch (error) { setBarangayState(error.message); } });
    document.querySelectorAll('.sorting-action-form').forEach((actionForm) => {
        if (!actionForm.querySelector('input[name="hub_id"]')) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'hub_id'; input.value = hub.value;
            actionForm.appendChild(input);
        }
    });
})();
</script>
@endpush
@endsection
