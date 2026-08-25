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
    audit: { type: Object, required: true },
    filters: { type: Object, required: true },
    indexUrl: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));

function label(value) {
    return value.replaceAll('_', ' ').replaceAll('.', ' › ');
}

function date(value) {
    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'medium',
        timeZone: 'Asia/Kuala_Lumpur',
    }).format(new Date(value));
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
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">Bukti kekal</p>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-black sm:text-3xl">Jejak tindakan kritikal</h2>
                    <p class="mt-2 max-w-2xl leading-7 text-emerald-50/80">
                        Setiap rekod ialah bukti read-only dan tidak boleh diubah melalui dashboard.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                    <p class="text-xs text-emerald-50/65">Rekod dipaparkan</p>
                    <p class="mt-1 text-3xl font-black text-lime-300">{{ audit.entries.length }}</p>
                </div>
            </div>
        </section>

        <form
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <label class="text-sm font-bold text-slate-700">
                Tindakan
                <input
                    name="action"
                    :value="filters.action ?? ''"
                    placeholder="Contoh: subscription"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                />
            </label>
            <label class="text-sm font-bold text-slate-700">
                Keputusan
                <select
                    name="outcome"
                    :value="filters.outcome ?? ''"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="">Semua keputusan</option>
                    <option value="succeeded">Berjaya</option>
                    <option value="failed">Gagal</option>
                    <option value="denied">Ditolak</option>
                </select>
            </label>
            <label class="text-sm font-bold text-slate-700">
                Jenis pelaku
                <select
                    name="actor_type"
                    :value="filters.actorType ?? ''"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="">Semua pelaku</option>
                    <option value="platform_identity">Identiti platform</option>
                    <option value="clinic_owner">Clinic Owner</option>
                    <option value="system">Sistem</option>
                    <option value="anonymous">Tanpa identiti</option>
                </select>
            </label>
            <label class="text-sm font-bold text-slate-700"
                >ID tenant
                <input
                    name="tenant_id"
                    :value="filters.tenantId ?? ''"
                    placeholder="UUID tenant"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                />
            </label>
            <label class="text-sm font-bold text-slate-700"
                >Correlation ID
                <input
                    name="correlation_id"
                    :value="filters.correlationId ?? ''"
                    placeholder="ID korelasi"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                />
            </label>
            <div class="flex items-end gap-2">
                <button class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white">
                    Tapis
                </button>
                <a
                    v-if="hasFilters"
                    :href="indexUrl"
                    class="inline-flex min-h-11 items-center rounded-xl px-4 font-bold text-slate-600 hover:bg-slate-100"
                    >Reset</a
                >
            </div>
        </form>

        <DashboardEmptyState
            v-if="audit.entries.length === 0"
            :title="hasFilters ? 'Tiada rekod sepadan' : 'Belum ada aktiviti audit'"
            description="Tiada bukti audit kekal yang sepadan dengan skop semasa."
        />
        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Masa</th>
                            <th class="px-4 py-3">Tindakan</th>
                            <th class="px-4 py-3">Pelaku</th>
                            <th class="px-4 py-3">Sasaran</th>
                            <th class="px-4 py-3">Keputusan</th>
                            <th class="px-4 py-3">Korelasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="entry in audit.entries" :key="entry.id">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ date(entry.occurredAt) }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ label(entry.action) }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <span class="block">{{ label(entry.actorType) }}</span>
                                <span class="block font-mono text-xs text-slate-500">
                                    {{ entry.actorIdentityId ?? 'Tiada identiti' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <span class="block">{{ label(entry.targetType) }}</span>
                                <span class="block font-mono text-xs text-slate-500">
                                    {{ entry.targetId }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-bold"
                                    :class="
                                        entry.outcome === 'succeeded'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-red-100 text-red-800'
                                    "
                                >
                                    {{ label(entry.outcome) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">
                                {{ entry.correlationId }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </DashboardShell>
</template>
