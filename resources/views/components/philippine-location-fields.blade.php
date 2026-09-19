@props([
    'prefix' => '',
    'region' => '',
    'regionCode' => '',
    'province' => '',
    'provinceCode' => '',
    'city' => '',
    'cityCode' => '',
    'barangay' => '',
    'barangayCode' => '',
    'required' => true,
])
@php($field = fn (string $name) => $prefix.$name)
<div
    {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2']) }}
    data-ph-location
    data-endpoint="{{ url('/api/philippine-locations') }}"
    data-initial-region="{{ $region }}"
    data-initial-region-code="{{ $regionCode }}"
    data-initial-province="{{ $province }}"
    data-initial-province-code="{{ $provinceCode }}"
    data-initial-city="{{ $city }}"
    data-initial-city-code="{{ $cityCode }}"
    data-initial-barangay="{{ $barangay }}"
    data-initial-barangay-code="{{ $barangayCode }}"
>
    <input type="hidden" name="{{ $field('region') }}" value="{{ $region }}" data-location-name="region">
    <input type="hidden" name="{{ $field('province') }}" value="{{ $province }}" data-location-name="province">
    <input type="hidden" name="{{ $field('city') }}" value="{{ $city }}" data-location-name="city">
    <input type="hidden" name="{{ $field('barangay') }}" value="{{ $barangay }}" data-location-name="barangay">

    <div>
        <label class="label">Region @if($required)*@endif</label>
        <select class="input" name="{{ $field('region_code') }}" data-location-select="region" @required($required)>
            <option value="">Loading regions...</option>
        </select>
        @if($errors->has($field('region')))<p class="mt-1 text-xs text-rose-600">{{ $errors->first($field('region')) }}</p>@endif
    </div>
    <div data-location-province-wrap>
        <label class="label">Province @if($required)*@endif</label>
        <select class="input" name="{{ $field('province_code') }}" data-location-select="province" disabled>
            <option value="">Select region first</option>
        </select>
        <p class="mt-1 hidden text-xs text-slate-500" data-location-no-province>This region has no province level.</p>
        @if($errors->has($field('province')))<p class="mt-1 text-xs text-rose-600">{{ $errors->first($field('province')) }}</p>@endif
    </div>
    <div>
        <label class="label">City / Municipality @if($required)*@endif</label>
        <select class="input" name="{{ $field('city_code') }}" data-location-select="city" disabled @required($required)>
            <option value="">Select province first</option>
        </select>
        @if($errors->has($field('city')))<p class="mt-1 text-xs text-rose-600">{{ $errors->first($field('city')) }}</p>@endif
    </div>
    <div>
        <label class="label">Barangay @if($required)*@endif</label>
        <select class="input" name="{{ $field('barangay_code') }}" data-location-select="barangay" disabled @required($required)>
            <option value="">Select city / municipality first</option>
        </select>
        @if($errors->has($field('barangay')))<p class="mt-1 text-xs text-rose-600">{{ $errors->first($field('barangay')) }}</p>@endif
    </div>
    <p class="hidden text-xs text-rose-600 sm:col-span-2" data-location-error role="alert"></p>
</div>
