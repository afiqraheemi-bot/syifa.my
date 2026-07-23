<script setup>
import {
    createDashboardNavigation,
    createDashboardQuickActions,
    DashboardQuickActions,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    job: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const actions = createDashboardQuickActions(props.job.actions);
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
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Assigned onboarding job
                    </p>
                    <h2 class="mt-1 break-all text-lg font-bold text-slate-950">{{ job.id }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Tenant {{ job.tenantId }} · Website {{ job.websiteId }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-800"
                >
                    {{ job.statusLabel }}
                </span>
            </div>

            <div class="mt-6" aria-labelledby="job-progress-label">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <h3 id="job-progress-label" class="font-bold text-slate-900">Progress</h3>
                    <span class="font-semibold text-slate-600">{{ job.progress.label }}</span>
                </div>
                <div
                    class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200"
                    role="progressbar"
                    aria-label="Onboarding progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="job.progress.value"
                >
                    <div
                        class="h-full rounded-full bg-emerald-600"
                        :style="{ width: `${job.progress.value}%` }"
                    />
                </div>
            </div>
        </section>

        <section aria-labelledby="workflow-stages-title">
            <h2 id="workflow-stages-title" class="text-lg font-bold text-slate-950">
                Operational workflow
            </h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stage in job.stages"
                    :key="stage.key"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="font-bold text-slate-950">{{ stage.label }}</h3>
                    <p
                        :class="[
                            'mt-3 text-sm font-semibold',
                            stage.state === 'current' ? 'text-emerald-700' : 'text-slate-500',
                        ]"
                    >
                        {{ stage.stateLabel }}
                    </p>
                </article>
            </div>
        </section>

        <section aria-labelledby="timeline-title">
            <h2 id="timeline-title" class="text-lg font-bold text-slate-950">Timeline</h2>
            <ol class="mt-4 space-y-3 border-l-2 border-slate-200 pl-5">
                <li v-for="event in job.timeline" :key="event.key" class="relative">
                    <span
                        class="absolute -left-[1.7rem] top-1.5 size-3 rounded-full bg-emerald-600 ring-4 ring-white"
                        aria-hidden="true"
                    />
                    <p class="font-semibold text-slate-900">{{ event.title }}</p>
                    <time :datetime="event.occurredAt" class="text-sm text-slate-600">
                        {{ event.occurredAtLabel }}
                    </time>
                </li>
            </ol>
        </section>

        <DashboardQuickActions :actions="actions" />
    </DashboardShell>
</template>
