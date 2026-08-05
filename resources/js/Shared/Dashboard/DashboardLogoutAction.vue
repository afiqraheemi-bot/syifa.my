<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { deleteBrowserSession } from '../Authentication/session.js';

const page = usePage();
const loading = ref(false);
const failed = ref(false);
const logoutUrl = computed(() => page.props.authentication?.logout_url ?? null);
const loginUrl = computed(() => page.props.authentication?.login_url ?? '/login');

async function logout() {
    if (!logoutUrl.value || loading.value) return;

    loading.value = true;
    failed.value = false;

    try {
        const response = await deleteBrowserSession(logoutUrl.value);

        if (!response.ok) {
            failed.value = true;
            return;
        }

        window.location.assign(loginUrl.value);
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div v-if="logoutUrl" class="flex items-center gap-2">
        <span
            v-if="failed"
            role="alert"
            class="hidden text-xs font-semibold text-red-700 md:inline"
        >
            Log keluar gagal. Cuba lagi.
        </span>
        <button
            type="button"
            :disabled="loading"
            class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60"
            @click="logout"
        >
            {{ loading ? 'Sedang keluar…' : 'Log keluar' }}
        </button>
    </div>
</template>
