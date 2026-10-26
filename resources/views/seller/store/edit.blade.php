@extends('layouts.seller')

@section('title', 'Store Settings')

@section('content')
@php
    $pickupLatitude = old('pickup_latitude', $store->pickup_latitude);
    $pickupLongitude = old('pickup_longitude', $store->pickup_longitude);
    $pickupConfirmed = old('pickup_location_confirmed', $store->hasConfirmedPickupLocation());
@endphp
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQ3R7h8bZ6x3Q0nQ0D5Q7M6D6sQ7mQ=" crossorigin="">

<div>
    <p class="text-sm font-semibold text-brand-600">Store Profile</p>
    <h1 class="text-2xl font-extrabold text-navy-900">Store Settings</h1>
    <p class="text-sm text-slate-500">Keep your public SHOPPICK store and Seller pickup location accurate.</p>
</div>

<form method="POST" action="{{ route('seller.store.update') }}" enctype="multipart/form-data" class="card mt-6 p-6">
    @csrf
    @method('PUT')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div>
                <label class="label" for="store-name">Store Name</label>
                <input id="store-name" class="input" name="name" value="{{ old('name', $store->name) }}" required>
            </div>
            <div>
                <label class="label" for="store-description">Store Description</label>
                <textarea id="store-description" class="input" rows="5" name="description">{{ old('description', $store->description) }}</textarea>
            </div>
            <div>
                <label class="label" for="store-location">Pickup address</label>
                <input id="store-location" class="input" name="location" value="{{ old('location', $store->location) }}" aria-describedby="pickup-address-help">
                <p id="pickup-address-help" class="mt-1 text-xs text-slate-500">Keep your full Region, Province, City/Municipality, Barangay, Street, and Postal Code details here. The map pin supplements this address.</p>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="pickup-location-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="pickup-location-heading" class="font-bold text-navy-900">Confirm pickup location on map</h2>
                        <p class="mt-1 text-sm text-slate-500">Place the pin at the exact Seller pickup point. Existing stores remain unverified until a Seller confirms a pin.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500">Permission-based</span>
                </div>
                <div id="seller-pickup-map" class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-200" style="height: 18rem" data-tile-url="{{ config('maps.tile_url') }}" data-attribution="{{ config('maps.attribution') }}" data-latitude="{{ $pickupLatitude }}" data-longitude="{{ $pickupLongitude }}">
                    <div class="flex h-full items-center justify-center p-6 text-center text-sm text-slate-500">Loading map…</div>
                </div>
                <p id="seller-pickup-map-status" class="mt-2 text-xs text-slate-500" role="status">Click the map or drag the pin to adjust the location.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="pickup-latitude">Latitude</label>
                        <input id="pickup-latitude" class="input" name="pickup_latitude" value="{{ $pickupLatitude }}" inputmode="decimal" step="0.0000001" min="-90" max="90" placeholder="14.5995000">
                    </div>
                    <div>
                        <label class="label" for="pickup-longitude">Longitude</label>
                        <input id="pickup-longitude" class="input" name="pickup_longitude" value="{{ $pickupLongitude }}" inputmode="decimal" step="0.0000001" min="-180" max="180" placeholder="120.9842000">
                    </div>
                </div>
                <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                    <input class="mt-1" type="checkbox" name="pickup_location_confirmed" value="1" @checked($pickupConfirmed)>
                    <span>I confirm that this pin is my Seller pickup location and may be used to find a nearby Pickup Rider.</span>
                </label>
                @error('pickup_latitude')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('pickup_location_confirmed')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                <button type="button" id="clear-pickup-pin" class="btn-outline mt-3">Clear pickup pin</button>
                <p class="mt-3 text-xs text-slate-500">Map tiles use the configured Leaflet/OpenStreetMap service. Set <code>SHOPPICK_MAP_TILE_URL</code> and <code>SHOPPICK_MAP_ATTRIBUTION</code> for an approved production tile provider if required.</p>
            </section>
        </div>

        <div class="space-y-4">
            <div>
                <label class="label" for="store-logo">Store Logo</label>
                @if($store->logo)<img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="mb-3 h-24 w-24 rounded-2xl object-cover">@endif
                <input id="store-logo" class="input" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div>
                <label class="label" for="store-banner">Store Banner</label>
                @if($store->banner)<img src="{{ asset('storage/'.$store->banner) }}" alt="{{ $store->name }} banner" class="mb-3 h-28 w-full rounded-xl object-cover">@endif
                <input id="store-banner" class="input" type="file" name="banner" accept="image/jpeg,image/png,image/webp">
            </div>
        </div>
    </div>
    <div class="mt-6 flex flex-wrap gap-3">
        <button class="btn-primary">Save Changes</button>
        <a class="btn-outline" href="{{ route('shops.show', $store->slug) }}">Preview Store</a>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const mapElement = document.getElementById('seller-pickup-map');
    if (!mapElement) return;
    const latitudeInput = document.getElementById('pickup-latitude');
    const longitudeInput = document.getElementById('pickup-longitude');
    const confirmedInput = document.querySelector('input[name="pickup_location_confirmed"]');
    const status = document.getElementById('seller-pickup-map-status');
    const clearButton = document.getElementById('clear-pickup-pin');
    const parse = value => Number.isFinite(Number.parseFloat(value)) ? Number.parseFloat(value) : null;
    const initialLatitude = parse(latitudeInput.value) ?? parse(mapElement.dataset.latitude);
    const initialLongitude = parse(longitudeInput.value) ?? parse(mapElement.dataset.longitude);
    const defaultCenter = [12.8797, 121.7740];
    const center = initialLatitude !== null && initialLongitude !== null ? [initialLatitude, initialLongitude] : defaultCenter;

    if (!window.L) {
        status.textContent = 'The map service is unavailable. You can enter verified coordinates manually and confirm them.';
        mapElement.innerHTML = '<div class="flex h-full items-center justify-center p-6 text-center text-sm text-slate-500">Map unavailable. Coordinate fields remain available.</div>';
        return;
    }

    const map = L.map(mapElement).setView(center, initialLatitude !== null && initialLongitude !== null ? 15 : 6);
    L.tileLayer(mapElement.dataset.tileUrl, { attribution: mapElement.dataset.attribution, maxZoom: 19 }).addTo(map);
    let marker = null;

    const setCoordinates = (latitude, longitude, pan = true) => {
        latitudeInput.value = Number(latitude).toFixed(7);
        longitudeInput.value = Number(longitude).toFixed(7);
        if (!marker) {
            marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
            marker.on('dragend', event => {
                const point = event.target.getLatLng();
                setCoordinates(point.lat, point.lng, false);
            });
        } else {
            marker.setLatLng([latitude, longitude]);
        }
        if (pan) map.panTo([latitude, longitude]);
        status.textContent = 'Pin selected. Confirm the checkbox before saving.';
    };

    if (initialLatitude !== null && initialLongitude !== null) setCoordinates(initialLatitude, initialLongitude, false);
    map.on('click', event => setCoordinates(event.latlng.lat, event.latlng.lng));
    clearButton?.addEventListener('click', () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        confirmedInput.checked = false;
        if (marker) { marker.remove(); marker = null; }
        status.textContent = 'Pickup pin cleared. Save to keep the address without verified coordinates.';
    });
    setTimeout(() => map.invalidateSize(), 150);
})();
</script>
@endpush