<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use App\Models\Category;
use App\Services\SellerRegistrationService;
use Illuminate\Http\Request;

class SellerApplicationController extends Controller
{
    public function create(Request $request)
    {
        if ($request->user()->hasApprovedSellerAccess()) return redirect()->route('seller.dashboard');
        $application = $request->user()->sellerApplications()->latest()->first();
        $categories=Category::active()->where('name','!=','Logistics Demo')->orderBy('name')->get();
        return view('seller.apply', compact('application','categories'));
    }

    public function store(Request $request, SellerRegistrationService $registration)
    {
        abort_if($request->user()->isSeller(), 422, 'You already have seller access.');
        if ($request->user()->sellerApplications()->whereIn('status', SellerApplication::REVIEWABLE)->exists()) return redirect()->route('seller.apply')->with('success', 'Your application is already under review.');
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'], 'store_description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'string', 'max:30'], 'address' => ['required', 'string', 'max:1000'],
            'category_id'=>['required','exists:categories,id'], 'business_information' => ['nullable', 'string', 'max:2000'],
            'valid_id'=>['required','file','mimes:jpg,jpeg,png,pdf','max:5120'], 'business_permit'=>['required','file','mimes:jpg,jpeg,png,pdf','max:5120'], 'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);
        foreach (['logo', 'banner'] as $file) if ($request->hasFile($file)) $data[$file] = $request->file($file)->store('stores', 'public');
        $data['valid_id_path']=$request->file('valid_id')->store('registration-documents');
        $data['business_permit_path']=$request->file('business_permit')->store('registration-documents');
        $data['address_line']=$data['address'];$data['same_address']=true;
        $registration->submit($request->user(),$data);
        return redirect()->route('seller.apply')->with('success', 'Your seller application has been submitted for review.');
    }
}
