@extends('layouts.auth')
@section('title','Register')
@section('body-class','registration-page')
@section('auth-card')
<main class="registration-main" aria-labelledby="registration-title">
    <section class="registration-panel">
        <div class="registration-intro">
            <p class="registration-kicker">SHOPPICK</p>
            <h1 id="registration-title">Create an account</h1>
            <p>Choose the account that matches how you want to use SHOPPICK.</p>
        </div>
        <div class="registration-options">
            <a href="{{ route('register.buyer') }}" class="registration-card registration-card--buyer">
                <div class="registration-art" aria-hidden="true"><svg viewBox="0 0 320 180" role="presentation"><path d="M37 119h221" class="registration-art-line"/><path d="M63 50h31l15 61h111l17-48H106" class="registration-art-teal"/><path d="M116 73h98l-9 26h-82z" class="registration-art-mint"/><path d="M142 69V45c0-16 26-21 35-6l11 30" class="registration-art-orange"/><path d="M173 42c4-9 15-10 22-2l12 15" class="registration-art-orange"/><circle cx="132" cy="117" r="11" class="registration-art-navy"/><circle cx="216" cy="117" r="11" class="registration-art-navy"/><circle cx="132" cy="117" r="4" class="registration-art-teal-fill"/><circle cx="216" cy="117" r="4" class="registration-art-teal-fill"/><path d="M52 70h-14M48 82H28M57 94H40" class="registration-art-orange-stroke"/><circle cx="260" cy="65" r="29" class="registration-art-soft"/><circle cx="239" cy="99" r="19" class="registration-art-soft"/></svg></div>
                <div class="registration-card-copy"><span class="registration-card-label">Buyer</span><h2>Shop and place orders</h2><p>Shop and place orders on SHOPPICK.</p></div><span class="registration-cta registration-cta--teal">Continue as Buyer <span aria-hidden="true">&rarr;</span></span>
            </a>
            <a href="{{ route('rider.register') }}" class="registration-card registration-card--rider">
                <div class="registration-art" aria-hidden="true"><svg viewBox="0 0 320 180" role="presentation"><path d="M42 128h232" class="registration-art-line"/><path d="M68 112h142l21-26h35l18 26H68Z" class="registration-art-orange"/><circle cx="104" cy="119" r="20" class="registration-art-navy"/><circle cx="237" cy="119" r="20" class="registration-art-navy"/><circle cx="104" cy="119" r="8" class="registration-art-orange-fill"/><circle cx="237" cy="119" r="8" class="registration-art-orange-fill"/><path d="M114 110l38-32 38 32M147 78h-26l-11-28h38l22 28" class="registration-art-teal"/><path d="M163 47c3-15 20-23 34-15l13 17-15 20h-27z" class="registration-art-orange"/><circle cx="184" cy="30" r="15" class="registration-art-navy"/><path d="M184 22c10 0 16 5 18 14" class="registration-art-orange-stroke"/><path d="M201 49l28 17-14 16-30-13" class="registration-art-teal"/><path d="M66 72H30M59 84H21M69 96H40" class="registration-art-orange-stroke"/></svg></div>
                <div class="registration-card-copy"><span class="registration-card-label">Rider</span><h2>Deliver with SHOPPICK</h2><p>Apply to deliver for an approved logistics company.</p></div><span class="registration-cta registration-cta--orange">Apply as Rider <span aria-hidden="true">&rarr;</span></span>
            </a>
            <a href="{{ route('register.logistics') }}" class="registration-card registration-card--logistics">
                <div class="registration-art" aria-hidden="true"><svg viewBox="0 0 320 180" role="presentation"><path d="M33 128h252" class="registration-art-line"/><path d="M64 119V74l96-43 96 43v45" class="registration-art-teal"/><path d="M70 74h180l-90-39z" class="registration-art-mint"/><path d="M126 119V82h68v37" class="registration-art-navy"/><path d="M137 91h46v28h-46z" class="registration-art-sky"/><path d="M80 101h30v28H80zM213 101h30v28h-30z" class="registration-art-orange"/><path d="M50 123h38v-19H50zM228 123h39v-22h-39z" class="registration-art-orange"/><path d="M90 50h35v15H90zM195 50h35v15h-35z" class="registration-art-sky"/><circle cx="50" cy="55" r="20" class="registration-art-soft"/><circle cx="269" cy="54" r="20" class="registration-art-soft"/></svg></div>
                <div class="registration-card-copy"><span class="registration-card-label">Logistics Company</span><h2>Join the delivery network</h2><p>Apply to become a logistics provider on SHOPPICK.</p></div><span class="registration-cta registration-cta--teal">Apply as Logistics <span aria-hidden="true">&rarr;</span></span>
            </a>
        </div>
        <div class="registration-login"><span></span><p>Already have an account? <a href="{{ route('login') }}">Login</a></p><span></span></div>
            <p class="mt-4 text-center text-sm text-slate-500">Want to sell on SHOPPICK? <a class="font-semibold text-brand-700" href="{{ route('register.seller') }}">Apply as Seller</a></p>
</section>
</main>

@endsection
