<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { browserHttpRequest } from '../../Shared/Authentication/session.js';

const props = defineProps({
    registration: { type: Object, required: true },
    accessConfigured: { type: Boolean, required: true },
    accessSetupUrl: { type: String, required: true },
    applicationLoginUrl: { type: String, required: true },
    applicationLogoutUrl: { type: String, required: true },
    offers: { type: Array, required: true },
    updateUrl: { type: String, required: true },
    submitUrl: { type: String, required: true },
    resubmitUrl: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    offersUrl: { type: String, required: true },
    addressAvailabilityUrl: { type: String, required: true },
    websiteBaseDomain: { type: String, required: true },
    templates: { type: Array, required: true },
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
        preferredSubdomain: value.preferredSubdomain ?? value.website_preferences?.subdomain ?? '',
        selectedWebsiteTemplate:
            value.selectedWebsiteTemplate ?? value.website_preferences?.template ?? '',
    };
}

const registration = ref(normalizeRegistration(props.registration));
const form = reactive({
    clinicName: registration.value.clinicName,
    clinicEmail: registration.value.clinicEmail,
    clinicPhone: registration.value.clinicPhone,
    clinicAddress: registration.value.clinicAddress,
    offeringId: registration.value.selectedPlanOfferingReference,
    preferredSubdomain: registration.value.preferredSubdomain,
    selectedWebsiteTemplate: registration.value.selectedWebsiteTemplate,
    declarationAccepted: (registration.value.declarations ?? []).length > 0,
    password: '',
    passwordConfirmation: '',
});
const accessConfigured = ref(props.accessConfigured);
const loading = ref('');
const message = ref('');
const error = ref('');
const errorPanel = ref(null);
const addressStatus = ref('');
const currentStep = ref(
    registration.value.selectedWebsiteTemplate || registration.value.preferredSubdomain
        ? registration.value.selectedPlanOfferingReference
            ? 3
            : 2
        : 1,
);
const steps = [
    { number: 1, label: 'Maklumat klinik' },
    { number: 2, label: 'Website anda' },
    { number: 3, label: 'Semak dan hantar' },
];
const editable = computed(() =>
    ['draft', 'correction_requested'].includes(registration.value.status),
);
const currentDecision = computed(
    () =>
        [...(registration.value.decisions ?? [])]
            .reverse()
            .find((decision) => decision.supersededAt === null) ?? null,
);
const selectedOffer = computed(() =>
    props.offers.find((offer) => offer.planOfferingId === form.offeringId),
);
const clinicDetailsComplete = computed(
    () =>
        form.clinicName.trim() !== '' &&
        form.clinicEmail.trim() !== '' &&
        form.clinicPhone.trim() !== '' &&
        form.clinicAddress.trim() !== '',
);
const accessDetailsComplete = computed(
    () =>
        accessConfigured.value ||
        (form.password.length >= 12 && form.password === form.passwordConfirmation),
);
const websitePreferencesComplete = computed(
    () =>
        /^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/.test(form.preferredSubdomain) &&
        form.selectedWebsiteTemplate !== '',
);
const completionPercent = computed(() => {
    const complete = [
        clinicDetailsComplete.value,
        websitePreferencesComplete.value,
        form.offeringId !== '' && form.declarationAccepted,
    ].filter(Boolean).length;

    return Math.round((complete / steps.length) * 100);
});

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
        preferred_subdomain: form.preferredSubdomain.toLowerCase().trim() || null,
        selected_website_template: form.selectedWebsiteTemplate || null,
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

async function checkAddress() {
    if (loading.value || form.preferredSubdomain.length < 3) return;
    loading.value = 'address';
    addressStatus.value = '';
    try {
        const url = new URL(props.addressAvailabilityUrl, window.location.origin);
        url.searchParams.set('subdomain', form.preferredSubdomain.toLowerCase().trim());
        const result = await browserHttpRequest(url.toString());
        addressStatus.value = result.body.message;
    } catch {
        addressStatus.value = 'Alamat tidak dapat disemak sekarang.';
    }
    loading.value = '';
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

async function save(showConfirmation = true) {
    if (loading.value || !editable.value) return false;
    loading.value = 'save';
    const saved = await request(props.updateUrl, 'PATCH', payload());
    if (saved && showConfirmation) {
        message.value = 'Draf pendaftaran telah disimpan.';
    }
    loading.value = '';

    return saved;
}

async function configureAccess() {
    if (accessConfigured.value) return true;

    error.value = '';
    message.value = '';
    loading.value = 'access';

    try {
        const result = await browserHttpRequest(props.accessSetupUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                password: form.password,
                password_confirmation: form.passwordConfirmation,
            }),
        });

        if (result.ok) {
            accessConfigured.value = true;
            form.password = '';
            form.passwordConfirmation = '';
            message.value = 'Login permohonan anda telah diaktifkan.';
            loading.value = '';
            return true;
        }

        const fields = result.body.errors ?? {};
        error.value =
            Object.values(fields).flat()[0] ??
            result.body.detail ??
            'Login permohonan tidak dapat diaktifkan.';
    } catch {
        error.value = 'Perkhidmatan login permohonan tidak dapat dihubungi.';
    }

    loading.value = '';
    await nextTick();
    errorPanel.value?.focus();
    return false;
}

