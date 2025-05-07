<template>
  <div class="py-2 flex justify-center pt-8 sm:justify-start sm:pt-0">
    <button
      :class="['bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2', answer === 'yes' ? 'ring-4 ring-green-300' : '', loading ? 'opacity-50 pointer-events-none grayscale' : '']"
      @click="submit('yes')"
      :disabled="loading"
    >Yes (1)</button>
    <button
      :class="['bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2', answer === 'skip' ? 'ring-4 ring-blue-300' : '', loading ? 'opacity-50 pointer-events-none grayscale' : '']"
      @click="submit('skip')"
      :disabled="loading"
    >Skip (e)</button>
    <button
      :class="['bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded', answer === 'no' ? 'ring-4 ring-red-300' : '', loading ? 'opacity-50 pointer-events-none grayscale' : '']"
      @click="submit('no')"
      :disabled="loading"
    >No (2)</button>
  </div>
</template>

<script>
export default {
  name: 'YesNoMaybeButtons',
  props: {
    questionId: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      answer: null,
      loading: false
    };
  },
  mounted() {
    window.addEventListener('keypress', this.handleKey);
  },
  beforeUnmount() {
    window.removeEventListener('keypress', this.handleKey);
  },
  methods: {
    handleKey(e) {
      if (this.loading) return;
      if (e.key === '1') this.submit('yes');
      if (e.key === '2') this.submit('no');
      if (e.key.toLowerCase() === 'e') this.submit('skip');
    },
    async submit(ans) {
      if (this.loading) return;
      this.answer = ans;
      this.loading = true;
      // Emit immediately before waiting for fetch
      this.$emit('answered', ans);
      try {
        await fetch('/api/answers', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            ...(window.apiToken ? { 'Authorization': `Bearer ${window.apiToken}` } : {})
          },
          body: JSON.stringify({
            question_id: this.questionId,
            answer: ans
          })
        });
      } catch (e) {
        alert('Failed to submit answer.');
      }
      this.loading = false;
    }
  }
};
</script>
