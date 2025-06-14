<template>
  <div class="flex justify-center mt-2 mb-2">
    <button
      class="ml-2 px-3 py-1 bg-blue-600 text-white rounded font-bold border border-blue-700 hover:bg-blue-700"
      :disabled="isDisabled"
      @click="$emit('save')"
    >Save</button>
    <button
      class="ml-2 px-2 py-1 text-sm bg-gray-200 text-gray-700 border border-gray-300 hover:bg-gray-300 rounded"
      @click="$emit('clearAnswered')"
    >
      Clear Done
    </button>
  </div>
</template>

<script>
export default {
  name: 'GridControlBar',
  props: {
    pendingAnswersCount: {
      type: Number,
      required: true
    },
    selectedCount: {
      type: Number,
      required: true
    }
  },
  emits: ['save', 'clearAnswered'],
  computed: {
    isDisabled() {
      return this.pendingAnswersCount === 0 && this.selectedCount === 0;
    }
  }
};
</script>
