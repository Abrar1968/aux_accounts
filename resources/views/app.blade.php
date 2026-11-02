<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name') }}</title>
    @php($branding = app(\App\Services\System\BrandingService::class))
    <link rel="icon" id="app-favicon" href="{{ $branding->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ $branding->faviconUrl('touch') }}">
    <script>
        // Apply saved theme before paint to avoid a flash.
        try { const t = localStorage.getItem('theme'); if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
