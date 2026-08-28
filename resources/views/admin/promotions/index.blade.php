@extends('layouts.admin')

@section('title', 'Promotions & Vouchers')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-navy-800">Promotions & Vouchers</h1>
    <button type="button" onclick="openVoucherModal()" class="btn-primary">+ Create Voucher</button>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Code</th>
                    <th class="table-th">Title</th>
                    <th class="table-th">Type</th>
                    <th class="table-th">Value</th>
                    <th class="table-th">Min Spend</th>
                    <th class="table-th">Usage</th>
                    <th class="table-th">Valid Until</th>
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
                        <td class="table-td capitalize text-slate-600">{{ $voucher->type }}</td>
                        <td class="table-td font-semibold">{{ $voucher->type === 'percent' ? $voucher->value.'%' : '₱'.$voucher->value }}
                            @if($voucher->max_discount > 0)<span class="text-xs text-slate-400"> (max ₱{{ $voucher->max_discount }})</span>@endif
                        </td>
                        <td class="table-td">{{ $voucher->min_purchase > 0 ? '₱'.$voucher->min_purchase : '—' }}</td>
                        <td class="table-td">{{ $voucher->used_count }}/{{ $voucher->usage_limit ?? '∞' }}</td>
                        <td class="table-td">{{ $voucher->ends_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="table-td">
                            @if($voucher->ends_at && now()->gt($voucher->ends_at))
                                <x-admin.status-badge status="expired" />
                            @else
                                <x-admin.status-badge :status="$voucher->status" />
                            @endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-1">
                                <button type="button" onclick="openVoucherModal({{ json_encode($voucher->toArray()) }})" class="p-2 text-slate-400 hover:text-brand-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                <form method="POST" action="{{ route('admin.promotions.toggle', $voucher->id) }}">
                                    @csrf
                                    <button type="submit" class="p-2 text-slate-400 hover:text-navy-700" title="{{ $voucher->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                                <a href="{{ route('admin.promotions.destroy', $voucher->id) }}" onclick="event.preventDefault(); if(confirm('Delete this voucher?')) document.getElementById('vf-{{ $voucher->id }}').submit();" class="p-2 text-slate-400 hover:text-rose-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></a>
                                <form id="vf-{{ $voucher->id }}" method="POST" action="{{ route('admin.promotions.destroy', $voucher->id) }}">@csrf @method('DELETE')</form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="table-td py-10 text-center text-slate-400">No vouchers yet.</td></tr>
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
                            <option value="fixed">Fixed (₱)</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Value</label>
                        <input type="number" name="value" id="v-value" required step="0.01" min="0" class="input">
                    </div>
                    <div>
                        <label class="label">Min Purchase (₱)</label>
                        <input type="number" name="min_purchase" id="v-min" value="0" step="0.01" min="0" class="input">
                    </div>
                    <div>
                        <label class="label">Max Discount (₱)</label>
                        <input type="number" name="max_discount" id="v-maxdisc" value="0" step="0.01" min="0" class="input">
                    </div>
                    <div>
                        <label class="label">Usage Limit</label>
                        <input type="number" name="usage_limit" id="v-usage" class="input" placeholder="Unlimited">
                    </div>
                    <div>
                        <label class="label">Per-User Limit</label>
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
        document.getElementById('voucher-form').action = v ? '/admin/promotions/' + v.id : '/admin/promotions';
        document.getElementById('v-code').value = v ? v.code : '';
        document.getElementById('v-title').value = v ? v.title : '';
        document.getElementById('v-desc').value = v ? (v.description || '') : '';
        document.getElementById('v-type').value = v ? v.type : 'percent';
        document.getElementById('v-value').value = v ? v.value : '';
        document.getElementById('v-min').value = v ? (v.min_purchase || 0) : 0;
        document.getElementById('v-maxdisc').value = v ? (v.max_discount || 0) : 0;
        document.getElementById('v-usage').value = v ? (v.usage_limit || '') : '';
        document.getElementById('v-peruser').value = v ? (v.per_user_limit || '') : '';
        document.getElementById('v-starts').value = v && v.starts_at ? v.starts_at.slice(0,10) : '';
        document.getElementById('v-ends').value = v && v.ends_at ? v.ends_at.slice(0,10) : '';
        document.getElementById('v-status').value = v ? v.status : 'active';
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeVoucherModal() {
        const modal = document.getElementById('voucher-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }
</script>
@endpush
