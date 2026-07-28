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
    if (confirmation.value === 'publish') return 'publish-plan-form';
    if (confirmation.value === 'retire-plan') return 'retire-plan-form';

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
            <section class="space-y-4" aria-labelledby="plans-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="plans-heading" class="text-lg font-bold text-slate-950">Plans</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Select a governed plan to view its annual offerings.
                        </p>
                    </div>
                    <a
                        :href="actions.createPlan"
                        class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 py-2 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        Create Plan
                    </a>
                </div>
                <div v-if="plans.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <a
                        v-for="plan in plans"
                        :key="plan.id"
                        :href="plan.detailUrl"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
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
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                                {{ plan.status }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">{{ plan.description }}</p>
                    </a>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No commercial plans"
                    description="Create a governed plan before adding offerings."
                />
            </section>

            <section class="space-y-4" aria-labelledby="catalogue-offerings-heading">
                <h2 id="catalogue-offerings-heading" class="text-lg font-bold text-slate-950">
                    Offerings
                </h2>
                <div v-if="offerings.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <a
                        v-for="offering in offerings"
                        :key="offering.id"
                        :href="offering.detailUrl"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <div class="flex justify-between gap-3">
                            <p class="text-lg font-bold">{{ offering.amount }}</p>
                            <span class="text-sm font-semibold text-slate-600">
                                {{ offering.status }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ offering.effectiveStart }} –
                            {{ offering.effectiveEnd ?? 'No end date' }}
                        </p>
                    </a>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No offerings"
                    description="Open a plan to create its first offering."
                />
            </section>

            <section class="space-y-4" aria-labelledby="billing-options-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="billing-options-heading" class="text-lg font-bold text-slate-950">
                            Billing Options
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Govern the billing cycles available when creating an offering.
                        </p>
                    </div>
                    <a
                        :href="actions.createBillingOption"
                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        Create Billing Option
                    </a>
                </div>
                <div v-if="billingOptions.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">
                                {{ option.availability }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">
                            {{
                                option.recurrence === 'recurring'
                                    ? `Every ${option.intervalCount} ${option.intervalUnit}`
                                    : 'One-off'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Effective {{ option.effectiveStart }} –
                            {{ option.effectiveEnd ?? 'No end date' }}
                        </p>
                        <a
                            :href="option.editUrl"
                            class="mt-4 inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            Edit Billing Option
                        </a>
                    </article>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No billing options"
                    description="Create a billing option before creating an offering."
                />
            </section>
        </template>

        <template v-else>
            <section class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
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
                            {{ selectedPlan.status }}
                        </span>
                    </div>
                    <p class="mt-4 text-slate-600">{{ selectedPlan.description }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a
                            :href="actions.editPlan"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                        >
                            Edit Plan
                        </a>
                        <a
                            :href="actions.createOffering"
                            class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 py-2 font-bold text-white"
                        >
                            Create Offering
                        </a>
                    </div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-950">Feature definitions</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li
                            v-for="capability in capabilities"
                            :key="capability.id"
                            class="flex justify-between gap-3"
                        >
                            <span>{{ capability.name }}</span>
                            <code class="text-xs text-slate-500">{{ capability.key }}</code>
                        </li>
                    </ul>
                </article>
            </section>

            <section
                class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <form
                    v-if="selectedPlan.status === 'draft'"
                    id="publish-plan-form"
                    :action="actions.publishPlan"
                    method="post"
                    @submit="beginSubmit('publish', $event)"
                >
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="expected_version" :value="selectedPlan.version" />
                    <button
                        type="button"
                        class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:opacity-60"
                        :disabled="Boolean(submitting)"
                        @click="askForConfirmation('publish')"
                    >
                        Publish plan
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
                    <input type="hidden" name="expected_version" :value="selectedPlan.version" />
                    <button
                        type="button"
                        class="min-h-11 rounded-xl border border-rose-300 bg-white px-5 py-2 font-bold text-rose-800 disabled:opacity-60"
                        :disabled="Boolean(submitting)"
                        @click="askForConfirmation('retire-plan')"
                    >
                        Retire plan
                    </button>
                </form>
            </section>

            <section class="space-y-4" aria-labelledby="offerings-heading">
                <h2 id="offerings-heading" class="text-lg font-bold text-slate-950">
                    Plan offerings
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
                        <div class="flex justify-between gap-3">
                            <p class="text-lg font-bold">{{ offering.amount }}</p>
                            <span class="text-sm font-semibold text-slate-600">
                                {{ offering.status }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ offering.effectiveStart }} –
                            {{ offering.effectiveEnd ?? 'No end date' }}
                        </p>
                    </a>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No offerings"
                    description="Create the first annual offering for this plan."
                />
            </section>

            <div v-if="selectedOffering" class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-950">Offering details</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ selectedOffering.amount }} · {{ selectedOffering.status }}
                            </p>
                        </div>
                        <a
                            :href="actions.editOffering"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                        >
                            Edit Offering
                        </a>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
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
                                Activate offering
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
                                Retire offering
                            </button>
                        </form>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">Pricing history</h2>
                    <ol v-if="pricingHistory.length" class="mt-4 space-y-4">
                        <li
                            v-for="price in pricingHistory"
                            :key="price.version"
                            class="border-l-2 border-emerald-200 pl-4"
                        >
                            <div class="flex flex-wrap justify-between gap-2">
                                <p class="font-bold">{{ price.amount }}</p>
                                <p class="text-sm text-slate-500">Version {{ price.version }}</p>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ price.effectiveStart }} –
                                {{ price.effectiveEnd ?? 'No end date' }}
                            </p>
                        </li>
                    </ol>
                    <DashboardEmptyState
                        v-else
                        title="No pricing history"
                        description="This offering has no recorded price version."
                    />
                </section>
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
                        Confirm lifecycle action
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        This action is governed by the existing Commercial lifecycle rules.
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