async function continueTo(step) {
    if (loading.value || step <= currentStep.value) {
        currentStep.value = step;
        return;
    }
    if (step > currentStep.value + 1) return;
    if (currentStep.value === 1 && !clinicDetailsComplete.value) {
        error.value = 'Lengkapkan semua maklumat klinik sebelum meneruskan.';
        return;
    }
    if (currentStep.value === 1 && !accessDetailsComplete.value) {
        error.value = 'Tetapkan kata laluan sekurang-kurangnya 12 aksara yang sepadan.';
        return;
    }
    if (currentStep.value === 2 && !websitePreferencesComplete.value) {
        error.value = 'Pilih alamat dan template Website sebelum meneruskan.';
        return;
    }
    if (
        (await save(false)) &&
        (currentStep.value !== 1 || accessConfigured.value || (await configureAccess()))
    ) {
        currentStep.value = step;
        message.value = 'Kemajuan anda telah disimpan.';
    }
}

async function submit() {
    if (loading.value || !editable.value) return;
    loading.value = 'submit';
    if (registration.value.status === 'correction_requested') {
        if (await request(props.resubmitUrl, 'POST', payload())) {
            message.value = 'Pembetulan telah dihantar semula untuk semakan.';
        }
    } else if (
        (await request(props.updateUrl, 'PATCH', payload())) &&
        (await request(props.submitUrl, 'POST', {
            expected_version: registration.value.version,
        }))
    ) {
        message.value = 'Pendaftaran klinik telah dihantar untuk semakan.';
    }
    loading.value = '';
}

