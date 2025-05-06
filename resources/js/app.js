require('./bootstrap');

import { createApp } from 'vue';
import HelloWorld from './components/HelloWorld.vue';

const app = createApp({});
app.component('hello-world', HelloWorld);
app.mount('#vue-hello-world');
