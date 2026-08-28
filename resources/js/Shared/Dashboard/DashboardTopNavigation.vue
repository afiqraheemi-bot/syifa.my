<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    identityName: {
        type: String,
        default: null,
    },
    contextLabel: {
        type: String,
        default: null,
    },
    navigationOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['open-navigation']);
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex min-h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button
                type="button"
                class="inline-flex size-11 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 lg:hidden"
                :aria-label="t('common.openNavigation')"
                aria-controls="dashboard-navigation"
                :aria-expanded="navigationOpen"
                @click="$emit('open-navigation')"
            >
                <svg
                    aria-hidden="true"
                    class="size-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <p v-if="contextLabel" class="truncate text-sm font-semibold text-slate-900">
                    {{ contextLabel }}
                </p>
            </div>

            <slot name="actions" />

            <div
                v-if="identityName"
                class="hidden min-w-0 items-center gap-3 border-l border-slate-200 pl-4 sm:flex"
            >
                <span
                    aria-hidden="true"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-800"
                >
                    {{ identityName.charAt(0).toUpperCase() }}
                </span>
                <span class="max-w-44 truncate text-sm font-semibold text-slate-800">{{
                    identityName
                }}</span>
            </div>
        </div>
    </header>
</template>
