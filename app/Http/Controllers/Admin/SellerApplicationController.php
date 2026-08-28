<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = SellerApplication::with(['user', 'reviewer'])->when($request->status, fn($q, $s) => $q->where('status', $s))->latest()->paginate(20);
        return view('admin.sellers.index', compact('applications'));
    }

    public function review(Request $request, SellerApplication $application)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        abort_if($application->status !== 'pending', 422, 'This application has already been reviewed.');
        DB::transaction(function () use ($application, $data, $request) {
            $application->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($data['status'] === 'approved') {
                $application->user->assignRole('seller');
                $profile = SellerProfile::updateOrCreate(['user_id' => $application->user_id], [
                    'phone' => $application->phone, 'address' => $application->address,
                    'business_information' => $application->business_information, 'status' => 'approved', 'approved_at' => now(),
                ]);
                Store::updateOrCreate(['user_id' => $application->user_id], [
                    'seller_profile_id' => $profile->id, 'name' => $application->store_name,
                    'slug' => $this->uniqueSlug($application->store_name), 'description' => $application->store_description,
                    'logo' => $application->logo, 'banner' => $application->banner, 'location' => $application->address, 'status' => 'active',
                ]);
            }
            NotificationService::send($application->user_id, 'Seller application '.$data['status'],
                'Your SHOPPICK seller application was '.$data['status'].'.', 'seller_application', route('seller.apply'));
            AdminActivityLog::record('seller_application.'.$data['status'], 'seller_application', $application->id);
        });
        return back()->with('success', 'Seller application updated.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name); $slug = $base; $i = 2;
        while (Store::withTrashed()->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }
}
