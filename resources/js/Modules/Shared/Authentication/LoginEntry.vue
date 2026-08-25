<script setup>
import { nextTick, ref } from 'vue';
import {
    completePlatformMfa,
    createUnifiedBrowserSession,
    requestUnifiedPasswordReset,
} from '../../../Shared/Authentication/session.js';

const props = defineProps({
    clinicOwnerSessionUrl: { type: String, required: true },
    clinicOwnerForgotPasswordUrl: { type: String, required: true },
    platformForgotPasswordUrl: { type: String, required: true },
    platformSessionUrl: { type: String, required: true },
    platformMfaUrl: { type: String, required: true },
    platformMfaEnabled: { type: Boolean, required: true },
    dashboardUrl: { type: String, required: true },
    clinicRegistrationUrl: { type: String, required: true },
    clinicRegistrationLoginUrl: { type: String, required: true },
});

const email = ref('');
const password = ref('');
const passwordVisible = ref(false);
const remember = ref(false);
const loading = ref(false);
const error = ref('');
const errorPanel = ref(null);
const mfaState = ref(null);
const mfaCode = ref('');
const mfaSetupKey = ref('');
const recoveryOpen = ref(false);
const recoveryLoading = ref(false);
const recoveryMessage = ref('');

async function submit() {
    if (loading.value) return;

    error.value = '';
    loading.value = true;
    let result;
    let redirectUrl = props.dashboardUrl;

    try {
        const credentials = { email: email.value, password: password.value };

        result = await createUnifiedBrowserSession(
            {
                clinicRegistration: props.clinicRegistrationLoginUrl,
                clinicOwner: props.clinicOwnerSessionUrl,
                platform: props.platformSessionUrl,
            },
            credentials,
            remember.value,
        );

        if (result.boundary === 'clinic_registration' && result.ok) {
            redirectUrl = result.body?.data?.redirect ?? props.clinicRegistrationUrl;
        }
    } catch {
        showError('Perkhidmatan log masuk tidak dapat dihubungi. Sila cuba lagi sebentar nanti.');
        loading.value = false;
        return;
    }

    if (props.platformMfaEnabled && result.status === 202 && result.body?.data?.state) {
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
        const result = await requestUnifiedPasswordReset(
            {
                clinicOwner: props.clinicOwnerForgotPasswordUrl,
                platform: props.platformForgotPasswordUrl,
            },
            email.value,
        );
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
    <main
        class="relative flex min-h-screen items-center overflow-hidden bg-[#f4f6f0] px-4 py-5 text-slate-950 sm:px-6 sm:py-8"
    >
        <div
            class="pointer-events-none absolute -top-48 -left-48 size-[36rem] rounded-full bg-lime-200/30 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -right-40 -bottom-52 size-[40rem] rounded-full bg-emerald-200/30 blur-3xl"
        />
        <div
            class="relative mx-auto grid min-w-0 w-full max-w-6xl overflow-hidden rounded-[2rem] border border-white/80 bg-white shadow-[0_32px_90px_-42px_rgba(6,78,59,.45)] lg:grid-cols-[1.05fr_0.95fr]"
        >
            <section
                class="relative hidden min-h-[46rem] flex-col justify-between overflow-hidden bg-emerald-950 px-12 py-12 text-white lg:flex"
            >
                <div
                    class="pointer-events-none absolute -right-32 -bottom-24 size-[30rem] rounded-full border-[70px] border-emerald-800/40"
                />
                <div class="relative z-10">
                    <a
                        href="/"
                        aria-label="Kembali ke halaman utama SYIFA.my"
                        class="inline-flex rounded-lg bg-white px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-lime-300"
                    >
                        <img
                            :src="'/images/marketing/syifa-logo.webp'"
                            alt="SYIFA.my"
                            class="h-7 w-auto"
                            width="1836"
                            height="857"
                        />
                    </a>
                    <p class="mt-14 text-xs font-black tracking-[0.2em] text-lime-300 uppercase">
                        Ruang kerja klinik anda
                    </p>
                    <h1
                        class="mt-5 max-w-md text-[2.75rem] leading-[1.08] font-black tracking-[-0.04em]"
                    >
                        Semua urusan digital klinik, di satu tempat.
                    </h1>
                    <p class="mt-5 max-w-md text-base leading-7 text-emerald-100/90">
                        Urus website, tempahan pesakit dan kandungan klinik dengan pengalaman yang
                        ringkas, tersusun dan selamat.
                    </p>

                    <div class="mt-9 grid max-w-md grid-cols-3 gap-3">
                        <div
                            v-for="item in ['Website', 'Tempahan', 'Kandungan']"
                            :key="item"
                            class="rounded-2xl border border-emerald-800 bg-emerald-900/70 px-3 py-4"
                        >
                            <span
                                class="mb-3 flex size-7 items-center justify-center rounded-lg bg-lime-300 text-emerald-950"
                            >
                                <svg
                                    viewBox="0 0 20 20"
                                    class="size-3.5 fill-none stroke-current stroke-[2.4]"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 10.5l3.5 3.5L16 6"
                                    />
                                </svg>
                            </span>
                            <p class="text-xs font-bold text-white">{{ item }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="relative z-10 mt-10 flex items-start gap-3 border-t border-emerald-800 pt-6"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-800 text-lime-300"
                    >
                        <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3l7 3v5c0 4.8-2.9 8.2-7 10-4.1-1.8-7-5.2-7-10V6l7-3zM9 12l2 2 4-4"
                            />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-white">Akses yang dilindungi</p>
                        <p class="mt-1 text-xs leading-5 text-emerald-100/75">
                            SYIFA.my tidak akan meminta kata laluan anda melalui e-mel atau
                            WhatsApp.
                        </p>
                    </div>
                </div>
            </section>

            <section class="flex min-w-0 items-center px-6 py-9 sm:px-12 sm:py-14 lg:px-16">
                <div class="mx-auto min-w-0 w-full max-w-md">
                    <a
                        href="/"
                        class="inline-flex rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600 lg:hidden"
                    >
                        <img
                            :src="'/images/marketing/syifa-logo.webp'"
                            alt="SYIFA.my"
                            class="h-8 w-auto"
                            width="1836"
                            height="857"
                        />
                    </a>
                    <p
                        class="mt-9 text-xs font-black tracking-[0.16em] text-emerald-700 uppercase lg:mt-0"
                    >
                        Selamat kembali
                    </p>
                    <h2
                        class="mt-3 text-3xl font-black tracking-[-0.04em] text-slate-950 sm:text-4xl"
                    >
                        Log masuk ke akaun anda
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Gunakan e-mel yang didaftarkan. Kami akan membawa anda ke ruang kerja yang
                        betul secara automatik.
                    </p>

                    <form v-if="!mfaState" class="mt-8 min-w-0 space-y-5" @submit.prevent="submit">
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
                                placeholder="nama@klinik.com"
                                class="mt-2 min-h-13 min-w-0 w-full max-w-full rounded-xl border border-slate-300 bg-slate-50/70 px-4 text-base transition placeholder:text-slate-400 hover:border-slate-400 focus:border-emerald-700 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                            />
                        </div>

                        <div>
                            <label
                                for="password"
                                class="block text-sm font-semibold text-slate-800"
                            >
                                Kata laluan
                            </label>
                            <div class="relative mt-2">
                                <input
                                    id="password"
                                    v-model="password"
                                    name="password"
                                    :type="passwordVisible ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Masukkan kata laluan"
                                    class="min-h-13 min-w-0 w-full max-w-full rounded-xl border border-slate-300 bg-slate-50/70 py-2 pr-12 pl-4 text-base transition placeholder:text-slate-400 hover:border-slate-400 focus:border-emerald-700 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-emerald-600"
                                    :aria-label="
                                        passwordVisible
                                            ? 'Sembunyikan kata laluan'
                                            : 'Paparkan kata laluan'
                                    "
                                    :title="
                                        passwordVisible
                                            ? 'Sembunyikan kata laluan'
                                            : 'Paparkan kata laluan'
                                    "
                                    :aria-pressed="passwordVisible"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <svg
                                        v-if="!passwordVisible"
                                        aria-hidden="true"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        class="size-5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"
                                        />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg
                                        v-else
                                        aria-hidden="true"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        class="size-5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m3 3 18 18M10.6 10.6A2 2 0 0 0 13.4 13.4M9.9 5.1A10 10 0 0 1 12 4.9c6.25 0 9.75 7.1 9.75 7.1a17 17 0 0 1-2.6 3.5M6.2 6.2C3.7 8 2.25 12 2.25 12s3.5 7.1 9.75 7.1a9.8 9.8 0 0 0 3.4-.6"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded text-sm font-semibold text-emerald-700 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                    @click="recoveryOpen = !recoveryOpen"
                                >
                                    Terlupa kata laluan?
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="recoveryOpen"
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"
                        >
                            <p class="font-semibold">Tetapkan semula kata laluan</p>
                            <p class="mt-1 leading-6 text-emerald-900/80">
                                Kami akan menghantar pautan pemulihan ke alamat e-mel di atas.
                            </p>
                            <button
                                type="button"
                                :disabled="recoveryLoading || !email"
                                class="mt-3 min-h-10 rounded-lg bg-emerald-700 px-4 font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="requestPasswordReset"
                            >
                                {{
                                    recoveryLoading
                                        ? 'Sedang menghantar…'
                                        : 'Hantar pautan pemulihan'
                                }}
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
                            class="flex min-h-13 w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:-translate-y-0.5 hover:bg-emerald-950 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-70"
                        >
                            {{ loading ? 'Sedang log masuk…' : 'Log masuk' }}
                            <svg
                                v-if="!loading"
                                viewBox="0 0 24 24"
                                class="size-4 fill-none stroke-current stroke-[2.5]"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12h14M13 6l6 6-6 6"
                                />
                            </svg>
                        </button>
                    </form>

                    <div v-if="!mfaState" class="mt-8 border-t border-slate-200 pt-6 text-center">
                        <p class="text-sm text-slate-600">Belum menggunakan SYIFA.my?</p>
                        <a
                            :href="clinicRegistrationUrl"
                            class="mt-3 inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold text-slate-800 transition hover:border-emerald-700 hover:bg-emerald-50 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            Daftar klinik baharu
                        </a>
                    </div>

                    <form v-else class="mt-8 space-y-5" @submit.prevent="submitMfa">
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
                </div>
            </section>
        </div>
    </main>
</template>
