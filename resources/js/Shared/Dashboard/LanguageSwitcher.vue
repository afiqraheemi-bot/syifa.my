<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const { t } = useI18n();
const page = usePage();
const currentLocale = computed(() => page.props.locale);
const submitting = ref(false);

function select(nextLocale) {
    if (submitting.value || nextLocale === currentLocale.value) return;

    submitting.value = true;
    router.patch(
        '/dashboard/preferences/locale',
        { locale: nextLocale },
        {
            preserveScroll: true,
            onSuccess: () => {
                // A full reload guarantees every translated string on the
                // page (not just this switcher) reflects the new language,
                // without depending on client-side i18n scope reactivity.
                window.location.reload();
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        class="flex items-center gap-1 rounded-lg border border-slate-300 bg-white p-1"
        :aria-label="t('common.language')"
    >
        <button
            v-for="option in ['en', 'ms']"
            :key="option"
            type="button"
            :disabled="submitting"
            :aria-pressed="currentLocale === option"
            class="min-h-9 rounded-md px-2.5 text-xs font-semibold transition disabled:cursor-wait disabled:opacity-60"
            :class="
                currentLocale === option
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
            "
            @click="select(option)"
        >
            {{ option.toUpperCase() }}
        </button>
    </div>
</template>
