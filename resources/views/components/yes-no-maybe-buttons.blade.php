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
        this.isFillingPreloadQueue = false; // For the fillPreloadQueue method
        this.maxPreload = 10;
        this.maxSeenHistory = 100; // Keep a bit more history than preload buffer
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
        console.log(`WikiCrowdQuestionHandler: Initialized. Preloaded questions: ${this.preloadedQuestions.length}`);
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
        // Use event delegation for robustness
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-answer]');
            if (btn) {
                const answer = btn.dataset.answer;
                console.log('Button clicked:', answer);
                this.submitAnswerAndLoadNext(answer);
            }
        });

        document.addEventListener('keypress', (e) => {
            if (e.key === '1') this.submitAnswerAndLoadNext('yes');
            if (e.key === '2') this.submitAnswerAndLoadNext('no');
            if (e.key === '3') this.submitAnswerAndLoadNext('skip');
        });
    }

    async fetchQuestion() { // Removed nextQuestionId parameter
        if (this.isFetching) {
            console.warn("WikiCrowdQuestionHandler: fetchQuestion called while already fetching.");
            return null;
        }
        this.isFetching = true;
        // Always fetch a random question for the given type and item_id
        let url = `/api/questions/${this.groupName}`;
        const seenIdsParam = Array.from(this.seenQuestionIds).join(',');
        url += `?seen_ids=${encodeURIComponent(seenIdsParam)}`;

        console.log(`WikiCrowdQuestionHandler: Fetching random question. URL: ${url}`);
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
                console.error(`WikiCrowdQuestionHandler: Error fetching question. Status: ${response.status}, URL: ${url}`);
                // No specific nextQuestionId to check for 404 against anymore
                this.isFetching = false;
                return null;
            }
            const data = await response.json();
            this.isFetching = false;
            if (!data.question) {
                console.log("WikiCrowdQuestionHandler: Fetched data, but no question object found.", data);
            }
            return data;
        } catch (error) {
            console.error('WikiCrowdQuestionHandler: Exception during fetchQuestion:', error);
            this.isFetching = false;
            return null;
        }
    }

    async fillPreloadQueue() {
        if (this.isFillingPreloadQueue) {
            console.log("WikiCrowdQuestionHandler: fillPreloadQueue is already in progress.");
            return;
        }
        this.isFillingPreloadQueue = true;
        console.log(`WikiCrowdQuestionHandler: fillPreloadQueue START. Current preload: ${this.preloadedQuestions.length}/${this.maxPreload}. Seen: ${this.seenQuestionIds.size}`);

        while (this.preloadedQuestions.length < this.maxPreload) {
            if (this.isFetching) { // If a fetchQuestion call is globally active
                console.log("WikiCrowdQuestionHandler: fillPreloadQueue - fetchQuestion is active, pausing loop.");
                break;
            }

            // Always fetch a random question
            console.log(`WikiCrowdQuestionHandler: fillPreloadQueue - Attempting fetch for a random question.`);
            let fetchedData = await this.fetchQuestion(); // No argument passed

            if (fetchedData && fetchedData.question) {
                let newQuestionPushedToPreload = false;

                // Process main question from fetch
                if (!this.seenQuestionIds.has(fetchedData.question.id) && !this.preloadedQuestions.find(q => q.id === fetchedData.question.id)) {
                    this.addSeenId(fetchedData.question.id); // Mark seen BEFORE adding
                    this.preloadedQuestions.push(fetchedData.question);
                    this.preloadImage(fetchedData.question.properties?.img_url);
                    newQuestionPushedToPreload = true;
                    console.log(`WikiCrowdQuestionHandler: Added Q ${fetchedData.question.id} (random) to preload. New size: ${this.preloadedQuestions.length}`);
                } else {
                    console.log(`WikiCrowdQuestionHandler: Q ${fetchedData.question.id} (random) already seen/in preload.`);
                }

                // Process next_question from fetch, if available and if we still need questions
                // This part remains useful as the random fetch might still return a 'next_question' hint
                // which can be a candidate for the *next* preloading cycle if it's not the same as the one just fetched.
                if (fetchedData.next_question && this.preloadedQuestions.length < this.maxPreload) {
                    if (!this.seenQuestionIds.has(fetchedData.next_question.id) && !this.preloadedQuestions.find(q => q.id === fetchedData.next_question.id)) {
                        this.addSeenId(fetchedData.next_question.id); // Mark seen BEFORE adding
                        this.preloadedQuestions.push(fetchedData.next_question);
                        this.preloadImage(fetchedData.next_question.properties?.img_url);
                        newQuestionPushedToPreload = true; // Count this as a new question for the loop break condition
                        console.log(`WikiCrowdQuestionHandler: Added next_Q ${fetchedData.next_question.id} (from random fetch's hint) to preload. New size: ${this.preloadedQuestions.length}`);
                    } else {
                         console.log(`WikiCrowdQuestionHandler: next_Q ${fetchedData.next_question.id} (from random fetch's hint) already seen/in preload.`);
                    }
                }
                
                // Remove the logic for setting .next_question_for_preload as we are not using specific IDs for fetching anymore.
                // The 'next_question' from the payload is now directly added to the queue if valid and space permits.

                if (!newQuestionPushedToPreload && this.preloadedQuestions.length < this.maxPreload) {
                    console.log(`WikiCrowdQuestionHandler: fillPreloadQueue - Fetch successful (random) but no new questions added (likely all seen/duplicates). Breaking loop.`);
                    break;
                }
            } else {
                console.log(`WikiCrowdQuestionHandler: fillPreloadQueue - Fetch failed or no question data (random).`);
                if (this.preloadedQuestions.length === 0 && !this.currentQuestionData?.id) {
                    this.displayNoMoreQuestions();
                }
                break; // Stop trying for this cycle
            }
        }

        console.log(`WikiCrowdQuestionHandler: fillPreloadQueue END. Preload: ${this.preloadedQuestions.length}/${this.maxPreload}.`);
        this.isFillingPreloadQueue = false;
    }

    submitAnswerAndLoadNext(answer) {
        const questionToAnswer = this.currentQuestionData;
        console.log(`WikiCrowdQuestionHandler: Submitting answer '${answer}' for question ${questionToAnswer?.id}. Preload queue size before: ${this.preloadedQuestions.length}`);

        // 1. Immediately update UI to the next preloaded question
        if (this.preloadedQuestions.length > 0) {
            this.currentQuestionData = this.preloadedQuestions.shift(); // Get next from queue
            console.log(`WikiCrowdQuestionHandler: Shifted question ${this.currentQuestionData.id} from preload queue. New queue size: ${this.preloadedQuestions.length}`);
            this.renderQuestion(this.currentQuestionData);
            this.addSeenId(this.currentQuestionData.id); // Ensure it's marked as seen upon display
        } else {
            // Preload buffer empty, try to fetch one directly for display
            this.currentQuestionData = null; // Clear current while fetching
            this.renderQuestion(null); // Show loading state / clear old question
            console.log("WikiCrowdQuestionHandler: Preload buffer empty, fetching next random question directly...");
            this.fetchQuestion().then(data => { // Fetch a random question
                if (data && data.question) {
                    this.currentQuestionData = data.question;
                    this.renderQuestion(this.currentQuestionData);
                    this.addSeenId(this.currentQuestionData.id);
                    console.log(`WikiCrowdQuestionHandler: Fetched question ${this.currentQuestionData.id} directly. Preload queue size: ${this.preloadedQuestions.length}`);
                    if (data.next_question && !this.seenQuestionIds.has(data.next_question.id) && this.preloadedQuestions.length < this.maxPreload) {
                        this.preloadedQuestions.push(data.next_question);
                        this.addSeenId(data.next_question.id);
                        this.preloadImage(data.next_question.properties?.img_url);
                        console.log(`WikiCrowdQuestionHandler: Added next_question ${data.next_question.id} to preload queue from direct fetch. Queue size: ${this.preloadedQuestions.length}`);
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
            .then(async response => { // Added async here
                if (response.status === 429) {
                    const retryAfter = parseInt(response.headers.get('X-RateLimit-Reset')) * 1000;
                    const now = Date.now();
                    const delay = Math.max(0, retryAfter - now) || 1000; // Default to 1s
                    console.warn(`Rate limited on submit. Retrying after ${delay}ms.`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                    // Retry the request
                    return fetch('/api/answers', {
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
                    });
                }
                return response;
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

        // Update the question text and details
        const mainQuestionTextContainer = depictsNameEl?.closest('.flex.justify-center.pt-8');
        if(mainQuestionTextContainer) {
            mainQuestionTextContainer.querySelector('.text-lg.leading-7').innerHTML =
                `Does this image clearly depict "<span id="current-depicts-name">${questionData.properties.depicts_name}</span>" (<a id="current-depicts-link" href="https://www.wikidata.org/wiki/${questionData.properties.depicts_id}" target="_blank"><span id="current-depicts-id">${questionData.properties.depicts_id}</span></a>)?`;
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
