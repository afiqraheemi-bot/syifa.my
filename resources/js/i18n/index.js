import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import ms from './locales/ms.json';

export function createDashboardI18n(initialLocale) {
    return createI18n({
        legacy: false,
        locale: ['en', 'ms'].includes(initialLocale) ? initialLocale : 'en',
        fallbackLocale: 'en',
        messages: { en, ms },
    });
}
