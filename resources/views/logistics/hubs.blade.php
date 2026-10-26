@extends('layouts.logistics') @section('title','Hubs / Warehouses') @section('content')
<div class="sorting-centers-page">
<div class="log-page-head"><div><h1>Hubs / Warehouses</h1><p>Logistics shipment facilities—not Seller inventory warehouses.</p></div><button class="btn-primary btn-sm" onclick="document.getElementById('add-hub').showModal()">+ Add Hub</button></div>
@php $totalCapacity=$hubs->sum('capacity');$totalOnHand=$hubs->sum('shipments_count'); @endphp<div class="log-kpis"><div class="log-kpi"><b>{{ $hubs->where('status','active')->count() }}</b><p>Active Hubs</p></div><div class="log-kpi"><b>{{ $totalCapacity?round($totalOnHand/$totalCapacity*100,1):0 }}%</b><p>Average Utilization</p></div><div class="log-kpi"><b>{{ $hubs->where('status','inactive')->count() }}</b><p>Hub Alerts</p></div><div class="log-kpi"><b>{{ $totalOnHand }} / {{ $totalCapacity?:'—' }}</b><p>Shipments / Capacity</p></div></div><form class="log-filters"><input class="input flex-1" name="q" value="{{ request('q') }}" placeholder="Search Hub or location"><select class="input" name="status"><option value="">All Statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select><button class="btn-primary btn-sm">Apply</button></form>
<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@forelse($hubs as $h)@php $pct=$h->capacity?min(100,round($h->shipments_count/$h->capacity*100,1)):0; @endphp<a href="{{ request()->fullUrlWithQuery(['view'=>$h->id]) }}" class="log-card p-4"><div class="flex justify-between"><div><b class="text-sm">{{ $h->name }}</b><p class="text-[10px] text-slate-400">{{ $h->code }} · {{ $h->address }}</p></div><x-admin.status-badge :status="$h->status"/></div><div class="mt-4 flex justify-between text-[10px]"><span>Capacity Utilization</span><b>{{ $pct }}%</b></div><div class="log-progress mt-1"><span style="width:{{ $pct }}%"></span></div><div class="mt-4 grid grid-cols-3 text-center text-xs"><div class="rounded-lg bg-blue-50 p-2"><b>{{ $h->shipments_count }}</b><p>Shipments</p></div><div class="rounded-lg bg-green-50 p-2"><b>{{ $h->riders_count }}</b><p>Riders</p></div><div class="rounded-lg bg-purple-50 p-2"><b>{{ $h->vehicles_count }}</b><p>Vehicles</p></div></div></a>@empty<div class="log-card log-empty">No Logistics Hubs configured.</div>@endforelse</div>
<dialog id="add-hub" class="m-auto w-full max-w-lg rounded-xl p-0 backdrop:bg-navy-900/50"><form method="POST" action="{{ route('logistics.hubs.store') }}" class="space-y-3 p-6">@csrf<div class="flex justify-between"><h2 class="font-bold">Add Hub</h2><button type="button" onclick="this.closest('dialog').close()">×</button></div><input class="input" name="code" required placeholder="Hub code"><input class="input" name="name" required placeholder="Hub name"><textarea class="input" name="address" required placeholder="Address"></textarea><input class="input" name="capacity" type="number" min="1" placeholder="Package capacity"><input type="hidden" name="status" value="active"><button class="btn-primary w-full">Save Hub</button></form></dialog>
@if($selected)<aside class="log-drawer"><div class="log-drawer-head"><h2 class="font-extrabold">Hub Details</h2><a href="{{ route('logistics.hubs',request()->except('view')) }}">×</a></div><section class="log-drawer-section"><h3>{{ $selected->name }}</h3><p class="text-xs text-slate-500">{{ $selected->address }}</p><div class="log-drawer-grid mt-4"><div><span>Code</span><b>{{ $selected->code }}</b></div><div><span>Status</span><b>{{ ucfirst($selected->status) }}</b></div><div><span>Capacity</span><b>{{ $selected->capacity??'—' }}</b></div><div><span>Shipments</span><b>{{ $selected->shipments_count }}</b></div></div></section><section class="log-drawer-section"><h3>Current Operations</h3>@forelse($selected->shipments->take(8) as $s)<a class="block border-t py-2 text-xs" href="{{ route('logistics.shipments.show',$s) }}"><b>{{ $s->shipment_number }}</b><span class="float-right">{{ $s->store?->name }}</span></a>@empty<p class="text-xs text-slate-400">No current Hub shipments.</p>@endforelse</section><section class="log-drawer-section"><h3>Resources</h3><p class="text-xs">{{ $selected->riders_count }} Riders · {{ $selected->vehicles_count }} Vehicles</p></section></aside>@endif
@if($selected)
<section class="log-card mt-4 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 class="text-lg font-extrabold">Manage {{ $selected->name }}</h2><p class="text-sm text-slate-500">Edit this Sorting Center or change its availability for new operational work.</p></div>
        <span class="text-sm font-semibold text-slate-600">{{ str($selected->status)->headline() }}</span>
    </div>
    <form method="POST" action="{{ route('logistics.hubs.update', $selected) }}" class="mt-4 space-y-3">
        @csrf @method('PUT')
        <div class="grid gap-3 md:grid-cols-2"><input class="input" name="code" required maxlength="30" value="{{ $selected->code }}" placeholder="Sorting Center code"><input class="input" name="name" required maxlength="100" value="{{ $selected->name }}" placeholder="Sorting Center name"></div>
        <textarea class="input" name="address" required maxlength="500" placeholder="Full address">{{ $selected->address }}</textarea>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><input class="input" name="street" value="{{ $selected->street }}" placeholder="Street"><input class="input" name="barangay" value="{{ $selected->barangay }}" placeholder="Barangay"><input class="input" name="municipality" value="{{ $selected->municipality }}" placeholder="Municipality"><input class="input" name="province" value="{{ $selected->province }}" placeholder="Province"><input class="input" name="postal_code" value="{{ $selected->postal_code }}" placeholder="Postal Code"><input class="input" name="contact_phone" value="{{ $selected->contact_phone }}" placeholder="Contact Phone"><input class="input" name="latitude" value="{{ $selected->latitude }}" inputmode="decimal" placeholder="Latitude"><input class="input" name="longitude" value="{{ $selected->longitude }}" inputmode="decimal" placeholder="Longitude"><input class="input" name="capacity" type="number" min="1" value="{{ $selected->capacity }}" placeholder="Capacity"><input class="input" name="operating_hours" value="{{ $selected->operating_hours }}" placeholder="Operating hours"></div>
        <div class="grid gap-3 md:grid-cols-2"><select class="input" name="status" required>@foreach(['active', 'inactive', 'maintenance'] as $status)<option value="{{ $status }}" @selected($selected->status === $status)>{{ str($status)->headline() }}</option>@endforeach</select><textarea class="input" name="notes" maxlength="1000" placeholder="Internal notes">{{ $selected->notes }}</textarea></div>
        <button class="btn-primary" type="submit">Save Sorting Center Changes</button>
    </form>
    <section class="mt-5 border-t border-slate-200 pt-4" aria-labelledby="service-coverage-heading">
        <h3 id="service-coverage-heading" class="font-bold">Service Coverage</h3>
        <p class="mt-1 text-sm text-slate-500">Choose only the provider-owned Delivery Areas served by this Sorting Center. Geographic reference data never activates coverage automatically.</p>
        <form method="POST" action="{{ route('logistics.hubs.coverage', $selected) }}" class="mt-3 space-y-3">
            @csrf @method('PUT')
            <div class="grid gap-2 md:grid-cols-2">
                @forelse($coverageAreas as $area)
                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm"><input type="checkbox" name="delivery_area_ids[]" value="{{ $area->id }}" @checked($selected->coverageAreas->contains('id', $area->id))><span><strong>{{ $area->name }}</strong><small class="block text-slate-500">{{ $area->province }}{{ $area->municipality ? ', '.$area->municipality : '' }}{{ $area->code ? ' · '.$area->code : '' }}</small></span></label>
                @empty
                    <p class="text-sm text-slate-500">No active Delivery Areas are configured for this provider yet.</p>
                @endforelse
            </div>
            <button class="btn-primary" type="submit">Save Service Coverage</button>
        </form>
    </section>
    <div class="mt-5 border-t border-slate-200 pt-4"><h3 class="font-bold">Center availability</h3><p class="mt-1 text-sm text-slate-500">Deactivation is blocked while active Parcels are assigned to this Center.</p><form method="POST" action="{{ route('logistics.hubs.status', $selected) }}" class="mt-3">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $selected->status === 'active' ? 'inactive' : 'active' }}"><button class="{{ $selected->status === 'active' ? 'btn-outline' : 'btn-primary' }}" type="submit">{{ $selected->status === 'active' ? 'Deactivate Sorting Center' : 'Reactivate Sorting Center' }}</button></form></div>
