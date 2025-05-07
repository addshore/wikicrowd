<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- Add CSRF token for AJAX --}}
        <title>WikiCrowd (Depicts)</title>
        <link href="/css/app.css" rel="stylesheet">
        {{-- Initial prefetch, will be updated by JS --}}
        @if ($qu && $qu->properties['img_url'])
            <link id="prefetch-current-image" rel="prefetch" href="{{ $qu->properties['img_url'] }}" />
        @endif
        @if ($next && $next->properties['img_url'])
            <link id="prefetch-next-image" rel="prefetch" href="{{ $next->properties['img_url'] }}" />
        @endif
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            <x-top-right-navbar/>

            <div id="question-container" class="max-w-full mx-auto sm:px-6 lg:px-16" data-group-name="{{ $qu->group->name ?? '' }}" data-current-question-id="{{ $qu->id ?? '' }}" data-next-question-id="{{ $next->id ?? '' }}">
                {{-- Question content will be dynamically inserted here by JavaScript --}}
                @if ($qu)
                    <div id="image-focus-vue-root">
                        <template v-if="gridMode">
                            <grid-mode @disable-grid="gridMode = false" />
                        </template>
                    </div>
                @else
                    <div id="no-more-questions" class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                        <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">
                            No more questions available in this group. <a href="{{ url('/') }}" class="underline">Go back to groups</a>.
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <script>
            // Pass the API token from PHP to JavaScript
            @if (isset($apiToken) && $apiToken)
                window.apiToken = "{{ $apiToken }}";
            @else
                window.apiToken = null;
            @endif
            // JavaScript will be in the yes-no-maybe-buttons component
            // and interact with the elements here.
        </script>
        <script src="{{ mix('js/app.js') }}"></script>
    </body>
</html>
