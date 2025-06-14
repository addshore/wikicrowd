<template>
  <div class="flex justify-center mt-2 mb-2">
    <button
      :class="['px-2 py-1 text-sm rounded-l font-bold', answerMode === 'yes-preferred' ? 'bg-green-700 text-white' : 'bg-gray-200 text-gray-700']"
      @click="updateAnswerMode('yes-preferred')"
    >Prominent (Q)</button>
    <button
      :class="['px-2 py-1 text-sm font-bold', answerMode === 'yes' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700']"
      @click="updateAnswerMode('yes')"
    >YES (1)</button>
    <button
      :class="['px-2 py-1 text-sm font-bold', answerMode === 'skip' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
      @click="updateAnswerMode('skip')"
    >SKIP (e)</button>
    <button
      :class="['px-2 py-1 text-sm rounded-r font-bold', answerMode === 'no' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700']"
      @click="updateAnswerMode('no')"
    >NO (2)</button>
  </div>
</template>

<script>
export default {
  name: 'AnswerModeButtons',
  props: {
    answerMode: {
      type: String,
      required: true
    }
  },
  emits: ['update:answerMode'],
  methods: {
    updateAnswerMode(mode) {
      this.$emit('update:answerMode', mode);
    }
  }
};
</script>
