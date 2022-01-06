<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WikiCrowd</title>
        <link href="/css/app.css" rel="stylesheet">
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            <x-top-right-navbar/>

            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">Is "<strong>{{ $qu->properties['suggestion'] }}</strong>" another English alias for {{ $qu->properties['item'] }} {{ $qu->properties['label'] }}?</div>
                </div>
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    @if ($qu->properties['aliases'])
                    <div class="text-sm leading-7 font-semibold text-gray-900 dark:text-white">Current Aliases:</div>&nbsp;<div class="text-md leading-7 text-gray-900 dark:text-white">{{ implode( ', ', $qu->properties['aliases']) }}</div>
                    @else
                    <div class="text-sm leading-7 font-semibold text-gray-900 dark:text-white">Current Aliases:</div>&nbsp;<div class="text-md leading-7 text-gray-900 dark:text-white"></div>
                    @endif
                </div>
                <div class="py-2 flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <x-yes-no-maybe-buttons quId="{{ $qu->id }}"/>
                </div>

                <div class="max-w-4xl p-4 text-gray-800 bg-white rounded-lg shadow" style="height:400px;width:800px;">
                    @php
                    {{ echo $qu->properties['html_context'];}}
                    @endphp
                </div>

            </div>
    </body>
</html>
