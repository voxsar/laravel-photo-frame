import './bootstrap';
import { createApp } from 'vue';
import PhotoFrameApp from './components/PhotoFrameApp.vue';

const el = document.getElementById('photo-frame-app');
if (el) {
    createApp(PhotoFrameApp).mount(el);
}
