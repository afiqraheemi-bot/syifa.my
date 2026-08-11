<script setup>
import { computed, nextTick, ref } from 'vue';
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
    csrfToken: { type: String, required: true },
    plans: { type: Array, required: true },
    archivedPlans: { type: Array, required: true },
    selectedPlan: { type: Object, default: null },
    offerings: { type: Array, required: true },
    selectedOffering: { type: Object, default: null },
    billingOptions: { type: Array, required: true },
    capabilities: { type: Array, required: true },
    pricingHistory: { type: Array, required: true },
    actions: { type: Object, required: true },
    feedback: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const submitting = ref('');
const confirmation = ref('');
const confirmationButton = ref(null);
const confirmationForm = computed(() => {
    if (confirmation.value.endsWith('-plan')) return `${confirmation.value}-form`;

    return `${confirmation.value}-offering-form`;
});

function beginSubmit(action, event) {
    if (submitting.value) {
        event.preventDefault();
        return;
    }
    submitting.value = action;
}

function askForConfirmation(action) {
    if (!submitting.value) {
        confirmation.value = action;
        nextTick(() => confirmationButton.value?.focus());
    }
}

function confirmFeatureLifecycle(action, event) {
    if (!window.confirm(`Confirm ${action} for this feature definition?`)) {
        event.preventDefault();
        return;
    }

    beginSubmit(`capability-${action}`, event);
}

function confirmPackageArchive(plan, event) {
    const confirmed = window.confirm(
        `Remove "${plan.name}" from current packages? It will move to the archive while subscriptions, payments, prices and audit history remain intact.`,
    );

    if (!confirmed) {
        event.preventDefault();
        return;
    }

    beginSubmit(`archive-plan-${plan.id}`, event);
}

