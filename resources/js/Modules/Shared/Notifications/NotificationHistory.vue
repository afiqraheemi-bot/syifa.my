<script setup>
import { computed } from 'vue';
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
    notificationHistory: { type: Object, required: true },
    filters: { type: Object, required: true },
    canFilterTenant: { type: Boolean, required: true },
    indexUrl: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const entries = computed(() => props.notificationHistory.entries);
const hasFilters = computed(() =>
    Boolean(props.filters.status || props.filters.triggerType || props.filters.tenantId),
);
const deliveredCount = computed(
    () => entries.value.filter((entry) => ['sent', 'delivered'].includes(entry.status)).length,
);
const attentionCount = computed(
    () =>
        entries.value.filter((entry) => ['delayed', 'failed', 'exhausted'].includes(entry.status))
            .length,
);

const statusLabels = {
    prepared: 'Disediakan',
    queued: 'Dalam giliran',
    sent: 'Dihantar',
    delivered: 'Diterima',
    delayed: 'Tertangguh',
    failed: 'Gagal',
    suppressed: 'Disekat',
    cancelled: 'Dibatalkan',
    exhausted: 'Percubaan tamat',
};
const triggerLabels = {
    booking: 'Tempahan',
    payment: 'Pembayaran',
    clinic_registration: 'Pendaftaran klinik',
};

function label(value) {
    return String(value ?? '').replaceAll('_', ' ');
}
function statusLabel(value) {
    return statusLabels[value] ?? label(value);
}
function triggerLabel(value) {
    return triggerLabels[value] ?? label(value);
}
function statusClass(status) {
    if (['sent', 'delivered'].includes(status)) return 'bg-emerald-100 text-emerald-800';
    if (['prepared', 'queued'].includes(status)) return 'bg-sky-100 text-sky-800';
    if (status === 'delayed') return 'bg-amber-100 text-amber-900';
    if (['failed', 'exhausted'].includes(status)) return 'bg-rose-100 text-rose-800';
    return 'bg-slate-100 text-slate-700';
}
function date(value) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Kuala_Lumpur',
    }).format(parsed);
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
                        Komunikasi transaksi
                    </p>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">Jejak setiap penghantaran</h2>
                    <p class="mt-2 leading-7 text-emerald-50/80">
                        Semak sama ada notifikasi sedang diproses, berjaya dihantar atau memerlukan
                        perhatian.
                    </p>
                </div>
                <dl
                    class="grid grid-cols-3 gap-5 rounded-2xl border border-white/15 bg-white/10 px-5 py-4"
                >
                    <div>
                        <dt class="text-xs text-emerald-50/65">Dipaparkan</dt>
                        <dd class="mt-1 text-2xl font-black">{{ entries.length }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-50/65">Berjaya</dt>
                        <dd class="mt-1 text-2xl font-black text-lime-300">{{ deliveredCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-50/65">Perhatian</dt>
                        <dd class="mt-1 text-2xl font-black text-amber-300">
                            {{ attentionCount }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <form
            method="get"
            class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3"
        >
            <label>
                <span class="text-sm font-bold text-slate-800">Status penghantaran</span>
                <select
                    name="status"
                    :value="filters.status ?? ''"
                    class="mt-1 min-h-12 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="">Semua status</option>
                    <option v-for="(text, status) in statusLabels" :key="status" :value="status">
                        {{ text }}
                    </option>
                </select>
            </label>
            <label>
                <span class="text-sm font-bold text-slate-800">Jenis aktiviti</span>
                <select
                    name="trigger_type"
                    :value="filters.triggerType ?? ''"
                    class="mt-1 min-h-12 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="">Semua aktiviti</option>
                    <option
                        v-for="(text, trigger) in triggerLabels"
                        :key="trigger"
                        :value="trigger"
                    >
                        {{ text }}
                    </option>
                </select>
            </label>
            <label v-if="canFilterTenant">
                <span class="text-sm font-bold text-slate-800">ID tenant</span>
                <input
                    name="tenant_id"
                    :value="filters.tenantId ?? ''"
                    placeholder="UUID tenant"
                    class="mt-1 min-h-12 w-full rounded-xl border border-slate-300 px-3"
                />
            </label>
            <div class="flex items-center gap-2 md:col-span-3">
                <button
                    class="min-h-12 rounded-xl bg-slate-950 px-5 font-bold text-white hover:bg-slate-800"
                >
                    Tapis rekod
                </button>
                <a
                    v-if="hasFilters"
                    :href="indexUrl"
                    class="inline-flex min-h-12 items-center rounded-xl px-4 font-bold text-slate-600 hover:bg-slate-100"
                    >Kosongkan penapis</a
                >
            </div>
        </form>

        <DashboardEmptyState
            v-if="entries.length === 0"
            :title="hasFilters ? 'Tiada rekod sepadan' : 'Belum ada notifikasi'"
            :description="
                hasFilters
                    ? 'Cuba ubah atau kosongkan penapis untuk melihat rekod lain.'
                    : 'Sejarah komunikasi transaksi akan dipaparkan selepas notifikasi diproses.'
            "
        />
        <section v-else aria-label="Sejarah notifikasi" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                <h2 class="font-bold text-slate-950">Sehingga 100 rekod terkini</h2>
                <p class="text-xs text-slate-500">Masa dipaparkan dalam zon Asia/Kuala Lumpur</p>
            </div>
            <article
                v-for="entry in entries"
                :key="entry.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-bold capitalize text-slate-950">
                                {{ label(entry.category) }}
                            </p>
                            <span class="text-slate-300" aria-hidden="true">•</span>
                            <p class="text-sm font-semibold text-slate-600">
                                {{ triggerLabel(entry.triggerType) }}
                            </p>
                        </div>
                        <p class="mt-2 break-all text-sm text-slate-500">
                            Penerima: {{ entry.recipientReference }}
                        </p>
                        <p
                            v-if="canFilterTenant && entry.tenantId"
                            class="mt-1 break-all text-xs text-slate-400"
                        >
                            Tenant: {{ entry.tenantId }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span
                            class="inline-flex rounded-full px-3 py-1.5 text-xs font-bold"
                            :class="statusClass(entry.status)"
                            >{{ statusLabel(entry.status) }}</span
                        >
                        <p class="mt-2 text-xs text-slate-500">{{ date(entry.createdAt) }}</p>
                    </div>
                </div>
                <details v-if="entry.attempts.length" class="mt-4 border-t border-slate-100 pt-4">
                    <summary class="cursor-pointer text-sm font-bold text-emerald-700">
                        Lihat {{ entry.attempts.length }} percubaan penghantaran
                    </summary>
                    <ol class="mt-3 space-y-2">
                        <li
                            v-for="attempt in entry.attempts"
                            :key="attempt.sequence"
                            class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"
                        >
                            Percubaan {{ attempt.sequence }}:
                            <strong class="capitalize text-slate-800">{{
                                label(attempt.outcome)
                            }}</strong>
                            · {{ date(attempt.attemptedAt) }}
                            <span
                                v-if="attempt.reasonCode"
                                class="mt-1 block text-xs text-slate-500"
                                >Kod sebab: {{ attempt.reasonCode }}</span
                            >
                        </li>
                    </ol>
                </details>
            </article>
        </section>
    </DashboardShell>
</template>
