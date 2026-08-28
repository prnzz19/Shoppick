<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Voucher;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::latest()->paginate(12);
        return view('admin.promotions.index', compact('vouchers'));
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $voucher = Voucher::create(array_merge($data, ['used_count' => 0]));

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
        return view('admin.promotions.edit', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code,' . $voucher->id],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $voucher->update($data);
        AdminActivityLog::record('promotion.updated', 'voucher', $voucher->id, ['code' => $voucher->code]);

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher updated.');
    }

    public function destroy(Voucher $voucher)
    {
        AdminActivityLog::record('promotion.deleted', 'voucher', $voucher->id, ['code' => $voucher->code]);
        $voucher->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Voucher deleted.');
    }

    public function toggleStatus(Voucher $voucher)
    {
        $newStatus = $voucher->status === 'active' ? 'inactive' : 'active';
        $voucher->update(['status' => $newStatus]);

        return back()->with('success', 'Voucher status updated.');
    }
}
