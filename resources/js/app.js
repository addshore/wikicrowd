require('./bootstrap');

import { createApp } from 'vue';
import HelloWorld from './components/HelloWorld.vue';
import GridMode from './components/GridMode.vue';

const app = createApp({});
app.component('hello-world', HelloWorld);
app.mount('#vue-hello-world');

const imageFocusApp = createApp({
    data() {
        return {
            gridMode: window.location.hash === '#grid'
        }
    },
    components: {
        GridMode
    }
});

imageFocusApp.mount('#image-focus-vue-root');
