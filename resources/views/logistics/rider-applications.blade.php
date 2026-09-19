@extends('layouts.logistics')
@section('title','Rider Applications')
@section('content')
<div class="log-page-head"><div><h1>Rider Applications</h1><p>Review people applying to become SHOPPICK Riders.</p></div><a class="btn-outline btn-sm" href="{{ route('logistics.riders') }}">Approved Riders</a></div>
<div class="log-kpis">
@foreach(['Pending Review'=>$stats['pending'],'Needs Resubmission'=>$stats['needs_resubmission'],'Approved Today'=>$stats['approved_today'],'Rejected'=>$stats['rejected']] as $label=>$value)
<div class="log-kpi"><b>{{ $value }}</b><p>{{ $label }}</p></div>
@endforeach
</div>
<nav class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Application status filters">
@foreach(['pending'=>'Pending Review','needs_resubmission'=>'Needs Resubmission','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label)
<a href="{{ route('logistics.rider-applications',['status'=>$key]) }}" class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold {{ $status===$key?'bg-brand-600 text-white':'border bg-white text-slate-600' }}">{{ $label }}</a>
@endforeach
</nav>
<form class="log-filters"><input type="hidden" name="status" value="{{ $status }}"><input class="input flex-1" name="q" value="{{ request('q') }}" placeholder="Search applicant name, email, or mobile"><input class="input" type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Applied from"><input class="input" type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Applied to"><button class="btn-primary btn-sm">Apply</button></form>
<div class="mt-4 grid gap-4 xl:grid-cols-2">
@forelse($applications as $profile)
@php
    $address = $profile->user->defaultAddress();
@endphp
<article class="log-card p-5">
<div class="flex items-start justify-between gap-3"><div><h2 class="font-extrabold text-navy-900">{{ $profile->user->name }}</h2><p class="text-xs font-semibold text-brand-700">Rider Applicant</p></div><x-admin.status-badge :status="$profile->user->registration_status"/></div>
<dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-400">Applied</dt><dd>{{ $profile->created_at->format('M d, Y') }}</dd></div><div><dt class="text-slate-400">Location</dt><dd>{{ collect([$address?->city,$address?->province])->filter()->implode(', ')?:'Not recorded' }}</dd></div><div><dt class="text-slate-400">Valid ID</dt><dd>{{ $profile->user->valid_id_path?'Submitted':'Missing' }}</dd></div><div><dt class="text-slate-400">Driver License</dt><dd>{{ $profile->driver_license_front_path&&$profile->driver_license_back_path?'Submitted':'Incomplete' }}</dd></div><div class="col-span-2"><dt class="text-slate-400">License Expiration</dt><dd>{{ $profile->driver_license_expires_at?->format('M d, Y')?:'Not recorded' }}</dd></div></dl>
<a class="btn-outline btn-sm mt-4 inline-flex" href="{{ request()->fullUrlWithQuery(['view'=>$profile->id]) }}">View Application</a>
</article>
@empty
<div class="log-empty xl:col-span-2"><b>No {{ str($status)->headline() }} applications</b><span>Applications matching this status will appear here.</span></div>
@endforelse
</div><div class="mt-5">{{ $applications->links() }}</div>
@if($selected)
@php $user=$selected->user; $address=$user->defaultAddress(); @endphp
<aside class="log-drawer">
<div class="log-drawer-head"><h2>Rider Application</h2><a href="{{ route('logistics.rider-applications',request()->except('view')) }}">×</a></div>
<section class="log-drawer-section"><h3>Personal Information</h3><div class="log-drawer-grid"><div><span>Name</span><b>{{ $user->name }}</b></div><div><span>Sex</span><b>{{ str($user->sex)->headline() }}</b></div><div><span>Birthday</span><b>{{ $user->birthday?->format('M d, Y')?:'—' }}</b></div><div><span>Age</span><b>{{ $user->age?:'—' }}</b></div><div><span>Email</span><b>{{ $user->email }}</b></div><div><span>Mobile</span><b>{{ $user->phone }}</b></div></div></section>
<section class="log-drawer-section"><h3>Address</h3><div class="log-drawer-grid"><div><span>Region</span><b>{{ $address?->region?:'—' }}</b></div><div><span>Province</span><b>{{ $address?->province?:'—' }}</b></div><div><span>City / Municipality</span><b>{{ $address?->city?:'—' }}</b></div><div><span>Barangay</span><b>{{ $address?->barangay?:'—' }}</b></div><div><span>House / Street</span><b>{{ $address?->address_line?:'—' }}</b></div><div><span>Postal Code</span><b>{{ $address?->postal_code?:'—' }}</b></div></div></section>
<section class="log-drawer-section"><h3>Documents</h3><div class="log-drawer-grid"><div><span>License Number</span><b>{{ $selected->driver_license_number }}</b></div><div><span>Classification</span><b>{{ $selected->driver_license_classification }}</b></div><div><span>Expiration</span><b>{{ $selected->driver_license_expires_at?->format('M d, Y') }}</b></div><div><span>License Status</span><b>{{ str($selected->license_display_status)->headline() }}</b></div></div><div class="mt-3 flex flex-wrap gap-2">@if($user->valid_id_path)<a class="btn-outline btn-sm" href="{{ route('logistics.riders.valid-id',$selected) }}">Valid ID</a>@endif @foreach(['front'=>'License Front','back'=>'License Back'] as $side=>$label)@if(data_get($selected,"driver_license_{$side}_path"))<a class="btn-outline btn-sm" href="{{ route('logistics.riders.license.file',[$selected,$side]) }}">{{ $label }}</a>@endif @endforeach</div></section>
<section class="log-drawer-section"><h3>Application Info</h3><p class="text-sm"><b>Applied:</b> {{ $selected->created_at->format('M d, Y g:i A') }}<br><b>Status:</b> {{ str($user->registration_status)->headline() }}</p>@if($user->registration_review_notes)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm"><b>Review note:</b><br>{{ $user->registration_review_notes }}</p>@endif</section>
@if(in_array($user->registration_status,['pending','needs_resubmission'],true))
<section class="log-drawer-section"><h3>Application Actions</h3><div class="mt-3 grid gap-2 sm:grid-cols-3"><form method="POST" action="{{ route('logistics.riders.application.review',$selected) }}" data-confirm-title="Approve this Rider application?" data-confirm-message="The applicant becomes an active Rider with Off Duty availability." data-confirm-action="Approve" data-confirm-type="success">@csrf<input type="hidden" name="decision" value="approved"><button class="btn-primary btn-sm w-full">Approve</button></form><form method="POST" action="{{ route('logistics.riders.application.review',$selected) }}" data-confirm-title="Reject this Rider application?" data-confirm-message="The applicant will not receive Rider Dashboard access." data-confirm-action="Reject" data-confirm-type="danger" data-confirm-reason="true">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn-outline btn-sm w-full text-rose-600">Reject</button></form><form method="POST" action="{{ route('logistics.riders.application.review',$selected) }}" data-confirm-title="Request resubmission?" data-confirm-message="Explain which information or document must be corrected." data-confirm-action="Request" data-confirm-type="warning" data-confirm-reason="true">@csrf<input type="hidden" name="decision" value="needs_resubmission"><button class="btn-outline btn-sm w-full">Resubmit</button></form></div></section>
@endif
</aside>
@endif
@endsection
