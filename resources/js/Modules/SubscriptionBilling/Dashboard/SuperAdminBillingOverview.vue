<script setup>
import { computed, ref } from 'vue';
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
    billingOverview: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const filtersSubmitting = ref(false);
const billingOverview = computed(() => props.billingOverview);
const hasFilters = computed(() =>
    Boolean(billingOverview.value.search.value || billingOverview.value.statusFilter.value),
);

function statusClass(status) {
    const normalized = String(status).toLowerCase();

    if (['active', 'succeeded', 'paid'].some((value) => normalized.includes(value))) {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (['pending', 'renewal due', 'draft'].some((value) => normalized.includes(value))) {
        return 'bg-amber-100 text-amber-900';
    }

    if (['failed', 'expired', 'cancelled', 'closed'].some((value) => normalized.includes(value))) {
        return 'bg-rose-100 text-rose-800';
    }

    return 'bg-slate-100 text-slate-700';
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
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Billing summary">
            <article
                v-for="item in billingOverview.summary"
                :key="item.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-sm font-semibold text-slate-500">{{ item.label }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ item.value }}</p>
            </article>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                    Payment operations
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Payment status</h2>
                <dl class="mt-4 grid grid-cols-3 gap-3">
                    <div
                        v-for="item in billingOverview.paymentStatus"
                        :key="item.key"
                        class="rounded-xl bg-slate-50 p-3"
                    >
                        <dt class="text-sm text-slate-500">{{ item.label }}</dt>
                        <dd class="mt-1 text-xl font-bold text-slate-950">{{ item.value }}</dd>
                    </div>
                </dl>
            </section>
            <section
                class="rounded-2xl border p-5 shadow-sm"
                :class="
                    billingOverview.health.status === 'healthy'
                        ? 'border-emerald-200 bg-emerald-50'
                        : 'border-amber-200 bg-amber-50'
                "
            >
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-600">
                    Operational check
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Billing health</h2>
                <p class="mt-3 font-bold text-slate-900">{{ billingOverview.health.label }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ billingOverview.health.description }}</p>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                        Money received and attempted
                    </p>
                    <h2 class="mt-2 text-lg font-bold text-slate-950">Recent payments</h2>
                </div>
                <p class="max-w-xl text-sm text-slate-600">
                    Open the clinic subscription below to view its invoice, payment proof and
                    renewal history.
                </p>
            </div>
            <div v-if="billingOverview.recentPayments.length" class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Payment reference</th>
                            <th class="pb-3">Clinic</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="payment in billingOverview.recentPayments" :key="payment.id">
                            <td class="py-3 pr-4">
                                <span
                                    class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold tracking-wide text-slate-800"
                                >
                                    {{ payment.reference }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <p class="font-semibold text-slate-900">{{ payment.clinicName }}</p>
                                <p
                                    v-if="payment.tenantReference"
                                    class="mt-0.5 text-xs text-slate-500"
                                >
                                    {{ payment.tenantReference }}
                                </p>
                            </td>
                            <td class="py-3 pr-4">{{ payment.amount }}</td>
                            <td class="py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="statusClass(payment.statusLabel)"
                                >
                                    {{ payment.statusLabel }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <DashboardEmptyState
                v-else
                title="No payments yet"
                description="Recent payments will appear here."
            />
        </section>

        <form
            :action="billingOverview.search.action"
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)_auto] sm:items-end"
            @submit="filtersSubmitting = true"
        >
            <label class="text-sm font-semibold text-slate-700">
                Search subscriptions
                <input
                    name="search"
                    type="search"
                    :value="billingOverview.search.value"
                    :placeholder="billingOverview.search.placeholder"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3"
                />
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Status
                <select
                    name="status"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="option in billingOverview.statusFilter.options"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === billingOverview.statusFilter.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60"
                    :disabled="filtersSubmitting"
                >
                    {{ filtersSubmitting ? 'Loading…' : 'Apply' }}
                </button>
                <a
                    v-if="hasFilters"
                    :href="billingOverview.search.action"
                    class="inline-flex min-h-11 items-center rounded-xl px-4 font-bold text-slate-600 hover:bg-slate-100"
                    >Reset</a
                >
            </div>
        </form>

        <section
            v-if="billingOverview.subscriptions.length"
            aria-label="Subscription overview"
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <table class="min-w-full text-left text-sm">
                <thead class="text-slate-500">
                    <tr>
                        <th class="pb-3">Subscription reference</th>
                        <th class="pb-3">Clinic</th>
                        <th class="pb-3">Plan</th>
                        <th class="pb-3">Period</th>
                        <th class="pb-3">Amount</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="subscription in billingOverview.subscriptions"
                        :key="subscription.id"
                    >
                        <td class="py-3 pr-4 font-medium text-slate-900">
                            <a
                                :href="subscription.detailHref"
                                class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold tracking-wide text-slate-800 hover:bg-slate-200"
                                >{{ subscription.reference }}</a
                            >
                        </td>
                        <td class="py-3 pr-4">
                            <p class="font-semibold text-slate-900">
                                {{ subscription.clinicName }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ subscription.tenantReference }}
                            </p>
                        </td>
                        <td class="py-3 pr-4">
                            <p class="font-semibold text-slate-900">{{ subscription.planName }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ subscription.planReference }}
                            </p>
                        </td>
                        <td class="py-3 pr-4">
                            {{ subscription.startsOn }} – {{ subscription.endsOn }}
                        </td>
                        <td class="py-3 pr-4">{{ subscription.amount }}</td>
                        <td class="py-3">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="statusClass(subscription.statusLabel)"
                            >
                                {{ subscription.statusLabel }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <DashboardEmptyState
            v-else
            title="No subscriptions found"
            description="Try adjusting the search or status filter."
        />
        <nav
            v-if="billingOverview.pagination.hasMore"
            class="flex justify-end"
            aria-label="Subscription pagination"
        >
            <a
                :href="billingOverview.pagination.nextHref"
                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold"
                >Next page</a
            >
        </nav>
    </DashboardShell>
</template>
