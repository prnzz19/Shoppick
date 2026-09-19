@props([
    'birthday' => '',
    'required' => true,
])
@php
    $birthdayValue = $birthday instanceof \DateTimeInterface ? $birthday->format('Y-m-d') : (string) $birthday;
    $initialAge = null;
    if ($birthdayValue !== '') {
        try {
            $initialAge = \App\Support\BirthdayAge::calculate(\Illuminate\Support\Carbon::parse($birthdayValue));
        } catch (\Throwable) {
            $initialAge = null;
        }
    }
@endphp
<div {{ $attributes->merge(['class' => 'contents']) }} data-birthday-age>
    <div>
        <label class="label">Birthday @if($required)*@endif</label>
        <input
            class="input"
            type="date"
            name="birthday"
            value="{{ $birthdayValue }}"
            max="{{ now()->toDateString() }}"
            data-birthday-input
            @required($required)
        >
        @error('birthday')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        <p class="mt-1 hidden text-xs text-rose-600" data-birthday-error role="alert">Birthday cannot be in the future.</p>
    </div>
    <div>
        <label class="label">Age</label>
        <input
            class="input bg-slate-50 text-navy-700"
            type="text"
            name="age"
            value="{{ $initialAge }}"
            placeholder="Auto-calculated"
            inputmode="numeric"
            data-age-output
            aria-label="Age, automatically calculated from birthday"
            readonly
        >
        <p class="mt-1 text-xs text-slate-500">Automatically calculated from Birthday.</p>
    </div>
</div>
