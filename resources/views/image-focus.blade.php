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

            <div id="question-container" class="max-w-6xl mx-auto sm:px-6 lg:px-8" data-group-name="{{ $qu->group->name ?? '' }}" data-current-question-id="{{ $qu->id ?? '' }}" data-next-question-id="{{ $next->id ?? '' }}">
                {{-- Question content will be dynamically inserted here by JavaScript --}}
                @if ($qu)
                    <div id="question-content">
                        @if ( array_key_exists('old_depicts_id',$qu->properties) )
                        <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                            <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">This image currently depicts
                                <a id="current-old-depicts-link" href="https://www.wikidata.org/wiki/{{$qu->properties['old_depicts_id']}}" target="_blank">
                                <span id="current-old-depicts-id">{{ $qu->properties['old_depicts_id'] }}</span> <u id="current-old-depicts-name">{{ $qu->properties['old_depicts_name'] }}</u>
                                </a>
                            </div>
                        </div>
                        <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                            <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">Does this image actually clearly depict
                                "<span id="current-depicts-name">{{ $qu->properties['depicts_name'] }}</span>"
                                (<a id="current-depicts-link" href="https://www.wikidata.org/wiki/{{$qu->properties['depicts_id']}}" target="_blank"><span id="current-depicts-id">{{ $qu->properties['depicts_id'] }}</span></a>)?
                            </div>
                        </div>
                        @else
                        <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                            <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">Does this image clearly depict
                                "<span id="current-depicts-name">{{ $qu->properties['depicts_name'] }}</span>"
                                (<a id="current-depicts-link" href="https://www.wikidata.org/wiki/{{$qu->properties['depicts_id']}}" target="_blank"><span id="current-depicts-id">{{ $qu->properties['depicts_id'] }}</span></a>)?
                            </div>
                        </div>
                        @endif
                        <div class="py-2 flex justify-center pt-8 sm:justify-start sm:pt-0">
                            {{-- Pass initial data to the component --}}
                            <x-yes-no-maybe-buttons :quId="$qu->id" :nextId="$next->id ?? null" />
                        </div>

                        <div class="flex flex-row justify-center items-start" style="max-height:800px;max-width:800px;">
                            <a id="current-image-commons-link" href="https://commons.wikimedia.org/wiki/Special:EntityData/{{ $qu->properties['mediainfo_id'] }}" target="_blank">
                                <img id="current-image" class="object-contain align-top" style="object-position:top" src="{{ $qu->properties['img_url'] }}"></img>
                            </a>
                        </div>
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
    </body>
</html>
