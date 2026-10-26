@extends('layouts.auth')
@section('title','Register')
@section('body-class','registration-page')
@section('auth-card')
<main class="registration-choice-main" aria-labelledby="registration-title">
    <section class="registration-choice-panel">
        <header class="registration-choice-intro">
            <h1 id="registration-title">Join SHOPPICK</h1>
            <p>Choose how you'd like to get started.</p>
        </header>

        <div class="registration-choice-grid" role="list" aria-label="Registration options">
            <a href="{{ route('register.buyer') }}" class="registration-choice-card registration-choice-card--buyer" role="listitem">
                <span class="registration-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false"><path d="M9 17h30l-2 23H11L9 17Z"/><path d="M16 19v-4a8 8 0 0 1 16 0v4"/><path d="M18 25h12"/></svg>
                </span>
                <span class="registration-choice-title">Shop as a Buyer</span>
                <span class="registration-choice-description">Create an account to shop, track your orders, and discover products on SHOPPICK.</span>
                <span class="registration-choice-action">Register as Buyer <span aria-hidden="true">&rarr;</span></span>
            </a>

            <div class="registration-choice-card registration-choice-card--rider" role="listitem">
                <span class="registration-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false"><circle cx="14" cy="34" r="5"/><circle cx="35" cy="34" r="5"/><path d="M14 34 21 20h8l6 14M21 20l-4-6h8l4 6M29 20h6l4 6h-7M22 27h10"/></svg>
                </span>
                <span class="registration-choice-title">Become a Rider</span>
                <span class="registration-choice-description">Apply to deliver orders with an approved SHOPPICK logistics company.</span>
                <a href="{{ route('rider.register') }}" class="registration-choice-action no-underline">Apply as Rider <span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('rider.login') }}" class="mt-3 inline-flex items-center justify-center gap-1 text-sm font-semibold text-brand-700 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Already a Rider? Sign in <span aria-hidden="true">&rarr;</span></a>
            </div>

            <a href="{{ route('register.logistics') }}" class="registration-choice-card registration-choice-card--logistics" role="listitem">
                <span class="registration-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48" focusable="false"><path d="M7 22 24 12l17 10v18H7V22Z"/><path d="M18 40V27h12v13M13 24h5M30 24h5M13 30h2M33 30h2"/></svg>
                </span>
                <span class="sr-only">Logistics Company</span>
                <span class="registration-choice-title">Join as Logistics</span>
                <span class="registration-choice-description">Register your logistics company to manage deliveries and Rider operations.</span>
                <span class="registration-choice-action">Apply as Logistics <span aria-hidden="true">&rarr;</span></span>
            </a>
        </div>

        <p class="registration-choice-seller-note">Want to become a Seller? Start with a Buyer account, then apply to open your shop.</p>
        <div class="registration-choice-login"><span>Already have an account?</span> <a href="{{ route('login') }}">Log in</a></div>
    </section>
</main>
@endsection
