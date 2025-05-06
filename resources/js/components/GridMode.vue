<template>
  <div class="p-4">
    <h2 class="text-xl font-bold mb-4">Grid Mode Enabled</h2>
    <div class="grid grid-cols-2 gap-4">
      <div v-for="n in 4" :key="n" class="bg-gray-200 rounded p-4 text-center">
        <span>Image {{ n }}</span>
      </div>
    </div>
    <button class="mt-4 px-4 py-2 bg-gray-300 text-white rounded" @click="$emit('disable-grid')">Disable Grid Mode</button>
  </div>
</template>

<script>
export default {
  name: 'GridMode',
}
</script>

<style scoped>
</style>
