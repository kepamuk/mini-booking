import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
import ToastService from 'primevue/toastservice';
import '@fontsource-variable/manrope';
import '../css/app.css';
import App from './App.vue';

const BookingTheme = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#f3f7ed',
      100: '#e5edda',
      200: '#d5e6b9',
      300: '#b9d58e',
      400: '#94b866',
      500: '#466b4b',
      600: '#36563e',
      700: '#294633',
      800: '#233a31',
      900: '#1b2e26',
      950: '#101e17',
    },
  },
});

createApp(App)
  .use(PrimeVue, {
    theme: { preset: BookingTheme, options: { darkModeSelector: false } },
  })
  .use(ToastService)
  .mount('#app');
