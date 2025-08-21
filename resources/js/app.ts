import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, App as VueApp } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { createHead } from '@unhead/vue';
import { plugin as formkitPlugin, defaultConfig } from '@formkit/vue';

import { initializeTheme } from './composables/useAppearance';

const appName: string = import.meta.env.VITE_APP_NAME || 'Laravel';

// i18n
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: {
    en: { hello: 'Hello' },
    bn: { hello: 'স্বাগতম' },
  },
});

// Head instance
const head = createHead();

// Inertia setup
createInertiaApp({
  title: (title?: string) => (title ? `${title} - ${appName}` : appName),
  resolve: (name: string) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue')
    ),
  setup({ el, App: InertiaApp, props, plugin }) {
    const pinia = createPinia();

    createApp({
      render: () => h(InertiaApp, props) // ✅ Fixed! Removed problematic head wrapper
    })
      .use(plugin as Parameters<VueApp['use']>[0]) // Inertia plugin
      .use(pinia)
      .use(i18n)
      .use(ZiggyVue)
      // .use(head) // ✅ Temporarily disabled until needed
      .use(formkitPlugin, defaultConfig()) // ✅ FormKit usage
      .mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});

// Apply dark/light theme
initializeTheme();