@extends('layouts.auth')

@section('title', $role === 'seller' ? 'Seller Login' : ($role === 'logistics' ? 'Logistics Login' : 'Login'))

@section('auth-card')
<div class="w-full max-w-md">
    @if(session('status'))<div class="alert-success mb-4">{{ session('status') }}</div>@endif
    <div class="card w-full p-6 sm:p-8">
        <p class="text-xs font-bold uppercase tracking-wider text-brand-700">{{ $role === 'seller' ? 'Seller Center' : ($role === 'logistics' ? 'SHOPPICK Logistics' : 'SHOPPICK Account') }}</p>
        <h1 class="mt-2 text-2xl font-bold text-navy-800">{{ $role === 'seller' ? 'Seller Login' : ($role === 'logistics' ? 'Logistics Login' : 'Welcome Back!') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $role === 'seller' ? 'Sign in to manage your approved shop.' : ($role === 'logistics' ? 'Sign in to access approved logistics operations.' : 'Log in to continue shopping with SHOPPICK.') }}</p>
        @if(session('authentication_error'))<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ session('authentication_error') }}</div>@endif
        @if($errors->any())<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-4">
            @csrf
            @if($role)<input type="hidden" name="role" value="{{ $role }}">@endif
            <div>
                <label class="label" for="login-email">Email</label>
                <input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between"><label class="label !mb-0" for="login-password">Password</label><a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Forgot password?</a></div>
                <div class="relative"><input id="login-password" type="password" name="password" required class="input pr-20"><button type="button" class="absolute inset-y-0 right-2 px-2 text-xs font-semibold text-slate-500" data-password-toggle="login-password" aria-controls="login-password" aria-label="Show password">Show</button></div>
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-navy-700"><input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-300"> Remember me</label>
            <button type="submit" class="btn-primary w-full">Login</button>
        </form>
        @if(!$role)
        <div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>Or<span class="h-px flex-1 bg-slate-200"></span></div>
        @if(\App\Support\GoogleOAuth::isAvailable())<a href="{{ route('auth.google.redirect') }}" class="btn-outline flex w-full items-center justify-center gap-3"><span class="text-lg font-bold text-brand-600">G</span>Continue with Google</a>@else<button type="button" disabled class="btn-outline flex w-full cursor-not-allowed items-center justify-center gap-3 opacity-55"><span class="text-lg font-bold text-brand-600">G</span>Continue with Google</button><p class="mt-2 text-center text-xs text-slate-400">Google sign-in is currently unavailable.</p>@endif
        @endif
    </div>
    <p class="mt-4 text-center text-sm text-slate-600">{{ $role === 'seller' ? 'Need to apply as a seller?' : ($role === 'logistics' ? 'Need a logistics account?' : "Don't have an account?") }} <a href="{{ $role === 'seller' ? route('register.seller') : route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ $role ? 'Start an application' : 'Register' }}</a></p>
</div>
@push('scripts')<script>document.querySelectorAll('[data-password-toggle]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.dataset.passwordToggle);var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';button.setAttribute('aria-label',visible?'Show password':'Hide password');});});</script>@endpush
@endsection