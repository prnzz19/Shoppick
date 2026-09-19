<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $addresses = auth()->user()->addresses()->latest()->get();
        return view('storefront.account.addresses', compact('addresses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'region_code' => ['nullable', 'string', 'max:20'],
            'province' => ['required_without:region_code', 'nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'city_code' => ['nullable', 'string', 'max:20'],
            'barangay' => ['required', 'string', 'max:100'],
            'barangay_code' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                $this->clearDefault();
            }

            $address = auth()->user()->addresses()->create($data);

            // First address automatically becomes default.
            if (auth()->user()->addresses()->count() === 1) {
                $address->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Address added successfully.');
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeAddress($address);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'region_code' => ['nullable', 'string', 'max:20'],
            'province' => ['required_without:region_code', 'nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'city_code' => ['nullable', 'string', 'max:20'],
            'barangay' => ['required', 'string', 'max:100'],
            'barangay_code' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        DB::transaction(function () use ($address, $data) {
            if ($data['is_default']) {
                $this->clearDefault();
                $address->refresh();
            }

            $address->update($data);
        });

        return back()->with('success', 'Address updated successfully.');
    }

    public function destroy(Address $address)
    {
        $this->authorizeAddress($address);
        $address->delete();

        return back()->with('success', 'Address deleted.');
    }

    public function setDefault(Address $address)
    {
        $this->authorizeAddress($address);
        DB::transaction(function () use ($address) {
            $this->clearDefault();
            $address->refresh()->update(['is_default' => true]);
        });

        return back()->with('success', 'Default address updated.');
    }

    protected function authorizeAddress(Address $address): void
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }
    }

    protected function clearDefault(): void
    {
        auth()->user()->addresses()->update(['is_default' => false]);
    }
}
