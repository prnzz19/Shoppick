@extends('layouts.account')

@section('title', 'My Addresses')

@section('account-content')
<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl font-bold text-navy-800">My Addresses</h1>
    <button type="button" onclick="openAddressModal()" class="btn-primary btn-sm">+ Add Address</button>
</div>

@if($addresses->isEmpty())
    <div class="card flex flex-col items-center justify-center p-12 text-center text-slate-400">
        <p class="text-sm">No addresses yet. Add one to start shopping.</p>
    </div>
@else
<div class="grid gap-4 sm:grid-cols-2">
    @foreach($addresses as $addr)
        <div class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="flex items-center gap-2 text-sm font-semibold text-navy-800">
                        {{ $addr->full_name }}
                        @if($addr->label)<span class="badge bg-brand-100 text-brand-600">{{ $addr->label }}</span>@endif
                        @if($addr->is_default)<span class="badge bg-leaf-100 text-leaf-500">Default</span>@endif
                    </p>
                    <p class="text-xs text-slate-500">{{ $addr->phone }}</p>
                </div>
                <div class="flex gap-1">
                    <button type="button" onclick="openAddressModal({{ json_encode($addr) }})" class="p-1.5 text-slate-400 hover:text-brand-600" title="Edit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                </div>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ $addr->address_line }}, {{ $addr->barangay }}, {{ $addr->city }}, {{ $addr->province }} {{ $addr->postal_code }}</p>
            <div class="mt-3 flex gap-2">
                @if(!$addr->is_default)
                    <form method="POST" action="{{ route('account.addresses.default', $addr->id) }}">
                        @csrf
                        <button type="submit" class="btn-outline btn-sm">Set Default</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('account.addresses.destroy', $addr->id) }}" onsubmit="return confirm('Delete this address?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Modal --}}
<div id="address-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="modal-title" class="text-lg font-bold text-navy-800">Add Address</h3>
            <button type="button" onclick="closeAddressModal()" class="text-slate-400 hover:text-navy-800"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="address-form" method="POST" action="{{ route('account.addresses.store') }}">
            <input type="hidden" name="_method" id="address-method" value="POST">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label">Full Name</label><input type="text" name="full_name" required class="input"></div>
                <div><label class="label">Phone</label><input type="text" name="phone" required class="input"></div>
                <div><label class="label">Label (e.g. Home, Office)</label><input type="text" name="label" class="input"></div>
                <div><label class="label">Province</label><input type="text" name="province" required class="input"></div>
                <div><label class="label">City / Municipality</label><input type="text" name="city" required class="input"></div>
                <div><label class="label">Barangay</label><input type="text" name="barangay" required class="input"></div>
                <div><label class="label">Postal Code</label><input type="text" name="postal_code" required class="input"></div>
                <div class="sm:col-span-2"><label class="label">Complete Address</label><textarea name="address_line" rows="2" required class="input"></textarea></div>
                <label class="flex items-center gap-2 text-sm text-navy-700 sm:col-span-2"><input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-500"> Set as default</label>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save Address</button>
                <button type="button" onclick="closeAddressModal()" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openAddressModal(addr) {
        const modal = document.getElementById('address-modal');
        const form = document.getElementById('address-form');
        document.getElementById('modal-title').textContent = addr ? 'Edit Address' : 'Add Address';
        document.getElementById('address-method').value = addr ? 'PUT' : 'POST';
        form.action = addr ? '/account/addresses/' + addr.id : '/account/addresses';
        form.full_name.value = addr ? addr.full_name : '';
        form.phone.value = addr ? addr.phone : '';
        form.label.value = addr ? (addr.label || '') : '';
        form.province.value = addr ? addr.province : '';
        form.city.value = addr ? addr.city : '';
        form.barangay.value = addr ? addr.barangay : '';
        form.postal_code.value = addr ? addr.postal_code : '';
        form.address_line.value = addr ? addr.address_line : '';
        if (form.is_default) form.is_default.checked = addr ? !!addr.is_default : false;
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeAddressModal() {
        const modal = document.getElementById('address-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }
</script>
@endpush
