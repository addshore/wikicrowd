<script>
    // Make initial question data available to the WikiCrowdQuestionHandler
    window.initialQuestionData = @json($qu ?? null);
    window.initialNextQuestionData = @json($next ?? null);
    window.apiToken = '{{ $apiToken ?? null }}'; // Ensure API token is also globally available if set
</script>

<div id="question-container" class="flex flex-col items-center" data-group-name="{{ $qu->group->name }}" data-current-question-id="{{ $qu->id ?? '' }}" data-next-question-id="{{ $next->id ?? '' }}">
    <div id="question-content" style="display: {{ $qu ? 'block' : 'none' }};">
        <div class="flex justify-center pt-8">
            <a id="current-image-commons-link" href="{{ $qu && $qu->properties['mediainfo_id'] ? 'https://commons.wikimedia.org/wiki/Special:EntityData/' . $qu->properties['mediainfo_id'] : '#' }}" target="_blank">
                <img id="current-image" src="{{ $qu->properties['img_url'] ?? '' }}" alt="Current Question Image" class="max-w-full md:max-w-lg lg:max-w-xl xl:max-w-2xl max-h-[60vh] rounded-lg shadow-lg">
            </a>
        </div>

        <div class="flex justify-center pt-8" @if(!$qu || !isset($qu->properties['old_depicts_id'])) style="display:none;" @endif>
            <p class="text-lg leading-7 text-gray-500">
                This image was previously said to depict "<span id="current-old-depicts-name">{{ $qu->properties['old_depicts_name'] ?? '' }}</span>" (<a id="current-old-depicts-link" href="{{ $qu && isset($qu->properties['old_depicts_id']) ? ('https://www.wikidata.org/wiki/' . $qu->properties['old_depicts_id']) : '#' }}" target="_blank"><span id="current-old-depicts-id">{{ $qu->properties['old_depicts_id'] ?? '' }}</span></a>).
            </p>
        </div>

        <div class="flex justify-center pt-8">
            <p class="text-lg leading-7 text-gray-500">
                @if($qu && isset($qu->properties['old_depicts_id']))
                    Does this image actually clearly depict "<span id="current-depicts-name">{{ $qu->properties['depicts_name'] ?? '' }}</span>" (<a id="current-depicts-link" href="{{ $qu && isset($qu->properties['depicts_id']) ? ('https://www.wikidata.org/wiki/' . $qu->properties['depicts_id']) : '#' }}" target="_blank"><span id="current-depicts-id">{{ $qu->properties['depicts_id'] ?? '' }}</span></a>)?
                @else
                    Does this image clearly depict "<span id="current-depicts-name">{{ $qu->properties['depicts_name'] ?? '' }}</span>" (<a id="current-depicts-link" href="{{ $qu && isset($qu->properties['depicts_id']) ? ('https://www.wikidata.org/wiki/' . $qu->properties['depicts_id']) : '#' }}" target="_blank"><span id="current-depicts-id">{{ $qu->properties['depicts_id'] ?? '' }}</span></a>)?
                @endif
            </p>
        </div>
    </div>

    <div id="no-more-questions" class="text-center p-8" style="display: {{ !$qu && !$next ? 'block' : 'none' }};">
        <h2 class="text-xl font-semibold text-gray-700">No more questions available in this group right now.</h2>
        <p class="text-gray-500">Please check back later or try a different group.</p>
    </div>

    <x-yes-no-maybe-buttons />

    <script>
    // Ensure WikiCrowdQuestionHandler is initialized after all DOM is rendered
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const container = document.getElementById('question-container');
            if (container) {
                const groupName = container.dataset.groupName;
                const initialCurrentId = container.dataset.currentQuestionId;
                const initialNextId = container.dataset.nextQuestionId;
                const initialCurrentQuestionData = window.initialQuestionData || null;
                const initialNextQuestionData = window.initialNextQuestionData || null;
                if (groupName) {
                    new WikiCrowdQuestionHandler(groupName, initialCurrentId, initialNextId, initialCurrentQuestionData, initialNextQuestionData);
                }
            }
        }, 0);
    });
    </script>
</div>
