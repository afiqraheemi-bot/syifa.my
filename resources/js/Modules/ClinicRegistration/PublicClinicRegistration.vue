<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { browserHttpRequest } from '../../Shared/Authentication/session.js';

const props = defineProps({
    registration: { type: Object, required: true },
    offers: { type: Array, required: true },
    updateUrl: { type: String, required: true },
    submitUrl: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    offersUrl: { type: String, required: true },
    homeUrl: { type: String, required: true },
});

function normalizeRegistration(value) {
    return {
        ...value,
        clinicName: value.clinicName ?? value.clinic?.name ?? '',
        clinicEmail: value.clinicEmail ?? value.clinic?.email ?? '',
        clinicPhone: value.clinicPhone ?? value.clinic?.phone ?? '',
        clinicAddress: value.clinicAddress ?? value.clinic?.address ?? '',
        selectedPlanOfferingReference:
            value.selectedPlanOfferingReference ??
            value.commercial_selection?.plan_offering_reference ??
            '',
    };
}

const registration = ref(normalizeRegistration(props.registration));
const form = reactive({
    clinicName: registration.value.clinicName,
    clinicEmail: registration.value.clinicEmail,
    clinicPhone: registration.value.clinicPhone,
    clinicAddress: registration.value.clinicAddress,
    offeringId: registration.value.selectedPlanOfferingReference,
    declarationAccepted: (registration.value.declarations ?? []).length > 0,
});
const loading = ref('');
const message = ref('');
const error = ref('');
const errorPanel = ref(null);
const editable = computed(() => registration.value.status === 'draft');
const selectedOffer = computed(() =>
    props.offers.find((offer) => offer.planOfferingId === form.offeringId),
);

function payload() {
    const offer = selectedOffer.value;
    return {
        clinic_name: form.clinicName || null,
        clinic_email: form.clinicEmail || null,
        clinic_phone: form.clinicPhone || null,
        clinic_address: form.clinicAddress || null,
        selected_plan_offering_reference: offer?.planOfferingId ?? null,
        selected_billing_option_reference: offer?.billingCycleId ?? null,
        commercial_snapshot_version: offer?.configurationVersion ?? null,
        declarations: form.declarationAccepted
            ? [
                  {
                      key: 'clinic_registration.accuracy',
                      version: '1',
                      accepted_at: new Date().toISOString(),
                  },
              ]
            : [],
        expected_version: registration.value.version,
    };
}

