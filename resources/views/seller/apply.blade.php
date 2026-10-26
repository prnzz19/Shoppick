@extends('layouts.storefront')
@section('title','Seller Application')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-10"><div class="card p-6 sm:p-8">
@if($application)
    @if(in_array($application->status,\App\Models\SellerApplication::REVIEWABLE,true))<h1 class="text-2xl font-black text-navy-900">Pending Admin review</h1><p class="mt-2 text-slate-500">Your Seller and Shop information is waiting for review.</p>
    @elseif($application->status==='needs_resubmission')<h1 class="text-2xl font-bold text-orange-700">Seller Application Needs Attention</h1><p class="mt-2 text-slate-600">{{ $application->review_notes }}</p>
    @elseif($application->status==='rejected')<h1 class="text-2xl font-black text-rose-700">Your seller application was not approved</h1><p class="mt-2 text-slate-500">Reason: {{ $application->review_notes ?: 'Please review your documents and resubmit.' }}</p>
    @elseif($application->status==='approved' && auth()->user()->hasApprovedSellerAccess())<h1 class="text-2xl font-black text-leaf-600">Your SHOPPICK store is approved!</h1><a href="{{ route('seller.dashboard') }}" class="btn-primary mt-5">Open Seller Center</a>@else<h1 class="text-2xl font-bold">Seller access is currently unavailable</h1><p>Your Buyer account remains available. Check your shop status with the administrator.</p>@endif
    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><b>{{ $application->store_name }}</b><p>Status: {{ str($application->status)->headline() }} Ã‚Â· Submitted {{ $application->created_at->format('M d, Y') }}</p></div>
