<script setup>
import { ref } from 'vue';
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
    actions: { type: Object, required: true },
    feedback: { type: Object, required: true },
});
const navigation = createDashboardNavigation(props.navigation);
const checkoutConfirmation = ref(false);
const checkoutSubmitting = ref(false);
const autoRenewConfirmation = ref(false);
const autoRenewSubmitting = ref(false);

function confirmCheckout() {
    checkoutSubmitting.value = true;
}

function confirmAutoRenew() {
    autoRenewSubmitting.value = true;
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
                <p class="mt-2 font-bold text-slate-950">{{ item[1] }}</p>
            </article>
        </section>
        <section
            class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:flex-wrap"
        >
            <form :action="actions.renew.action" method="post">
                <input type="hidden" name="_token" :value="actions.csrfToken" />
                <input
                    type="hidden"
                    name="expected_version"
                    :value="actions.renew.expectedVersion"
                />
                <input type="hidden" name="idempotency_key" :value="actions.renew.idempotencyKey" />
                <button
                    type="submit"
                    class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white"
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
                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900 disabled:cursor-wait disabled:opacity-60"
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
                    class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white"
                    :disabled="checkoutSubmitting"
                    @click="checkoutConfirmation = true"
                >
                    {{ actions.checkout.label }}
                </button>
            </form>
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
            <h2 class="text-lg font-bold text-slate-950">Subscription</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="font-semibold text-slate-500">ID</dt>
                    <dd class="mt-1 break-all">{{ subscription.id }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Tenant</dt>
                    <dd class="mt-1 break-all">{{ subscription.tenantId }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Plan</dt>
                    <dd class="mt-1">{{ subscription.planId }}</dd>
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
                <div v-if="payments.length" class="mt-4 space-y-4">
                    <article
                        v-for="payment in payments"
                        :key="payment.id"
                        class="rounded-xl bg-slate-50 p-4"
                    >
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="break-all font-semibold">{{ payment.id }}</p>
                            <p class="font-bold">{{ payment.amount }}</p>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ payment.purpose }} · {{ payment.status }}
                        </p>
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
