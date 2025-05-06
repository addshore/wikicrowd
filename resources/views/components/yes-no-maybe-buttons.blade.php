<div id="dynamic-yes-no-maybe-buttons">
    <button data-answer="yes" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded" title="Press 1 to execute">Yes [1]</button>
    <button data-answer="no" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded ml-2" title="Press 2 to execute">No [2]</button>
    <button data-answer="skip" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded ml-2" title="Press 3 to execute">Skip [3]</button>
</div>

<script>
class WikiCrowdQuestionHandler {
    constructor() {
        this.questionContainer = document.getElementById('question-container');
        this.questionContent = document.getElementById('question-content');
        this.noMoreQuestionsDiv = document.getElementById('no-more-questions');
        this.buttonsDiv = document.getElementById('dynamic-yes-no-maybe-buttons');
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        this.currentQuestionData = null;
        this.nextQuestionData = null;

        this.bindEvents();
        this.loadInitialQuestion();
    }

    bindEvents() {
        this.buttonsDiv.addEventListener('click', (event) => {
            if (event.target.tagName === 'BUTTON') {
                const answer = event.target.dataset.answer;
                this.submitAnswerAndLoadNext(answer);
            }
        });

        document.addEventListener('keypress', (evt) => {
            let charCode = evt.keyCode || evt.which;
            let character = String.fromCharCode(charCode);
            const answer = this.actionMapping(character);
            if (answer) {
                this.submitAnswerAndLoadNext(answer);
            }
        });
    }

    actionMapping(character) {
        switch (character) {
            case '1': return 'yes';
            case '2': return 'no';
            case '3': return 'skip';
            default: return false;
        }
    }

    async loadInitialQuestion() {
        const initialQuestionId = this.questionContainer.dataset.currentQuestionId;
        const initialNextQuestionId = this.questionContainer.dataset.nextQuestionId;
        const groupName = this.questionContainer.dataset.groupName;

        if (initialQuestionId) {
            // Fetch the initial question data (which might include the next question already)
            // This assumes the initial page load already has the first question details rendered
            // and we primarily need to ensure `nextQuestionData` is populated for the *second* question load.
            this.currentQuestionData = {
                id: initialQuestionId,
                // We'd ideally have the full initial question object if passed from PHP
                // For now, we'll rely on the initially rendered HTML for the first question
            };
            if (initialNextQuestionId) {
                 // If nextId is present, fetch it to have it ready
                await this.fetchQuestion(groupName, initialNextQuestionId, true); // true to store as next
            } else {
                // If no nextId, try to fetch any next question for the group
                await this.fetchQuestion(groupName, null, true);
            }
        } else if (groupName) {
            // No initial question, try to load the first one for the group
            await this.fetchQuestion(groupName, null, false); // false to store as current
        }
    }

