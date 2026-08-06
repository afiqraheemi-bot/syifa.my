<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, required: true },
    submitUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    if (form.processing) return;
    form.post(props.submitUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-10">
        <section class="mx-auto max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[0.18em] text-emerald-700">SYIFA.MY</p>
            <h1 class="mt-3 text-3xl font-bold text-slate-950">Tetapkan semula kata laluan</h1>
            <p class="mt-3 text-slate-600">
                Sahkan alamat e-mel dan pilih kata laluan baharu yang selamat untuk ruang kerja
                klinik anda.
            </p>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-800">
                    Alamat e-mel
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4"
                    />
                </label>
                <label class="block text-sm font-semibold text-slate-800">
                    Kata laluan baharu
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4"
                    />
                </label>
                <label class="block text-sm font-semibold text-slate-800">
                    Sahkan kata laluan baharu
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4"
                    />
                </label>
                <ul
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-xl bg-red-50 p-4 text-sm text-red-800"
                >
                    <li v-for="message in form.errors" :key="message">{{ message }}</li>
                </ul>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-12 w-full rounded-xl bg-emerald-700 px-5 font-bold text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Sedang menyimpan…' : 'Simpan kata laluan' }}
                </button>
            </form>
            <a
                :href="loginUrl"
                class="mt-6 block text-center text-sm font-semibold text-emerald-700"
            >
                Kembali ke log masuk
            </a>
        </section>
    </main>
</template>