@else<h1 class="text-2xl font-black text-navy-900">Become a SHOPPICK Seller</h1><p class="mt-2 text-slate-500">Apply using your existing Buyer account. Your Buyer account remains active during review.</p>@endif
@if(!auth()->user()->isSeller() && (!$application || in_array($application->status,['rejected','needs_resubmission'],true)))
<form method="POST" action="{{ route('seller.apply.store') }}" enctype="multipart/form-data" class="mt-6 space-y-7" data-seller-application>
@csrf
<section class="rounded-xl bg-slate-50 p-4"><h2 class="font-bold text-navy-900">Your Buyer account</h2><p class="mt-1 text-sm text-slate-600">You are applying with your existing verified Buyer account. No second login or password is created.</p><dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Name</dt><dd class="font-semibold text-navy-900">{{ auth()->user()->name }}</dd></div><div><dt class="text-slate-500">Email</dt><dd class="font-semibold text-navy-900">{{ auth()->user()->email }}</dd></div></dl></section>
<section><h2 class="font-bold text-navy-900">Business information</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
<div><label class="label" for="seller-store-name">Shop name *</label><input id="seller-store-name" class="input" name="store_name" value="{{ old('store_name',$application?->store_name) }}" required maxlength="30">@error('store_name')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div><label class="label" for="seller-category">Shop category *</label><select id="seller-category" class="input" name="category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id',$application?->category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select>@error('category_id')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div><label class="label" for="seller-type">Seller type *</label><select id="seller-type" class="input" name="seller_type" required data-seller-type><option value="">Choose one</option>@foreach(['Individual','Sole Proprietorship','Corporation'] as $type)<option value="{{ $type }}" @selected(old('seller_type',$application?->seller_type)===$type)>{{ $type }}</option>@endforeach</select>@error('seller_type')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div><label class="label" for="seller-tin">TIN *</label><input id="seller-tin" class="input" name="tin" value="{{ old('tin',$application?->tin) }}" placeholder="000-000-000-000" inputmode="numeric" required>@error('tin')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div class="sm:col-span-2"><label class="label" for="seller-phone">Contact number *</label><input id="seller-phone" class="input" name="phone" value="{{ old('phone',auth()->user()->phone) }}" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required autocomplete="tel">@error('phone')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div class="sm:col-span-2"><label class="label" for="seller-description">Shop description *</label><textarea id="seller-description" class="input" name="store_description" rows="3" maxlength="2000" required>{{ old('store_description',$application?->store_description) }}</textarea>@error('store_description')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div class="sm:col-span-2"><label class="label" for="seller-business-info">Business information (optional)</label><textarea id="seller-business-info" class="input" name="business_information" rows="2" maxlength="2000">{{ old('business_information',$application?->business_information) }}</textarea></div>
</div></section>
<section><h2 class="font-bold text-navy-900">Shop media</h2><p class="mt-1 text-xs text-slate-500"><strong>Shop Logo ({{ app(\App\Services\SystemSettings::class)->get('seller.logo_required') ? 'Required' : 'Optional' }})</strong> — upload a logo for your shop. Maximum {{ app(\App\Services\SystemSettings::class)->get('seller.logo_max_size') }} KB.</p><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="label" for="seller-logo">Shop logo</label><input id="seller-logo" class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp" @required(app(\App\Services\SystemSettings::class)->get('seller.logo_required'))>@error('logo')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div><div><label class="label" for="seller-banner">Shop banner (optional)</label><input id="seller-banner" class="input" type="file" name="banner" accept="image/png,image/jpeg,image/webp">@error('banner')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div></div></section>
<section><h2 class="font-bold text-navy-900">Business address</h2><div class="mt-4"><label class="label" for="seller-address">Street address *</label><input id="seller-address" class="input" name="address_line" value="{{ old('address_line',$application?->address) }}" maxlength="255" required><input type="hidden" name="address" value="{{ old('address',old('address_line',$application?->address)) }}">@error('address_line')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div><x-philippine-location-fields class="mt-4" :region="old('region',$application?->region)" :region-code="old('region_code',$application?->region_code)" :province="old('province',$application?->province)" :province-code="old('province_code',$application?->province_code)" :city="old('city',$application?->city)" :city-code="old('city_code',$application?->city_code)" :barangay="old('barangay',$application?->barangay)" :barangay-code="old('barangay_code',$application?->barangay_code)"/><div class="mt-4 max-w-xs"><label class="label" for="seller-postal">Postal code *</label><input id="seller-postal" class="input" name="postal_code" value="{{ old('postal_code',$application?->postal_code) }}" inputmode="numeric" maxlength="4" required>@error('postal_code')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div></section>
<section><h2 class="font-bold text-navy-900">Private documents</h2><p class="mt-1 text-xs text-slate-500">JPG, PNG, or PDF only; maximum 5 MB (5,242,880 bytes) per file. Files are stored privately for authorized Admin review.</p><div class="mt-4 grid gap-4 sm:grid-cols-2">
<div><label class="label" for="seller-valid-id">Government-issued ID *</label><input id="seller-valid-id" class="input" type="file" name="valid_id" accept="image/jpeg,image/png,application/pdf" required>@error('valid_id')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div><label class="label" for="seller-business-permit">Business permit (optional)</label><input id="seller-business-permit" class="input" type="file" name="business_permit" accept="image/jpeg,image/png,application/pdf">@error('business_permit')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div data-seller-document="dti" class="hidden"><label class="label" for="seller-dti">DTI certificate *</label><input id="seller-dti" class="input" type="file" name="dti_certificate" accept="image/jpeg,image/png,application/pdf" data-required-for="Sole Proprietorship">@error('dti_certificate')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div data-seller-document="sec" class="hidden"><label class="label" for="seller-sec">SEC certificate *</label><input id="seller-sec" class="input" type="file" name="sec_certificate" accept="image/jpeg,image/png,application/pdf" data-required-for="Corporation">@error('sec_certificate')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
<div data-seller-document="bir" class="hidden"><label class="label" for="seller-bir">BIR Form 2303 *</label><input id="seller-bir" class="input" type="file" name="bir_2303" accept="image/jpeg,image/png,application/pdf" data-required-for="Sole Proprietorship,Corporation">@error('bir_2303')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</div>
</div></section>
<section class="border-t border-slate-100 pt-5"><label class="flex items-start gap-3 text-sm leading-6 text-slate-600"><input type="checkbox" name="seller_terms" value="1" @checked(old('seller_terms')) required class="mt-1"> <span>I confirm these details are accurate and accept the Seller terms and Privacy Policy.</span></label>@error('seller_terms')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror</section>
<div class="flex flex-wrap items-center gap-3"><button type="submit" class="btn-primary">{{ $application ? 'Resubmit Application' : 'Submit application' }}</button><a href="{{ route('home') }}" class="btn-outline">Continue shopping as Buyer</a></div>
</form>
@endif
</div></div>
@endsection
@push('scripts')
<script>
(() => {
    const select = document.querySelector('[data-seller-type]');
    if (!select) return;
    const update = () => document.querySelectorAll('[data-seller-document]').forEach((wrapper) => {
        const input = wrapper.querySelector('input[type=file]');
        const requiredFor = (input?.dataset.requiredFor || '').split(',');
        const required = requiredFor.includes(select.value);
        wrapper.classList.toggle('hidden', !required);
        if (input) input.required = required;
    });
    select.addEventListener('change', update);
    update();
})();
</script>
@endpush
