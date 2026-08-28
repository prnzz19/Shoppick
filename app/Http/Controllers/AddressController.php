<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

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
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            $this->clearDefault();
        }

        $address = auth()->user()->addresses()->create($data);

        // First address automatically becomes default.
        if (auth()->user()->addresses()->count() === 1) {
            $address->update(['is_default' => true]);
        }

        return back()->with('success', 'Address added.');
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeAddress($address);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            $this->clearDefault();
        }

        $address->update($data);

        return back()->with('success', 'Address updated.');
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
        $this->clearDefault();
        $address->update(['is_default' => true]);

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
