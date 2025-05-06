<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WikiCrowd (Depicts)</title>
        <link href="/css/app.css" rel="stylesheet">
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            <x-top-right-navbar/>

            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">WikiCrowd (Depicts)</span></div>
                </div>

                <div class="flex">
                    <div class="text-sm text-gray-500">
                        Quick and easy micro contributions to the wiki space, showing what images depict.<br>
                        Using this tool will result in edits being made for your account.
                    </div>
                </div>

                @forelse ($groups as $group)
                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="ml-4 text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">{{$group->display_name}}</span></div>
                        </div>
                        <?php if (isset($group->display_description)): ?>
                            <div class="flex items-center">
                                    <div class="ml-4 text-sm"><span class="text-gray-900 dark:text-white">{{$group->display_description}}</span></div>
                            </div>
                        <?php endif; ?>

                        @forelse ($group->subGroups as $subGroup)
                        <div class="ml-12">
                            <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">
                                <a href="{{ url('/questions/' . $subGroup->name) }}" class="text-sm text-gray-700 dark:text-gray-500 underline">{{$subGroup->display_name}}</a>
                                <span>({{$subGroup->unanswered}})</span>
                            </div>
                        </div>
                        @empty
                        @endif
                    </div>
                </div>
                @empty
                <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="ml-4 text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">No groups currently ready for contributing to</span></div>
                        </div>
                    </div>

                </div>
            @endif

            <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
                <div class="ml-4 text-sm text-gray-500 sm:ml-0">
                    <p>Developed by <a href="https://twitter.com/addshore" target="_blank">Addshore</a> (<a href="https://github.com/addshore/wikicrowd" target="_blank">source code</a>)</p>

                    <p>Questions: {{$stats['questions']}} | Answers: {{$stats['answers']}} | Edits: {{$stats['edits']}} | Users: {{$stats['users']}}</p>

                    <p>
                        Wikidata:&nbsp;
                            <a target="_blank" href="https://www.wikidata.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2">All edits</a>
                            @auth
                                / <a target="_blank" href="https://www.wikidata.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2&hidebyothers=1">Your edits</a>
                            @endauth
                    </p>
                    <p>
                        Commons:&nbsp;
                        <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2">All edits</a>
                        @auth
                            / <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2&hidebyothers=1">Your edits</a>
                        @endauth
                    </p>
                    <p>
                        <a href="{{ route('api.docs') }}">API Documentation</a>
                    </p>
                </div>
            </div>
        </div>
        <div id="vue-hello-world" class="mt-8">
            <hello-world></hello-world>
        </div>
        <script src="{{ mix('js/app.js') }}"></script>
    </body>
</html>
