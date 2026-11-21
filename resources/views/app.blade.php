<!DOCTYPE html>
{{-- F-20 (Slice 3): Die App rendert immer hell; keine Hell/Dunkel-Umschaltung, keine Klasse "dark". --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline style: heller Seitenhintergrund vor dem Laden des CSS --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Logo-Vorbereitung (docs/modernization/LOGO.md): Farbe der Browserleiste = Waldgrün.
             Manifest und Vorschaubild erst, wenn `npm run brand:icons` die Dateien erzeugt hat. --}}
        <meta name="theme-color" content="#143D2D">
        @if (file_exists(public_path('icons/icon-192.png')))
            <link rel="manifest" href="/site.webmanifest">
        @endif
        @if (file_exists(public_path('og-image.png')))
            <meta property="og:site_name" content="PlantMel">
            <meta property="og:image" content="{{ url('/og-image.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="PlantMel – Pflanzenbetonte Rezepte, gesammelt an einem Ort.">
            <meta name="twitter:card" content="summary_large_image">
        @endif

        {{-- F-58 (Slice 4): Schrift selbst gehostet, @font-face in resources/css/app.css --}}

        @routes

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
