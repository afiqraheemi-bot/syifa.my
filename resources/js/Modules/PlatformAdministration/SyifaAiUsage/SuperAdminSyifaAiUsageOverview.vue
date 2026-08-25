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
    syifaAiUsage: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);

function usageBarClass(percentOfLimit) {
    if (percentOfLimit >= 90) return 'bg-rose-500';
    if (percentOfLimit >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
}

function usageBarWidth(percentOfLimit) {
    return `${Math.max(0, Math.min(100, Number(percentOfLimit) || 0))}%`;
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
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                Kawalan kos AI
            </p>
            <h2 class="mt-3 text-2xl font-black sm:text-3xl">Penggunaan bulan semasa</h2>
            <p class="mt-2 max-w-2xl leading-7 text-emerald-50/80">
                Pantau penggunaan token mengikut capability, model dan tenant sebelum mencapai had.
            </p>
        </section>

        <section
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="SYIFA AI usage summary"
        >
            <article
                v-for="item in syifaAiUsage.summary"
                :key="item.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-sm font-semibold text-slate-500">{{ item.label }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ item.value }}</p>
            </article>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                    Bulan ini
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">
                    Penggunaan mengikut capability
                </h2>
                <div v-if="syifaAiUsage.byCapability.length" class="mt-4 space-y-3">
                    <div
                        v-for="row in syifaAiUsage.byCapability"
                        :key="row.capability"
                        class="flex items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-semibold text-slate-900">{{ row.label }}</p>
                            <p class="text-xs text-slate-500">{{ row.requests }} permintaan</p>
                        </div>
                        <p class="font-mono text-sm font-bold text-slate-900">
                            {{ row.tokensLabel }} token
                        </p>
                    </div>
                </div>
                <DashboardEmptyState
                    v-else
                    title="Belum ada penggunaan"
                    description="Penggunaan capability akan dipaparkan selepas permintaan AI dibuat bulan ini."
                />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                    Bulan ini
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Penggunaan mengikut model</h2>
                <div v-if="syifaAiUsage.byEngine.length" class="mt-4 space-y-3">
                    <div
                        v-for="row in syifaAiUsage.byEngine"
                        :key="row.model"
                        class="flex items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-mono font-semibold text-slate-900">{{ row.model }}</p>
                            <p class="text-xs text-slate-500">{{ row.requests }} permintaan</p>
                        </div>
                        <p class="font-mono text-sm font-bold text-slate-900">
                            {{ row.tokensLabel }} token
                        </p>
                    </div>
                </div>
                <DashboardEmptyState
                    v-else
                    title="Belum ada penggunaan"
                    description="Penggunaan model akan dipaparkan selepas permintaan AI dibuat bulan ini."
                />
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                        Pendedahan kos
                    </p>
                    <h2 class="mt-2 text-lg font-bold text-slate-950">
                        Tenant dengan penggunaan token tertinggi
                    </h2>
                </div>
                <p class="max-w-xl text-sm text-slate-600">
                    Had bulanan setiap tenant ialah
                    {{ syifaAiUsage.monthlyTenantLimit.toLocaleString() }} token.
                </p>
            </div>
            <div v-if="syifaAiUsage.topTenants.length" class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Klinik</th>
                            <th class="pb-3">Permintaan</th>
                            <th class="pb-3">Token</th>
                            <th class="pb-3">% had bulanan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="tenant in syifaAiUsage.topTenants" :key="tenant.tenantId">
                            <td class="py-3 pr-4">
                                <p class="font-semibold text-slate-900">{{ tenant.clinicName }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ tenant.tenantId }}</p>
                            </td>
                            <td class="py-3 pr-4">{{ tenant.requests }}</td>
                            <td class="py-3 pr-4 font-mono">{{ tenant.tokensLabel }}</td>
                            <td class="py-3 pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full"
                                            :class="usageBarClass(tenant.percentOfLimit)"
                                            :style="{ width: usageBarWidth(tenant.percentOfLimit) }"
                                        />
                                    </div>
                                    <span class="text-xs font-bold text-slate-700"
                                        >{{ tenant.percentOfLimit }}%</span
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <DashboardEmptyState
                v-else
                title="Belum ada penggunaan tenant"
                description="Tenant yang menggunakan SYIFA AI bulan ini akan disenaraikan mengikut penggunaan token."
            />
        </section>
    </DashboardShell>
</template>
