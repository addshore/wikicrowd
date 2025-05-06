<div class="py-2 flex justify-center pt-8 sm:justify-start sm:pt-0">
    <button data-answer="yes" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2">Yes (1)</button>
    <button data-answer="no" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mr-2">No (2)</button>
    <button data-answer="skip" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Skip (3)</button>
</div>

<script>
class WikiCrowdQuestionHandler {
    constructor(groupName, initialCurrentQuestionId, initialNextQuestionId, initialCurrentQuestionData, initialNextQuestionData) {
        this.groupName = groupName;
        this.currentQuestionData = initialCurrentQuestionData; // Full data for the initially displayed question
        this.preloadedQuestions = []; // Queue for preloaded question data objects
        this.seenQuestionIds = new Set(); // Use a Set for efficient add/check/delete and to store more history
        this.isFetching = false;
        this.maxPreload = 10;
        this.maxSeenHistory = 30; // Keep a bit more history than preload buffer
        this.apiToken = this.getApiToken();
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Initialize with server-provided data
        if (this.currentQuestionData && this.currentQuestionData.id) {
            this.addSeenId(this.currentQuestionData.id);
            this.renderQuestion(this.currentQuestionData); // Render initial question
        }

        if (initialNextQuestionData && initialNextQuestionData.id) {
            if (!this.seenQuestionIds.has(initialNextQuestionData.id)) {
                this.preloadedQuestions.push(initialNextQuestionData);
                this.addSeenId(initialNextQuestionData.id);
                this.preloadImage(initialNextQuestionData.properties?.img_url);
            }
        }

        this.bindEvents();
        this.fillPreloadQueue(); // Start filling the preload queue
    }

    getApiToken() {
        return window.apiToken || null;
    }

    addSeenId(id) {
        if (!id) return;
        this.seenQuestionIds.add(id);
        // Trim the set if it grows too large, removing oldest entries (Sets are ordered by insertion)
        if (this.seenQuestionIds.size > this.maxSeenHistory) {
            const oldestId = this.seenQuestionIds.values().next().value;
            this.seenQuestionIds.delete(oldestId);
        }
    }

    bindEvents() {
        document.querySelectorAll('[data-answer]').forEach(button => {
            button.addEventListener('click', (e) => {
                const answer = e.target.dataset.answer;
                this.submitAnswerAndLoadNext(answer);
            });
        });

        document.addEventListener('keypress', (e) => {
            if (e.key === '1') this.submitAnswerAndLoadNext('yes');
            if (e.key === '2') this.submitAnswerAndLoadNext('no');
            if (e.key === '3') this.submitAnswerAndLoadNext('skip');
        });
    }

