<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DashboardBreadcrumb from './DashboardBreadcrumb.vue';
import DashboardPageHeader from './DashboardPageHeader.vue';
import DashboardLogoutAction from './DashboardLogoutAction.vue';
import DashboardSidebar from './DashboardSidebar.vue';
import DashboardTopNavigation from './DashboardTopNavigation.vue';

const props = defineProps({
    navigation: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        required: true,
    },
    pageDescription: {
        type: String,
        default: null,
    },
    pageEyebrow: {
        type: String,
        default: null,
    },
    identityName: {
        type: String,
        default: null,
    },
    contextLabel: {
        type: String,
        default: null,
    },
    productName: {
        type: String,
        default: 'SYIFA.my',
    },
});

const collapsed = ref(false);
const mobileOpen = ref(false);
const operations = computed(() => usePage().props.dashboardOperations ?? null);
const shellNavigation = computed(
    () => usePage().props.globalDashboardNavigation ?? props.navigation,
);
const pendingJobs = computed(() => Math.max(0, Number(operations.value?.pending_count) || 0));
const recentPendingJobs = computed(() => operations.value?.items ?? []);
const pendingJobsCount = computed(() =>
    pendingJobs.value > 99 ? '99+' : String(pendingJobs.value),
);
const pendingJobsLabel = computed(() =>
    pendingJobs.value === 1
        ? `1 ${operations.value?.singular_label ?? 'task is waiting'}`
        : `${pendingJobs.value} ${operations.value?.plural_label ?? 'tasks are waiting'}`,
);

function statusLabel(status) {
    return String(status ?? 'pending')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <a
            href="#dashboard-content"
            class="sr-only z-[60] rounded-md bg-white px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-600"
        >
            Skip to dashboard content
        </a>

        <DashboardSidebar
            :navigation="shellNavigation"
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            :product-name="productName"
            @close-mobile="mobileOpen = false"
            @toggle-collapse="collapsed = !collapsed"
        />

        <div
            :class="[
                'min-h-screen transition-[padding] duration-200',
                collapsed ? 'lg:pl-20' : 'lg:pl-72',
            ]"
        >
            <DashboardTopNavigation
                :identity-name="identityName"
                :context-label="contextLabel"
                :navigation-open="mobileOpen"
                @open-navigation="mobileOpen = true"
            >
                <template #actions>
                    <details v-if="pendingJobs > 0" class="group/pending relative">
                        <summary
                            class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2.5 rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-2.5 py-1.5 text-amber-950 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 sm:px-3 [&::-webkit-details-marker]:hidden"
                            :aria-label="`${pendingJobsLabel}. Show pending work list.`"
                        >
                            <span
                                class="relative flex size-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 5.5h6M9 9.5h6M9 13.5h3M7 3h10a2 2 0 012 2v14l-3-2-4 2-4-2-3 2V5a2 2 0 012-2z"
                                    />
                                </svg>
                                <span
                                    class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-orange-500 ring-2 ring-amber-50"
                                />
                            </span>
                            <span class="hidden min-w-0 text-left sm:block">
                                <span
                                    class="block text-[10px] font-black tracking-[0.12em] text-amber-700 uppercase"
                                    >Action required</span
                                >
                                <span class="block truncate text-xs font-bold">{{
                                    pendingJobsLabel
                                }}</span>
                            </span>
                            <span
                                class="inline-flex min-w-7 items-center justify-center rounded-full bg-amber-700 px-2 py-1 text-xs font-black text-white shadow-sm"
                                aria-hidden="true"
                                >{{ pendingJobsCount }}</span
                            >
                            <svg
                                viewBox="0 0 24 24"
                                class="hidden size-4 fill-none stroke-current stroke-2 transition group-open/pending:rotate-180 lg:block"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 9l6 6 6-6"
                                />
                            </svg>
                        </summary>

                        <div
                            class="absolute right-0 z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                        >
                            <div class="border-b border-slate-100 px-4 py-3.5">
                                <p class="text-sm font-black text-slate-950">
                                    {{ operations.heading }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ operations.description }}
                                </p>
                            </div>
                            <ul
                                v-if="recentPendingJobs.length"
                                class="max-h-80 divide-y divide-slate-100 overflow-y-auto"
                            >
                                <li v-for="job in recentPendingJobs" :key="job.id">
                                    <a
                                        :href="job.url"
                                        class="group/job flex items-center gap-3 px-4 py-3 transition hover:bg-amber-50 focus-visible:bg-amber-50 focus-visible:outline-none"
                                    >
                                        <span
                                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-black text-slate-700 group-hover/job:bg-amber-100 group-hover/job:text-amber-800"
                                            aria-hidden="true"
                                        >
                                            {{ job.clinic_name?.charAt(0)?.toUpperCase() || 'C' }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block truncate text-sm font-bold text-slate-900"
                                                >{{ job.clinic_name }}</span
                                            >
                                            <span
                                                class="mt-0.5 block text-xs font-semibold text-amber-700"
                                                >{{
                                                    job.pending_tasks
                                                        ? `${job.pending_tasks} ${job.pending_tasks === 1 ? 'task' : 'tasks'} · ${statusLabel(job.status)}`
                                                        : statusLabel(job.status)
                                                }}</span
                                            >
                                        </span>
                                        <svg
                                            viewBox="0 0 24 24"
                                            class="size-4 shrink-0 fill-none stroke-slate-400 stroke-2 transition group-hover/job:translate-x-0.5 group-hover/job:stroke-amber-700"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                            <p v-else class="px-4 py-5 text-center text-sm text-slate-500">
                                {{ operations.empty_label }}
                            </p>
                            <a
                                :href="operations.all_url"
                                class="flex min-h-11 items-center justify-center border-t border-slate-100 px-4 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50"
                            >
                                {{ operations.all_label }}
                            </a>
                        </div>
                    </details>
                    <slot name="top-actions" />
                    <DashboardLogoutAction />
                </template>
            </DashboardTopNavigation>

            <main
                id="dashboard-content"
                tabindex="-1"
                class="px-4 py-6 focus:outline-none sm:px-6 sm:py-8 lg:px-8"
            >
                <div class="mx-auto max-w-screen-2xl space-y-6 sm:space-y-8">
                    <DashboardBreadcrumb :items="breadcrumbs" />
                    <DashboardPageHeader
                        :title="pageTitle"
                        :description="pageDescription"
                        :eyebrow="pageEyebrow"
                    >
                        <template #actions><slot name="page-actions" /></template>
                    </DashboardPageHeader>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
