@extends('layouts.admin')

@section('title', 'Promotions & Vouchers')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div><p class="text-sm font-semibold text-brand-600">Promotions</p><h1 class="text-2xl font-bold text-navy-800">Platform Vouchers</h1><p class="text-sm text-slate-500">Admin-created SHOPPICK offers for all current and future Shops.</p></div>
    <button type="button" onclick="openVoucherModal()" class="btn-primary">+ Create Platform Voucher</button>
</div>

@if($errors->any())
<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><p class="font-semibold">Please correct the voucher details:</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-[1fr_180px_180px_auto]">
    <input type="search" name="q" value="{{ request('q') }}" class="input" placeholder="Search voucher name or code">
    <select name="type" class="input">
        <option value="">All types</option>
        <option value="fixed" @selected(request('type') === 'fixed')>Fixed Amount</option>
        <option value="percent" @selected(request('type') === 'percent')>Percentage</option>
        <option value="free_shipping" @selected(request('type') === 'free_shipping')>Free Shipping</option>
    </select>
    <select name="status" class="input">
        <option value="">All statuses</option>
        @foreach(['active'=>'Active','inactive'=>'Inactive','expired'=>'Expired','archived'=>'Archived'] as $value=>$label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="flex gap-2"><button class="btn-primary">Filter</button><a href="{{ route('admin.promotions.index') }}" class="btn-ghost">Reset</a></div>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Code</th>
                    <th class="table-th">Title</th>
                    <th class="table-th">Type</th>
                    <th class="table-th">Scope</th>
                    <th class="table-th">Value / Cap</th>
                    <th class="table-th">Min Spend</th>
                    <th class="table-th">Usage</th>
                    <th class="table-th">Start / End</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($vouchers as $voucher)
                    <tr>
                        <td class="table-td font-mono font-bold uppercase text-navy-800">{{ $voucher->code }}</td>
                        <td class="table-td">
                            <p class="line-clamp-1">{{ $voucher->title }}</p>
                            @if($voucher->description)<p class="line-clamp-1 text-xs text-slate-400">{{ $voucher->description }}</p>@endif
                        </td>
                        <td class="table-td capitalize text-slate-600"><span class="badge bg-brand-50 text-brand-700">Platform Voucher</span><br>{{ str($voucher->type)->headline() }}</td>
                        <td class="table-td">All Shops</td>
                        <td class="table-td font-semibold">{{ $voucher->type === 'free_shipping' ? 'FREE SHIPPING' : ($voucher->type === 'percent' ? $voucher->value.'%' : '₱'.number_format($voucher->value, 2)) }}
                            @if($voucher->max_discount !== null)<p class="text-xs text-slate-400">{{ $voucher->type === 'free_shipping' ? 'Shipping' : 'Discount' }} up to ₱{{ number_format($voucher->max_discount, 2) }}</p>@elseif($voucher->type === 'free_shipping')<p class="text-xs text-slate-400">Unlimited eligible shipping</p>@endif
                        </td>
                        <td class="table-td">{{ $voucher->min_purchase > 0 ? '₱'.$voucher->min_purchase : '—' }}</td>
                        <td class="table-td">{{ $voucher->used_count }}/{{ $voucher->usage_limit ?? '∞' }}@if($voucher->usages_sum_shipping_discount_amount>0)<p class="text-xs text-slate-400">₱{{ number_format($voucher->usages_sum_shipping_discount_amount,2) }} shipping support</p>@endif</td>
                        <td class="table-td"><span class="block">{{ $voucher->starts_at?->format('M d, Y') ?? 'Immediately' }}</span><span class="text-xs text-slate-400">to {{ $voucher->ends_at?->format('M d, Y') ?? 'No expiry' }}</span></td>
                        <td class="table-td">
                            @if($voucher->archived_at)
                                <x-admin.status-badge status="archived" />
                            @elseif($voucher->ends_at && now()->gt($voucher->ends_at))
                                <x-admin.status-badge status="expired" />
                            @else
                                <x-admin.status-badge :status="$voucher->status" />
                            @endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('admin.promotions.show', $voucher) }}" class="p-2 text-slate-400 hover:text-navy-700" title="View usage"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                @if(!$voucher->archived_at)
                                <button type="button" onclick="openVoucherModal({{ json_encode($voucher->toArray()) }})" class="p-2 text-slate-400 hover:text-brand-600" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                <form method="POST" action="{{ route('admin.promotions.toggle', $voucher->id) }}">
                                    @csrf
                                    <button type="submit" class="p-2 text-slate-400 hover:text-navy-700" title="{{ $voucher->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.promotions.destroy', $voucher->id) }}" data-confirm-title="Archive this voucher?" data-confirm-message="It will no longer be available for new orders. Historical usage remains unchanged." data-confirm-action="Archive" data-confirm-type="danger">@csrf @method('DELETE')<button type="submit" class="p-2 text-slate-400 hover:text-rose-600" title="Archive"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="table-td py-10 text-center text-slate-400">No platform vouchers match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $vouchers->links('components.pagination') }}</div>

