// resources/js/app.js

// 1. Iniciar Alpine.js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// 2. Cargar Bootstrap (Axios, Echo)
import './bootstrap';

// 3. Cargar el CSS principal
import '../css/app.css';

// 4. Iniciar la aplicación principal de Inertia/Vue
import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m.js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    
    // --- ESTA ES LA LÍNEA ORIGINAL Y CORRECTA ---
    // Le dice a Inertia que busque SÓLO en la carpeta ./Pages/
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    // --- FIN DE LA LÍNEA ---

    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue) 
            .component('Link', Link)
            .component('Head', Head)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});