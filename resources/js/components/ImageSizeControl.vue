<template>
  <div class="flex justify-center items-center mt-2 mb-2">
    <label class="flex items-center text-sm text-gray-700">
      <span class="mr-3">Image Size:</span>
      <span class="mr-2">Large</span>
      <input 
        type="range" 
        min="1" 
        max="12" 
        :value="imageSize"
        @input="updateImageSize"
        class="mx-2 w-32"
      />
      <span class="ml-2">Small</span>
      <span class="ml-3 text-xs text-gray-500">({{ imageSize }} col{{ imageSize === 1 ? '' : 's' }})</span>
    </label>
  </div>
</template>

<script>
export default {
  name: 'ImageSizeControl',
  props: {
    imageSize: {
      type: Number,
      required: true
    }
  },
  emits: ['update:imageSize'],
  methods: {
    updateImageSize(event) {
      this.$emit('update:imageSize', parseInt(event.target.value));
    }
  }
};
</script>
