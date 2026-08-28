import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { createDashboardI18n } from './i18n';

const pages = import.meta.glob('./Modules/**/*.vue');

createInertiaApp({
    resolve: (name) => {
        const page = pages[`./Modules/${name}.vue`];

        if (!page) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        const i18n = createDashboardI18n(props.initialPage.props.locale);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
});
