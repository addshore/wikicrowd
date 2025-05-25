<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Depicts Grid</title>
    <link href="/css/app.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 min-h-screen">
    <x-top-right-navbar/>
    <script>
        // Pass the API token from PHP to JavaScript
        @if (isset($apiToken) && $apiToken)
            window.apiToken = "{{ $apiToken }}";
        @else
            window.apiToken = null;
        @endif
    </script>
    <div id="custom-depicts-grid-vue-root"></div>
    <script src="/js/app.js"></script>
</body>
</html>
