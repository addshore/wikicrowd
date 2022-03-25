<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WikiCrowd</title>
        <link href="/css/app.css" rel="stylesheet">
        <link rel="prefetch" href="{{ $qu->properties['img_url'] }}" />
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            <x-top-right-navbar/>

            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                {{-- TODO make this generic... --}}
                @if ( array_key_exists('old_depicts_id',$qu->properties) )
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">This image currently depicts
                        <a href="https://www.wikidata.org/wiki/{{$qu->properties['old_depicts_id']}}" target="_blank">
                        {{ $qu->properties['old_depicts_id'] }} <u>{{ $qu->properties['old_depicts_name'] }}</u>
                        </a>
                    </div>
                </div>
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">Does this image actually clearly depict
                        "{{ $qu->properties['depicts_name'] }}"
                        (<a href="https://www.wikidata.org/wiki/{{$qu->properties['depicts_id']}}" target="_blank">{{ $qu->properties['depicts_id'] }}</a>)?
                    </div>
                </div>
                @else
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">Does this image clearly depict
                        "{{ $qu->properties['depicts_name'] }}"
                        (<a href="https://www.wikidata.org/wiki/{{$qu->properties['depicts_id']}}" target="_blank">{{ $qu->properties['depicts_id'] }}</a>)?
                    </div>
                </div>
                @endif
                <div class="py-2 flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <x-yes-no-maybe-buttons quId="{{ $qu->id }}"/>
                </div>

                <div class="flex flex-row justify-center items-start" style="height:800px;width:800px;">
                    <img class="object-contain align-top" style="object-position:top" src="{{ $qu->properties['img_url'] }}"></img>
                </div>
            </div>

        </div>
    </body>
</html>
