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
    subscription: { type: Object, default: null },
    renewal: { type: Object, default: null },
    feedback: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const confirmationOpen = ref(false);
const submitting = ref(false);

function submitRenewal() {
    submitting.value = true;
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
        <div class="space-y-6">
            <div
                v-if="feedback.error"
                role="alert"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900"
            >
                {{ feedback.error }}
            </div>

            <DashboardEmptyState
                v-if="!subscription"
                title="No subscription available"
                description="No authoritative subscription is currently available for this clinic."
            />

            <template v-else>
                <section
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    aria-label="Subscription details"
                >
                    <article
                        v-for="item in [
                            ['Current plan', subscription.plan],
                            ['Status', subscription.status],
                            ['Billing cycle', subscription.billingCycle],
                            ['Start date', subscription.startsOn],
                            ['Term end date', subscription.endsOn],
                            ['Renewal eligibility', subscription.renewalStatus],
                            ['Latest payment', subscription.latestPaymentStatus],
                        ]"
                        :key="item[0]"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-sm font-semibold text-slate-500">{{ item[0] }}</p>
                        <p class="mt-2 font-bold text-slate-950">{{ item[1] }}</p>
                    </article>
                </section>

                <section
                    v-if="renewal"
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
                >
                    <h2 class="text-lg font-bold text-slate-950">Renewal available</h2>
                    <p class="mt-2 text-sm text-slate-700">
                        Continue to the configured provider's secure hosted checkout.
                    </p>
                    <form
                        id="clinic-owner-renewal-form"
                        :action="renewal.action"
                        method="post"
                        class="mt-4"
                        @submit="submitRenewal"
                    >
                        <input type="hidden" name="_token" :value="renewal.csrfToken" />
                        <button
                            type="button"
                            class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60"
                            :disabled="submitting"
                            @click="confirmationOpen = true"
                        >
                            {{ renewal.label }}
                        </button>
                    </form>
                </section>

                <section
                    v-if="confirmationOpen"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="renewal-confirmation-title"
                    class="rounded-2xl border-2 border-slate-300 bg-white p-5 shadow-lg"
                >
                    <h2 id="renewal-confirmation-title" class="text-lg font-bold text-slate-950">
                        Renew Subscription?
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        You will be redirected to a secure hosted payment page.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button
                            type="submit"
                            form="clinic-owner-renewal-form"
                            class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60"
                            :disabled="submitting"
                        >
                            {{ submitting ? 'Starting checkout…' : 'Continue' }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
                            :disabled="submitting"
                            @click="confirmationOpen = false"
                        >
                            Cancel
                        </button>
                    </div>
                </section>
            </template>
        </div>
    </DashboardShell>
</template>