async function cancel() {
    if (
        loading.value ||
        !['draft', 'submitted', 'under_review', 'correction_requested'].includes(
            registration.value.status,
        )
    )
        return;
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
            <h1 class="mt-3 text-3xl font-bold tracking-tight">Permohonan klinik anda</h1>
            <p class="mt-3 text-slate-600">
                Simpan draf anda dan login semula dari mana-mana peranti.
            </p>
            <div class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                <p class="font-semibold">
                    {{
                        accessConfigured
                            ? 'Login permohonan anda sudah aktif.'
                            : 'Tetapkan kata laluan untuk menyambung kemudian.'
                    }}
                </p>
                <p class="mt-1 leading-6">
                    Ini memberi akses kepada permohonan sahaja. Ruang kerja Clinic Owner diaktifkan
                    selepas pembayaran dan provisioning selesai.
                </p>
                <a
                    v-if="accessConfigured"
                    :href="applicationLoginUrl"
                    class="mt-2 inline-flex font-semibold text-sky-800 underline underline-offset-4"
                >
                    Buka halaman login Klinik
                </a>
            </div>

            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">
                Status: <strong class="capitalize">{{ registration.status }}</strong>
            </div>
            <div
                v-if="registration.status === 'correction_requested' && currentDecision"
                class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
            >
                <strong>Pembetulan diperlukan:</strong>
                {{ currentDecision.correctionInstructions }}
            </div>
            <div
                v-if="message"
                role="status"
                class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                {{ message }}
            </div>
            <div
                v-if="error"
                ref="errorPanel"
                tabindex="-1"
                role="alert"
                class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800"
            >
                {{ error }}
            </div>

            <form v-if="editable" class="mt-8 space-y-6" @submit.prevent="save">
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700">Kemajuan pendaftaran</span>
                        <span class="font-bold text-emerald-700">{{ completionPercent }}%</span>
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"
                        role="progressbar"
                        aria-label="Kemajuan pendaftaran"
                        :aria-valuenow="completionPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-600 transition-all"
                            :style="{ width: `${completionPercent}%` }"
                        ></div>
                    </div>
                    <ol class="mt-5 grid gap-2 sm:grid-cols-3">
                        <li v-for="step in steps" :key="step.number">
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center gap-3 rounded-xl border px-3 text-left text-sm font-semibold"
                                :class="
                                    currentStep === step.number
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-900'
                                        : 'border-slate-200 text-slate-600'
                                "
                                :aria-current="currentStep === step.number ? 'step' : undefined"
                                @click="continueTo(step.number)"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        currentStep === step.number
                                            ? 'bg-emerald-700 text-white'
                                            : 'bg-slate-100'
                                    "
                                    >{{ step.number }}</span
                                >
                                {{ step.label }}
                            </button>
                        </li>
                    </ol>
                </div>

                <section v-if="currentStep === 1" aria-labelledby="clinic-details-heading">
                    <h2 id="clinic-details-heading" class="text-xl font-bold">
                        Beritahu kami tentang klinik anda
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Maklumat ini menjadi asas untuk Website dan onboarding anda.
                    </p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
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
                    <div
                        v-if="!accessConfigured"
                        class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5"
                    >
                        <h3 class="font-bold">Cipta akses login</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Gunakan sekurang-kurangnya 12 aksara. Kata laluan ini melindungi
                            permohonan anda.
                        </p>
                        <div class="mt-4 grid gap-5 sm:grid-cols-2">
                            <label class="text-sm font-semibold">
                                Kata laluan
                                <input
                                    v-model="form.password"
                                    required
                                    minlength="12"
                                    type="password"
                                    autocomplete="new-password"
                                    class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                                />
                            </label>
                            <label class="text-sm font-semibold">
                                Sahkan kata laluan
                                <input
                                    v-model="form.passwordConfirmation"
                                    required
                                    minlength="12"
                                    type="password"
                                    autocomplete="new-password"
                                    class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                                />
                            </label>
                        </div>
                    </div>
                    <button
                        type="button"
                        :disabled="
                            Boolean(loading) || !clinicDetailsComplete || !accessDetailsComplete
                        "
                        class="mt-6 min-h-12 rounded-xl bg-emerald-700 px-6 font-bold text-white disabled:opacity-50"
                        @click="continueTo(2)"
                    >
                        {{ loading === 'save' ? 'Menyimpan…' : 'Simpan dan pilih Website' }}
                    </button>
                </section>

                <section v-if="currentStep === 2" aria-labelledby="website-preferences-heading">
                    <h2 id="website-preferences-heading" class="text-xl font-bold">
                        Pilih identiti Website anda
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Pilih alamat rasmi dan template permulaan. Designer akan menyempurnakannya
                        kemudian.
                    </p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="text-sm font-semibold">
                            Alamat Website
                            <span class="mt-2 flex rounded-xl border border-slate-300 bg-white">
                                <input
                                    v-model="form.preferredSubdomain"
                                    required
                                    minlength="3"
                                    maxlength="63"
                                    pattern="[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])"
                                    class="min-h-12 min-w-0 flex-1 rounded-l-xl px-4 font-normal"
                                    @input="
                                        form.preferredSubdomain = form.preferredSubdomain
                                            .toLowerCase()
                                            .replace(/[^a-z0-9-]/g, '');
                                        addressStatus = '';
                                    "
                                />
                                <span class="flex items-center pr-4 text-slate-500"
                                    >.{{ websiteBaseDomain }}</span
                                >
                            </span>
                            <button
                                type="button"
                                :disabled="Boolean(loading) || form.preferredSubdomain.length < 3"
                                class="mt-2 font-semibold text-emerald-700 underline disabled:opacity-50"
                                @click="checkAddress"
                            >
                                {{ loading === 'address' ? 'Menyemak…' : 'Semak ketersediaan' }}
                            </button>
                            <span v-if="addressStatus" class="ml-3 font-normal text-slate-600">{{
                                addressStatus
                            }}</span>
                        </label>

                        <label class="text-sm font-semibold">
                            Template Website
                            <select
                                v-model="form.selectedWebsiteTemplate"
                                required
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 font-normal"
                            >
                                <option value="" disabled>Pilih template</option>
                                <option
                                    v-for="template in templates"
                                    :key="template.value"
                                    :value="template.value"
                                >
                                    {{ template.label }}
                                </option>
                            </select>
                            <span class="mt-2 block font-normal text-slate-500">
                                Designer akan menggunakan pilihan ini sebagai asas Website anda.
                            </span>
                        </label>
                    </div>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="min-h-12 rounded-xl border border-slate-300 px-6 font-bold"
                            @click="currentStep = 1"
                        >
                            Kembali
                        </button>
                        <button
                            type="button"
                            :disabled="Boolean(loading) || !websitePreferencesComplete"
                            class="min-h-12 rounded-xl bg-emerald-700 px-6 font-bold text-white disabled:opacity-50"
                            @click="continueTo(3)"
                        >
                            {{ loading === 'save' ? 'Menyimpan…' : 'Simpan dan semak' }}
                        </button>
                    </div>
                </section>

                <section v-if="currentStep === 3" aria-labelledby="review-heading">
                    <h2 id="review-heading" class="text-xl font-bold">Semak dan hantar</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Pilih pakej dan pastikan pilihan Website anda tepat.
                    </p>
                    <dl class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Klinik</dt>
                            <dd class="mt-1 font-semibold">{{ form.clinicName }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Alamat Website</dt>
                            <dd class="mt-1 font-semibold">
                                {{ form.preferredSubdomain }}.{{ websiteBaseDomain }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Template</dt>
                            <dd class="mt-1 font-semibold">
                                {{
                                    templates.find(
                                        (template) =>
                                            template.value === form.selectedWebsiteTemplate,
                                    )?.label
                                }}
                            </dd>
                        </div>
                    </dl>

                    <label class="mt-5 block text-sm font-semibold">
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
                </section>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        v-if="currentStep === 3"
                        type="button"
                        class="min-h-12 rounded-xl border border-slate-300 px-6 font-bold"
                        @click="currentStep = 2"
                    >
                        Kembali
                    </button>
                    <button
                        v-if="registration.status === 'draft' && currentStep === 3"
                        type="submit"
                        :disabled="Boolean(loading)"
                        class="min-h-12 rounded-xl bg-slate-900 px-6 font-bold text-white disabled:opacity-60"
                    >
                        {{ loading === 'save' ? 'Menyimpan…' : 'Simpan draf' }}
                    </button>
                    <button
                        v-if="currentStep === 3"
                        type="button"
                        :disabled="
                            Boolean(loading) || !form.offeringId || !form.declarationAccepted
                        "
                        class="min-h-12 rounded-xl bg-emerald-700 px-6 font-bold text-white disabled:opacity-60"
                        @click="submit"
                    >
                        {{
                            loading === 'submit'
                                ? 'Menghantar…'
                                : registration.status === 'correction_requested'
                                  ? 'Hantar semula pembetulan'
                                  : 'Hantar pendaftaran'
                        }}
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
                <section
                    v-if="!accessConfigured"
                    class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5"
                >
                    <h2 class="text-lg font-bold text-amber-950">Aktifkan login permohonan</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-900">
                        Tetapkan kata laluan supaya anda boleh kembali dan menyemak status dari
                        mana-mana peranti.
                    </p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold">
                            Kata laluan
                            <input
                                v-model="form.password"
                                minlength="12"
                                type="password"
                                autocomplete="new-password"
                                class="mt-2 min-h-12 w-full rounded-xl border border-amber-300 bg-white px-4 font-normal"
                            />
                        </label>
                        <label class="text-sm font-semibold">
                            Sahkan kata laluan
                            <input
                                v-model="form.passwordConfirmation"
                                minlength="12"
                                type="password"
                                autocomplete="new-password"
                                class="mt-2 min-h-12 w-full rounded-xl border border-amber-300 bg-white px-4 font-normal"
                            />
                        </label>
                    </div>
                    <button
                        type="button"
                        :disabled="Boolean(loading) || !accessDetailsComplete"
                        class="mt-4 min-h-12 rounded-xl bg-amber-900 px-6 font-bold text-white disabled:opacity-50"
                        @click="configureAccess"
                    >
                        {{ loading === 'access' ? 'Mengaktifkan…' : 'Aktifkan login' }}
                    </button>
                </section>
                <template v-if="registration.status === 'approved'">
                    <p class="text-lg font-semibold">Pendaftaran anda telah diluluskan.</p>
                    <p class="mt-2 text-slate-600">
                        Teruskan ke pilihan komersial yang telah diluluskan untuk pembayaran.
                    </p>
                    <a
                        :href="offersUrl"
                        class="mt-5 inline-flex min-h-12 items-center rounded-xl bg-emerald-700 px-6 font-bold text-white"
                    >
                        Teruskan ke pembayaran
                    </a>
                </template>
                <template v-else>
                    <p class="text-lg font-semibold">
                        {{
                            registration.status === 'rejected'
                                ? 'Pendaftaran tidak diluluskan.'
                                : 'Pendaftaran sedang menunggu semakan.'
                        }}
                    </p>
                    <p class="mt-2 text-slate-600">
                        Status berautoriti pendaftaran anda dipaparkan di atas.
                    </p>
                    <div
                        class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950"
                    >
                        <strong>Untuk kembali:</strong> gunakan halaman login permohonan dengan
                        e-mel klinik dan kata laluan anda. Login Clinic Owner akan tersedia selepas
                        provisioning.
                    </div>
                </template>
            </div>

            <a
                :href="homeUrl"
                class="mt-8 inline-flex min-h-11 items-center font-semibold text-emerald-700 underline underline-offset-4"
                >Kembali ke halaman utama</a
            >
        </section>
    </main>
</template>
