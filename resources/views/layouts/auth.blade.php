<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') &middot; SHOPPICK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 @yield('body-class')">
    <div class="auth-page-shell flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="auth-brand mb-8 flex items-center gap-2">
            <x-shoppick.wordmark class="h-14 w-56" />
        </a>
        @yield('auth-card')
        <p class="mt-6 text-sm text-slate-500"><a href="{{ route('home') }}" class="hover:text-brand-600">&larr; Back to store</a></p>
    </div>
    @stack('scripts')
</body>
</html>