{{-- Modal --}}
<div id="voucher-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="voucher-modal-title" class="text-lg font-bold text-navy-800">Create Voucher</h3>
            <button type="button" onclick="closeVoucherModal()" class="text-slate-400 hover:text-navy-800"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="voucher-form" method="POST" action="{{ route('admin.promotions.store') }}">
            @csrf
            <input type="hidden" name="_method" id="voucher-method" value="POST">
            <div class="max-h-[70vh] space-y-3 overflow-y-auto pr-1">
                <div>
                    <label class="label">Code</label>
                    <input type="text" name="code" id="v-code" required class="input uppercase" placeholder="e.g. SHOP10">
                </div>
                <div>
                    <label class="label">Title</label>
                    <input type="text" name="title" id="v-title" required class="input" placeholder="e.g. 10% off everything">
                </div>
                <div>
                    <label class="label">Description</label>
                    <input type="text" name="description" id="v-desc" class="input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Type</label>
                        <select name="type" id="v-type" class="input">
                            <option value="percent">Percentage</option>
                            <option value="free_shipping">Free Shipping</option>
                            <option value="fixed">Fixed (₱)</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Value</label>
                        <input type="number" name="value" id="v-value" step="0.01" min="0" class="input" placeholder="Not required for Free Shipping">
                    </div>
                    <div>
                        <label class="label">Min Purchase (₱)</label>
                        <input type="number" name="min_purchase" id="v-min" value="0" step="0.01" min="0" class="input">
                    </div>
                    <div>
                        <label class="label" id="v-maxdisc-label">Maximum Discount (₱)</label>
                        <input type="number" name="max_discount" id="v-maxdisc" step="0.01" min="0" class="input" placeholder="Unlimited">
                    </div>
                    <div>
                        <label class="label">Usage Limit</label>
                        <input type="number" name="usage_limit" id="v-usage" class="input" placeholder="Unlimited">
                    </div>
                    <div>
                        <label class="label">Usage Limit Per Buyer</label>
                        <input type="number" name="per_user_limit" id="v-peruser" class="input" placeholder="Unlimited">
                    </div>
                    <div>
                        <label class="label">Starts (optional)</label>
                        <input type="date" name="starts_at" id="v-starts" class="input">
                    </div>
                    <div>
                        <label class="label">Ends (optional)</label>
                        <input type="date" name="ends_at" id="v-ends" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select name="status" id="v-status" class="input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div><label class="label">Scope</label><input class="input" value="All Shops" disabled><p class="mt-1 text-xs text-slate-400">Automatically includes all current and future active Shops.</p></div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save</button>
                <button type="button" onclick="closeVoucherModal()" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openVoucherModal(v) {
        const modal = document.getElementById('voucher-modal');
        document.getElementById('voucher-modal-title').textContent = v ? 'Edit Voucher' : 'Create Voucher';
        document.getElementById('voucher-method').value = v ? 'PUT' : 'POST';
        document.getElementById('voucher-form').action = v ? @json(url('/admin/promotions')) + '/' + v.id : @json(route('admin.promotions.store'));
        document.getElementById('v-code').value = v ? v.code : '';
        document.getElementById('v-title').value = v ? v.title : '';
        document.getElementById('v-desc').value = v ? (v.description || '') : '';
        document.getElementById('v-type').value = v ? v.type : 'percent';
        document.getElementById('v-value').value = v ? v.value : '';
        document.getElementById('v-min').value = v ? (v.min_purchase || 0) : 0;
        document.getElementById('v-maxdisc').value = v ? (v.max_discount ?? '') : '';
        document.getElementById('v-usage').value = v ? (v.usage_limit || '') : '';
        document.getElementById('v-peruser').value = v ? (v.per_user_limit || '') : '';
        document.getElementById('v-starts').value = v && v.starts_at ? v.starts_at.slice(0,10) : '';
        document.getElementById('v-ends').value = v && v.ends_at ? v.ends_at.slice(0,10) : '';
        document.getElementById('v-status').value = v ? v.status : 'active';
        updateVoucherType();
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeVoucherModal() {
        const modal = document.getElementById('voucher-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }
    function updateVoucherType() {
        const freeShipping = document.getElementById('v-type').value === 'free_shipping';
        const value = document.getElementById('v-value');
        value.disabled = freeShipping;
        value.required = !freeShipping;
        if (freeShipping) value.value = '';
        document.getElementById('v-maxdisc-label').textContent = freeShipping ? 'Maximum Shipping Discount (₱)' : 'Maximum Discount (₱)';
    }
    document.getElementById('v-type').addEventListener('change', updateVoucherType);
    @if(request('create'))
        openVoucherModal();
    @elseif(request('edit'))
        @php($editVoucher = $vouchers->firstWhere('id', (int) request('edit')))
        @if($editVoucher) openVoucherModal(@json($editVoucher->toArray())); @endif
    @endif
</script>
@endpush
