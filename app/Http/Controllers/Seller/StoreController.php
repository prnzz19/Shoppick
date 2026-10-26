<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    public function edit(Request $request)
    {
        return view('seller.store.edit', ['store' => $request->user()->store]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pickup_location_confirmed' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $coordinatesSubmitted = $request->has('pickup_latitude') || $request->has('pickup_longitude') || $request->has('pickup_location_confirmed');
        if ($coordinatesSubmitted) {
            $hasLatitude = filled($data['pickup_latitude'] ?? null);
            $hasLongitude = filled($data['pickup_longitude'] ?? null);
            if ($hasLatitude xor $hasLongitude) {
                throw ValidationException::withMessages(['pickup_latitude' => 'Choose a complete pickup pin with both latitude and longitude.']);
            }
            if (($hasLatitude || $hasLongitude) && ! $request->boolean('pickup_location_confirmed')) {
                throw ValidationException::withMessages(['pickup_location_confirmed' => 'Confirm that the map pin is your Seller pickup location.']);
            }
            if ($hasLatitude && $hasLongitude) {
                $data['pickup_location_confirmed_at'] = now();
            } else {
                $data['pickup_latitude'] = null;
                $data['pickup_longitude'] = null;
                $data['pickup_location_confirmed_at'] = null;
            }
        } else {
            unset($data['pickup_latitude'], $data['pickup_longitude'], $data['pickup_location_confirmed']);
        }
        unset($data['pickup_location_confirmed']);

        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field)) {
                if ($store->{$field}) Storage::disk('public')->delete($store->{$field});
                $data[$field] = $request->file($field)->store('stores', 'public');
            }
        }
        $store->update($data);
        AdminActivityLog::record('store.updated', 'store', $store->id, ['name' => $store->name, 'pickup_coordinates_confirmed' => $store->hasConfirmedPickupLocation()]);
        return back()->with('success', 'Store profile updated.');
    }
}