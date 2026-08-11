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
    subscription: { type: Object, required: true },
    timeline: { type: Array, required: true },
    payments: { type: Array, required: true },
    documents: { type: Array, required: true },
    actions: { type: Object, required: true },
    feedback: { type: Object, required: true },
});
const navigation = createDashboardNavigation(props.navigation);
const checkoutConfirmation = ref(false);
const checkoutSubmitting = ref(false);
const renewalConfirmation = ref(false);
const renewalSubmitting = ref(false);
const autoRenewConfirmation = ref(false);
const autoRenewSubmitting = ref(false);
const planChangeConfirmation = ref(false);
const planChangeSubmitting = ref(false);
const selectedOfferingId = ref('');
const documentsByPayment = computed(
    () => new Map(props.documents.map((document) => [document.paymentId, document])),
);

function statusClass(status) {
    const normalized = String(status).toLowerCase();

    if (['active', 'enabled', 'succeeded', 'paid'].some((value) => normalized.includes(value))) {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (['pending', 'renewal due', 'draft'].some((value) => normalized.includes(value))) {
        return 'bg-amber-100 text-amber-900';
    }

    if (
        ['failed', 'expired', 'cancelled', 'disabled'].some((value) => normalized.includes(value))
    ) {
        return 'bg-rose-100 text-rose-800';
    }

    return 'bg-slate-100 text-slate-700';
}

function confirmCheckout() {
    checkoutSubmitting.value = true;
}

function confirmRenewal() {
    renewalSubmitting.value = true;
}

function confirmAutoRenew() {
    autoRenewSubmitting.value = true;
}

function confirmPlanChange() {
    planChangeSubmitting.value = true;
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
        <div
            v-if="feedback.success"
            role="status"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900"
        >
            {{ feedback.success }}
        </div>
        <div
            v-if="feedback.error"
            role="alert"
            class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900"
        >
            {{ feedback.error }}
        </div>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Subscription status">
            <article
                v-for="item in [
                    ['Subscription', subscription.status],
                    ['Renewal', subscription.renewalStatus],
                    ['Auto-renew', subscription.autoRenewStatus],
                    ['Current term', `${subscription.startsOn} – ${subscription.endsOn}`],
                ]"
                :key="item[0]"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-sm font-semibold text-slate-500">{{ item[0] }}</p>
                <p
                    class="mt-2 inline-flex rounded-full px-2.5 py-1 text-sm font-bold"
                    :class="
                        item[0] === 'Current term'
                            ? 'bg-slate-100 text-slate-800'
                            : statusClass(item[1])
                    "
                >
                    {{ item[1] }}
                </p>
            </article>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                    Subscription controls
                </p>
                <h2 class="mt-2 text-lg font-bold text-slate-950">Available actions</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Each action uses the current subscription version and refreshes the
                    authoritative state after completion.
                </p>
            </div>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <form
                    id="change-plan-form"
                    :action="actions.changePlan.action"
                    method="post"
                    class="flex flex-col gap-2 sm:flex-row"
                    @submit="confirmPlanChange"
                >
                    <input type="hidden" name="_token" :value="actions.csrfToken" />
                    <input
                        type="hidden"
                        name="expected_version"
                        :value="actions.changePlan.expectedVersion"
                    />
                    <select
                        v-model="selectedOfferingId"
                        name="plan_offering_id"
                        required
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-900"
                    >
                        <option value="" disabled>Select package</option>
                        <option
                            v-for="offering in actions.changePlan.offerings"
                            :key="offering.id"
                            :value="offering.id"
                            :disabled="offering.current"
                        >
                            {{ offering.label }}{{ offering.current ? ' (Current)' : '' }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:opacity-50"
                        :disabled="!selectedOfferingId || planChangeSubmitting"
                        @click="planChangeConfirmation = true"
                    >
                        Change package
                    </button>
                </form>
                <form
                    id="manual-renewal-form"
                    :action="actions.renew.action"
                    method="post"
                    @submit="confirmRenewal"
                >
                    <input type="hidden" name="_token" :value="actions.csrfToken" />
                    <input
                        type="hidden"
                        name="expected_version"
                        :value="actions.renew.expectedVersion"
                    />
                    <input
                        type="hidden"
                        name="idempotency_key"
                        :value="actions.renew.idempotencyKey"
                    />
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                        :disabled="renewalSubmitting"
                        @click="renewalConfirmation = true"
                    >
                        {{ actions.renew.label }}
                    </button>
                </form>
                <form
                    id="auto-renew-form"
                    :action="
                        actions.autoRenew.enabled
                            ? actions.autoRenew.disableAction
                            : actions.autoRenew.enableAction
                    "
                    method="post"
                    @submit="confirmAutoRenew"
                >
                    <input type="hidden" name="_token" :value="actions.csrfToken" />
                    <input
                        type="hidden"
                        name="expected_version"
                        :value="actions.autoRenew.expectedVersion"
                    />
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                        :disabled="autoRenewSubmitting"
                        @click="autoRenewConfirmation = true"
                    >
                        {{ actions.autoRenew.enabled ? 'Disable auto-renew' : 'Enable auto-renew' }}
                    </button>
                </form>
                <form
                    v-if="actions.checkout"
                    id="renewal-checkout-form"
                    :action="actions.checkout.action"
                    method="post"
                    @submit="confirmCheckout"
                >
                    <input type="hidden" name="_token" :value="actions.csrfToken" />
                    <button
                        type="button"
                        class="min-h-11 w-full rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                        :disabled="checkoutSubmitting"
                        @click="checkoutConfirmation = true"
                    >
                        {{ actions.checkout.label }}
                    </button>
                </form>
            </div>
        </section>
        <section
            v-if="planChangeConfirmation"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="plan-change-title"
            class="rounded-2xl border-2 border-emerald-300 bg-white p-5 shadow-lg"
        >
            <h2 id="plan-change-title" class="text-lg font-bold text-slate-950">
                Change subscriber package?
            </h2>
            <p class="mt-2 text-sm text-slate-600">
                The new price and feature entitlement take effect immediately. The current term
                dates remain unchanged.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="submit"
                    form="change-plan-form"
                    class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:opacity-60"
                    :disabled="planChangeSubmitting"
                >
                    {{ planChangeSubmitting ? 'Changing…' : 'Confirm package change' }}
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                    :disabled="planChangeSubmitting"
                    @click="planChangeConfirmation = false"
                >
                    Cancel
                </button>
            </div>
        </section>
        <section
            v-if="renewalConfirmation"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="manual-renewal-confirmation-title"
            class="rounded-2xl border-2 border-slate-300 bg-white p-5 shadow-lg"
        >
            <h2 id="manual-renewal-confirmation-title" class="text-lg font-bold text-slate-950">
                Request manual renewal?
            </h2>
            <p class="mt-2 text-sm text-slate-600">
                This records one governed renewal request for the current subscription term. It does
                not record a successful payment.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="submit"
                    form="manual-renewal-form"
                    class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60"
                    :disabled="renewalSubmitting"
                >
                    {{ renewalSubmitting ? 'Requesting…' : 'Confirm renewal request' }}
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                    :disabled="renewalSubmitting"
                    @click="renewalConfirmation = false"
                >
                    Cancel
                </button>
            </div>
        </section>
        <section
            v-if="autoRenewConfirmation"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="auto-renew-confirmation-title"
            class="rounded-2xl border-2 border-slate-300 bg-white p-5 shadow-lg"
        >
            <h2 id="auto-renew-confirmation-title" class="text-lg font-bold text-slate-950">
                {{ actions.autoRenew.enabled ? 'Disable auto-renew?' : 'Enable auto-renew?' }}
            </h2>
            <p class="mt-2 text-sm text-slate-600">
                Confirm this change to the subscription renewal preference.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="submit"
                    form="auto-renew-form"
                    class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60"
                    :disabled="autoRenewSubmitting"
                >
                    {{ autoRenewSubmitting ? 'Saving…' : 'Confirm' }}
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                    :disabled="autoRenewSubmitting"
                    @click="autoRenewConfirmation = false"
                >
                    Cancel
                </button>
            </div>
        </section>
        <section
            v-if="checkoutConfirmation"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="renewal-checkout-confirmation-title"
            class="rounded-2xl border-2 border-slate-300 bg-white p-5 shadow-lg"
        >
            <h2 id="renewal-checkout-confirmation-title" class="text-lg font-bold text-slate-950">
                Start Renewal Checkout?
            </h2>
            <p class="mt-2 text-sm text-slate-600">
                You will be redirected to the hosted payment page for this renewal.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="submit"
                    form="renewal-checkout-form"
                    class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                    :disabled="checkoutSubmitting"
                >
                    {{ checkoutSubmitting ? 'Starting checkout…' : 'Continue' }}
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                    :disabled="checkoutSubmitting"
                    @click="checkoutConfirmation = false"
                >
                    Cancel
                </button>
            </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Clinic subscription</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-950">
                        {{ subscription.clinicName }}
                    </h2>
                </div>
                <span
                    class="inline-flex rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-xs font-bold tracking-wide text-slate-800"
                >
                    {{ subscription.reference }}
                </span>
            </div>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="font-semibold text-slate-500">Subscription reference</dt>
                    <dd class="mt-1 font-semibold text-slate-950">
                        {{ subscription.reference }}
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Tenant reference</dt>
                    <dd class="mt-1 font-semibold text-slate-950">
                        {{ subscription.tenantReference }}
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Plan</dt>
                    <dd class="mt-1 font-semibold text-slate-950">
                        {{ subscription.planName }}
                    </dd>
                    <dd class="mt-0.5 text-xs text-slate-500">
                        {{ subscription.planReference }}
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Amount</dt>
                    <dd class="mt-1">{{ subscription.amount }}</dd>
                </div>
            </dl>
        </section>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-950">Renewal timeline</h2>
                <ol v-if="timeline.length" class="mt-4 space-y-4">
                    <li
                        v-for="item in timeline"
                        :key="item.id"
                        class="border-l-2 border-emerald-200 pl-4"
                    >
                        <p class="font-semibold">{{ item.label }}</p>
                        <p class="text-sm text-slate-500">{{ item.occurredAt }}</p>
                    </li>
                </ol>
                <DashboardEmptyState
                    v-else
                    title="No renewal events"
                    description="No authoritative renewal timeline has been recorded."
                />
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-950">Payment history</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Payment status tracks the transaction. The invoice records the charge; the
                    receipt confirms that payment was received.
                </p>
                <div v-if="payments.length" class="mt-4 space-y-4">
                    <article
                        v-for="payment in payments"
                        :key="payment.id"
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <div class="flex flex-wrap justify-between gap-2">
                            <p
                                class="rounded-lg bg-white px-2.5 py-1 font-mono text-xs font-bold tracking-wide text-slate-800"
                            >
                                {{ payment.reference }}
                            </p>
                            <p class="font-bold">{{ payment.amount }}</p>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ payment.purpose }}
                            <span
                                class="ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="statusClass(payment.status)"
                            >
                                {{ payment.status }}
                            </span>
                        </p>
                        <div
                            v-if="documentsByPayment.has(payment.id)"
                            class="mt-3 flex flex-wrap gap-3 text-sm"
                        >
                            <a
                                :href="documentsByPayment.get(payment.id).invoiceHref"
                                class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-bold text-slate-900"
                                :title="documentsByPayment.get(payment.id).invoiceNumber"
                            >
                                View invoice
                            </a>
                            <a
                                v-if="documentsByPayment.get(payment.id).receiptHref"
                                :href="documentsByPayment.get(payment.id).receiptHref"
                                class="inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-3 py-1.5 font-bold text-white"
                                :title="documentsByPayment.get(payment.id).receiptNumber"
                            >
                                View receipt
                            </a>
                        </div>
                    </article>
                </div>
                <DashboardEmptyState
                    v-else
                    title="No payments"
                    description="No payment history is available."
                />
            </section>
        </div>
    </DashboardShell>
</template>
