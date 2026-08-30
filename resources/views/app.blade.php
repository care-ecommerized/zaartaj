<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->has('locale_direction') ? app('locale_direction') : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="/images/zaartaj-logo.png">
        <link rel="apple-touch-icon" href="/images/zaartaj-logo.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|cormorant-garamond:400,500,600,700" rel="stylesheet" />
        {{-- Arabic faces for the RTL locale: IBM Plex Sans Arabic (body/chrome) and Amiri (display headings). --}}
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600|amiri:400,700" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
