<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="description" content="Fast, effortless personal and household money tracking.">
    <meta name="theme-color" content="#FDFDFC">
    <meta name="color-scheme" content="light">

    {{-- iOS standalone app. black-translucent renders under the status bar,
         which is what activates env(safe-area-inset-top) for the mobile
         context bar; the bottom dock handles its own inset. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

    <title>{{ config('app.name') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @inertiaHead
</head>

{{-- bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] --}}

<body class="min-h-screen">
    @inertia()
</body>

</html>
