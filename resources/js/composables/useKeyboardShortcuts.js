import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Composable for handling keyboard shortcuts
 * @param {Object} config - Configuration object for shortcuts
 * @param {Object} config.answerMode - Reactive reference to answer mode
 * @param {boolean} config.disabled - Whether shortcuts are disabled (e.g., when fullscreen is active)
 * @param {Object} config.shortcuts - Object mapping keys to answer modes
 * @returns {Object} Composable object with setup and cleanup methods
 */
export function useKeyboardShortcuts({ answerMode, disabled = ref(false), shortcuts = {} }) {
  const defaultShortcuts = {
    'q': 'yes-preferred',
    '1': 'yes',
    '2': 'no',
    'e': 'skip',
    ...shortcuts
  };

  let keydownHandler = null;

  const setup = () => {
    keydownHandler = (e) => {
      // Skip if disabled, typing in input/textarea, or shortcuts are disabled
      if (disabled.value || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
      }

      const key = e.key.toLowerCase();
      const shortcut = defaultShortcuts[key] || defaultShortcuts[e.key];
      
      if (shortcut && answerMode) {
        answerMode.value = shortcut;
        e.preventDefault(); // Prevent default browser behavior
      }
    };

    window.addEventListener('keydown', keydownHandler);
  };

  const cleanup = () => {
    if (keydownHandler) {
      window.removeEventListener('keydown', keydownHandler);
      keydownHandler = null;
    }
  };

  // Auto-setup and cleanup
  onMounted(setup);
  onUnmounted(cleanup);

  return {
    setup,
    cleanup
  };
}
