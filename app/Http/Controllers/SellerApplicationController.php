<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellerApplicationRequest;
use App\Models\Category;
use App\Models\SellerApplication;
use App\Services\SellerRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerApplicationController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user()->hasApprovedSellerAccess()) return redirect()->route('seller.dashboard');
        $application = $request->user()->sellerApplications()->latest()->first();
        $categories = Category::active()->where('name', '!=', 'Logistics Demo')->orderBy('name')->get();

        return view('seller.apply', compact('application', 'categories'));
    }

    public function store(SellerApplicationRequest $request, SellerRegistrationService $registration)
    {
        if ($request->user()->sellerApplications()->whereIn('status', SellerApplication::REVIEWABLE)->exists()) {
            return redirect()->route('seller.apply')->with('success', 'Your application is already under review.');
        }
        $data = $request->validated();
        $paths = [];
        $disks = [];
        $documents = [
            'valid_id' => 'valid_id_path',
            'business_permit' => 'business_permit_path',
            'dti_certificate' => 'dti_certificate_path',
            'sec_certificate' => 'sec_certificate_path',
            'bir_2303' => 'bir_2303_path',
        ];

        try {
            foreach ($documents as $field => $target) {
                if ($request->hasFile($field)) {
                    $paths[$target] = $request->file($field)->store('registration-documents', 'local');
                    $disks[$target] = 'local';
                    if (! $paths[$target]) throw new \RuntimeException('Unable to store a Seller document.');
                }
            }
            foreach (['logo', 'banner'] as $field) {
                if ($request->hasFile($field)) {
                    $paths[$field] = $request->file($field)->store('stores', 'public');
                    $disks[$field] = 'public';
                }
            }
            $registration->submit($request->user(), array_merge($data, $paths));
        } catch (\Throwable $error) {
            foreach ($paths as $key => $path) {
                if (is_string($path)) Storage::disk($disks[$key] ?? 'local')->delete($path);
            }
            report($error);

            return back()->withInput($request->except(['valid_id', 'business_permit', 'dti_certificate', 'sec_certificate', 'bir_2303', 'logo', 'banner']))
                ->withErrors(['application' => 'We could not submit your Seller application. Please check the details and try again.']);
        }

        return redirect()->route('seller.apply')->with('success', 'Your Seller application has been submitted for review. Your Buyer account remains available.');
    }
}
