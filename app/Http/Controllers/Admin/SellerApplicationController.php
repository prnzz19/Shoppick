<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\SellerShopApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SellerApplicationController extends Controller
{
    public function __construct(protected SellerShopApprovalService $approvals) {}
    public function index(Request $request)
    {
        $archived = $request->input('tab') === 'archived';
        $archiveCounts = ['normal' => SellerApplication::whereNull('archived_at')->count(), 'archived' => SellerApplication::whereNotNull('archived_at')->count()];
        $applications = SellerApplication::with(['user','category','reviewer'])
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn($q) => $q->where(fn($q) => $q->where('store_name','like','%'.$request->q.'%')->orWhereHas('user',fn($u) => $u->where('name','like','%'.$request->q.'%')->orWhere('email','like','%'.$request->q.'%'))))
            ->latest()->paginate(15)->withQueryString();
        return view('admin.sellers.index', compact('applications','archiveCounts','archived'));
    }

    public function show(SellerApplication $application)
    {
        $application->load(['user','category','reviewer']);
        $history = \App\Models\AdminActivityLog::where('target_type', SellerApplication::class)->where('target_id',$application->id)->with('user')->latest()->get();
        return view('admin.sellers.application', compact('application','history'));
    }

    public function reviewBuyer(Request $request, User $user)
    {
        abort(410, 'Buyer registration no longer requires approval. Use user status controls for account restrictions.');
    }

    public function buyerDocument(User $user)
    {
        abort_unless($user->registration_type==='buyer' && $user->valid_id_path,404);
        return Storage::disk('local')->download($user->valid_id_path);
    }

    public function sellerDocument(SellerApplication $application, string $document)
    {
        abort_unless(in_array($document,['valid-id','business-permit'],true),404);
        $path=$document==='valid-id'?$application->valid_id_path:$application->business_permit_path;
        abort_unless($path,404);
        return Storage::disk('local')->download($path);
    }

    public function review(Request $request, SellerApplication $application)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,needs_resubmission'], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        $this->approvals->review($application,$request->user(),$data['status'],$data['review_notes']??null);
        return redirect()->route('admin.sellers.applications.show', $application)->with('success', 'Seller application decision saved.');
    }

}
