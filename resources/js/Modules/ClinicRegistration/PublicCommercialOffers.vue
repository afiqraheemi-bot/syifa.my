<script setup>
import { ref } from 'vue';
import { browserHttpRequest } from '../../Shared/Authentication/session.js';

const props = defineProps({
    registrationStatus: { type: String, required: true },
    clinicName: { type: String, default: null },
    offers: { type: Array, required: true },
    selectionUrl: { type: String, required: true },
    demoPaymentUrl: { type: String, default: null },
    homeUrl: { type: String, required: true },
});

const selecting = ref('');
const message = ref('');
const error = ref('');
const demoCompleting = ref(false);
const demoCompleted = ref(false);

async function completeDemoPayment() {
    if (
        demoCompleting.value ||
        !window.confirm('Complete a local demo payment and start provisioning?')
    )
        return;

    demoCompleting.value = true;
    message.value = '';
    error.value = '';
    try {
        const result = await browserHttpRequest(props.demoPaymentUrl, { method: 'POST' });
        if (!result.ok) {
            throw new Error(
                result.body?.detail ??
                    result.body?.message ??
                    'Pembayaran demo tidak dapat diselesaikan.',
            );
        }
        demoCompleted.value = true;
        message.value =
            'Pembayaran demo dan penyediaan akaun telah selesai. Klinik anda kini sedia untuk proses onboarding.';
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'Pembayaran demo tidak dapat diselesaikan.';
    } finally {
        demoCompleting.value = false;
    }
}

async function selectOffer(offer) {
    if (selecting.value || !window.confirm(`Continue with ${offer.planName}?`)) return;

    selecting.value = offer.planOfferingId;
    message.value = '';
    error.value = '';

    try {
        const result = await browserHttpRequest(props.selectionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_offering_id: offer.planOfferingId }),
        });

        if (result.ok) {
            const redirect = result.body?.redirect_action;
            if (
                redirect?.kind !== 'redirect' ||
                redirect?.method !== 'GET' ||
                typeof redirect?.destination !== 'string'
            ) {
                throw new Error('Hosted checkout response is invalid.');
            }

            message.value = 'Hosted checkout is ready. Redirecting securely…';
            window.location.assign(redirect.destination);
        } else {
            error.value =
                result.body?.detail ?? 'Hosted checkout could not be started. Please try again.';
        }
    } catch {
        error.value = 'The commercial offer service is temporarily unavailable.';
    } finally {
        selecting.value = '';
    }
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-950 sm:px-6 sm:py-12">
        <section class="mx-auto max-w-4xl rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[0.18em] text-emerald-700">SYIFA.MY</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">Pilih pelan tahunan anda</h1>
            <p class="mt-3 text-slate-600">
                Permohonan {{ clinicName || 'klinik anda' }} telah diluluskan. Pilih tawaran tahunan
                untuk meneruskan pembayaran.
            </p>

            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">
                Status permohonan:
                <strong class="capitalize">{{ registrationStatus }}</strong>
            </div>
            <div
                v-if="demoPaymentUrl"
                class="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950"
            >
                <p class="font-bold">Pembayaran demo tempatan</p>
                <p class="mt-1 text-sm leading-6">
                    Lengkapkan perjalanan demo menggunakan aliran subscription dan provisioning
                    sebenar tanpa membuka penyedia pembayaran luar. Pilihan ini tidak tersedia dalam
                    production.
                </p>
                <button
                    type="button"
                    :disabled="demoCompleting || demoCompleted"
                    class="mt-4 min-h-11 rounded-xl bg-amber-900 px-5 font-bold text-white disabled:opacity-60"
                    @click="completeDemoPayment"
                >
                    {{
                        demoCompleting
                            ? 'Memproses demo…'
                            : demoCompleted
                              ? 'Pembayaran Demo Selesai'
                              : 'Selesaikan Pembayaran Demo'
                    }}
                </button>
            </div>

            <div
                v-if="demoCompleted"
                class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950"
            >
                <p class="font-bold">Langkah seterusnya</p>
                <p class="mt-1 text-sm leading-6">
                    Login sebagai Clinic Owner untuk membuka dashboard. Super Admin kini boleh
                    menetapkan Website Designer kepada onboarding klinik ini.
                </p>
                <a
                    :href="homeUrl"
                    class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-emerald-800 px-5 font-bold text-white"
                >
                    Terus ke Login Klinik
                </a>
            </div>

            <div v-else-if="offers.length" class="mt-8 grid gap-5 md:grid-cols-2">
                <article
                    v-for="offer in offers"
                    :key="offer.planOfferingId"
                    class="flex flex-col rounded-2xl border border-slate-200 p-6 shadow-sm"
                >
                    <p class="text-sm font-bold uppercase tracking-wide text-emerald-700">
                        {{ offer.billingCycleName }}
                    </p>
                    <h2 class="mt-2 text-2xl font-bold">{{ offer.planName }}</h2>
                    <p class="mt-4 text-3xl font-bold">{{ offer.formattedPrice }}</p>
                    <p class="mt-1 text-sm text-slate-500">per annual billing cycle</p>

                    <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <strong class="block text-slate-900">Termasuk penyediaan</strong>
                        {{ offer.includedSetup }}
                    </div>

                    <button
                        type="button"
                        :disabled="Boolean(selecting)"
                        class="mt-6 min-h-12 rounded-xl bg-emerald-700 px-5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        @click="selectOffer(offer)"
                    >
                        {{
                            selecting === offer.planOfferingId
                                ? 'Menyediakan checkout…'
                                : 'Teruskan ke Checkout'
                        }}
                    </button>
                </article>
            </div>

            <div
                v-else-if="!demoCompleted"
                class="mt-8 rounded-2xl border border-slate-200 p-6 text-slate-700"
            >
                Tiada tawaran tahunan yang boleh dibeli buat masa ini. Sila cuba lagi kemudian.
            </div>

            <p v-if="message" role="status" class="mt-5 text-sm font-semibold text-emerald-700">
                {{ message }}
            </p>
            <p v-if="error" role="alert" class="mt-5 text-sm font-semibold text-red-700">
                {{ error }}
            </p>

            <a
                :href="homeUrl"
                class="mt-8 inline-flex min-h-11 items-center font-semibold text-emerald-700 underline underline-offset-4"
                >Kembali ke halaman utama</a
            >
        </section>
    </main>
</template>
