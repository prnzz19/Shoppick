<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use Illuminate\Http\Request;

class SellerApplicationController extends Controller
{
    public function create(Request $request)
    {
        $application = $request->user()->sellerApplications()->latest()->first();
        return view('seller.apply', compact('application'));
    }

    public function store(Request $request)
    {
        abort_if($request->user()->isSeller(), 422, 'You already have seller access.');
        abort_if($request->user()->sellerApplications()->where('status', 'pending')->exists(), 422, 'You already have a pending application.');
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'], 'store_description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'string', 'max:30'], 'address' => ['required', 'string', 'max:1000'],
            'business_information' => ['nullable', 'string', 'max:2000'], 'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);
        foreach (['logo', 'banner'] as $file) if ($request->hasFile($file)) $data[$file] = $request->file($file)->store('stores', 'public');
        $request->user()->sellerApplications()->create($data);
        return back()->with('success', 'Your seller application has been submitted for review.');
    }
}
