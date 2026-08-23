<script setup>
import { useForm } from '@inertiajs/vue3';
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

function updateProfile() {
    profileForm.patch(props.profile.profileUpdateUrl, { preserveScroll: true });
}

function updatePassword() {
    passwordForm.put(props.profile.passwordUpdateUrl, {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
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

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <p class="text-xs font-black tracking-[0.16em] text-emerald-700 uppercase">
                        Profile
                    </p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Clinic Owner details</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        This name is used across your secure Clinic Owner workspace.
                    </p>
                </div>
                <form class="space-y-5 p-6 sm:p-8" @submit.prevent="updateProfile">
                    <label class="block text-sm font-bold text-slate-800">
                        Full name
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
                        <p class="text-sm font-bold text-slate-800">Email address</p>
                        <div
                            class="mt-2 flex min-h-12 items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4"
                        >
                            <span class="min-w-0 truncate text-slate-700">{{ profile.email }}</span>
                            <span
                                class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800"
                            >
                                {{ profile.emailVerified ? 'Verified' : 'Unverified' }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            Contact SYIFA support to transfer the owner email securely.
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="profileForm.processing || !profileForm.isDirty"
                        class="min-h-12 rounded-xl bg-emerald-700 px-6 font-black text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ profileForm.processing ? 'Saving…' : 'Save profile' }}
                    </button>
                </form>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
                    <p class="text-xs font-black tracking-[0.16em] text-sky-700 uppercase">
                        Security
                    </p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Change password</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Use at least 15 characters. You will be signed out after a successful
                        change.
                    </p>
                </div>
                <form class="grid gap-5 p-6 sm:p-8" @submit.prevent="updatePassword">
                    <label class="block text-sm font-bold text-slate-800">
                        Current password
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
                        />
                        <span
                            v-if="passwordForm.errors.current_password"
                            class="mt-2 block text-sm text-rose-700"
                            >{{ passwordForm.errors.current_password }}</span
                        >
                    </label>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-bold text-slate-800">
                            New password
                            <input
                                v-model="passwordForm.password"
                                type="password"
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
                            Confirm new password
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                minlength="15"
                                autocomplete="new-password"
                                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 focus:border-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-100"
                            />
                        </label>
                    </div>
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="min-h-12 w-fit rounded-xl bg-slate-950 px-6 font-black text-white transition hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ passwordForm.processing ? 'Updating…' : 'Update password' }}
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-sky-200 bg-sky-50 p-6 sm:p-8">
                <h2 class="text-lg font-black text-sky-950">Forgot your password?</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-sky-900">
                    Sign out and use “Forgot password” on the login page. A secure link valid for 60
                    minutes will be sent to your verified email.
                </p>
            </section>
        </div>
    </DashboardShell>
</template>
