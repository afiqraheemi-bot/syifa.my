<script setup>
import { computed, nextTick, ref } from 'vue';
import {
    completePlatformMfa,
    createBrowserSession,
    requestClinicOwnerPasswordReset,
    requestPlatformPasswordReset,
} from '../../../Shared/Authentication/session.js';

const props = defineProps({
    clinicPortal: { type: Boolean, required: true },
    localClinicOwnerLogin: { type: Boolean, required: true },
    clinicOwnerSessionUrl: { type: String, required: true },
    clinicOwnerForgotPasswordUrl: { type: String, required: true },
    platformForgotPasswordUrl: { type: String, required: true },
    platformSessionUrl: { type: String, required: true },
    platformMfaUrl: { type: String, required: true },
    dashboardUrl: { type: String, required: true },
    clinicRegistrationUrl: { type: String, required: true },
    clinicRegistrationLoginUrl: { type: String, required: true },
    clinicPortalBaseDomains: { type: Array, required: true },
});

const actor = ref(props.clinicPortal ? 'clinic_owner' : 'website_designer');
const email = ref('');
const password = ref('');
const remember = ref(false);
const clinicLabel = ref('');
const loading = ref(false);
const error = ref('');
const errorPanel = ref(null);
const mfaState = ref(null);
const mfaCode = ref('');
const mfaSetupKey = ref('');
const recoveryOpen = ref(false);
const recoveryLoading = ref(false);
const recoveryMessage = ref('');

const isClinicOwner = computed(() => actor.value === 'clinic_owner');
const canAuthenticateClinicOwner = computed(
    () => props.clinicPortal || props.localClinicOwnerLogin,
);
const heading = computed(() => {
    if (actor.value === 'clinic_owner') return 'Log masuk Pemilik Klinik';
    if (actor.value === 'super_admin') return 'Log masuk Super Admin';
    return 'Log masuk Pereka Laman Web';
});

function chooseActor(nextActor) {
    actor.value = nextActor;
    error.value = '';
    mfaState.value = null;
    mfaCode.value = '';
    mfaSetupKey.value = '';
    recoveryOpen.value = false;
    recoveryMessage.value = '';
}

function openClinicPortal() {
    const normalized = clinicLabel.value.trim().toLowerCase();
    const baseDomain = props.clinicPortalBaseDomains[0];

    if (!/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(normalized) || !baseDomain) {
        showError('Masukkan alamat portal klinik yang sah.');
        return;
    }

    const port = window.location.port ? `:${window.location.port}` : '';
    window.location.assign(`${window.location.protocol}//${normalized}.${baseDomain}${port}/`);
}

async function submit() {
    if (loading.value) return;

    error.value = '';
    loading.value = true;
    let result;
    let redirectUrl = props.dashboardUrl;

    try {
        const credentials = { email: email.value, password: password.value };

        if (isClinicOwner.value && !props.clinicPortal) {
            result = await createBrowserSession(
                props.clinicRegistrationLoginUrl,
                credentials,
                remember.value,
            );
            if (result.ok && result.body?.data?.authenticated === true) {
                redirectUrl = result.body.data.redirect ?? props.clinicRegistrationUrl;
            } else if (result.status === 401) {
                result = await createBrowserSession(
                    props.clinicOwnerSessionUrl,
                    credentials,
                    remember.value,
                );
            }
        } else {
            const endpoint = isClinicOwner.value
                ? props.clinicOwnerSessionUrl
                : props.platformSessionUrl;
            result = await createBrowserSession(endpoint, credentials, remember.value);
        }
    } catch {
        showError('Perkhidmatan log masuk tidak dapat dihubungi. Sila cuba lagi sebentar nanti.');
        loading.value = false;
        return;
    }

    if (result.status === 202 && result.body?.data?.state) {
        mfaState.value = result.body.data.state;
        mfaSetupKey.value = result.body.data.setup_key ?? '';
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && result.body.data.csrf_token) {
            csrfMeta.setAttribute('content', result.body.data.csrf_token);
        }
        password.value = '';
        loading.value = false;
        return;
    }

    if (result.ok) {
        error.value = '';
        window.location.assign(redirectUrl);
        return;
    }

    loading.value = false;
    if (result.status === 422) {
        showError('Sila semak alamat e-mel dan kata laluan anda.');
    } else if (result.status === 429) {
        showError('Terlalu banyak percubaan. Sila tunggu sebentar sebelum mencuba lagi.');
    } else {
        showError(
            'Log masuk tidak berjaya. Semak maklumat anda atau hubungi pentadbir jika akaun dikunci atau belum disahkan.',
        );
    }
}

