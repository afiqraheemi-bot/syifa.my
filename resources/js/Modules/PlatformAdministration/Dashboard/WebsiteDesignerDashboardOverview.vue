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
    recentAssignments: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const summaries = createDashboardSummaries(props.summaries);
const quickActions = createDashboardQuickActions(props.quickActions);
const recentAssignments = createDashboardActivity(props.recentAssignments);
const summaryByKey = (key) => summaries.find((item) => item.key === key);
const queueUrl =
    quickActions.find((action) => action.key === 'view-assignments')?.href ??
    '/dashboard/onboarding';
const attentionUrl = `${queueUrl}${queueUrl.includes('?') ? '&' : '?'}status=needs_attention`;
const activeCount = computed(() => Number(summaryByKey('assigned-jobs')?.value) || 0);
const attentionCount = computed(
    () =>
        (Number(summaryByKey('review-revision')?.value) || 0) +
        (Number(summaryByKey('ready-publish')?.value) || 0),
);

const summaryMeta = {
    'assigned-jobs': { step: '01', accent: 'emerald' },
    'pending-content': { step: '02', accent: 'amber' },
    'website-setup': { step: '03', accent: 'sky' },
    'review-revision': { step: '04', accent: 'violet' },
    'ready-publish': { step: '05', accent: 'lime' },
    'completed-projects': { step: '06', accent: 'slate' },
};

const accentClasses = {
    emerald: 'bg-emerald-100 text-emerald-700',
    amber: 'bg-amber-100 text-amber-700',
    sky: 'bg-sky-100 text-sky-700',
    violet: 'bg-violet-100 text-violet-700',
    lime: 'bg-lime-200 text-emerald-900',
    slate: 'bg-slate-100 text-slate-600',
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
                aria-labelledby="designer-welcome-title"
            >
                <div
                    class="absolute -right-16 -top-20 -z-10 size-72 rounded-full border-[60px] border-emerald-800/25"
                />
                <div class="grid items-end gap-8 lg:grid-cols-[1fr_auto]">
                    <div>
                        <div
                            class="flex items-center gap-2 text-xs font-black tracking-[0.16em] text-lime-300 uppercase"
                        >
                            <span class="size-2 rounded-full bg-lime-300 ring-4 ring-lime-300/10" />
                            Designer workspace
                        </div>
                        <h1
                            id="designer-welcome-title"
                            class="mt-5 max-w-3xl text-3xl font-black tracking-[-0.04em] sm:text-4xl"
                        >
                            {{ welcomeTitle }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                            {{ welcomeMessage }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:min-w-72">
                        <div class="rounded-2xl bg-white p-4 text-slate-950">
                            <p class="text-xs font-bold text-slate-500">Active projects</p>
                            <p class="mt-1 text-3xl font-black tracking-tight">{{ activeCount }}</p>
                        </div>
                        <a
                            :href="attentionUrl"
                            class="rounded-2xl bg-lime-300 p-4 text-emerald-950 transition hover:-translate-y-0.5 hover:bg-lime-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            <p class="text-xs font-bold text-emerald-900/70">Needs attention</p>
                            <p class="mt-1 text-3xl font-black tracking-tight">
                                {{ attentionCount }}
                            </p>
                            <span class="mt-1 block text-[11px] font-bold">Open full queue →</span>
                        </a>
                    </div>
                </div>
            </section>

            <section
                aria-label="Website Designer assignment overview"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="summary in summaries"
                    :key="summary.key"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-950/5 sm:p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <span
                            :class="[
                                'flex size-10 items-center justify-center rounded-xl text-xs font-black',
                                accentClasses[summaryMeta[summary.key]?.accent] ??
                                    accentClasses.slate,
                            ]"
                            >{{ summaryMeta[summary.key]?.step }}</span
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
                    title="Designer actions"
                    eyebrow="Work shortcuts"
                />
                <DashboardRecentActivity
                    :activity="recentAssignments"
                    title="Recent assignments"
                    eyebrow="Assignment feed"
                    empty-title="No recent assignments"
                    empty-description="New onboarding assignments will appear here."
                />
            </div>
        </div>
    </DashboardShell>
</template>
