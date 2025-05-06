<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WikiCrowd API Documentation</title>
        <link href="/css/app.css" rel="stylesheet">
    </head>
    <body class="antialiased">
        <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
            <x-top-right-navbar/>

            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-center pt-8 sm:justify-start sm:pt-0">
                    <div class="text-lg leading-7 font-semibold"><span class="text-gray-900 dark:text-white">WikiCrowd API</span></div>
                </div>

                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-gray-900 dark:text-white">
                            <p class="mb-4">This page documents the JSON API for WikiCrowd.</p>

                            <h2 class="text-xl font-semibold mb-2">Authentication</h2>
                            <p class="mb-4">Authentication is handled using Laravel Sanctum. Clients should first authenticate to obtain an API token. This token must be included in the <code>Authorization</code> header as a Bearer token for all authenticated requests: <code>Authorization: Bearer &lt;YOUR_TOKEN&gt;</code>.</p>

                            <h2 class="text-xl font-semibold mb-2">Endpoints</h2>

                            <h3 class="text-lg font-semibold mt-4 mb-1">Question Groups</h3>
                            <div class="mb-4">
                                <p><strong>GET <code>/api/groups</code></strong></p>
                                <p>Retrieves a list of question groups.</p>
                                <p class="mt-2"><strong>Example <code>curl</code> command:</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>curl -X GET "{{ url('/api/groups') }}"</code></pre>
                                <p class="mt-2"><strong>Response:</strong> A JSON object where each key is a main group category (e.g., "animals", "food", "refinement"). Each category contains:</p>
                                <ul class="list-disc ml-6">
                                    <li><code>display_name</code>: (string) The human-readable name of the main group.</li>
                                    <li><code>display_description</code>: (string, optional) A description for the main group (primarily for "Refinement").</li>
                                    <li><code>subGroups</code>: (array) A list of specific sub-groups. Each sub-group object has:
                                        <ul class="list-disc ml-6">
                                            <li><code>id</code>: (integer) The ID of the sub-group.</li>
                                            <li><code>name</code>: (string) The internal name/identifier of the sub-group (e.g., "depicts/animals/Q144", "depicts-refine/some-identifier").</li>
                                            <li><code>display_name</code>: (string) The human-readable name for the sub-group.</li>
                                            <li><code>unanswered</code>: (integer) The count of unanswered questions in this sub-group.</li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>

                            <h3 class="text-lg font-semibold mt-4 mb-1">Questions</h3>
                            <div class="mb-4">
                                <p><strong>GET <code>/api/questions/{groupName}/{desiredId?}</code></strong> (Requires Authentication)</p>
                                <p>Fetches questions for a specific group.</p>
                                <ul class="list-disc ml-6">
                                    <li><code>{groupName}</code>: (string) The name of the question group (e.g., <code>animals/Q144</code> or <code>depicts-refine/some-name</code>). You can get this from the <code>name</code> property of a sub-group in the <code>/api/groups</code> response.</li>
                                    <li><code>{desiredId}</code>: (integer, optional) The ID of a specific question to retrieve. If not provided, or if the desired question is not found or already answered, a random unanswered question from the group is returned.</li>
                                </ul>
                                <p class="mt-2"><strong>Example <code>curl</code> command (replace <code>{groupName}</code> and <code>&lt;YOUR_TOKEN&gt;</code>):</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>curl -X GET -H "Authorization: Bearer &lt;YOUR_TOKEN&gt;" "{{ url('/api/questions/') }}/{groupName}"</code></pre>
                                <p class="mt-1">To request a specific question ID (replace <code>{groupName}</code>, <code>{questionId}</code> and <code>&lt;YOUR_TOKEN&gt;</code>):</p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>curl -X GET -H "Authorization: Bearer &lt;YOUR_TOKEN&gt;" "{{ url('/api/questions/') }}/{groupName}/{questionId}"</code></pre>
                                <p class="mt-2"><strong>Response:</strong> A JSON object containing:</p>
                                <ul class="list-disc ml-6">
                                    <li><code>question</code>: (object) The question object, including its <code>id</code>, <code>properties</code> (like image URL, depicts information), etc.</li>
                                    <li><code>next_question_id</code>: (integer|null) The ID of another random unanswered question in the same group, or null if no other questions are available. This can be used to pre-fetch or navigate.</li>
                                </ul>
                            </div>

                            <h3 class="text-lg font-semibold mt-4 mb-1">Answers</h3>
                            <div class="mb-4">
                                <p><strong>POST <code>/api/answers</code></strong> (Requires Authentication)</p>
                                <p>Submits an answer to a question.</p>
                                <p><strong>Request Body (JSON):</strong></p>
                                <ul class="list-disc ml-6">
                                    <li><code>question_id</code>: (integer, required) The ID of the question being answered.</li>
                                    <li><code>answer</code>: (string, required) The answer. Must be one of: <code>yes</code>, <code>no</code>, <code>skip</code>.</li>
                                </ul>
                                <p class="mt-2"><strong>Example <code>curl</code> command (replace <code>&lt;YOUR_TOKEN&gt;</code>, <code>{question_id}</code>, and <code>{answer_value}</code>):</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>curl -X POST -H "Authorization: Bearer &lt;YOUR_TOKEN&gt;" -H "Content-Type: application/json" -H "Accept: application/json" -d '{
    "question_id": {question_id},
    "answer": "{answer_value}"
}' "{{ url('/api/answers') }}"</code></pre>
                                <p class="mt-2"><strong>Response (Success - 201 Created):</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>{
    "message": "Answer submitted successfully.",
    "answer": { /* Answer object */ }
}</code></pre>
                                <p class="mt-2"><strong>Response (Validation Error - 422 Unprocessable Entity):</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>{
    "message": "The given data was invalid.",
    "errors": { /* Validation error details */ }
}</code></pre>
                                <p class="mt-2"><strong>Response (Question Not Found - 404 Not Found):</strong></p>
                                <pre class="bg-gray-200 dark:bg-gray-700 p-2 rounded"><code>{
    "message": "Question not found."
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
                    <div class="text-center text-sm text-gray-500 sm:text-left">
                        <div class="flex items-center">
                            <a href="{{ url('/') }}" class="ml-1 underline">
                                Back to Home
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </body>
</html>
