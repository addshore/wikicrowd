<template>
  <div class="fixed top-16 right-4 z-40 w-full max-w-xs sm:max-w-sm">
    <transition-group name="toast-fade" tag="div" class="space-y-2">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="p-4 rounded-md shadow-lg text-sm relative"
        :class="{
          'bg-green-500 text-white': toast.type === 'success',
          'bg-red-500 text-white': toast.type === 'error',
          'bg-yellow-400 text-gray-800': toast.type === 'warning',
          'bg-blue-500 text-white': toast.type === 'info',
          'bg-gray-700 text-white': !toast.type || toast.type === 'default',
        }"
      >
        <button
          @click="removeToast(toast.id)"
          class="absolute top-1 right-1 p-1 text-xs font-bold leading-none rounded-full hover:bg-opacity-20 hover:bg-black focus:outline-none"
          :class="{
            'text-green-100 hover:text-green-50': toast.type === 'success',
            'text-red-100 hover:text-red-50': toast.type === 'error',
            'text-yellow-700 hover:text-yellow-900': toast.type === 'warning',
            'text-blue-100 hover:text-blue-50': toast.type === 'info',
            'text-gray-100 hover:text-gray-50': !toast.type || toast.type === 'default',
          }"
          aria-label="Close"
        >
          &#x2715; <!-- '✕' character -->
        </button>
        <p class="pr-4" v-html="toast.message"></p>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { toastStore } from '../toastStore'; // Ensure this path is correct

const toasts = computed(() => toastStore.toasts);

const removeToast = (id) => {
  toastStore.removeToast(id);
};
</script>

<style scoped>
/* Transitions for toasts */
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.5s ease;
}
.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
.toast-fade-move {
  transition: transform 0.5s ease;
}

/* Fallback basic styling if Tailwind is not fully available or for overrides */
/* The Tailwind classes above should handle most of this. */
.fixed { position: fixed; }
.top-4 { top: 1rem; }
.right-4 { right: 1rem; }
.z-\[1000\] { z-index: 1000; } /* Example of escaping for Tailwind JIT if needed, though direct values are fine in <style> */
.w-full { width: 100%; }
.max-w-xs { max-width: 20rem; } /* Approx 320px */
@media (min-width: 640px) { /* sm breakpoint */
  .sm\:max-w-sm { max-width: 24rem; } /* Approx 384px */
}
.space-y-2 > :not([hidden]) ~ :not([hidden]) {
  --tw-space-y-reverse: 0;
  margin-top: calc(0.5rem * calc(1 - var(--tw-space-y-reverse)));
  margin-bottom: calc(0.5rem * var(--tw-space-y-reverse));
}

/* Individual toast styling (largely handled by Tailwind classes in template) */
.toast-item { /* Placeholder if not using Tailwind */
  padding: 1rem;
  border-radius: 0.375rem; /* 6px */
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
  color: white;
  font-size: 0.875rem; /* 14px */
  position: relative;
}
.toast-item.success { background-color: #48bb78; } /* Green */
.toast-item.error { background-color: #f56565; } /* Red */
.toast-item.warning { background-color: #f6e05e; color: #2d3748; } /* Yellow, dark text */
.toast-item.info { background-color: #4299e1; } /* Blue */
.toast-item.default { background-color: #4a5568; } /* Gray */

.close-button { /* Placeholder */
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  padding: 0.25rem;
  font-size: 0.75rem; /* 12px */
  font-weight: bold;
  line-height: 1;
  border-radius: 9999px; /* full */
}
.close-button:hover {
  background-color: rgba(0,0,0,0.2);
}
</style>
