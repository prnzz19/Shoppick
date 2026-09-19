<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Voucher;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:percent,fixed,free_shipping'],
            'status' => ['nullable', 'in:active,inactive,archived,expired'],
        ]);

        $vouchers = Voucher::query()
            ->where('source_type', 'platform')
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(fn ($nested) => $nested
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, function ($query, $status) {
                match ($status) {
                    'archived' => $query->whereNotNull('archived_at'),
                    'expired' => $query->whereNull('archived_at')->whereNotNull('ends_at')->where('ends_at', '<', now()),
                    'active' => $query->whereNull('archived_at')->where('status', 'active')
                        ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>=', now())),
                    'inactive' => $query->whereNull('archived_at')->where('status', 'inactive'),
                };
            })
            ->withCount('usages')
            ->withSum('usages', 'discount_amount')
            ->withSum('usages', 'shipping_discount_amount')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.promotions.index', compact('vouchers'));
    }

    public function show(Voucher $voucher)
    {
        $this->platform($voucher);
        $voucher->load('creator');
        $usages = $voucher->usages()->with(['user', 'order'])->latest('used_at')->paginate(20);

        return view('admin.promotions.show', compact('voucher', 'usages'));
    }

    public function create()
    {
        return redirect()->route('admin.promotions.index', ['create' => 1]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percent,fixed,free_shipping'],
            'value' => ['nullable', 'numeric', 'min:0', 'required_unless:type,free_shipping'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        if($data['type']==='percent'&&(float)$data['value']>100)return back()->withErrors(['value'=>'Percentage discount may not exceed 100%.'])->withInput();
        if($data['type']==='free_shipping')$data['value']=0;
        $voucher = Voucher::create(array_merge($data, ['used_count' => 0,'source_type'=>'platform','platform_scope'=>'all_shops','application_scope'=>'entire_shop','store_id'=>null,'created_by'=>$request->user()->id]));

        AdminActivityLog::record('promotion.created', 'voucher', $voucher->id, ['code' => $voucher->code]);

        // Notify all buyers about the new promotion.
        if ($voucher->status === 'active') {
            $buyers = \App\Models\User::whereHas('roles', fn ($q) => $q->where('slug', 'buyer'))->get();
            foreach ($buyers as $buyer) {
                NotificationService::notifyPromo($buyer, 'New voucher: ' . $voucher->code, $voucher->title, $voucher->code);
            }
        }

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher created.');
    }

    public function edit(Voucher $voucher)
    {
        $this->platform($voucher);
        abort_if($voucher->archived_at, 422, 'Archived vouchers cannot be edited.');

        return redirect()->route('admin.promotions.index', ['q' => $voucher->code, 'edit' => $voucher->id]);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->platform($voucher);
        abort_if($voucher->archived_at, 422, 'Archived vouchers cannot be edited.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code,' . $voucher->id],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percent,fixed,free_shipping'],
            'value' => ['nullable', 'numeric', 'min:0', 'required_unless:type,free_shipping'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        if($data['type']==='percent'&&(float)$data['value']>100)return back()->withErrors(['value'=>'Percentage discount may not exceed 100%.'])->withInput();
        if($data['type']==='free_shipping')$data['value']=0;
        $voucher->update($data+['source_type'=>'platform','platform_scope'=>'all_shops','store_id'=>null]);
        AdminActivityLog::record('promotion.updated', 'voucher', $voucher->id, ['code' => $voucher->code]);

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher updated.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->platform($voucher);
        AdminActivityLog::record('promotion.archived', 'voucher', $voucher->id, ['code' => $voucher->code]);
        $voucher->update(['status'=>'inactive','archived_at'=>now()]);

        return redirect()->route('admin.promotions.index')->with('success', 'Platform Voucher archived.');
    }

    public function toggleStatus(Voucher $voucher)
    {
        $this->platform($voucher);
        abort_if($voucher->archived_at, 422, 'Archived vouchers cannot be activated.');
        $newStatus = $voucher->status === 'active' ? 'inactive' : 'active';
        $voucher->update(['status' => $newStatus]);
        AdminActivityLog::record('promotion.status_updated', 'voucher', $voucher->id, ['status' => $newStatus]);

        return back()->with('success', 'Voucher status updated.');
    }

    private function platform(Voucher $voucher):void
    {
        abort_unless($voucher->source_type==='platform',403);
    }
}
