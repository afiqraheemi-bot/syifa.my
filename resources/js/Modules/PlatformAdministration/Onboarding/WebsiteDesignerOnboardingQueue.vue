<script setup>
import {
    createDashboardNavigation,
    DashboardEmptyState,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    onboardingQueue: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
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
        <form
            :action="onboardingQueue.search.action"
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)_auto] sm:items-end"
        >
            <label class="text-sm font-semibold text-slate-700">
                Search assigned jobs
                <input
                    name="search"
                    type="search"
                    :value="onboardingQueue.search.value"
                    :placeholder="onboardingQueue.search.placeholder"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                />
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Status
                <select
                    name="status"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <option value="">All active statuses</option>
                    <option
                        v-for="option in onboardingQueue.statusFilter.options"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === onboardingQueue.statusFilter.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <button
                type="submit"
                class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Apply
            </button>
        </form>

        <section v-if="onboardingQueue.items.length" aria-label="Assigned onboarding jobs">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="job in onboardingQueue.items"
                    :key="job.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Assigned clinic
                            </p>
                            <h2 class="mt-1 text-xl font-bold text-slate-950">
                                {{ job.clinicName }}
                            </h2>
                            <a
                                v-if="job.publicUrl"
                                :href="job.publicUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-2 inline-flex break-all text-sm font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900"
                            >
                                {{ job.publicHost }} ↗
                            </a>
                            <p v-else class="mt-2 text-sm text-slate-500">
                                Website address is being prepared
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800"
                        >
                            {{ job.statusLabel }}
                        </span>
                    </div>

                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div
                            v-for="stage in [
                                ['Content collection', job.contentCollection],
                                ['Website setup', job.websiteSetup],
                                ['Review', job.review],
                                ['Publish readiness', job.publishReadiness],
                            ]"
                            :key="stage[0]"
                        >
                            <dt class="font-semibold text-slate-500">{{ stage[0] }}</dt>
                            <dd class="mt-1 text-slate-900">{{ stage[1] }}</dd>
                        </div>
                    </dl>

                    <details class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <summary class="cursor-pointer font-bold text-slate-700">
                            Technical references
                        </summary>
                        <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold text-slate-500">Job</dt>
                                <dd :title="job.id" class="mt-1 font-mono text-xs text-slate-800">
                                    {{ job.jobReference }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-slate-500">Tenant</dt>
                                <dd
                                    :title="job.tenantId"
                                    class="mt-1 font-mono text-xs text-slate-800"
                                >
                                    {{ job.tenantReference }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-slate-500">Website</dt>
                                <dd
                                    :title="job.websiteId"
                                    class="mt-1 font-mono text-xs text-slate-800"
                                >
                                    {{ job.websiteReference }}
                                </dd>
                            </div>
                        </dl>
                    </details>

                    <p class="mt-5 text-xs text-slate-500">
                        Assigned <time :datetime="job.assignedAt">{{ job.assignedAtLabel }}</time> ·
                        Updated <time :datetime="job.updatedAt">{{ job.updatedAtLabel }}</time>
                    </p>
                    <a
                        :href="job.detailHref"
                        class="mt-5 inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        View job
                    </a>
                </article>
            </div>
        </section>

        <DashboardEmptyState
            v-else
            title="No assigned jobs found"
            description="Try adjusting the search or status filter."
        />

        <nav
            v-if="onboardingQueue.pagination.hasMore"
            class="flex justify-end"
            aria-label="Onboarding queue pagination"
        >
            <a
                :href="onboardingQueue.pagination.nextHref"
                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Next page
            </a>
        </nav>
    </DashboardShell>
</template>
