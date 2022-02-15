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
                    <div class="text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">WikiCrowd</span></div>
                </div>

                <div class="flex">
                    <div class="text-sm text-gray-500">
                        Quick and easy micro contributions to the wiki space.<br>
                        Using this tool will result in edits being made for your account.
                    </div>
                </div>

                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6">

                        <div class="flex items-center">
                            <div class="ml-4 text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">Wikimedia</span></div>
                        </div>

                        <div class="flex justify-center sm:items-center sm:justify-between">
                            <div class="ml-4 text-sm text-gray-500 sm:ml-0">
                                <ul class="ml-4">
                                    @foreach($rcurls as $key => $url)
                                    <li>
                                        {{$key}}:&nbsp;
                                        <a href="{{$url}}">All edits</a>
                                        @auth
                                         / <a href="{{$url}}&hidebyothers=1">Your edits</a>
                                        @endauth
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6">

                        <div class="flex items-center">
                            <div class="ml-4 text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">Stats</span></div>
                        </div>

                        <div class="flex justify-center sm:items-center sm:justify-between">
                            <div class="ml-4 text-sm text-gray-500 sm:ml-0">
                                <ul class="ml-4">
                                    <li>
                                        Overall:&nbsp;
                                        Questions: {{$stats['questions']}} | Answers: {{$stats['answers']}} | Edits: {{$stats['edits']}} | Users: {{$stats['users']}}
                                    </li>
                                    @auth
                                    <li>
                                        {{ Auth::user()->username }}:&nbsp;
                                        Answers: {{$userstats['answers']}} | Edits: {{$userstats['edits']}}
                                    </li>
                                    @endauth
                                </ul>
                            </div>
                        </div>

                        {!! $chart->renderHtml() !!}
                        {{-- The below is generated as the chart library, but we want to load it from elsewhere for toolforge
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
                        {!! $chart->renderChartJsLibrary() !!} --}}
                        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
                        {!! $chart->renderJs() !!}
                        <div class="ml-4 text-center text-sm text-gray-500 sm:text-right sm:ml-0">
                            Per day metrics
                        </div>
                        <div class="ml-4 text-center text-sm text-gray-500 sm:text-right sm:ml-0">
                            Chart has a 5 min cache
                        </div>
                    </div>
                </div>

                <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
                    <div class="ml-4 text-center text-sm text-gray-500 sm:text-right sm:ml-0">
                        Developed by&nbsp;<a href="https://twitter.com/addshore">Addshore</a> (<a href="https://github.com/addshore/wikicrowd">source</a>)
                    </div>
                </div>
    </body>
</html>