function formatDate(value) {
    if (!value) return 'No end date';

    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
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
        <p
            v-if="feedback.success"
            role="status"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900"
        >
            {{ feedback.success }}
        </p>
        <p
            v-if="feedback.error"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900"
        >
            {{ feedback.error }}
        </p>

        <template v-if="!selectedPlan">
            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                aria-labelledby="plans-heading"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200 bg-emerald-50/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
                >
                    <div>
                        <p class="text-xs font-bold tracking-[0.18em] text-emerald-700 uppercase">
                            Commercial catalogue
                        </p>
                        <h2 id="plans-heading" class="text-lg font-bold text-slate-950">
                            Packages clinics can buy
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                            Create the package, billing cycle and MYR price in one guided form. Open
                            a package only when its availability, price or included features need
                            attention.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="actions.previewPackages"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-emerald-700 bg-white px-5 py-2 font-bold text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            Preview on website
                        </a>
                        <a
                            :href="actions.createPackage"
                            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-5 py-2 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            New package
                        </a>
                    </div>
                </div>
                <div class="space-y-5 p-5 sm:p-6">
                    <div v-if="plans.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="plan in plans"
                            :key="plan.id"
                            class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-xs font-bold tracking-wider text-emerald-700 uppercase"
                                    >
                                        {{ plan.code }}
                                    </p>
                                    <h3 class="mt-1 text-lg font-bold text-slate-950">
                                        {{ plan.name }}
                                    </h3>
                                </div>
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold"
                                >
                                    {{ plan.statusLabel ?? plan.status }}
                                </span>
                            </div>
                            <p class="mt-3 flex-1 text-sm leading-6 text-slate-600">
                                {{ plan.description }}
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                                <a
                                    :href="plan.detailUrl"
                                    class="inline-flex min-h-10 items-center rounded-lg bg-slate-950 px-4 py-2 text-sm font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >
                                    Manage package
                                </a>
                                <form
                                    :action="plan.retireUrl"
                                    method="post"
                                    @submit="confirmPackageArchive(plan, $event)"
                                >
                                    <input type="hidden" name="_token" :value="csrfToken" />
                                    <input
                                        type="hidden"
                                        name="expected_version"
                                        :value="plan.version"
                                    />
                                    <button
                                        type="submit"
                                        class="min-h-10 rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-bold text-rose-800 disabled:opacity-60"
                                        :disabled="Boolean(submitting)"
                                    >
                                        {{
                                            submitting === `archive-plan-${plan.id}`
                                                ? 'Removing…'
                                                : 'Remove package'
                                        }}
                                    </button>
                                </form>
                            </div>
                        </article>
                    </div>
                    <DashboardEmptyState
                        v-else
                        title="No current packages"
                        description="Create the first complete package customers can purchase."
                    />

                    <details
                        v-if="archivedPlans.length"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                    >
                        <summary class="cursor-pointer list-none px-4 py-3">
                            <span class="flex items-center justify-between gap-3">
                                <span>
                                    <span class="block font-bold text-slate-900">
                                        Archived packages ({{ archivedPlans.length }})
                                    </span>
                                    <span class="mt-0.5 block text-sm text-slate-600">
                                        Hidden from new sales; financial and audit records remain
                                        intact.
                                    </span>
                                </span>
                                <span aria-hidden="true" class="text-slate-500">⌄</span>
                            </span>
                        </summary>
                        <ul class="divide-y divide-slate-200 border-t border-slate-200 bg-white">
                            <li
                                v-for="plan in archivedPlans"
                                :key="plan.id"
                                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p class="font-bold text-slate-900">{{ plan.name }}</p>
                                    <p class="text-xs font-semibold text-slate-500 uppercase">
                                        {{ plan.code }} · Archived
                                    </p>
                                </div>
                                <a
                                    :href="plan.detailUrl"
                                    class="text-sm font-bold text-emerald-800 underline underline-offset-4"
                                >
                                    View record
                                </a>
                            </li>
                        </ul>
                    </details>
                </div>
            </section>

            <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary
                    class="cursor-pointer list-none px-5 py-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:px-6"
                >
                    <span class="flex items-center justify-between gap-4">
                        <span>
                            <span class="block font-bold text-slate-950"
                                >Advanced catalogue settings</span
                            >
                            <span class="mt-1 block text-sm leading-6 text-slate-600">
                                Billing cycles, raw price records and shared platform feature
                                definitions.
                            </span>
                        </span>
                        <span class="text-xl text-slate-500" aria-hidden="true">⌄</span>
                    </span>
                </summary>
                <div class="space-y-8 border-t border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <section class="space-y-4" aria-labelledby="catalogue-offerings-heading">
                        <h2
                            id="catalogue-offerings-heading"
                            class="text-lg font-bold text-slate-950"
                        >
                            Plan pricing
                        </h2>
                        <div
                            v-if="offerings.length"
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <a
                                v-for="offering in offerings"
                                :key="offering.id"
                                :href="offering.detailUrl"
                                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-950">
                                            {{ offering.planName }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ offering.billingOptionLabel }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        {{ offering.statusLabel ?? offering.status }}
                                    </span>
                                </div>
                                <p class="mt-4 text-xl font-bold text-slate-950">
                                    {{ offering.amount }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ formatDate(offering.effectiveStart) }} –
                                    {{ formatDate(offering.effectiveEnd) }}
                                </p>
                            </a>
                        </div>
                        <DashboardEmptyState
                            v-else
                            title="No plan prices"
                            description="Open a subscription plan to add its first price."
                        />
                    </section>

                    <section class="space-y-4" aria-labelledby="billing-options-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2
                                    id="billing-options-heading"
                                    class="text-lg font-bold text-slate-950"
                                >
                                    Billing cycles
                                </h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    Define when and how often a clinic is charged.
                                </p>
                            </div>
                            <a
                                :href="actions.createBillingOption"
                                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            >
                                Add billing cycle
                            </a>
                        </div>
                        <div
                            v-if="billingOptions.length"
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <article
                                v-for="option in billingOptions"
                                :key="option.id"
                                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p
                                            class="text-xs font-bold tracking-wider text-emerald-700 uppercase"
                                        >
                                            {{ option.code }}
                                        </p>
                                        <h3 class="mt-1 text-lg font-bold text-slate-950">
                                            {{ option.label }}
                                        </h3>
                                    </div>
                                    <span
                                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold"
                                    >
                                        {{ option.availabilityLabel ?? option.availability }}
                                    </span>
                                </div>
                                <p class="mt-3 text-sm text-slate-600">
                                    {{ option.recurrenceLabel }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Effective {{ formatDate(option.effectiveStart) }} –
                                    {{ formatDate(option.effectiveEnd) }}
                                </p>
                                <a
                                    :href="option.editUrl"
                                    class="mt-4 inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >
                                    Edit billing cycle
                                </a>
                            </article>
                        </div>
                        <DashboardEmptyState
                            v-else
                            title="No billing cycles"
                            description="Add a billing cycle before setting a plan price."
                        />
                    </section>

                    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                        <a
                            :href="actions.createPlan"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                        >
                            Create plan only
                        </a>
                        <p class="self-center text-sm text-slate-600">
                            Use this only when pricing will be added later.
                        </p>
                    </div>
                </div>
            </details>
        </template>

        <template v-else>
            <section class="space-y-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold tracking-wider text-emerald-700 uppercase">
                                {{ selectedPlan.code }}
                            </p>
                            <h2 class="mt-1 text-xl font-bold text-slate-950">
                                {{ selectedPlan.name }}
                            </h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">
                            {{ selectedPlan.statusLabel ?? selectedPlan.status }}
                        </span>
                    </div>
                    <p class="mt-4 text-slate-600">{{ selectedPlan.description }}</p>
                    <div v-if="selectedPlan.status !== 'retired'" class="mt-5 flex flex-wrap gap-3">
                        <a
                            :href="actions.editPlan"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                        >
                            Edit package
                        </a>
                        <a
                            :href="actions.createOffering"
                            class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 py-2 font-bold text-white"
                        >
                            Add price
                        </a>
                    </div>
                    <div
                        v-else
                        class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600"
                    >
                        This package is archived and read-only. Its subscriptions, payments, prices
                        and audit history remain available below.
                    </div>
                </article>
                <details
                    v-if="selectedPlan.status !== 'retired'"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <summary
                        class="cursor-pointer list-none p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <span class="flex items-center justify-between gap-4">
                            <span>
                                <span
                                    class="block text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Advanced catalogue
                                </span>
                                <span class="mt-1 block font-bold text-slate-950">
                                    Package features
                                </span>
                                <span class="mt-1 block text-sm font-normal text-slate-600">
                                    {{ capabilities.length }} configured. Open only when customer
                                    entitlements need to change.
                                </span>
                            </span>
                            <span aria-hidden="true" class="text-xl text-slate-500">⌄</span>
                        </span>
                    </summary>
                    <div class="border-t border-slate-200 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="max-w-2xl text-sm leading-6 text-slate-600">
                                Features control what clinics receive with this package. Existing
                                feature lifecycle and audit rules remain authoritative.
                            </p>
                            <a
                                :href="actions.createCapability"
                                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-900"
                            >
                                Add feature
                            </a>
                        </div>
                        <ul class="mt-4 grid gap-3 lg:grid-cols-2">
                            <li
                                v-for="capability in capabilities"
                                :key="capability.id"
                                class="rounded-xl border border-slate-200 p-3"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-950">
                                            {{ capability.name }}
                                        </p>
                                        <code class="text-xs text-slate-500">{{
                                            capability.key
                                        }}</code>
                                    </div>
                                    <span
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        {{ capability.statusLabel ?? capability.status }}
                                    </span>
                                </div>
                                <p class="mt-2 text-slate-600">{{ capability.description }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a
                                        :href="capability.editUrl"
                                        class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-1.5 font-bold text-slate-900"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        v-if="capability.status === 'draft'"
                                        :action="capability.activateUrl"
                                        method="post"
                                        @submit="confirmFeatureLifecycle('activation', $event)"
                                    >
                                        <input type="hidden" name="_token" :value="csrfToken" />
                                        <input
                                            type="hidden"
                                            name="expected_version"
                                            :value="capability.version"
                                        />
                                        <button
                                            type="submit"
                                            class="min-h-10 rounded-lg bg-emerald-700 px-3 py-1.5 font-bold text-white disabled:opacity-60"
                                            :disabled="Boolean(submitting)"
                                        >
                                            Activate
                                        </button>
                                    </form>
                                    <form
                                        v-if="capability.status === 'active'"
                                        :action="capability.deprecateUrl"
                                        method="post"
                                        @submit="confirmFeatureLifecycle('deprecation', $event)"
                                    >
                                        <input type="hidden" name="_token" :value="csrfToken" />
                                        <input
                                            type="hidden"
                                            name="expected_version"
                                            :value="capability.version"
                                        />
                                        <button
                                            type="submit"
                                            class="min-h-10 rounded-lg border border-amber-300 px-3 py-1.5 font-bold text-amber-900 disabled:opacity-60"
                                            :disabled="Boolean(submitting)"
                                        >
                                            Deprecate
                                        </button>
                                    </form>
                                    <form
                                        v-if="capability.status !== 'retired'"
                                        :action="capability.retireUrl"
                                        method="post"
                                        @submit="confirmFeatureLifecycle('retirement', $event)"
                                    >
                                        <input type="hidden" name="_token" :value="csrfToken" />
                                        <input
                                            type="hidden"
                                            name="expected_version"
                                            :value="capability.version"
                                        />
                                        <button
                                            type="submit"
                                            class="min-h-10 rounded-lg border border-rose-300 px-3 py-1.5 font-bold text-rose-800 disabled:opacity-60"
                                            :disabled="Boolean(submitting)"
                                        >
                                            Retire
                                        </button>
                                    </form>
                                </div>
                            </li>
                        </ul>
                    </div>
                </details>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="font-bold text-slate-950">Package availability</h2>
                    <p
                        v-if="selectedPlan.status !== 'retired'"
                        class="mt-1 text-sm leading-6 text-slate-600"
                    >
                        Control whether new clinics can buy this package. Removing it moves the
                        package to the archive while subscriptions, payments, prices and audit
                        history remain intact.
                    </p>
                    <p v-else class="mt-1 text-sm leading-6 text-slate-600">
                        This package is archived, hidden from new sales and retained only for
                        existing subscriptions, financial records and audit history.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <form
                        v-if="selectedPlan.status === 'draft'"
                        id="publish-plan-form"
                        :action="actions.publishPlan"
                        method="post"
                        @submit="beginSubmit('publish-plan', $event)"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input
                            type="hidden"
                            name="expected_version"
                            :value="selectedPlan.version"
                        />
                        <button
                            type="button"
                            class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:opacity-60"
                            :disabled="Boolean(submitting)"
                            @click="askForConfirmation('publish-plan')"
                        >
                            Publish plan
                        </button>
                    </form>
                    <form
                        v-if="selectedPlan.status === 'active'"
                        id="unavailable-plan-form"
                        :action="actions.unavailablePlan"
                        method="post"
                        @submit="beginSubmit('unavailable-plan', $event)"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input
                            type="hidden"
                            name="expected_version"
                            :value="selectedPlan.version"
                        />
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-amber-300 bg-white px-5 py-2 font-bold text-amber-900 disabled:opacity-60"
                            :disabled="Boolean(submitting)"
                            @click="askForConfirmation('unavailable-plan')"
                        >
                            Make unavailable
                        </button>
                    </form>
                    <form
                        v-if="selectedPlan.status === 'unavailable'"
                        id="grandfather-plan-form"
                        :action="actions.grandfatherPlan"
                        method="post"
                        @submit="beginSubmit('grandfather-plan', $event)"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input
                            type="hidden"
                            name="expected_version"
                            :value="selectedPlan.version"
                        />
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-sky-300 bg-white px-5 py-2 font-bold text-sky-900 disabled:opacity-60"
                            :disabled="Boolean(submitting)"
                            @click="askForConfirmation('grandfather-plan')"
                        >
                            Grandfather plan
                        </button>
                    </form>
                    <form
                        v-if="
                            ['draft', 'active', 'unavailable', 'grandfathered'].includes(
                                selectedPlan.status,
                            )
                        "
                        id="retire-plan-form"
                        :action="actions.retirePlan"
                        method="post"
                        @submit="beginSubmit('retire-plan', $event)"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input
                            type="hidden"
                            name="expected_version"
                            :value="selectedPlan.version"
                        />
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-rose-300 bg-white px-5 py-2 font-bold text-rose-800 disabled:opacity-60"
                            :disabled="Boolean(submitting)"
                            @click="askForConfirmation('retire-plan')"
                        >
                            Remove package
                        </button>
                    </form>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="offerings-heading">
                <h2 id="offerings-heading" class="text-lg font-bold text-slate-950">
                    Package prices
                </h2>
                <div v-if="offerings.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <a
                        v-for="offering in offerings"
                        :key="offering.id"
                        :href="offering.detailUrl"
                        class="rounded-2xl border bg-white p-5 shadow-sm transition hover:border-emerald-400"
                        :class="
                            selectedOffering?.id === offering.id
                                ? 'border-emerald-500 ring-2 ring-emerald-100'
                                : 'border-slate-200'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-bold">{{ offering.amount }}</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ offering.billingOptionLabel }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            >
                                {{ offering.statusLabel ?? offering.status }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ formatDate(offering.effectiveStart) }} –
                            {{ formatDate(offering.effectiveEnd) }}
                        </p>
                    </a>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No package prices"
                    description="Add the first price and billing cycle for this package."
                />
            </section>

            <div v-if="selectedOffering" class="space-y-4">
                <section
                    class="rounded-2xl border p-5"
                    :class="
                        selectedOffering.websiteReady
                            ? 'border-emerald-200 bg-emerald-50'
                            : 'border-amber-300 bg-amber-50'
                    "
                >
                    <p
                        class="font-bold"
                        :class="
                            selectedOffering.websiteReady ? 'text-emerald-900' : 'text-amber-950'
                        "
                    >
                        {{
                            selectedOffering.websiteReady
                                ? 'Ready for website display'
                                : 'Not yet visible on the website'
                        }}
                    </p>
                    <p
                        class="mt-1 text-sm leading-6"
                        :class="
                            selectedOffering.websiteReady ? 'text-emerald-800' : 'text-amber-900'
                        "
                    >
                        <template v-if="selectedOffering.websiteReady">
                            This price uses an approved public feature profile. It appears when the
                            plan, billing cycle and effective dates are also available.
                        </template>
                        <template v-else-if="!selectedOffering.profileConfigured">
                            Feature profile
                            <strong>{{ selectedOffering.featureConfiguration }}</strong> is not
                            configured. Configure its capabilities before publishing it for sale.
                        </template>
                        <template v-else-if="!selectedOffering.publiclyListed">
                            This feature profile is governed but is not included in the public
                            package order.
                        </template>
                        <template v-else>
                            Activate this price before it can appear on the website.
                        </template>
                    </p>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Price details</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ selectedOffering.amount }} ·
                                {{ selectedOffering.billingOptionLabel }} ·
                                {{ selectedOffering.statusLabel ?? selectedOffering.status }}
                            </p>
                        </div>
                        <a
                            v-if="selectedPlan.status !== 'retired'"
                            :href="actions.editOffering"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                        >
                            Edit price
                        </a>
                    </div>
                    <div v-if="selectedPlan.status !== 'retired'" class="mt-4 flex flex-wrap gap-3">
                        <form
                            v-if="selectedOffering.status === 'draft'"
                            id="activate-offering-form"
                            :action="actions.activate"
                            method="post"
                            @submit="beginSubmit('activate', $event)"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input
                                type="hidden"
                                name="expected_version"
                                :value="selectedOffering.version"
                            />
                            <button
                                type="button"
                                class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:opacity-60"
                                :disabled="Boolean(submitting)"
                                @click="askForConfirmation('activate')"
                            >
                                Activate price
                            </button>
                        </form>
                        <form
                            v-if="selectedOffering.status !== 'retired'"
                            id="retire-offering-form"
                            :action="actions.retire"
                            method="post"
                            @submit="beginSubmit('retire', $event)"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input
                                type="hidden"
                                name="expected_version"
                                :value="selectedOffering.version"
                            />
                            <button
                                type="button"
                                class="min-h-11 rounded-xl border border-rose-300 bg-white px-5 py-2 font-bold text-rose-800 disabled:opacity-60"
                                :disabled="Boolean(submitting)"
                                @click="askForConfirmation('retire')"
                            >
                                Retire price
                            </button>
                        </form>
                        <form
                            v-if="selectedOffering.status === 'active'"
                            id="unavailable-offering-form"
                            :action="actions.unavailable"
                            method="post"
                            @submit="beginSubmit('unavailable', $event)"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input
                                type="hidden"
                                name="expected_version"
                                :value="selectedOffering.version"
                            />
                            <button
                                type="button"
                                class="min-h-11 rounded-xl border border-amber-300 bg-white px-5 py-2 font-bold text-amber-900 disabled:opacity-60"
                                :disabled="Boolean(submitting)"
                                @click="askForConfirmation('unavailable')"
                            >
                                Make unavailable
                            </button>
                        </form>
                        <form
                            v-if="selectedOffering.status === 'unavailable'"
                            id="grandfather-offering-form"
                            :action="actions.grandfather"
                            method="post"
                            @submit="beginSubmit('grandfather', $event)"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input
                                type="hidden"
                                name="expected_version"
                                :value="selectedOffering.version"
                            />
                            <button
                                type="button"
                                class="min-h-11 rounded-xl border border-sky-300 bg-white px-5 py-2 font-bold text-sky-900 disabled:opacity-60"
                                :disabled="Boolean(submitting)"
                                @click="askForConfirmation('grandfather')"
                            >
                                Grandfather price
                            </button>
                        </form>
                    </div>
                </section>
                <details
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <summary
                        class="cursor-pointer list-none p-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <span class="flex items-center justify-between gap-4">
                            <span>
                                <span class="block text-lg font-bold text-slate-950">
                                    Price change history
                                </span>
                                <span class="mt-1 block text-sm font-normal text-slate-600">
                                    {{ pricingHistory.length }} retained revisions for financial and
                                    audit traceability.
                                </span>
                            </span>
                            <span aria-hidden="true" class="text-xl text-slate-500">⌄</span>
                        </span>
                    </summary>
                    <div class="border-t border-slate-200 p-5">
                        <p class="text-sm leading-6 text-slate-600">
                            The same amount may appear more than once when availability, effective
                            dates or other governed price metadata changed.
                        </p>
                        <ol v-if="pricingHistory.length" class="mt-4 space-y-4">
                            <li
                                v-for="price in pricingHistory"
                                :key="price.version"
                                class="border-l-2 border-emerald-200 pl-4"
                            >
                                <div class="flex flex-wrap justify-between gap-2">
                                    <p class="font-bold">{{ price.amount }}</p>
                                    <p class="text-sm text-slate-500">
                                        Revision {{ price.version }}
                                    </p>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ formatDate(price.effectiveStart) }} –
                                    {{ formatDate(price.effectiveEnd) }}
                                </p>
                            </li>
                        </ol>
                        <DashboardEmptyState
                            v-else
                            title="No recorded revisions"
                            description="This package price has no recorded changes yet."
                        />
                    </div>
                </details>
            </div>

            <section
                v-if="confirmation"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="commercial-confirmation-title"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
            >
                <div
                    class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
                >
                    <h2 id="commercial-confirmation-title" class="text-lg font-bold text-slate-950">
                        Confirm Commercial change
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        This change follows the existing Commercial lifecycle, authorization and
                        audit rules. Continue only if the new catalogue state is intended.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button
                            ref="confirmationButton"
                            type="submit"
                            :form="confirmationForm"
                            class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                            :disabled="Boolean(submitting)"
                        >
                            {{ submitting ? 'Applying…' : 'Confirm' }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                            :disabled="Boolean(submitting)"
                            @click="confirmation = ''"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </DashboardShell>
</template>
