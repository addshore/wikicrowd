import { reactive } from 'vue';

const store = reactive({
  toasts: [],

  addToast({ message, type = 'info', duration = 5000 }) {
    const id = Date.now() + Math.random().toString(36).substring(2, 9);
    this.toasts.push({ id, message, type, duration });

    if (duration !== 0) { // Allow duration 0 for persistent toasts until manually closed
      setTimeout(() => {
        this.removeToast(id);
      }, duration);
    }
  },

  removeToast(id) {
    this.toasts = this.toasts.filter(toast => toast.id !== id);
  }
});

export const toastStore = store;

// Helper function for easy global access if needed, or can be imported directly
export function useToast() {
  return toastStore;
}

// Example usage (can be removed, just for illustration):
// import { toastStore } from './toastStore';
// toastStore.addToast({ message: 'Hello world!', type: 'success' });
// toastStore.addToast({ message: 'Something went wrong.', type: 'error' });
// toastStore.addToast({ message: 'Please check this.', type: 'warning', duration: 10000 });
// toastStore.addToast({ message: 'Just an info.', type: 'info' });
