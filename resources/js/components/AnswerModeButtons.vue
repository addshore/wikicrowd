<template>
  <div class="flex justify-center mt-2 mb-2">
    <!-- Answer Mode Buttons -->
    <div class="flex">
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
    
    <!-- Remove Superclasses Checkbox -->
    <div class="flex items-center space-x-2 ml-4">
      <input
        id="remove-superclasses-checkbox"
        type="checkbox"
        :checked="removeSuperclasses"
        @change="updateRemoveSuperclasses($event.target.checked)"
        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
      />
      <label for="remove-superclasses-checkbox" class="text-sm">
        Remove superclasses
      </label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AnswerModeButtons',
  props: {
    answerMode: {
      type: String,
      required: true
    },
    removeSuperclasses: {
      type: Boolean,
      default: true
    }
  },
  emits: ['update:answerMode', 'update:removeSuperclasses'],
  methods: {
    updateAnswerMode(mode) {
      this.$emit('update:answerMode', mode);
    },
    updateRemoveSuperclasses(value) {
      this.$emit('update:removeSuperclasses', value);
    }
  }
};
</script>
