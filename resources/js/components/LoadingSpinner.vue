<template>
  <div v-if="loading" class="flex justify-center items-center py-8">
    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
    <span class="text-gray-600">{{ message }}</span>
  </div>
</template>

<script>
export default {
  name: 'LoadingSpinner',
  props: {
    loading: {
      type: Boolean,
      required: true
    },
    message: {
      type: String,
      default: 'Loading...'
    }
  }
};
</script>
