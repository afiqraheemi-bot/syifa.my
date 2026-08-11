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
    tenantOverview: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);

const summaryToneClasses = {
    neutral: 'border-slate-200 bg-white text-slate-950',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-950',
    info: 'border-sky-200 bg-sky-50 text-sky-950',
    warning: 'border-amber-200 bg-amber-50 text-amber-950',
};

const badgeToneClasses = {
    neutral: 'bg-slate-100 text-slate-700',
    success: 'bg-emerald-100 text-emerald-800',
    info: 'bg-sky-100 text-sky-800',
    warning: 'bg-amber-100 text-amber-900',
    danger: 'bg-rose-100 text-rose-800',
};

const hasFilters = Boolean(
    props.tenantOverview.search.value || props.tenantOverview.statusFilter.value,
);
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
            aria-label="Tenant health summary"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <article
                v-for="item in tenantOverview.summary"
                :key="item.key"
                class="rounded-2xl border p-5 shadow-sm"
                :class="summaryToneClasses[item.tone]"
            >
                <p class="text-sm font-bold opacity-75">{{ item.label }}</p>
                <p class="mt-2 text-3xl font-black tracking-tight">{{ item.value }}</p>
            </article>
        </section>

        <form
            :action="tenantOverview.search.action"
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)_auto] sm:items-end"
        >
            <label class="text-sm font-semibold text-slate-700">
                Find a tenant
                <input
                    name="search"
                    type="search"
                    :value="tenantOverview.search.value"
                    :placeholder="tenantOverview.search.placeholder"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                />
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Lifecycle status
                <select
                    name="status"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="option in tenantOverview.statusFilter.options"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === tenantOverview.statusFilter.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <div class="flex flex-wrap gap-2">
                <button
                    type="submit"
                    class="min-h-11 flex-1 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    Apply
                </button>
                <a
                    v-if="hasFilters"
                    :href="tenantOverview.resetHref"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    Reset
                </a>
            </div>
        </form>

        <section v-if="tenantOverview.items.length" aria-label="Tenant overview">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="tenant in tenantOverview.items"
                    :key="tenant.id"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <header class="border-b border-slate-200 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700"
                                >
                                    Tenant {{ tenant.reference }}
                                </p>
                                <h2 class="mt-2 text-xl font-black text-slate-950">
                                    {{ tenant.clinicName }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Provisioned {{ tenant.provisionedAtLabel }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="badgeToneClasses[tenant.statusTone]"
                            >
                                {{ tenant.statusLabel }}
                            </span>
                        </div>
                    </header>

                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <section aria-label="Clinic owner">
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                                Clinic Owner
                            </p>
                            <p class="mt-2 font-bold text-slate-950">{{ tenant.ownerName }}</p>
                            <p class="mt-1 break-all text-sm text-slate-600">
                                {{ tenant.ownerEmail }}
                            </p>
                        </section>

                        <section aria-label="Subscription">
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                                Subscription
                            </p>
                            <p class="mt-2 font-bold text-slate-950">
                                {{ tenant.subscriptionStatusLabel }}
                            </p>
                            <a
                                v-if="tenant.subscriptionHref"
                                :href="tenant.subscriptionHref"
                                class="mt-2 inline-flex text-sm font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900"
                            >
                                View subscription
                            </a>
                        </section>

                        <section aria-label="Website and public address">
                            <div class="flex flex-wrap items-center gap-2">
                                <p
                                    class="text-xs font-black uppercase tracking-wider text-slate-500"
                                >
                                    Website
                                </p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[0.68rem] font-bold"
                                    :class="badgeToneClasses[tenant.publicHostStatusTone]"
                                >
                                    {{ tenant.publicHostStatus }}
                                </span>
                            </div>
                            <p class="mt-2 font-bold text-slate-950">
                                {{ tenant.websiteLifecycleLabel }} ·
                                {{ tenant.websitePublicationStatus }}
                            </p>
                            <p class="mt-1 break-all text-sm text-slate-600">
                                {{ tenant.publicHost }}
                            </p>
                        </section>

                        <section aria-label="Onboarding">
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                                Onboarding
                            </p>
                            <p class="mt-2 font-bold text-slate-950">
                                {{ tenant.onboardingStatusLabel }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                Designer: {{ tenant.websiteDesigner }}
                            </p>
                            <a
                                v-if="tenant.onboardingHref"
                                :href="tenant.onboardingHref"
                                class="mt-2 inline-flex text-sm font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900"
                            >
                                Open onboarding
                            </a>
                        </section>
                    </div>

                    <aside
                        v-if="tenant.attention.length"
                        class="border-t border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950 sm:px-6"
                    >
                        <p class="font-black">Setup attention required</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li v-for="item in tenant.attention" :key="item">{{ item }}</li>
                        </ul>
                    </aside>
                </article>
            </div>
        </section>
        <DashboardEmptyState
            v-else
            title="No tenants found"
            description="Try adjusting the search or lifecycle filter."
        />
        <nav
            v-if="tenantOverview.pagination.hasMore"
            class="flex justify-end"
            aria-label="Tenant pagination"
        >
            <a
                :href="tenantOverview.pagination.nextHref"
                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Next page
            </a>
        </nav>
    </DashboardShell>
</template>