async function request(url, method, body) {
    error.value = '';
    message.value = '';
    try {
        const result = await browserHttpRequest(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        if (result.ok) {
            registration.value = normalizeRegistration(result.body.data);
            return true;
        }
        const fields = result.body.errors ?? {};
        error.value =
            Object.values(fields).flat()[0] ??
            result.body.detail ??
            'Permintaan tidak dapat diselesaikan. Sila cuba lagi.';
    } catch {
        error.value = 'Perkhidmatan pendaftaran tidak dapat dihubungi.';
    }
    await nextTick();
    errorPanel.value?.focus();
    return false;
}

async function save() {
    if (loading.value || !editable.value) return;
    loading.value = 'save';
    if (await request(props.updateUrl, 'PATCH', payload())) {
        message.value = 'Draf pendaftaran telah disimpan.';
    }
    loading.value = '';
}

async function submit() {
    if (loading.value || !editable.value) return;
    loading.value = 'submit';
    if (
        (await request(props.updateUrl, 'PATCH', payload())) &&
        (await request(props.submitUrl, 'POST', {
            expected_version: registration.value.version,
        }))
    ) {
        message.value = 'Pendaftaran klinik telah dihantar.';
        window.location.assign(props.offersUrl);
    }
    loading.value = '';
}

async function cancel() {
    if (loading.value || !['draft', 'submitted'].includes(registration.value.status)) return;
    if (!window.confirm('Batalkan pendaftaran klinik ini?')) return;
    loading.value = 'cancel';
    if (
        await request(props.cancelUrl, 'POST', {
            expected_version: registration.value.version,
        })
    ) {
        message.value = 'Pendaftaran klinik telah dibatalkan.';
    }
    loading.value = '';
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-950 sm:px-6 sm:py-12">
        <section class="mx-auto max-w-3xl rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[0.18em] text-emerald-700">SYIFA.MY</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">Daftarkan klinik anda</h1>
            <p class="mt-3 text-slate-600">
                Simpan draf anda dan kembali menggunakan pelayar ini pada bila-bila masa.
            </p>

            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">
                Status: <strong class="capitalize">{{ registration.status }}</strong>
            </div>

            <form v-if="editable" class="mt-8 space-y-6" @submit.prevent="save">
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="text-sm font-semibold">
                        Nama klinik
                        <input
                            v-model="form.clinicName"
                            required
                            maxlength="200"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                        />
                    </label>
                    <label class="text-sm font-semibold">
                        E-mel klinik
                        <input
                            v-model="form.clinicEmail"
                            required
                            type="email"
                            maxlength="254"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                        />
                    </label>
                    <label class="text-sm font-semibold">
                        Telefon klinik
                        <input
                            v-model="form.clinicPhone"
                            required
                            maxlength="40"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                        />
                    </label>
                    <label class="text-sm font-semibold">
                        Alamat klinik
                        <input
                            v-model="form.clinicAddress"
                            required
                            maxlength="1000"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                        />
                    </label>
                </div>

                <label class="block text-sm font-semibold">
                    Pelan
                    <select
                        v-model="form.offeringId"
                        required
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                    >
                        <option value="" disabled>Pilih pelan</option>
                        <option
                            v-for="offer in offers"
                            :key="offer.planOfferingId"
                            :value="offer.planOfferingId"
                        >
                            {{ offer.planName }} — {{ offer.billingCycleName }} ({{
                                offer.currency
                            }}
                            {{ (offer.amountMinor / 100).toFixed(2) }})
                        </option>
                    </select>
                </label>

                <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-700">
                    <input
                        v-model="form.declarationAccepted"
                        required
                        type="checkbox"
                        class="mt-1 size-5"
                    />
                    Saya mengesahkan bahawa maklumat pendaftaran yang diberikan adalah tepat.
                </label>

                <div
                    v-if="message"
                    role="status"
                    class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ message }}
                </div>
                <div
                    v-if="error"
                    ref="errorPanel"
                    tabindex="-1"
                    role="alert"
                    class="rounded-xl bg-red-50 p-4 text-sm text-red-800"
                >
                    {{ error }}
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="submit"
                        :disabled="Boolean(loading)"
                        class="min-h-12 rounded-xl bg-slate-900 px-6 font-bold text-white disabled:opacity-60"
                    >
                        {{ loading === 'save' ? 'Menyimpan…' : 'Simpan draf' }}
                    </button>
                    <button
                        type="button"
                        :disabled="Boolean(loading)"
                        class="min-h-12 rounded-xl bg-emerald-700 px-6 font-bold text-white disabled:opacity-60"
                        @click="submit"
                    >
                        {{ loading === 'submit' ? 'Menghantar…' : 'Hantar pendaftaran' }}
                    </button>
                    <button
                        type="button"
                        :disabled="Boolean(loading)"
                        class="min-h-12 px-4 font-semibold text-red-700 disabled:opacity-60"
                        @click="cancel"
                    >
                        Batal
                    </button>
                </div>
            </form>

            <div v-else class="mt-8">
                <p class="text-lg font-semibold">Pendaftaran ini tidak lagi boleh diedit.</p>
                <p class="mt-2 text-slate-600">Status semasa anda dipaparkan di atas.</p>
            </div>

            <a
                :href="homeUrl"
                class="mt-8 inline-flex min-h-11 items-center font-semibold text-emerald-700 underline underline-offset-4"
                >Kembali ke halaman utama</a
            >
        </section>
    </main>
</template>
