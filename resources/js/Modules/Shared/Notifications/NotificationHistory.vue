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
    notificationHistory: { type: Object, required: true },
    filters: { type: Object, required: true },
    canFilterTenant: { type: Boolean, required: true },
});

const navigation = createDashboardNavigation(props.navigation);

function label(value) {
    return value.replaceAll('_', ' ');
}

function date(value) {
    return new Intl.DateTimeFormat('en-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
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
            class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3"
        >
            <select
                name="status"
                :value="filters.status ?? ''"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            >
                <option value="">All delivery states</option>
                <option
                    v-for="status in [
                        'queued',
                        'sent',
                        'delivered',
                        'delayed',
                        'failed',
                        'suppressed',
                        'cancelled',
                        'exhausted',
                    ]"
                    :key="status"
                    :value="status"
                >
                    {{ label(status) }}
                </option>
            </select>
            <input
                name="trigger_type"
                :value="filters.triggerType ?? ''"
                placeholder="Trigger type"
                class="min-h-11 rounded-xl border border-slate-300 px-3"
            />
            <input
                v-if="canFilterTenant"
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
            v-if="notificationHistory.entries.length === 0"
            title="No notifications"
            description="No transactional communication matches this scope."
        />
        <div v-else class="space-y-3">
            <article
                v-for="entry in notificationHistory.entries"
                :key="entry.id"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold capitalize text-slate-950">
                            {{ label(entry.category) }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ label(entry.triggerType) }} · {{ entry.recipientReference }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold capitalize">
                        {{ label(entry.status) }}
                    </span>
                </div>
                <p class="mt-4 text-sm text-slate-500">{{ date(entry.createdAt) }}</p>
                <ol v-if="entry.attempts.length > 0" class="mt-4 space-y-2 border-t pt-4">
                    <li
                        v-for="attempt in entry.attempts"
                        :key="attempt.sequence"
                        class="text-sm text-slate-600"
                    >
                        Attempt {{ attempt.sequence }}: {{ label(attempt.outcome) }} ·
                        {{ date(attempt.attemptedAt) }}
                    </li>
                </ol>
            </article>
        </div>
    </DashboardShell>
</template>
