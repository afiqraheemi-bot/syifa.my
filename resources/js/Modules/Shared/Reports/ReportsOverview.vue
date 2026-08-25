<script setup>
import { computed } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    report: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const metricLabels = {
    websitePublicationStatus: 'Status penerbitan website',
    bookingTotal: 'Jumlah tempahan',
    bookingsByStatus: 'Tempahan mengikut status',
    bookingsByService: 'Tempahan mengikut servis',
    subscriptionStatus: 'Status langganan',
    subscriptionStartsOn: 'Langganan bermula',
    subscriptionEndsOn: 'Langganan tamat',
    assignedTotal: 'Jumlah tugasan aktif',
    jobsByStatus: 'Tugasan mengikut status',
    tenantTotal: 'Jumlah tenant',
    activeTenants: 'Tenant aktif',
    publishedWebsites: 'Website diterbitkan',
    activeSubscriptions: 'Langganan aktif',
    bookingAdoptingTenants: 'Tenant menggunakan tempahan',
    openOnboardingJobs: 'Onboarding masih terbuka',
    registrationsByStatus: 'Pendaftaran mengikut status',
    websitesByStatus: 'Website mengikut status',
    subscriptionsByStatus: 'Langganan mengikut status',
    onboardingByStatus: 'Onboarding mengikut status',
};
const valueLabels = {
    active: 'Aktif',
    reactivated: 'Diaktifkan semula',
    renewal_due: 'Pembaharuan diperlukan',
    published: 'Diterbitkan',
    draft: 'Draf',
    confirmed: 'Disahkan',
    cancelled: 'Dibatalkan',
    completed: 'Selesai',
    pending: 'Menunggu',
    submitted: 'Dihantar',
    not_available: 'Belum tersedia',
    awaiting_confirmation: 'Menunggu pengesahan',
    in_progress: 'Sedang diproses',
};
const scopeLabels = {
    tenant: 'Klinik anda',
    designer_assignment: 'Tugasan aktif anda',
    platform_portfolio: 'Keseluruhan platform',
};

const scalarMetrics = computed(() =>
    Object.entries(props.report.metrics).filter(([, value]) => !isBreakdown(value)),
);
const breakdownMetrics = computed(() =>
    Object.entries(props.report.metrics).filter(([, value]) => isBreakdown(value)),
);

function words(value) {
    return String(value)
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replaceAll('_', ' ')
        .replace(/^\w/, (letter) => letter.toUpperCase());
}
function metricLabel(value) {
    return metricLabels[value] ?? words(value);
}
function valueLabel(value) {
    return valueLabels[value] ?? words(value);
}
function isBreakdown(value) {
    return typeof value === 'object' && value !== null;
}
function isDateMetric(key) {
    return key.endsWith('On') || key.endsWith('At');
}
function formatDate(value, includeTime = false) {
    if (!value) return 'Belum tersedia';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        ...(includeTime ? { timeStyle: 'short' } : {}),
        timeZone: 'Asia/Kuala_Lumpur',
    }).format(parsed);
}
function display(value, key = '') {
    if (value === null || value === undefined) return 'Belum tersedia';
    if (typeof value === 'number') return new Intl.NumberFormat('ms-MY').format(value);
    if (isDateMetric(key)) return formatDate(value);
    return valueLabel(String(value));
}
function breakdownTotal(value) {
    return Object.values(value).reduce((total, count) => total + Number(count || 0), 0);
}
function breakdownWidth(value, count) {
    const total = breakdownTotal(value);
    if (total === 0 || Number(count) === 0) return '0%';
    return `${Math.max(4, (Number(count) / total) * 100)}%`;
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
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                        Snapshot operasi
                    </p>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">
                        {{ scopeLabels[report.scope] ?? valueLabel(report.scope) }}
                    </h2>
                    <p class="mt-2 leading-7 text-emerald-50/80">
                        Data ini merangkumi keseluruhan rekod dalam skop anda dan dikira terus
                        daripada sistem operasi.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                    <p class="text-xs text-emerald-50/65">Dikemas kini</p>
                    <p class="mt-1 font-bold text-lime-300">
                        {{ formatDate(report.freshAt, true) }}
                    </p>
                    <p class="mt-1 text-xs text-emerald-50/60">Asia/Kuala Lumpur</p>
                </div>
            </div>
        </section>

        <section aria-label="Metrik utama">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-slate-950">Ringkasan utama</h2>
                <p class="mt-1 text-sm text-slate-600">Status dan jumlah semasa untuk skop ini.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="([key, value], index) in scalarMetrics"
                    :key="key"
                    class="rounded-2xl border bg-white p-5 shadow-sm"
                    :class="index === 0 ? 'border-emerald-200' : 'border-slate-200'"
                >
                    <h3 class="text-sm font-semibold text-slate-500">{{ metricLabel(key) }}</h3>
                    <p class="mt-3 text-2xl font-black text-slate-950">{{ display(value, key) }}</p>
                </article>
            </div>
        </section>

        <section v-if="breakdownMetrics.length" aria-label="Pecahan metrik">
            <div class="mb-4">
                <h2 class="text-xl font-bold text-slate-950">Pecahan prestasi</h2>
                <p class="mt-1 text-sm text-slate-600">Bandingkan agihan setiap kategori.</p>
            </div>
            <div class="grid items-start gap-5 xl:grid-cols-2">
                <article
                    v-for="[key, value] in breakdownMetrics"
                    :key="key"
                    class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-bold text-slate-950">{{ metricLabel(key) }}</h3>
                            <p class="mt-1 text-xs text-slate-500">Agihan keseluruhan rekod</p>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-3 py-1 text-sm font-bold text-slate-700"
                        >
                            {{ display(breakdownTotal(value)) }}
                        </span>
                    </div>
                    <div v-if="Object.keys(value).length" class="mt-5 space-y-4">
                        <div v-for="(nestedValue, nestedKey) in value" :key="nestedKey">
                            <div class="mb-1.5 flex justify-between gap-4 text-sm">
                                <span class="text-slate-600">{{ valueLabel(nestedKey) }}</span>
                                <strong class="text-slate-950">{{ display(nestedValue) }}</strong>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-emerald-700 to-lime-400"
                                    :style="{ width: breakdownWidth(value, nestedValue) }"
                                />
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                        Belum ada aktiviti dalam skop ini.
                    </p>
                </article>
            </div>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <details>
                <summary class="cursor-pointer list-none font-bold text-slate-950">
                    <span class="inline-flex items-center gap-2">
                        Definisi dan sumber metrik
                        <span class="text-sm font-normal text-slate-500"
                            >({{ report.definitions.length }})</span
                        >
                    </span>
                </summary>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Definisi dikawal dan mempunyai versi. Paparan laporan tidak mengubah rekod
                    transaksi asal.
                </p>
                <ul class="mt-4 grid gap-3 md:grid-cols-2">
                    <li
                        v-for="definition in report.definitions"
                        :key="definition.key"
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <p class="font-semibold text-slate-900">{{ definition.label }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Versi {{ definition.version }} · {{ valueLabel(definition.freshness) }}
                        </p>
                    </li>
                </ul>
            </details>
        </section>
    </DashboardShell>
</template>
