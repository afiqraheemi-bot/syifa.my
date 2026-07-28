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
    audit: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);

function label(value) {
    return value.replaceAll('_', ' ').replaceAll('.', ' › ');
}

function date(value) {
    return new Intl.DateTimeFormat('en-MY', {
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
        <form
            method="get"
            class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5"
        >
            <input
                name="action"
                :value="filters.action ?? ''"
                placeholder="Action contains"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            />
            <select
                name="outcome"
                :value="filters.outcome ?? ''"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            >
                <option value="">All outcomes</option>
                <option value="succeeded">Succeeded</option>
                <option value="failed">Failed</option>
                <option value="denied">Denied</option>
            </select>
            <select
                name="actor_type"
                :value="filters.actorType ?? ''"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            >
                <option value="">All actors</option>
                <option value="platform_identity">Platform identity</option>
                <option value="clinic_owner">Clinic Owner</option>
                <option value="system">System</option>
                <option value="anonymous">Anonymous</option>
            </select>
            <input
                name="tenant_id"
                :value="filters.tenantId ?? ''"
                placeholder="Tenant UUID"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            />
            <button class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white">
                Apply filters
            </button>
        </form>

        <DashboardEmptyState
            v-if="audit.entries.length === 0"
            title="No audit activity"
            description="No immutable audit evidence matches the current filters."
        />
        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Actor</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3">Outcome</th>
                            <th class="px-4 py-3">Correlation</th>
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
                                    {{ entry.actorIdentityId ?? 'No identity' }}
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
