<script setup>
import { computed } from 'vue';

const props = defineProps({
    documentType: { type: String, required: true },
    documentNumber: { type: String, required: true },
    backHref: { type: String, required: true },
    document: { type: Object, required: true },
});

const title = props.documentType === 'receipt' ? 'Payment receipt' : 'Subscription invoice';
const isReceipt = computed(() => props.documentType === 'receipt');
const normalizedStatus = computed(() => String(props.document.status).toLowerCase());
const invoiceStatus = computed(() => {
    if (['succeeded', 'paid'].some((status) => normalizedStatus.value.includes(status))) {
        return 'Paid';
    }

    if (
        ['pending', 'draft', 'awaiting'].some((status) => normalizedStatus.value.includes(status))
    ) {
        return 'Awaiting payment confirmation';
    }

    if (normalizedStatus.value.includes('failed')) {
        return 'Payment failed';
    }

    if (normalizedStatus.value.includes('cancelled')) {
        return 'Cancelled';
    }

    return props.document.status;
});
const paymentReceivedAt = computed(() => props.document.paidAt ?? props.document.issuedAt);
const recordDescription = computed(() =>
    isReceipt.value
        ? 'Official proof that payment was received'
        : 'Authoritative record of the subscription charge',
);

function printDocument() {
    window.print();
}
</script>

<template>
    <main class="min-h-screen bg-slate-100 px-4 py-8 text-slate-950 sm:px-6 lg:py-12">
        <div class="document-actions mx-auto mb-5 flex max-w-4xl flex-wrap justify-between gap-3">
            <a
                :href="backHref"
                class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-5 py-2 font-bold text-slate-900"
            >
                ← Back to billing
            </a>
            <button
                type="button"
                class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2 font-bold text-white"
                @click="printDocument"
            >
                Print / Save PDF
            </button>
        </div>

        <article
            class="billing-document mx-auto max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl"
        >
            <header
                class="flex flex-col gap-6 bg-slate-950 px-6 py-8 text-white sm:flex-row sm:items-start sm:justify-between sm:px-10"
            >
                <div>
                    <p class="text-sm font-black tracking-[0.22em] text-emerald-300">SYIFA.MY</p>
                    <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ title }}</h1>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ recordDescription }}
                    </p>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">
                        Document number
                    </p>
                    <p class="mt-2 break-all font-mono text-sm font-bold">{{ documentNumber }}</p>
                    <p class="mt-3 text-sm text-slate-300">Issued {{ document.issuedAt }}</p>
                </div>
            </header>

            <div class="space-y-8 px-6 py-8 sm:px-10 sm:py-10">
                <section class="grid gap-6 border-b border-slate-200 pb-8 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-slate-500">
                            {{ isReceipt ? 'Received from' : 'Billed to' }}
                        </p>
                        <p class="mt-2 text-xl font-black">{{ document.clinicName }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            Tenant {{ document.tenantReference }} · Subscription
                            {{ document.subscriptionReference }}
                        </p>
                    </div>
                    <dl class="grid gap-3 text-sm sm:text-right">
                        <div v-if="isReceipt">
                            <dt class="font-semibold text-slate-500">Payment received</dt>
                            <dd class="mt-1 font-black text-emerald-800">
                                {{ paymentReceivedAt }}
                            </dd>
                        </div>
                        <template v-else>
                            <div>
                                <dt class="font-semibold text-slate-500">Invoice status</dt>
                                <dd class="mt-1 font-black text-slate-950">{{ invoiceStatus }}</dd>
                            </div>
                            <div v-if="document.paidAt">
                                <dt class="font-semibold text-slate-500">Payment confirmed</dt>
                                <dd class="mt-1 font-bold">{{ document.paidAt }}</dd>
                            </div>
                        </template>
                    </dl>
                </section>

                <section
                    class="rounded-2xl border px-5 py-4 text-sm"
                    :class="
                        isReceipt
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                            : 'border-slate-200 bg-slate-50 text-slate-700'
                    "
                >
                    <p class="font-black">
                        {{
                            isReceipt
                                ? 'This is proof of payment.'
                                : 'This is a record of a charge.'
                        }}
                    </p>
                    <p class="mt-1 leading-6">
                        {{
                            isReceipt
                                ? 'It is issued only after SYIFA.my records a successful payment.'
                                : 'Its status shows whether the related payment has been confirmed.'
                        }}
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-black">
                        {{ isReceipt ? 'Payment details' : 'Charge details' }}
                    </h2>
                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                        <dl class="divide-y divide-slate-200 text-sm">
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr]">
                                <dt class="font-semibold text-slate-500">Charge</dt>
                                <dd class="font-bold">{{ document.purpose }}</dd>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr]">
                                <dt class="font-semibold text-slate-500">Plan</dt>
                                <dd class="font-bold">{{ document.plan }}</dd>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr]">
                                <dt class="font-semibold text-slate-500">Billing cycle</dt>
                                <dd class="font-bold">{{ document.billingCycle }}</dd>
                            </div>
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[12rem_1fr]">
                                <dt class="font-semibold text-slate-500">Service period</dt>
                                <dd class="font-bold">{{ document.period }}</dd>
                            </div>
                        </dl>
                        <div
                            class="flex items-center justify-between gap-4 bg-emerald-50 px-5 py-5"
                        >
                            <p class="font-black">
                                {{ isReceipt ? 'Amount received' : 'Invoice amount' }}
                            </p>
                            <p class="text-2xl font-black text-emerald-900">
                                {{ document.amount }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-slate-50 p-5 text-sm">
                    <h2 class="font-black">
                        {{ isReceipt ? 'Payment evidence' : 'Related payment' }}
                    </h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-slate-500">Payment reference</dt>
                            <dd class="mt-1 font-mono font-bold">
                                {{ document.paymentReference }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Provider</dt>
                            <dd class="mt-1 font-bold">{{ document.provider }}</dd>
                        </div>
                        <div v-if="document.providerReference" class="sm:col-span-2">
                            <dt class="font-semibold text-slate-500">Provider reference</dt>
                            <dd class="mt-1 break-all font-mono font-bold">
                                {{ document.providerReference }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <p class="text-xs leading-5 text-slate-500">
                    This system-generated {{ isReceipt ? 'receipt' : 'invoice' }} reflects
                    SYIFA.my's authoritative subscription and payment records. It is not a tax
                    invoice or MyInvois document.
                </p>
            </div>
        </article>
    </main>
</template>

<style>
@media print {
    @page {
        margin: 12mm;
    }

    .document-actions {
        display: none !important;
    }

    .billing-document {
        border: 0 !important;
        box-shadow: none !important;
    }

    body {
        background: white !important;
    }
}
</style>
