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
    upgradePlans: { type: Array, default: () => [] },
    renewal: { type: Object, default: null },
    documents: { type: Array, required: true },
    feedback: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const confirmationOpen = ref(false);
const submitting = ref(false);

function submitRenewal() {
    submitting.value = true;
}

function statusClass(status) {
    const normalized = String(status).toLowerCase();

    if (['active', 'succeeded', 'available', 'paid'].some((value) => normalized.includes(value))) {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (['pending', 'renewal due', 'draft'].some((value) => normalized.includes(value))) {
        return 'bg-amber-100 text-amber-900';
    }

    if (['failed', 'expired', 'cancelled'].some((value) => normalized.includes(value))) {
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
                    v-if="subscription.isTrial"
                    class="overflow-hidden rounded-3xl bg-gradient-to-br from-sky-950 via-emerald-900 to-emerald-700 p-6 text-white shadow-xl sm:p-8"
                >
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p
                                class="text-xs font-black uppercase tracking-[0.18em] text-emerald-200"
                            >
                                Percubaan percuma sedang aktif
                            </p>
                            <h2 class="mt-3 text-2xl font-black sm:text-3xl">
                                {{ subscription.trialDaysRemaining }} hari berbaki untuk meneroka
                                SYIFA.my
                            </h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50">
                                Tiada bayaran diperlukan sepanjang trial. Pilih pakej di bawah bila
                                anda bersedia; akses trial kekal aktif sehingga
                                {{ subscription.endsOn }}.
                            </p>
                        </div>
                        <span
                            class="inline-flex w-fit rounded-full bg-white/15 px-4 py-2 text-sm font-black ring-1 ring-white/25"
                        >
                            RM0 · Tanpa kad
                        </span>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm"
                >
                    <div class="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
                        <div class="p-6 sm:p-8">
                            <p
                                class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700"
                            >
                                Current subscription
                            </p>
                            <h2 class="mt-3 text-3xl font-black text-slate-950">
                                {{ subscription.plan }}
                            </h2>
                            <p class="mt-3 max-w-xl text-slate-600">
                                Your {{ subscription.billingCycle.toLowerCase() }} plan is valid
                                from {{ subscription.startsOn }} until {{ subscription.endsOn }}.
                            </p>
                        </div>
                        <div
                            class="border-t border-emerald-200 bg-emerald-50 p-6 lg:border-l lg:border-t-0"
                        >
                            <p class="text-sm font-semibold text-slate-600">Plan status</p>
                            <span
                                class="mt-2 inline-flex rounded-full px-3 py-1.5 text-sm font-black"
                                :class="statusClass(subscription.status)"
                            >
                                {{ subscription.status }}
                            </span>
                            <p class="mt-5 text-sm font-semibold text-slate-600">
                                {{ subscription.isTrial ? 'Trial charge' : 'Latest payment' }}
                            </p>
                            <span
                                class="mt-2 inline-flex rounded-full px-3 py-1.5 text-sm font-black"
                                :class="statusClass(subscription.latestPaymentStatus)"
                            >
                                {{
                                    subscription.isTrial
                                        ? 'No payment required'
                                        : subscription.latestPaymentStatus
                                }}
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    v-if="subscription.isTrial && upgradePlans.length"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
                >
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">
                        Upgrade bila anda bersedia
                    </p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">
                        Pilih pakej selepas trial
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Pasukan kami akan sahkan pilihan, sediakan pembayaran selamat dan memastikan
                        akses klinik tidak terputus.
                    </p>
                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        <article
                            v-for="plan in upgradePlans"
                            :key="plan.name"
                            class="relative rounded-2xl border p-5"
                            :class="
                                plan.recommended
                                    ? 'border-emerald-400 bg-emerald-50'
                                    : 'border-slate-200 bg-slate-50'
                            "
                        >
                            <span
                                v-if="plan.recommended"
                                class="absolute right-4 top-4 rounded-full bg-emerald-700 px-3 py-1 text-xs font-black text-white"
                                >Disyorkan</span
                            >
                            <h3 class="text-xl font-black text-slate-950">{{ plan.name }}</h3>
                            <p class="mt-1 text-lg font-bold text-emerald-800">{{ plan.price }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ plan.description }}
                            </p>
                            <a
                                :href="plan.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-center text-sm font-black text-white transition hover:bg-emerald-800"
                            >
                                Pilih {{ plan.name }} & teruskan pembayaran
                            </a>
                        </article>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">
                        Anda tidak akan dicaj hanya dengan membuka WhatsApp. Bayaran dibuat selepas
                        pakej disahkan.
                    </p>
                </section>

                <section
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Subscription details"
                >
                    <article
                        v-for="item in [
                            ['Billing cycle', subscription.billingCycle],
                            ['Start date', subscription.startsOn],
                            ['Term end date', subscription.endsOn],
                            ['Renewal eligibility', subscription.renewalStatus],
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
                        Continue to the configured payment provider's secure checkout. No payment is
                        recorded until the provider confirms it successfully.
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
                            class="min-h-11 w-full rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                            :disabled="submitting"
                            @click="confirmationOpen = true"
                        >
                            {{ renewal.label }}
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700"
                            >
                                Billing documents
                            </p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">
                                Invoices and receipts
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                An invoice is available for every recorded charge. A receipt appears
                                only after its payment succeeds.
                            </p>
                        </div>
                    </div>
                    <div v-if="documents.length" class="mt-5 space-y-3">
                        <article
                            v-for="document in documents"
                            :key="document.paymentId"
                            class="flex flex-col gap-4 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-black text-slate-950">{{ document.purpose }}</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ document.amount }} · {{ document.status }} ·
                                    {{ document.issuedAt }}
                                </p>
                                <p class="mt-2 font-mono text-xs text-slate-500">
                                    {{ document.invoiceNumber }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    :href="document.invoiceHref"
                                    class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 font-bold text-slate-900"
                                    :title="document.invoiceNumber"
                                >
                                    View invoice
                                </a>
                                <a
                                    v-if="document.receiptHref"
                                    :href="document.receiptHref"
                                    class="inline-flex min-h-11 items-center rounded-xl bg-emerald-700 px-4 py-2 font-bold text-white"
                                    :title="document.receiptNumber"
                                >
                                    View receipt
                                </a>
                            </div>
                        </article>
                    </div>
                    <DashboardEmptyState
                        v-else
                        title="No billing documents"
                        description="Invoices will appear when a subscription payment is recorded."
                    />
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