</section>
@endif
@push('scripts')
<script>
(() => {
    const drawerClose = document.querySelector('.log-drawer-head > a');
    if (drawerClose) {
        drawerClose.setAttribute('aria-label', 'Close hub details');
        drawerClose.setAttribute('title', 'Close hub details');
    }
    const api = '/api/philippine-locations';
    const addPicker = (form) => {
        if (form.querySelector('[data-hub-location-picker]')) return;
        const address = form.querySelector('textarea[name="address"]');
        if (!address) return;
        const wrap = document.createElement('div');
        wrap.dataset.hubLocationPicker = 'true';
        wrap.className = 'mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4';
        wrap.innerHTML = '<p class="col-span-full text-xs font-semibold text-slate-500">Official location (loaded from the PSGC API)</p>' +
            '<select class="input" data-region><option value="">Select region</option></select>' +
            '<select class="input" data-province disabled><option value="">Select province</option></select>' +
            '<select class="input" data-municipality disabled><option value="">Select city / municipality</option></select>' +
            '<select class="input" data-barangay disabled><option value="">Select barangay</option></select>';
        address.after(wrap);
        ['province', 'municipality', 'barangay'].forEach((name) => {
            if (!form.querySelector('[name="' + name + '"]')) { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.appendChild(input); }
        });
        const region = wrap.querySelector('[data-region]'), province = wrap.querySelector('[data-province]'), municipality = wrap.querySelector('[data-municipality]'), barangay = wrap.querySelector('[data-barangay]');
        const fill = (select, rows, placeholder) => { select.innerHTML = '<option value="">' + placeholder + '</option>'; (rows || []).forEach((row) => select.add(new Option(row.name, row.code))); select.disabled = !(rows || []).length; };
        const load = async (url, select, placeholder) => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); const payload = response.ok ? await response.json() : {data: []}; fill(select, payload.data || [], placeholder); };
        load(api + '/regions', region, 'Select region');
        region.addEventListener('change', async () => { fill(province, [], 'Select province'); fill(municipality, [], 'Select city / municipality'); fill(barangay, [], 'Select barangay'); if (region.value) await load(api + '/regions/' + encodeURIComponent(region.value) + '/provinces', province, 'Select province'); });
        province.addEventListener('change', async () => { fill(municipality, [], 'Select city / municipality'); fill(barangay, [], 'Select barangay'); if (province.value) await load(api + '/provinces/' + encodeURIComponent(province.value) + '/cities-municipalities', municipality, 'Select city / municipality'); const option = province.selectedOptions[0]; if (option) form.querySelector('[name="province"]').value = option.textContent || ''; });
        municipality.addEventListener('change', async () => { fill(barangay, [], 'Select barangay'); if (municipality.value) await load(api + '/cities-municipalities/' + encodeURIComponent(municipality.value) + '/barangays', barangay, 'Select barangay'); const option = municipality.selectedOptions[0]; form.querySelector('[name="municipality"]').value = option?.textContent || ''; });
        barangay.addEventListener('change', () => { const option = barangay.selectedOptions[0]; form.querySelector('[name="barangay"]').value = option?.textContent || ''; const selectedMunicipality = municipality.selectedOptions[0]; if (selectedMunicipality) form.querySelector('[name="municipality"]').value = selectedMunicipality.textContent || ''; });
    };
    document.querySelectorAll('form').forEach(addPicker);
})();
</script>
@endpush
</div>
@endsection