    async fetchQuestion(questionId = null) {
        if (!this.groupName || this.isFetching) {
            return null;
        }
        this.isFetching = true;

        let url = `/api/questions/${this.groupName}`;
        if (questionId) {
            url += `/${questionId}`;
        }

        // Prepare seen_ids for query parameters
        const seenIdsParam = Array.from(this.seenQuestionIds).join(',');
        if (seenIdsParam) {
            url += `?seen_ids=${encodeURIComponent(seenIdsParam)}`;
        }

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.apiToken}`,
                    'X-CSRF-TOKEN': this.csrfToken
                }
            });
            if (!response.ok) {
                if(response.status === 404) {
                    console.warn('No more questions found from API or question not found.');
                    if (this.preloadedQuestions.length === 0 && !this.currentQuestionData) {
                         this.displayNoMoreQuestions();
                    }
                } else {
                    console.error('Failed to fetch question:', response.status, await response.text());
                }
                return null;
            }
            const data = await response.json();
            return data; // API returns { question: {}, next_question: {} }
        } catch (error) {
            console.error('Error fetching question:', error);
            return null;
        } finally {
            this.isFetching = false;
        }
    }

    async fillPreloadQueue() {
        if (this.isFetching) return;

        while (this.preloadedQuestions.length < this.maxPreload) {
            if (this.isFetching) break; // Check again in case a fetch started

            let questionToFetchId = null;
            // If queue is not empty, use the next_question_id of the last preloaded item
            if (this.preloadedQuestions.length > 0) {
                const lastPreloaded = this.preloadedQuestions[this.preloadedQuestions.length - 1];
                // The API now returns next_question object directly
                if (lastPreloaded.next_question_for_preload && !this.seenQuestionIds.has(lastPreloaded.next_question_for_preload.id)) {
                     questionToFetchId = lastPreloaded.next_question_for_preload.id;
                }
            }
            // If no specific next ID, fetch a random one (that hasn't been seen)
            // The API handles seen_ids, so passing null for questionId works.

            const fetchedData = await this.fetchQuestion(questionToFetchId);

            if (fetchedData && fetchedData.question) {
                if (!this.seenQuestionIds.has(fetchedData.question.id)) {
                    this.preloadedQuestions.push(fetchedData.question);
                    this.addSeenId(fetchedData.question.id);
                    this.preloadImage(fetchedData.question.properties?.img_url);
                    // Store the next_question from this fetch to guide the next iteration
                    if(fetchedData.next_question && !this.seenQuestionIds.has(fetchedData.next_question.id)) {
                        // Add to preloadedQuestions directly if not full, otherwise it will be picked up next
                        if (this.preloadedQuestions.length < this.maxPreload && !this.preloadedQuestions.find(q => q.id === fetchedData.next_question.id)) {
                            this.preloadedQuestions.push(fetchedData.next_question);
                            this.addSeenId(fetchedData.next_question.id);
                            this.preloadImage(fetchedData.next_question.properties?.img_url);
                        } else if (this.preloadedQuestions.length > 0) {
                            // Tag the last added question with its potential next, to be used if questionToFetchId was null
                             this.preloadedQuestions[this.preloadedQuestions.length-1].next_question_for_preload = fetchedData.next_question;
                        }
                    }
                } else if (fetchedData.next_question && !this.seenQuestionIds.has(fetchedData.next_question.id)) {
                    // If the main fetched question was a duplicate but its next_question is new
                     if (this.preloadedQuestions.length < this.maxPreload && !this.preloadedQuestions.find(q => q.id === fetchedData.next_question.id)) {
                        this.preloadedQuestions.push(fetchedData.next_question);
                        this.addSeenId(fetchedData.next_question.id);
                        this.preloadImage(fetchedData.next_question.properties?.img_url);
                    }
                }
            } else {
                // No more questions could be fetched or an error occurred
                if (this.preloadedQuestions.length === 0 && !this.currentQuestionData?.id) {
                    this.displayNoMoreQuestions();
                }
                break; // Stop trying if API returns null or error
            }
        }
    }

    submitAnswerAndLoadNext(answer) {
        const questionToAnswer = this.currentQuestionData;

        // 1. Immediately update UI to the next preloaded question
        if (this.preloadedQuestions.length > 0) {
            this.currentQuestionData = this.preloadedQuestions.shift(); // Get next from queue
            this.renderQuestion(this.currentQuestionData);
            this.addSeenId(this.currentQuestionData.id); // Ensure it's marked as seen upon display
        } else {
            // Preload buffer empty, try to fetch one directly for display
            this.currentQuestionData = null; // Clear current while fetching
            this.renderQuestion(null); // Show loading state / clear old question
            console.log("Preload buffer empty, fetching next question directly...");
            this.fetchQuestion(null).then(data => {
                if (data && data.question) {
                    this.currentQuestionData = data.question;
                    this.renderQuestion(this.currentQuestionData);
                    this.addSeenId(this.currentQuestionData.id);
                    if (data.next_question && !this.seenQuestionIds.has(data.next_question.id) && this.preloadedQuestions.length < this.maxPreload) {
                        this.preloadedQuestions.push(data.next_question);
                        this.addSeenId(data.next_question.id);
                        this.preloadImage(data.next_question.properties?.img_url);
                    }
                } else {
                    this.displayNoMoreQuestions();
                }
                this.fillPreloadQueue(); // Try to refill buffer
            });
        }

        // 2. Submit the answer for the *previous* question in the background
        if (questionToAnswer && questionToAnswer.id) {
            fetch('/api/answers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.apiToken}`,
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    question_id: questionToAnswer.id,
                    answer: answer
                })
            })
            .then(response => {
                if (!response.ok) {
                    console.error('Failed to submit answer:', response.status, response.statusText);
                    // Handle failed submission, e.g., retry or notify user
                }
                // console.log('Answer submitted for:', questionToAnswer.id);
            })
            .catch(error => console.error('Error submitting answer:', error));
        } else {
            // console.log("No question was displayed, so no answer submitted.");
        }

        // 3. Ensure the preload queue is being refilled
        this.fillPreloadQueue();
    }

    renderQuestion(questionData) {
        const questionContent = document.getElementById('question-content');
        const noMoreQuestionsDiv = document.getElementById('no-more-questions');

        if (!questionData || !questionData.id) { // Handles initial load if no question or end of questions
            if (questionContent) questionContent.style.display = 'none';
            if (noMoreQuestionsDiv && this.preloadedQuestions.length === 0) { // Only show if truly no more
                 noMoreQuestionsDiv.style.display = 'block';
            }
            // Update data attribute even if no question
            document.getElementById('question-container').dataset.currentQuestionId = '';
            return;
        }

        if (questionContent) questionContent.style.display = 'block';
        if (noMoreQuestionsDiv) noMoreQuestionsDiv.style.display = 'none';

        document.getElementById('question-container').dataset.currentQuestionId = questionData.id;

        // Update image
        const imgEl = document.getElementById('current-image');
        const imgLinkEl = document.getElementById('current-image-commons-link');
        if (imgEl && questionData.properties?.img_url) {
            imgEl.src = questionData.properties.img_url;
        } else if (imgEl) {
            imgEl.src = ''; // Clear if no image
        }
        if (imgLinkEl && questionData.properties?.mediainfo_id) {
            imgLinkEl.href = `https://commons.wikimedia.org/wiki/Special:EntityData/${questionData.properties.mediainfo_id}`;
        }


        // Update depicts information
        const oldDepictsIdEl = document.getElementById('current-old-depicts-id');
        const oldDepictsNameEl = document.getElementById('current-old-depicts-name');
        const oldDepictsLink = document.getElementById('current-old-depicts-link');
        const depictsNameEl = document.getElementById('current-depicts-name');
        const depictsIdEl = document.getElementById('current-depicts-id');
        const depictsLinkEl = document.getElementById('current-depicts-link');

        // This logic needs to adapt based on whether old_depicts_id exists, similar to Blade
        const oldDepictsContainer = oldDepictsLink?.closest('.flex.justify-center.pt-8'); // Find parent container to hide/show
        const mainQuestionTextContainer = depictsNameEl?.closest('.flex.justify-center.pt-8');


        if (questionData.properties?.old_depicts_id) {
            if(oldDepictsContainer) oldDepictsContainer.style.display = 'block'; // or flex etc.
            if(oldDepictsIdEl) oldDepictsIdEl.textContent = questionData.properties.old_depicts_id;
            if(oldDepictsNameEl) oldDepictsNameEl.textContent = questionData.properties.old_depicts_name;
            if(oldDepictsLink) oldDepictsLink.href = `https://www.wikidata.org/wiki/${questionData.properties.old_depicts_id}`;

            if(mainQuestionTextContainer) {
                 // Reword the question text slightly if old_depicts_id is present
                mainQuestionTextContainer.querySelector('.text-lg.leading-7').innerHTML =
                    `Does this image actually clearly depict "<span id="current-depicts-name">${questionData.properties.depicts_name}</span>" (<a id="current-depicts-link" href="https://www.wikidata.org/wiki/${questionData.properties.depicts_id}" target="_blank"><span id="current-depicts-id">${questionData.properties.depicts_id}</span></a>)?`;
            }

        } else {
            if(oldDepictsContainer) oldDepictsContainer.style.display = 'none';
             if(mainQuestionTextContainer) {
                mainQuestionTextContainer.querySelector('.text-lg.leading-7').innerHTML =
                    `Does this image clearly depict "<span id="current-depicts-name">${questionData.properties.depicts_name}</span>" (<a id="current-depicts-link" href="https://www.wikidata.org/wiki/${questionData.properties.depicts_id}" target="_blank"><span id="current-depicts-id">${questionData.properties.depicts_id}</span></a>)?`;
            }
        }
        // Always update the main depicts part
        if(depictsNameEl) depictsNameEl.textContent = questionData.properties.depicts_name;
        if(depictsIdEl) depictsIdEl.textContent = questionData.properties.depicts_id;
        if(depictsLinkEl) depictsLinkEl.href = `https://www.wikidata.org/wiki/${questionData.properties.depicts_id}`;


        // Prefetch image for the *next* item in the preloaded queue, if any
        if (this.preloadedQuestions.length > 0 && this.preloadedQuestions[0].properties?.img_url) {
            this.preloadImage(this.preloadedQuestions[0].properties.img_url);
        }
    }

    preloadImage(url) {
        if (!url) return;
        // Using Image object for preloading is simpler than managing <link> tags for many images
        const img = new Image();
        img.src = url;
        // console.log('Prefetching image:', url);
    }

    displayNoMoreQuestions() {
        const questionContent = document.getElementById('question-content');
        const noMoreQuestionsDiv = document.getElementById('no-more-questions');
        if (questionContent) questionContent.style.display = 'none';
        if (noMoreQuestionsDiv) noMoreQuestionsDiv.style.display = 'block';
        console.log("No more questions available.");
        this.currentQuestionData = null; // Ensure no stale data
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('question-container');
    if (container) {
        const groupName = container.dataset.groupName;
        const initialCurrentId = container.dataset.currentQuestionId; // This is from Blade $qu
        const initialNextId = container.dataset.nextQuestionId; // This is from Blade $next

        // initialCurrentQuestionData and initialNextQuestionData are expected to be set globally
        // by the parent Blade view (e.g., image-focus.blade.php) like so:
        // window.initialQuestionData = someJsonEncodedInitialQuestion;
        // window.initialNextQuestionData = someJsonEncodedInitialNextQuestion;

        // Then access them here:
        const initialCurrentQuestionData = window.initialQuestionData || null;
        const initialNextQuestionData = window.initialNextQuestionData || null;


        if (groupName) {
            new WikiCrowdQuestionHandler(groupName, initialCurrentId, initialNextId, initialCurrentQuestionData, initialNextQuestionData);
        } else if (!initialCurrentQuestionData && !initialNextQuestionData) {
            // If there was no initial $qu (e.g. no questions at all for the group)
            // The handler might try to fetch, or display "no questions" if fetch also fails.
             const noMoreQuestionsDiv = document.getElementById('no-more-questions');
             if(noMoreQuestionsDiv) noMoreQuestionsDiv.style.display = 'block';
             const questionContent = document.getElementById('question-content');
             if(questionContent) questionContent.style.display = 'none';
        }
    }
});
</script>
