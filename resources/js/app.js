require('./bootstrap');

import { createApp } from 'vue';
import GridMode from './components/GridMode.vue';
import DepictsGroupsFromYaml from './components/DepictsGroupsFromYaml.vue';
import DepictsGroupsPage from './components/DepictsGroupsPage.vue';
import CustomDepictsGrid from './components/CustomDepictsGrid.vue';
import ToastNotification from './components/ToastNotification.vue';
import OfflineModeManager from './components/OfflineModeManager.vue';

console.log('Vue app is starting...');

if (document.getElementById('image-focus-vue-root')) {
  const props = {};
  if (window.offlineQuestions) {
    props.questions = window.offlineQuestions;
  }
  const gridApp = createApp(GridMode, props);
  gridApp.mount('#image-focus-vue-root');
}

if (document.getElementById('depicts-groups-from-yaml')) {
  const depictsGroupsFromYamlApp = createApp(DepictsGroupsFromYaml);
  depictsGroupsFromYamlApp.mount('#depicts-groups-from-yaml');
}

if (document.getElementById('depicts-groups-vue-root')) {
  const depictsGroupsPageApp = createApp(DepictsGroupsPage);
  depictsGroupsPageApp.mount('#depicts-groups-vue-root');
}

if (document.getElementById('custom-depicts-grid-vue-root')) {
  const customGridApp = createApp(CustomDepictsGrid);
  customGridApp.mount('#custom-depicts-grid-vue-root');
}

if (document.getElementById('offline-mode-manager-root')) {
    const offlineModeManagerApp = createApp(OfflineModeManager);
    offlineModeManagerApp.mount('#offline-mode-manager-root');
}

// Mount the ToastNotification component
// This requires a <div id="toast-notifications"></div> in the main layout file.
if (document.getElementById('toast-notifications')) {
  const toastApp = createApp(ToastNotification);
  toastApp.mount('#toast-notifications');
} else {
  // Optional: Create the div if it doesn't exist, then mount.
  // This is a fallback if modifying the Blade template isn't immediately possible.
  // However, it's better to have the div present in the initial HTML.
  let toastDiv = document.createElement('div');
  toastDiv.id = 'toast-notifications';
  document.body.appendChild(toastDiv);
  const toastApp = createApp(ToastNotification);
  toastApp.mount('#toast-notifications');
  console.log('ToastNotification div created and component mounted.');
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(registration => {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }, err => {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}
