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
                    This month
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Usage by capability</h2>
                <div v-if="syifaAiUsage.byCapability.length" class="mt-4 space-y-3">
                    <div
                        v-for="row in syifaAiUsage.byCapability"
                        :key="row.capability"
                        class="flex items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-semibold text-slate-900">{{ row.label }}</p>
                            <p class="text-xs text-slate-500">{{ row.requests }} request(s)</p>
                        </div>
                        <p class="font-mono text-sm font-bold text-slate-900">
                            {{ row.tokensLabel }} tokens
                        </p>
                    </div>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No usage yet"
                    description="Usage by capability will appear here once requests are made this month."
                />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                    This month
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Usage by model</h2>
                <div v-if="syifaAiUsage.byEngine.length" class="mt-4 space-y-3">
                    <div
                        v-for="row in syifaAiUsage.byEngine"
                        :key="row.model"
                        class="flex items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-mono font-semibold text-slate-900">{{ row.model }}</p>
                            <p class="text-xs text-slate-500">{{ row.requests }} request(s)</p>
                        </div>
                        <p class="font-mono text-sm font-bold text-slate-900">
                            {{ row.tokensLabel }} tokens
                        </p>
                    </div>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No usage yet"
                    description="Usage by model will appear here once requests are made this month."
                />
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                        Cost exposure
                    </p>
                    <h2 class="mt-2 text-lg font-bold text-slate-950">
                        Top tenants by token usage
                    </h2>
                </div>
                <p class="max-w-xl text-sm text-slate-600">
                    Each tenant's monthly cap is
                    {{ syifaAiUsage.monthlyTenantLimit.toLocaleString() }} tokens.
                </p>
            </div>
            <div v-if="syifaAiUsage.topTenants.length" class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Clinic</th>
                            <th class="pb-3">Requests</th>
                            <th class="pb-3">Tokens</th>
                            <th class="pb-3">% of monthly cap</th>
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
                                            :style="{ width: tenant.percentOfLimit + '%' }"
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
                title="No tenant usage yet"
                description="Tenants that use SYIFA AI this month will appear here, ranked by token consumption."
            />
        </section>
    </DashboardShell>
</template>
