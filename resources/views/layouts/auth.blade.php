<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') · SHOPPICK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2">
            <x-shoppick.logo class="h-12 w-12" />
            <span class="text-3xl font-extrabold tracking-tight"><span class="text-brand-500">SHOP</span><span class="text-accent-500">PICK</span></span>
        </a>
        @yield('auth-card')
        <p class="mt-6 text-sm text-slate-500"><a href="{{ route('home') }}" class="hover:text-brand-600">← Back to store</a></p>
    </div>
</body>
</html>
