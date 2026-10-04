<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="description" content="Fast, effortless personal and household money tracking.">
    <meta name="theme-color" content="#FDFDFC">
    <meta name="color-scheme" content="light">
    <meta name="application-name" content="{{ config('app.name') }}">

    {{-- iOS standalone app. black-translucent renders under the status bar,
         which is what activates env(safe-area-inset-top) for the mobile
         context bar; the bottom dock handles its own inset. --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="msapplication-config" content="/icons/browserconfig.xml">
    <meta name="msapplication-TileColor" content="#FDFDFC">

    <title>{{ config('app.name') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="icon" href="/icons/favicon-16x16.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" sizes="180x180">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @inertiaHead
</head>

<body class="min-h-screen">
    @inertia()
</body>

</html>
