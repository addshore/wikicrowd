<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WikiCrowd (Depicts)</title>
    <link href="/css/app.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- Add CSRF token for AJAX --}}
</head>
<body class="antialiased bg-gray-100 dark:bg-gray-900 min-h-screen">
    <x-top-right-navbar/>
    <script>
        // Pass the API token from PHP to JavaScript
        @if (isset($apiToken) && $apiToken)
            window.apiToken = "{{ $apiToken }}";
        @else
            window.apiToken = null;
        @endif
    </script>
    <div id="depicts-groups-vue-root"></div>
    <script src="/js/app.js"></script>
</body>
</html>
