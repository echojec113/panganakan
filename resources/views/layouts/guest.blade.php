<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">

<div class="min-h-screen flex flex-col items-center justify-center">

    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold">
            Maternity Management System
        </h1>

        <p class="text-gray-600">
            Depla Family Care Maternity Clinic
        </p>
    </div>

    <div class="w-full sm:max-w-md px-6 py-6 bg-white shadow-md rounded-lg">
        {{ $slot }}
    </div>

</div>

</body>
</html>
