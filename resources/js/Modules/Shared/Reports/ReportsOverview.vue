<script setup>
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

function label(value) {
    return value
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replaceAll('_', ' ')
        .replace(/^\w/, (letter) => letter.toUpperCase());
}

function display(value) {
    if (value === null || value === undefined) return 'Not available';
    if (typeof value === 'number') return new Intl.NumberFormat('en-MY').format(value);
    return label(String(value));
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
        <p class="mb-6 text-sm text-slate-600">
            Scope: <strong>{{ label(report.scope) }}</strong> · Live as at
            {{ date(report.freshAt) }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="(value, key) in report.metrics"
                :key="key"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <h2 class="text-sm font-semibold text-slate-600">{{ label(key) }}</h2>
                <template v-if="typeof value === 'object' && value !== null">
                    <dl class="mt-4 space-y-2">
                        <div
                            v-for="(nestedValue, nestedKey) in value"
                            :key="nestedKey"
                            class="flex justify-between gap-4 text-sm"
                        >
                            <dt class="capitalize text-slate-600">{{ label(nestedKey) }}</dt>
                            <dd class="font-semibold text-slate-950">
                                {{ display(nestedValue) }}
                            </dd>
                        </div>
                    </dl>
                </template>
                <p v-else class="mt-2 text-2xl font-bold text-slate-950">{{ display(value) }}</p>
            </article>
        </div>

        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-bold text-slate-950">Metric definitions</h2>
            <p class="mt-1 text-sm text-slate-600">
                Definitions are governed and versioned; reports never become transactional truth.
            </p>
            <ul class="mt-4 grid gap-3 md:grid-cols-2">
                <li
                    v-for="definition in report.definitions"
                    :key="definition.key"
                    class="rounded-xl bg-slate-50 p-4"
                >
                    <p class="font-semibold text-slate-900">{{ definition.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Version {{ definition.version }} · {{ label(definition.freshness) }}
                    </p>
                </li>
            </ul>
        </section>
    </DashboardShell>
</template>
