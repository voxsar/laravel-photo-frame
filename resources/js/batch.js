import './bootstrap';
import { createApp } from 'vue';
import BatchFrameApp from './components/BatchFrameApp.vue';

const el = document.getElementById('batch-frame-app');
if (el) {
    createApp(BatchFrameApp).mount(el);
}