    async fetchQuestion(groupName, questionId = null, isNext = false) {
        if (!groupName) return;
        let url = `/api/questions/${groupName}`;
        if (questionId) {
            url += `/${questionId}`;
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getApiToken()}` // Assuming a global function or variable for token
                }
            });
            if (!response.ok) {
                if (response.status === 404) {
                    if (!isNext) this.displayNoMoreQuestions();
                     this.nextQuestionData = null; // No more questions
                } else {
                    console.error('Error fetching question:', response.statusText);
                }
                return;
            }
            const data = await response.json();
            if (isNext) {
                this.nextQuestionData = data.question ? data.question : (data.next_question ? data.next_question : null); 
                if(data.next_question && data.question && data.question.id == questionId) { // if specific ID was requested for next, and it also has a next
                    this.preloadImage(data.next_question.properties.img_url, 'prefetch-next-image');
                } else if (this.nextQuestionData) {
                     this.preloadImage(this.nextQuestionData.properties.img_url, 'prefetch-next-image');
                }
            } else {
                this.currentQuestionData = data.question;
                this.nextQuestionData = data.next_question;
                this.renderQuestion(this.currentQuestionData);
                if (this.nextQuestionData) {
                    this.preloadImage(this.nextQuestionData.properties.img_url, 'prefetch-next-image');
                }
            }
        } catch (error) {
            console.error('Failed to fetch question:', error);
        }
    }

    async submitAnswerAndLoadNext(answer) {
        if (!this.currentQuestionData || !this.currentQuestionData.id) return;

        const questionIdToSubmit = this.currentQuestionData.id;

        // Display next question immediately if available
        if (this.nextQuestionData) {
            this.currentQuestionData = this.nextQuestionData;
            this.renderQuestion(this.currentQuestionData);
            // Fetch the *new* next question
            this.fetchQuestion(this.questionContainer.dataset.groupName, null, true);
        } else {
            // No next question readily available, fetch one (might show no more questions)
            await this.fetchQuestion(this.questionContainer.dataset.groupName, null, false);
        }

        // Submit answer in the background
        try {
            await fetch('/api/answers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Authorization': `Bearer ${this.getApiToken()}`
                },
                body: JSON.stringify({
                    question_id: questionIdToSubmit,
                    answer: answer
                })
            });
            // console.log('Answer submitted for:', questionIdToSubmit);
        } catch (error) {
            console.error('Failed to submit answer:', error);
            // Handle submission error (e.g., retry or notify user)
        }
    }

    renderQuestion(questionData) {
        if (!questionData || !this.questionContent) {
            this.displayNoMoreQuestions();
            return;
        }
        this.questionContent.style.display = 'block';
        if(this.noMoreQuestionsDiv) this.noMoreQuestionsDiv.style.display = 'none';

        // Update question text and links (assuming IDs exist in image-focus.blade.php)
        const depictsNameEl = document.getElementById('current-depicts-name');
        const depictsIdEl = document.getElementById('current-depicts-id');
        const depictsLinkEl = document.getElementById('current-depicts-link');

        if (depictsNameEl) depictsNameEl.textContent = questionData.properties.depicts_name;
        if (depictsIdEl) depictsIdEl.textContent = questionData.properties.depicts_id;
        if (depictsLinkEl) depictsLinkEl.href = `https://www.wikidata.org/wiki/${questionData.properties.depicts_id}`;

        const oldDepictsContainer = document.getElementById('current-old-depicts-link')?.closest('.flex.justify-center.pt-8');
        if (questionData.properties.old_depicts_id) {
            if(oldDepictsContainer) oldDepictsContainer.style.display = 'flex';
            document.getElementById('current-old-depicts-id').textContent = questionData.properties.old_depicts_id;
            document.getElementById('current-old-depicts-name').textContent = questionData.properties.old_depicts_name;
            document.getElementById('current-old-depicts-link').href = `https://www.wikidata.org/wiki/${questionData.properties.old_depicts_id}`;
        } else {
            if(oldDepictsContainer) oldDepictsContainer.style.display = 'none';
        }

        // Update image
        const imageEl = document.getElementById('current-image');
        const imageCommonsLinkEl = document.getElementById('current-image-commons-link');
        if (imageEl) imageEl.src = questionData.properties.img_url;
        if (imageCommonsLinkEl) imageCommonsLinkEl.href = `https://commons.wikimedia.org/wiki/Special:EntityData/${questionData.properties.mediainfo_id}`;

        // Update prefetch for current image (which is now the one displayed)
        this.preloadImage(questionData.properties.img_url, 'prefetch-current-image');

        // Update data attributes on container
        this.questionContainer.dataset.currentQuestionId = questionData.id;
        this.questionContainer.dataset.nextQuestionId = this.nextQuestionData ? this.nextQuestionData.id : '';
    }

    displayNoMoreQuestions() {
        if(this.questionContent) this.questionContent.style.display = 'none';
        if(this.noMoreQuestionsDiv) {
            this.noMoreQuestionsDiv.style.display = 'block';
        } else {
            // Fallback if the div isn't on the page for some reason
            const container = document.getElementById('question-container');
            if(container) container.innerHTML = '<div class="flex justify-center pt-8 sm:justify-start sm:pt-0"><div class="text-lg leading-7 font-semibold text-gray-900 dark:text-white">No more questions available in this group. <a href="/" class="underline">Go back to groups</a>.</div></div>';
        }
    }

    preloadImage(url, linkId) {
        let link = document.getElementById(linkId);
        if (!link) {
            link = document.createElement('link');
            link.id = linkId;
            link.rel = 'prefetch';
            document.head.appendChild(link);
        }
        link.href = url;
    }

    getApiToken() {
        // Attempt to get token from the api-docs page if available, or a global var
        // This is a placeholder: implement proper token retrieval for your app
        if (typeof window.apiToken !== 'undefined') {
            return window.apiToken;
        }
        // Fallback or more robust token retrieval mechanism
        // For example, if you store it in localStorage or a cookie after login
        // const token = localStorage.getItem('api_token');
        // if (token) return token;
        // If you have it in a meta tag (less ideal for Bearer tokens but possible)
        // const metaToken = document.querySelector('meta[name="api-token"]');
        // if (metaToken) return metaToken.getAttribute('content');
        return null; // Or a default/guest token if your API supports it
    }
}

// Initialize the handler
document.addEventListener('DOMContentLoaded', () => {
    // Pass initial question and next question data if available from Blade
    new WikiCrowdQuestionHandler();
});

</script>
