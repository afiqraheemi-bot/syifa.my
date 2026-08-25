<script setup>
import {
    createDashboardActivity,
    createDashboardNavigation,
    createDashboardQuickActions,
    createDashboardSummaries,
    DashboardQuickActions,
    DashboardRecentActivity,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';
import { computed } from 'vue';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    welcomeTitle: { type: String, required: true },
    welcomeMessage: { type: String, required: true },
    summaries: { type: Array, required: true },
    quickActions: { type: Array, required: true },
    recentActivity: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const summaries = createDashboardSummaries(props.summaries);
const quickActions = createDashboardQuickActions(props.quickActions);
const recentActivity = createDashboardActivity(props.recentActivity);
const platformHealth = computed(() => summaries.find((item) => item.key === 'health'));
const platformHealthy = computed(() => platformHealth.value?.tone === 'positive');
const metrics = computed(() => summaries.filter((item) => item.key !== 'health'));
const metricMeta = {
    tenants: { label: 'TEN', color: 'bg-emerald-100 text-emerald-700' },
    subscriptions: { label: 'SUB', color: 'bg-sky-100 text-sky-700' },
    designers: { label: 'DES', color: 'bg-violet-100 text-violet-700' },
    onboarding: { label: 'ONB', color: 'bg-amber-100 text-amber-700' },
    websites: { label: 'WEB', color: 'bg-lime-200 text-emerald-900' },
    bookings: { label: 'BKG', color: 'bg-rose-100 text-rose-700' },
};
</script>

<template>
    <DashboardShell
        :navigation="navigation"
        :breadcrumbs="breadcrumbs"
        :page-title="pageTitle"
        :page-description="pageDescription"
        :identity-name="identityName"
        :context-label="contextLabel"
    >
        <div class="space-y-7">
            <section
                class="relative isolate overflow-hidden rounded-[1.75rem] bg-slate-950 px-6 py-7 text-white shadow-xl shadow-slate-950/10 sm:px-9 sm:py-9"
                aria-labelledby="admin-welcome-title"
            >
                <div
                    class="absolute -right-20 -top-24 -z-10 size-80 rounded-full border-[70px] border-violet-800/20"
                />
                <div class="grid items-end gap-8 lg:grid-cols-[1fr_auto]">
                    <div>
                        <p class="text-xs font-black tracking-[0.16em] text-violet-300 uppercase">
                            Platform command centre
                        </p>
                        <h2
                            id="admin-welcome-title"
                            class="mt-5 max-w-3xl text-3xl font-black tracking-[-0.04em] sm:text-4xl"
                        >
                            {{ welcomeTitle }}
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                            {{ welcomeMessage }}
                        </p>
                    </div>
                    <div
                        :class="[
                            'min-w-64 rounded-2xl border p-4 backdrop-blur',
                            platformHealthy
                                ? 'border-emerald-400/20 bg-emerald-400/10'
                                : 'border-rose-400/30 bg-rose-400/10',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'flex size-10 items-center justify-center rounded-xl',
                                    platformHealthy
                                        ? 'bg-emerald-400/15 text-emerald-300'
                                        : 'bg-rose-400/15 text-rose-300',
                                ]"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 13h3l2-6 4 12 2-6h5"
                                    />
                                </svg>
                            </span>
                            <div>
                                <p
                                    class="text-[10px] font-black tracking-[0.14em] text-slate-400 uppercase"
                                >
                                    System status
                                </p>
                                <p class="mt-0.5 text-base font-black">
                                    {{ platformHealth?.value ?? 'Unknown' }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-400">
                            {{ platformHealth?.detail }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                aria-label="Platform overview"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="summary in metrics"
                    :key="summary.key"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-lg hover:shadow-slate-950/5 sm:p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <span
                            :class="[
                                'flex h-9 min-w-11 items-center justify-center rounded-xl px-2 text-[10px] font-black tracking-wide',
                                metricMeta[summary.key]?.color ?? 'bg-slate-100 text-slate-600',
                            ]"
                            >{{ metricMeta[summary.key]?.label }}</span
                        >
                        <span class="text-3xl font-black tracking-[-0.04em] text-slate-950">{{
                            summary.value
                        }}</span>
                    </div>
                    <h2 class="mt-5 font-bold text-slate-950">{{ summary.label }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ summary.detail }}</p>
                </article>
            </section>

            <div class="grid items-start gap-7 xl:grid-cols-[1.05fr_0.95fr]">
                <DashboardQuickActions
                    :actions="quickActions"
                    title="Platform controls"
                    eyebrow="Administration"
                />
                <DashboardRecentActivity
                    :activity="recentActivity"
                    title="Recent platform activity"
                    eyebrow="Audit trail"
                    empty-title="No recent platform activity"
                    empty-description="Platform audit activity will appear here."
                />
            </div>
        </div>
    </DashboardShell>
</template>
