<script setup>
import { nextTick, ref } from 'vue';
import { submitPlatformPasswordReset } from '../../../Shared/Authentication/session.js';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, required: true },
    submitUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
});

const email = ref(props.email);
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const errorPanel = ref(null);
const succeeded = ref(false);

async function submit() {
    if (loading.value) return;

    error.value = '';
    loading.value = true;

    let result;
    try {
        result = await submitPlatformPasswordReset(props.submitUrl, {
            email: email.value,
            token: props.token,
            password: password.value,
            passwordConfirmation: passwordConfirmation.value,
        });
    } catch {
        loading.value = false;
        await showError('Perkhidmatan tidak dapat dihubungi. Sila cuba lagi.');
        return;
    }

    loading.value = false;

    if (result.ok && result.body?.data?.reset === true) {
        succeeded.value = true;
        return;
    }

    if (result.status === 422 && result.body?.errors) {
        const [firstMessage] = Object.values(result.body.errors).flat();
        await showError(firstMessage ?? 'Sila semak maklumat yang dimasukkan.');
        return;
    }

    await showError(
        result.body?.detail ??
            'Pautan ini tidak sah atau telah tamat tempoh. Minta pautan baharu dan cuba lagi.',
    );
}

async function showError(message) {
    error.value = message;
    await nextTick();
    errorPanel.value?.focus();
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-10">
        <section class="mx-auto max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[0.18em] text-emerald-700">SYIFA.MY</p>
            <h1 class="mt-3 text-3xl font-bold text-slate-950">Tetapkan semula kata laluan</h1>
            <p class="mt-3 text-slate-600">
                Pilih kata laluan baharu yang selamat untuk akaun anda.
            </p>

            <div v-if="succeeded" class="mt-8">
                <div
                    role="status"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950"
                >
                    Kata laluan anda telah berjaya ditetapkan semula. Anda kini boleh log masuk
                    dengan kata laluan baharu.
                </div>
                <a
                    :href="loginUrl"
                    class="mt-6 flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-5 font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    Log masuk sekarang
                </a>
            </div>

            <form v-else class="mt-8 space-y-5" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-800">
                    Alamat e-mel
                    <input
                        v-model="email"
                        type="email"
                        required
                        autocomplete="username"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    />
                </label>
                <label class="block text-sm font-semibold text-slate-800">
                    Kata laluan baharu
                    <input
                        v-model="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        minlength="15"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    />
                </label>
                <label class="block text-sm font-semibold text-slate-800">
                    Sahkan kata laluan baharu
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        minlength="15"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                    />
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
                    {{ loading ? 'Sedang menetapkan…' : 'Tetapkan kata laluan' }}
                </button>
            </form>

            <a
                :href="loginUrl"
                class="mt-6 block text-center text-sm font-semibold text-emerald-700 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Kembali ke log masuk
            </a>
        </section>
    </main>
</template>
