import '../css/app.css';
import './lib/echo';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Auxtech Accounts` : 'Auxtech Accounts'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: false });
        return pages[`./Pages/${name}.vue`]().then((m) => {
            const page = m.default;
            // Persistent layout unless the page opts out (e.g. auth screens).
            if (page.layout === undefined) page.layout = AppLayout;
            return page;
        });
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#3d6ef5', showSpinner: false },
});