async function submitMfa() {
    if (loading.value) return;

    error.value = '';
    loading.value = true;

    try {
        const result = await completePlatformMfa(props.platformMfaUrl, mfaCode.value);
        if (result.ok && result.body?.data?.authenticated === true) {
            window.location.assign(props.dashboardUrl);
            return;
        }
    } catch {
        // The same safe message is used for transport and verification failure.
    }

    loading.value = false;
    showError('Kod pengesahan tidak sah atau telah tamat tempoh. Sila cuba lagi.');
}

async function showError(message) {
    error.value = message;
    await nextTick();
    errorPanel.value?.focus();
}

async function requestPasswordReset() {
    if (recoveryLoading.value) return;

    recoveryLoading.value = true;
    recoveryMessage.value = '';
    try {
        const result = isClinicOwner.value
            ? await requestClinicOwnerPasswordReset(props.clinicOwnerForgotPasswordUrl, email.value)
            : await requestPlatformPasswordReset(props.platformForgotPasswordUrl, email.value);
        recoveryMessage.value = result.ok
            ? 'Jika akaun tersebut wujud, pautan menetapkan semula kata laluan telah dihantar.'
            : 'Permintaan tidak dapat diproses. Semak alamat e-mel dan cuba lagi.';
    } catch {
        recoveryMessage.value = 'Perkhidmatan tidak dapat dihubungi. Sila cuba lagi.';
    } finally {
        recoveryLoading.value = false;
    }
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-950 sm:px-6 sm:py-12">
        <div
            class="mx-auto grid max-w-6xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-2"
        >
            <section
                class="flex flex-col justify-between bg-emerald-950 px-6 py-10 text-white sm:px-10 lg:min-h-[44rem] lg:px-12"
            >
                <div>
                    <p class="text-sm font-bold tracking-[0.18em] text-emerald-300">SYIFA.MY</p>
                    <h1 class="mt-6 max-w-lg text-3xl font-bold tracking-tight sm:text-4xl">
                        Urus klinik dan laman web anda dengan lebih mudah.
                    </h1>
                    <p class="mt-5 max-w-lg text-base leading-7 text-emerald-100">
                        Satu ruang kerja selamat untuk operasi klinik, kandungan laman web dan
                        tempahan pesakit.
                    </p>
                </div>

                <div class="mt-10 rounded-2xl border border-emerald-700/70 bg-emerald-900/60 p-5">
                    <p class="font-semibold">Akses dilindungi</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-100">
                        Jangan kongsi kata laluan. SYIFA.my tidak akan meminta kata laluan melalui
                        e-mel atau mesej.
                    </p>
                </div>
            </section>

            <section class="px-6 py-10 sm:px-10 lg:px-12 lg:py-14">
                <div class="mx-auto max-w-md">
                    <p class="text-sm font-semibold text-emerald-700">Selamat kembali</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight">{{ heading }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Pilih ruang kerja dan gunakan akaun anda untuk meneruskan.
                    </p>
                    <a
                        :href="clinicRegistrationUrl"
                        class="mt-5 inline-flex min-h-11 items-center font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        Daftar klinik
                    </a>

                    <div
                        v-if="!clinicPortal"
                        class="mt-7 grid grid-cols-3 gap-2"
                        role="tablist"
                        aria-label="Jenis akaun"
                    >
                        <button
                            v-for="option in [
                                ['clinic_owner', 'Klinik'],
                                ['website_designer', 'Pereka'],
                                ['super_admin', 'Admin'],
                            ]"
                            :key="option[0]"
                            type="button"
                            role="tab"
                            :aria-selected="actor === option[0]"
                            :class="[
                                'min-h-11 rounded-xl border px-2 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
                                actor === option[0]
                                    ? 'border-emerald-700 bg-emerald-50 text-emerald-800'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="chooseActor(option[0])"
                        >
                            {{ option[1] }}
                        </button>
                    </div>

                    <form
                        v-if="(!isClinicOwner || canAuthenticateClinicOwner) && !mfaState"
                        class="mt-8 space-y-5"
                        @submit.prevent="submit"
                    >
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-800">
                                Alamat e-mel
                            </label>
                            <input
                                id="email"
                                v-model="email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                required
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 text-base focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                            />
                        </div>

                        <div>
                            <label
                                for="password"
                                class="block text-sm font-semibold text-slate-800"
                            >
                                Kata laluan
                            </label>
                            <input
                                id="password"
                                v-model="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 text-base focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                            />
                            <button
                                type="button"
                                class="mt-3 min-h-11 font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                @click="recoveryOpen = !recoveryOpen"
                            >
                                Lupa kata laluan?
                            </button>
                        </div>

                        <div
                            v-if="recoveryOpen"
                            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"
                        >
                            <p>
                                Gunakan alamat e-mel di atas untuk menerima pautan menetapkan semula
                                kata laluan.
                            </p>
                            <button
                                type="button"
                                :disabled="recoveryLoading || !email"
                                class="mt-3 min-h-11 rounded-lg bg-emerald-700 px-4 font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                @click="requestPasswordReset"
                            >
                                {{ recoveryLoading ? 'Sedang menghantar…' : 'Hantar pautan reset' }}
                            </button>
                            <p v-if="recoveryMessage" class="mt-3 leading-6" role="status">
                                {{ recoveryMessage }}
                            </p>
                        </div>

                        <label
                            class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-700"
                        >
                            <input
                                v-model="remember"
                                type="checkbox"
                                class="size-5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                            />
                            Ingat saya pada peranti ini
                        </label>

                        <div
                            v-if="error"
                            ref="errorPanel"
                            tabindex="-1"
                            role="alert"
                            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800 focus:outline-2 focus:outline-offset-2 focus:outline-red-600"
                        >
                            {{ error }}
                        </div>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-5 font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-70"
                        >
                            {{ loading ? 'Sedang log masuk…' : 'Log masuk' }}
                        </button>
                    </form>

                    <form v-else-if="mfaState" class="mt-8 space-y-5" @submit.prevent="submitMfa">
                        <div
                            v-if="mfaState === 'mfa_enrollment_required'"
                            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950"
                        >
                            <p class="font-bold">Aktifkan pengesahan dua langkah</p>
                            <p class="mt-1">
                                Tambah kunci ini dalam aplikasi authenticator anda. Kunci ini hanya
                                dipaparkan semasa pendaftaran.
                            </p>
                            <code
                                class="mt-3 block break-all rounded-lg bg-white p-3 font-mono text-xs"
                                >{{ mfaSetupKey }}</code
                            >
                        </div>
                        <div>
                            <label
                                for="mfa-code"
                                class="block text-sm font-semibold text-slate-800"
                            >
                                Kod authenticator 6 digit
                            </label>
                            <input
                                id="mfa-code"
                                v-model="mfaCode"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                required
                                autofocus
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 text-center font-mono text-xl tracking-[0.35em] focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                            />
                        </div>
                        <div
                            v-if="error"
                            ref="errorPanel"
                            tabindex="-1"
                            role="alert"
                            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800"
                        >
                            {{ error }}
                        </div>
                        <button
                            type="submit"
                            :disabled="loading || mfaCode.length !== 6"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-5 font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-70"
                        >
                            {{ loading ? 'Mengesahkan…' : 'Sahkan dan teruskan' }}
                        </button>
                    </form>

                    <form v-else class="mt-8 space-y-5" @submit.prevent="openClinicPortal">
                        <div>
                            <label
                                for="clinic-label"
                                class="block text-sm font-semibold text-slate-800"
                            >
                                Alamat portal klinik
                            </label>
                            <div
                                class="mt-2 flex min-h-12 overflow-hidden rounded-xl border border-slate-300 focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-200"
                            >
                                <input
                                    id="clinic-label"
                                    v-model="clinicLabel"
                                    required
                                    autocomplete="organization"
                                    placeholder="nama-klinik"
                                    class="min-w-0 flex-1 px-4 text-base outline-none"
                                />
                                <span
                                    class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500"
                                >
                                    .{{ clinicPortalBaseDomains[0] }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="error"
                            ref="errorPanel"
                            tabindex="-1"
                            role="alert"
                            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                        >
                            {{ error }}
                        </div>

                        <button
                            type="submit"
                            class="min-h-12 w-full rounded-xl bg-emerald-700 px-5 font-bold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            Buka portal klinik
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</template>
