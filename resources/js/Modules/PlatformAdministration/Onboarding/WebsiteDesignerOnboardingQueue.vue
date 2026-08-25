<script setup>
import {
    createDashboardNavigation,
    DashboardEmptyState,
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
    onboardingQueue: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const onboardingQueue = computed(() => props.onboardingQueue);
const hasFilters = computed(() =>
    Boolean(onboardingQueue.value.search.value || onboardingQueue.value.statusFilter.value),
);

function stageClass(value) {
    return value === 'Current' ? 'bg-lime-300 text-emerald-950' : 'bg-slate-100 text-slate-500';
}

function stageLabel(value) {
    return value === 'Current' ? 'Semasa' : 'Belum semasa';
}
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
        <section
            class="overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950 px-6 py-7 text-white shadow-sm sm:px-8"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                        Tugasan aktif
                    </p>
                    <h2 class="mt-3 text-2xl font-black sm:text-3xl">
                        Klinik di bawah jagaan anda
                    </h2>
                    <p class="mt-2 leading-7 text-emerald-50/80">
                        Utamakan klinik yang memerlukan tindakan dan teruskan kerja daripada
                        peringkat semasa.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                    <p class="text-xs text-emerald-50/65">Dipaparkan</p>
                    <p class="mt-1 text-3xl font-black text-lime-300">
                        {{ onboardingQueue.items.length }}
                    </p>
                </div>
            </div>
        </section>

        <form
            :action="onboardingQueue.search.action"
            method="get"
            class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)_auto] sm:items-end"
        >
            <label class="text-sm font-semibold text-slate-700">
                Cari tugasan klinik
                <input
                    name="search"
                    type="search"
                    :value="onboardingQueue.search.value"
                    :placeholder="onboardingQueue.search.placeholder"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                />
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Status tugasan
                <select
                    name="status"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <option value="">Semua status aktif</option>
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
                class="min-h-11 w-full rounded-xl bg-slate-950 px-5 py-2 font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:w-auto"
            >
                Tapis
            </button>
            <a
                v-if="hasFilters"
                :href="onboardingQueue.search.action"
                class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 font-bold text-slate-600 hover:bg-slate-100 sm:col-start-3"
            >
                Kosongkan penapis
            </a>
        </form>

        <section v-if="onboardingQueue.items.length" aria-label="Assigned onboarding jobs">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="job in onboardingQueue.items"
                    :key="job.id"
                    class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Klinik ditugaskan
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
                                Alamat website sedang disediakan
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
                                ['Kandungan', job.contentCollection],
                                ['Website', job.websiteSetup],
                                ['Semakan', job.review],
                                ['Sedia terbit', job.publishReadiness],
                            ]"
                            :key="stage[0]"
                        >
                            <dt class="font-semibold text-slate-500">{{ stage[0] }}</dt>
                            <dd class="mt-1">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="stageClass(stage[1])"
                                >
                                    {{ stageLabel(stage[1]) }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <details class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <summary class="cursor-pointer font-bold text-slate-700">
                            Rujukan teknikal
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
                        Ditugaskan <time :datetime="job.assignedAt">{{ job.assignedAtLabel }}</time>
                        · Dikemas kini
                        <time :datetime="job.updatedAt">{{ job.updatedAtLabel }}</time>
                    </p>
                    <a
                        :href="job.detailHref"
                        class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:w-auto"
                    >
                        Buka ruang kerja
                    </a>
                </article>
            </div>
        </section>

        <DashboardEmptyState
            v-else
            :title="hasFilters ? 'Tiada tugasan sepadan' : 'Belum ada tugasan aktif'"
            :description="
                hasFilters
                    ? 'Cuba ubah atau kosongkan penapis carian.'
                    : 'Tugasan onboarding baharu akan dipaparkan selepas assignment dibuat.'
            "
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
                Halaman seterusnya
            </a>
        </nav>
    </DashboardShell>
</template>
