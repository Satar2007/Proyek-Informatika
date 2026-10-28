<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>JIMNY COFFEE</title>

    <script src="{{ asset('js/jimny-theme-v11.js') }}?v=11.1"></script>
    <script src="{{ asset('js/jimny-theme-motion-v12.js') }}?v=12.0" defer></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        input,
        textarea,
        select {
            color: var(--jimny-field-ink, #000000) !important;
            caret-color: var(--jimny-field-ink, #000000) !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: #6b7280 !important;
            opacity: 1 !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill,
        textarea:-webkit-autofill:hover,
        textarea:-webkit-autofill:focus,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--jimny-field-ink, #000000) !important;
            caret-color: var(--jimny-field-ink, #000000) !important;
            box-shadow: 0 0 0px 1000px var(--jimny-field-bg, #ffffff) inset !important;
            -webkit-box-shadow: 0 0 0px 1000px var(--jimny-field-bg, #ffffff) inset !important;
            transition: background-color 9999s ease-in-out 0s;
        }

        input[type="date"],
        input[type="time"],
        input[type="number"],
        input[type="email"],
        input[type="password"],
        input[type="text"],
        textarea,
        select {
            color-scheme: var(--jimny-scheme, light) !important;
        }

        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: var(--jimny-picker-filter, invert(0)) !important;
            opacity: 1 !important;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/pos-theme.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/ember-theme.css') }}?v=4">
    <link rel="stylesheet" href="{{ asset('css/page-transition.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('css/guest-theme.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('css/jimny-theme-v11.css') }}?v=11.2">
    <link rel="stylesheet" href="{{ asset('css/jimny-theme-motion-v12.css') }}?v=12.0">
    <script src="{{ asset('js/pos-motion.js') }}?v=4" defer></script>
    <script src="{{ asset('js/page-motion.js') }}?v=2" defer></script>

</head>

<body data-jimny-v12-role="guest" class="ember-theme guest-page jimny-v11 m-0 min-h-screen bg-slate-950 p-0 antialiased">
    <x-page-transition />
    <div class="guest-shell">{{ $slot }}</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')
</body>
</html>