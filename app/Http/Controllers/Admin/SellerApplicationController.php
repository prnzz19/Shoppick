<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Services\SellerShopApprovalService;
use Illuminate\Http\Request;

class SellerApplicationController extends Controller
{
    public function __construct(protected SellerShopApprovalService $approvals) {}
    public function index(Request $request)
    {
        $applications = SellerApplication::with(['user', 'adminReviewer', 'escalator', 'reviewer'])->when($request->status, fn($q, $s) => $q->where('status', $s))->latest()->paginate(20);
        return view('admin.sellers.index', compact('applications'));
    }

    public function review(Request $request, SellerApplication $application)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $this->approvals->review($application,$request->user(),$data['status'],$data['review_notes']??null);
        return back()->with('success', 'Seller application decision saved.');
    }

    public function escalate(Request $request, SellerApplication $application)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->approvals->escalate($application, $request->user(), $data['reason']);
        return back()->with('success', 'Seller application escalated to Super Admin.');
    }
}
