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
            :action="tenantOverview.search.action"
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_minmax(12rem,auto)_auto] sm:items-end"
        >
            <label class="text-sm font-semibold text-slate-700">
                Search tenants
                <input
                    name="search"
                    type="search"
                    :value="tenantOverview.search.value"
                    :placeholder="tenantOverview.search.placeholder"
                    class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                />
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Status
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
            <button
                type="submit"
                class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Apply
            </button>
        </form>

        <section v-if="tenantOverview.items.length" aria-label="Tenant overview">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="tenant in tenantOverview.items"
                    :key="tenant.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold text-slate-950">{{ tenant.clinicName }}</h2>
                            <p class="mt-1 text-sm font-medium text-slate-700">
                                {{ tenant.ownerName }}
                            </p>
                            <p class="mt-0.5 break-all text-sm text-slate-500">
                                {{ tenant.ownerEmail }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800"
                        >
                            {{ tenant.statusLabel }}
                        </span>
                    </div>
                    <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-slate-500">Subscription</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ tenant.subscriptionStatusLabel }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Website</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ tenant.websitePublicationStatus }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-semibold text-slate-500">Website Designer</dt>
                            <dd class="mt-1 break-all text-slate-900">
                                {{ tenant.websiteDesigner }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </div>
        </section>
        <DashboardEmptyState
            v-else
            title="No tenants found"
            description="Try adjusting the search or status filter."
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
