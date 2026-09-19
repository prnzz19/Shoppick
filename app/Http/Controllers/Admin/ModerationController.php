<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ModerateProductImage;
use App\Models\AdminActivityLog;
use App\Models\ModerationScan;
use App\Models\Store;
use App\Models\Violation;
use App\Services\NotificationService;
use App\Services\ProductModerationStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModerationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $status = (string) $request->input('status');
        $statusValues = $status === 'pending_scan' ? ['pending_scan', 'under_review'] : [$status];
        $shops = Store::query()->marketplaceActive()->with(['user', 'sellerProfile'])
            ->whereHas('products.images.moderationScans')
            ->when($status !== '', fn ($query) => $query->whereHas('products.images.moderationScans', fn ($scan) => $scan->whereIn('status', $statusValues)))
            ->when($request->filled('shop'), fn ($query) => $query->whereKey($request->integer('shop')))
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search, $status, $statusValues) {
                $match->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($seller) => $seller->where(fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                    ->orWhereHas('products', fn ($product) => $product->where('name', 'like', "%{$search}%")
                        ->whereHas('images.moderationScans', fn ($scan) => $scan->when($status !== '', fn ($query) => $query->whereIn('status', $statusValues))));
            }))->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        $storeIds = $shops->getCollection()->pluck('id');
        $scans = ModerationScan::with(['product', 'image', 'seller', 'reviewer', 'store.user', 'store.sellerProfile'])
            ->whereIn('store_id', $storeIds)->whereHas('product')->whereHas('image')
            ->when($status !== '', fn ($query) => $query->whereIn('status', $statusValues))
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->whereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('seller', fn ($seller) => $seller->where(fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
            }))->latest()->get();

        $groupedScans = $scans->groupBy(fn ($scan) => (string) $scan->store_id);
        $summaries = $storeIds->isEmpty() ? collect() : ModerationScan::whereIn('store_id', $storeIds)->whereHas('product')->whereHas('image')
            ->selectRaw("store_id, COUNT(DISTINCT product_id) as product_count, COUNT(DISTINCT CASE WHEN status IN ('pending_scan','under_review') THEN product_id END) as pending_count, COUNT(DISTINCT CASE WHEN status = 'flagged' THEN product_id END) as flagged_count, COUNT(DISTINCT CASE WHEN status = 'approved' THEN product_id END) as approved_count, COUNT(DISTINCT CASE WHEN status = 'rejected' THEN product_id END) as rejected_count, COUNT(DISTINCT CASE WHEN status = 'scan_failed' THEN product_id END) as scan_failed_count")
            ->groupBy('store_id')->get()->keyBy('store_id');
        $shopOptions = Store::marketplaceActive()->with('user:id,name,email')->whereHas('products.images.moderationScans')->orderBy('name')->orderBy('id')->get(['id', 'user_id', 'name']);

        return view('admin.moderation.index', compact('shops', 'groupedScans', 'summaries', 'shopOptions'));
    }

    public function show(ModerationScan $scan)
    {
        $scan->load(['product.images', 'image', 'seller.violations', 'store.violations', 'reviewer']);

        return view('admin.moderation.show', compact('scan'));
    }

    public function review(Request $request, ModerationScan $scan, ProductModerationStateService $state)
    {
        if ($request->filled('reason') && ! $request->filled('notes')) {
            $request->merge(['notes' => $request->input('reason')]);
        }
        $data = $request->validate([
            'decision' => ['required', 'in:approved,false_positive,rejected'],
            'notes' => ['nullable', 'string', 'max:1500', 'required_if:decision,rejected'],
            'reason' => ['nullable', 'string', 'max:1500'],
        ]);
        $notes = trim((string) ($data['reason'] ?? $data['notes'] ?? ''));

        DB::transaction(function () use ($request, $scan, $state, $data, $notes) {
            $approved = in_array($data['decision'], ['approved', 'false_positive'], true);
            $scan->update([
                'status' => $approved ? 'approved' : 'rejected',
                'decision' => $data['decision'], 'review_type' => 'manual',
                'moderation_result' => $approved ? 'safe' : 'policy_violation_confirmed',
                'review_notes' => $notes ?: null, 'rejection_reason' => $approved ? null : $notes,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'failure_message' => null,
            ]);
            if (! $approved) {
                $scan->product->update(['suspension_reason' => $notes]);
                Violation::create([
                    'seller_id' => $scan->seller_id, 'store_id' => $scan->store_id, 'product_id' => $scan->product_id,
                    'moderation_scan_id' => $scan->id, 'recorded_by' => $request->user()->id,
                    'category' => $scan->detected_category ?? 'other', 'severity' => $scan->risk_level,
                    'description' => $notes, 'action_taken' => 'product_image_rejected',
                ]);
            }
            $state->refresh($scan->product->fresh());
            if ($scan->seller_id) {
                NotificationService::send(
                    $scan->seller_id,
                    $approved ? 'Product image approved' : 'Your product image was rejected',
                    $approved ? 'An Admin approved your product image.' : $notes,
                    'moderation', route('seller.products.edit', $scan->product_id)
                );
            }
            AdminActivityLog::record('moderation.'.$data['decision'], 'moderation_scan', $scan->id, ['notes' => $notes]);
        });

        return redirect()->route('admin.moderation.index')->with('success', 'Moderation decision saved.');
    }

    public function retry(ModerationScan $scan)
    {
        abort_unless($scan->status === 'scan_failed', 422);
        $scan->update([
            'status' => 'pending_scan', 'moderation_result' => null, 'decision' => null, 'review_type' => null,
            'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null, 'rejection_reason' => null, 'failure_message' => null,
        ]);
        $scan->product->update(['moderation_status' => 'pending_scan', 'is_active' => false]);
        config('services.image_moderation.queued')
            ? ModerateProductImage::dispatch($scan->id)
            : ModerateProductImage::dispatchSync($scan->id);

        return back()->with('success', 'Image moderation scan retried.');
    }
}
