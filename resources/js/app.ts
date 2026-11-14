import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

// F-42 (Slice 3): Markenname fest statt VITE_APP_NAME (Fallback war "Laravel").
// Seitentitel kommen aus <Head> (zentral in AppLayout bzw. auf den Auth-Seiten).
const appName = 'PlantMel';

createInertiaApp({
    // "Seitentitel – PlantMel"; ohne Titel nur "PlantMel". Enthaelt der Titel die
    // Marke bereits (z. B. "Willkommen bei PlantMel"), wird sie nicht doppelt angehaengt.
    title: (title) => {
        const pageTitle = title?.trim();
        if (!pageTitle) return appName;
        return pageTitle.includes(appName)
            ? pageTitle
            : `${pageTitle} – ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)

            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
