<template>
  <label class="flex items-center ml-4 cursor-pointer select-none">
    <input 
      type="checkbox" 
      :checked="autoSave" 
      @change="updateAutoSave"
      class="mr-1" 
    />
    Auto Save
    <span class="ml-2 flex items-center">
      after
      <input
        type="number"
        min="1"
        :value="autoSaveDelay"
        @input="updateAutoSaveDelay"
        class="mx-1 w-12 px-1 py-0.5 border border-gray-300 rounded text-center text-sm"
        style="width:3.5em;"
      />
      seconds
    </span>
  </label>
</template>

<script>
export default {
  name: 'AutoSaveSettings',
  props: {
    autoSave: {
      type: Boolean,
      required: true
    },
    autoSaveDelay: {
      type: Number,
      required: true
    }
  },
  emits: ['update:autoSave', 'update:autoSaveDelay'],
  methods: {
    updateAutoSave(event) {
      this.$emit('update:autoSave', event.target.checked);
    },
    updateAutoSaveDelay(event) {
      this.$emit('update:autoSaveDelay', parseInt(event.target.value));
    }
  }
};
</script>
