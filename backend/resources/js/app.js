import './bootstrap';
import { createApp } from 'vue';
import App from './components/KioskApp.vue'; // Komponen utama kita nanti

const app = createApp(App);
app.mount('#app');