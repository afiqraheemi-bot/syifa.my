<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    profile: { type: Object, required: true },
    security: { type: Object, required: true },
    feedback: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const profileForm = useForm({ name: props.profile.name });
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const showPasswords = ref(false);
const passwordLongEnough = computed(() => [...passwordForm.password].length >= 15);
const passwordsMatch = computed(
    () =>
        passwordForm.password.length > 0 &&
        passwordForm.password === passwordForm.password_confirmation,
);
const passwordReady = computed(
    () =>
        passwordForm.current_password.length > 0 &&
        passwordLongEnough.value &&
        passwordsMatch.value,
);

function updateProfile() {
    profileForm.patch(props.profile.profileUpdateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const currentName = page.props.profile?.name ?? profileForm.name;
            profileForm.name = currentName;
            profileForm.defaults({ name: currentName });
        },
    });
}

function updatePassword() {
    passwordForm.put(props.profile.passwordUpdateUrl, {
        preserveScroll: true,
        onError: () => passwordForm.reset('current_password'),
    });
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
        <div class="mx-auto max-w-5xl space-y-6">
            <div
                v-if="feedback.status"
                role="status"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-900"
            >
                {{ feedback.status }}
            </div>

            <section
                class="overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950 px-6 py-7 text-white shadow-sm sm:px-8"
            >
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                            Identiti akaun
                        </p>
                        <h2 class="mt-3 text-2xl font-black sm:text-3xl">{{ profile.name }}</h2>
                        <p class="mt-2 text-emerald-50/75">{{ profile.email }}</p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-2 rounded-full px-4 py-2 text-sm font-bold"
                        :class="
                            profile.emailVerified
                                ? 'bg-lime-300 text-emerald-950'
                                : 'bg-amber-300 text-amber-950'
                        "
                    >
                        <span class="size-2 rounded-full bg-current" aria-hidden="true" />
                        {{ profile.emailVerified ? 'E-mel disahkan' : 'E-mel belum disahkan' }}
                    </span>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <p class="text-xs font-black tracking-[0.16em] text-emerald-700 uppercase">
                        Profil
                    </p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Maklumat pemilik klinik</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Nama ini digunakan di seluruh ruang kerja Clinic Owner anda.
                    </p>
                </div>
                <form class="space-y-5 p-6 sm:p-8" @submit.prevent="updateProfile">
                    <label class="block text-sm font-bold text-slate-800">
                        Nama penuh
                        <input
                            v-model="profileForm.name"
                            type="text"
                            maxlength="120"
                            autocomplete="name"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-emerald-600 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                        />
                        <span
                            v-if="profileForm.errors.name"
                            class="mt-2 block text-sm text-rose-700"
                            >{{ profileForm.errors.name }}</span
                        >
                    </label>
                    <div>
                        <p class="text-sm font-bold text-slate-800">Alamat e-mel</p>
                        <div
                            class="mt-2 flex min-h-12 items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4"
                        >
                            <span class="min-w-0 truncate text-slate-700">{{ profile.email }}</span>
                            <span
                                class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800"
                            >
                                {{ profile.emailVerified ? 'Disahkan' : 'Belum disahkan' }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            Hubungi sokongan SYIFA.my untuk menukar e-mel pemilik dengan selamat.
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="profileForm.processing || !profileForm.isDirty"
                        class="min-h-12 rounded-xl bg-emerald-700 px-6 font-black text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ profileForm.processing ? 'Menyimpan…' : 'Simpan profil' }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <p class="text-xs font-black tracking-[0.16em] text-sky-700 uppercase">
                        Keselamatan
                    </p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Tukar kata laluan</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Gunakan sekurang-kurangnya 15 aksara. Semua sesi akan ditamatkan selepas
                        perubahan berjaya.
                    </p>
                </div>
                <form class="grid gap-5 p-6 sm:p-8" @submit.prevent="updatePassword">
                    <label class="block text-sm font-bold text-slate-800">
                        Kata laluan semasa
                        <div class="relative mt-2">
                            <input
                                v-model="passwordForm.current_password"
                                :type="showPasswords ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="min-h-12 w-full rounded-xl border border-slate-300 px-4 pr-20 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-3 text-xs font-bold text-sky-700"
                                :aria-pressed="showPasswords"
                                @click="showPasswords = !showPasswords"
                            >
                                {{ showPasswords ? 'Sorok' : 'Lihat' }}
                            </button>
                        </div>
                        <span
                            v-if="passwordForm.errors.current_password"
                            class="mt-2 block text-sm text-rose-700"
                            >{{ passwordForm.errors.current_password }}</span
                        >
                    </label>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-bold text-slate-800">
                            Kata laluan baharu
                            <input
                                v-model="passwordForm.password"
                                :type="showPasswords ? 'text' : 'password'"
                                required
                                minlength="15"
                                autocomplete="new-password"
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
                            />
                            <span
                                v-if="passwordForm.errors.password"
                                class="mt-2 block text-sm text-rose-700"
                                >{{ passwordForm.errors.password }}</span
                            >
                        </label>
                        <label class="block text-sm font-bold text-slate-800">
                            Sahkan kata laluan baharu
                            <input
                                v-model="passwordForm.password_confirmation"
                                :type="showPasswords ? 'text' : 'password'"
                                required
                                minlength="15"
                                autocomplete="new-password"
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
                            />
                        </label>
                    </div>
                    <div class="grid gap-2 rounded-2xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
                        <p
                            class="flex items-center gap-2"
                            :class="passwordLongEnough ? 'text-emerald-700' : 'text-slate-500'"
                        >
                            <span aria-hidden="true">{{ passwordLongEnough ? '✓' : '○' }}</span>
                            Sekurang-kurangnya 15 aksara
                        </p>
                        <p
                            class="flex items-center gap-2"
                            :class="passwordsMatch ? 'text-emerald-700' : 'text-slate-500'"
                        >
                            <span aria-hidden="true">{{ passwordsMatch ? '✓' : '○' }}</span>
                            Kedua-dua kata laluan sepadan
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="passwordForm.processing || !passwordReady"
                        class="min-h-12 w-fit rounded-xl bg-slate-950 px-6 font-black text-white transition hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ passwordForm.processing ? 'Mengemas kini…' : 'Tukar kata laluan' }}
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-sky-200 bg-sky-50 p-6 sm:p-8">
                <h2 class="text-lg font-black text-sky-950">Terlupa kata laluan?</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-sky-900">
                    Log keluar dan pilih “Lupa kata laluan” pada halaman log masuk. Pautan selamat
                    yang sah selama 60 minit akan dihantar ke e-mel anda.
                </p>
            </section>
        </div>
    </DashboardShell>
</template>
