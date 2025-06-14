<template>
  <div class="text-center py-8">
    <div class="text-gray-500 text-lg">{{ message }}</div>
    <div v-if="subMessage" class="text-gray-400 text-sm mt-2">{{ subMessage }}</div>
  </div>
</template>

<script>
export default {
  name: 'EmptyState',
  props: {
    message: {
      type: String,
      default: 'No items available'
    },
    subMessage: {
      type: String,
      default: null
    }
  }
};
</script>
