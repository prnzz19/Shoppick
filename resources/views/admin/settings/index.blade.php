@extends('layouts.admin')
@section('title', 'System Settings')
@section('content')
<header class="mb-6"><h1 class="text-2xl font-bold text-navy-900">System Settings</h1><p class="mt-2 text-slate-500">Manage SHOPPICK marketplace preferences and system configuration.</p></header>
<div class="grid min-w-0 gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
<nav aria-label="Settings sections" class="grid grid-cols-2 content-start gap-2 sm:grid-cols-4 lg:grid-cols-1">
@foreach($groups as $key => $section)
<a href="{{ route('admin.settings.index', ['group'=>$key]) }}" @if($key === $group) aria-current="page" @endif class="rounded-xl px-4 py-3 text-sm font-semibold {{ $key === $group ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-brand-50' }}">{{ $section['label'] }}</a>
@endforeach
</nav>
<section class="card min-w-0 p-5 sm:p-7">
<h2 class="text-xl font-bold text-navy-900">{{ $groups[$group]['label'] }}</h2><p class="mt-2 mb-6 text-sm leading-6 text-slate-500">{{ $groups[$group]['description'] }}</p>
@if($group === 'maintenance')
<dl class="grid gap-5 sm:grid-cols-3">
@foreach(['Application Environment'=>app()->environment(), 'Laravel Version'=>app()->version(), 'PHP Version'=>PHP_VERSION] as $label=>$value)
<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $value }}</dd></div>
@endforeach
</dl>
<form method="POST" action="{{ route('admin.settings.cache') }}" class="mt-8 border-t border-slate-100 pt-6">
@csrf
<p class="mb-4 text-sm text-slate-500">Clear cached application data, configuration, routes and compiled views. Uploaded files and marketplace records are preserved.</p>
<button class="btn-primary" type="submit">Clear Application Cache</button>
</form>
@else
<form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" id="settings-form">
@csrf @method('PUT')
<div class="grid gap-6 sm:grid-cols-2">
@foreach($fields as $name=>$field)
@php($value = old($name, $values[$name]))
<div class="{{ in_array($field['type'], ['boolean','textarea']) ? 'sm:col-span-2' : '' }} min-w-0">
@if($field['type'] === 'boolean')
<label for="setting-{{ $name }}" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
<span class="font-medium text-navy-900">{{ $field['label'] }}@if($field['locked'] ?? false)<span class="ml-2 text-xs text-slate-500">Required</span>@endif</span>
<input type="hidden" name="{{ $name }}" value="{{ ($field['locked'] ?? false) ? 1 : 0 }}">
<input type="checkbox" role="switch" id="setting-{{ $name }}" name="{{ $name }}" value="1" @checked($value) @disabled($field['locked'] ?? false) class="h-5 w-5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
</label>
@else
<label for="setting-{{ $name }}" class="label">{{ $field['label'] }}</label>
@if($field['type'] === 'select')
<select class="input" id="setting-{{ $name }}" name="{{ $name }}">@foreach($field['options'] as $key=>$label)<option value="{{ $key }}" @selected((string)$value === (string)$key)>{{ $label }}</option>@endforeach</select>
@elseif($field['type'] === 'textarea')
<textarea class="input" rows="3" id="setting-{{ $name }}" name="{{ $name }}">{{ $value }}</textarea>
@elseif($field['type'] === 'file')
<div class="mb-3 flex min-h-24 items-center rounded-xl border border-slate-100 bg-slate-50 p-4">
@if($values[$name])
<img src="{{ Storage::disk('public')->url($values[$name]) }}" alt="Current {{ strtolower($field['label']) }}" class="max-h-24 max-w-full object-contain">
@else
<x-shoppick.logo class="h-16 w-16" /><span class="ml-3 text-sm text-slate-500">Default SHOPPICK branding</span>
@endif
</div>
<input class="input" type="file" accept="image/png,image/jpeg,image/webp" id="setting-{{ $name }}" name="{{ $name }}">
@if($values[$name])<label class="mt-3 flex gap-2 text-sm"><input type="checkbox" name="remove_{{ $name }}" value="1" @checked(old('remove_'.$name))>Restore default</label>@endif
@else
<input class="input {{ $field['type'] === 'color' ? 'h-12' : '' }}" type="{{ $field['type'] }}" id="setting-{{ $name }}" name="{{ $name }}" value="{{ $value }}" @if($field['type'] === 'number') min="0" step="1" @endif>
@endif
@endif
@if($field['note'])<p class="mt-2 text-sm leading-5 text-slate-500">{{ $field['note'] }}</p>@endif
@error($name)<p class="mt-2 text-sm font-medium text-rose-600" role="alert">{{ $message }}</p>@enderror
</div>
@endforeach
</div>
<div class="mt-8 flex justify-end border-t border-slate-100 pt-5"><button class="btn-primary" type="submit">Save Changes</button></div>
</form>
@endif
</section>
</div>
@endsection
@push('scripts')
<script>
(() => {
 const form = document.getElementById('settings-form');
 if (!form) return;
 let dirty = false;
 form.addEventListener('input', () => dirty = true);
 form.addEventListener('change', () => dirty = true);
 form.addEventListener('submit', () => dirty = false);
 window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
</script>
@endpush
