<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SHOPPICK')) · SHOPPICK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen flex flex-col">
    <x-storefront.header />
    <x-storefront.mobile-nav />

    <main class="flex-1 pt-14 pb-20 md:pb-8">
        @if(session('success'))
            <div class="mx-auto max-w-7xl px-4 pt-4"><div class="alert-success" data-flash-success>{{ session('success') }}</div></div>
        @endif
        @if(session('error'))
            <div class="mx-auto max-w-7xl px-4 pt-4"><div class="alert-error" data-flash-error>{{ session('error') }}</div></div>
        @endif
        @if($errors->any() && !isset($showRawErrors))
            <div class="mx-auto max-w-7xl px-4 pt-4">
                <div class="alert-error">
                    <p class="mb-1 font-semibold">Please fix the following:</p>
                    <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <x-storefront.footer />

    <div id="toast-container" class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 space-y-2"></div>

    <script>
        window.APP_ROUTES = {
            autocomplete: "{{ route('search.autocomplete') }}",
        };
        document.addEventListener('DOMContentLoaded', function () {
            const toast = @json(session('cart_toast'));
            if (toast) showToast(toast, 'success');
        });
    </script>
    @stack('scripts')
</body>
</html>
