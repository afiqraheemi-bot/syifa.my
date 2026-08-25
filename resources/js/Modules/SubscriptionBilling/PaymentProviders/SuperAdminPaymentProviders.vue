<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    createDashboardNavigation,
    DashboardEmptyState,
    DashboardLoadingState,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    providerEndpoints: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const configurations = ref([]);
const health = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const success = ref('');
const confirmation = ref(null);

const providers = computed(() =>
    configurations.value.map((provider) => ({
        ...provider,
        health: health.value.find((item) => item.provider_key === provider.provider_key) ?? null,
        ready:
            provider.verification_passed && provider.webhook_configured && provider.provider_ready,
    })),
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(body.error ?? body.detail ?? 'The payment provider request failed.');
    }

    return body;
}

async function loadProviders() {
    loading.value = true;
    error.value = '';

    try {
        const [configurationResponse, healthResponse] = await Promise.all([
            request(props.providerEndpoints.index),
            request(props.providerEndpoints.health),
        ]);
        configurations.value = configurationResponse.data ?? [];
        health.value = healthResponse.data ?? [];
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'Payment providers could not be loaded.';
    } finally {
        loading.value = false;
    }
}

function ask(provider, action) {
    success.value = '';
    error.value = '';
    confirmation.value = {
        providerKey: provider.provider_key,
        action,
        webhookConfigured: provider.webhook_configured,
        providerReady: provider.provider_ready,
    };
}

function cancel() {
    if (!busy.value) {
        confirmation.value = null;
    }
}

function handleEscape(event) {
    if (event.key === 'Escape') cancel();
}

async function confirmAction() {
    if (!confirmation.value) {
        return;
    }

    const pending = confirmation.value;
    busy.value = true;
    error.value = '';

    try {
        const payload =
            pending.action === 'assess'
                ? {
                      webhook_configured: pending.webhookConfigured,
                      provider_ready: pending.providerReady,
                  }
                : {};
        await request(
            `${props.providerEndpoints.index}/${encodeURIComponent(pending.providerKey)}/${pending.action}`,
            {
                method: 'POST',
                body: JSON.stringify(payload),
            },
        );
        success.value = `${pending.providerKey} was updated successfully.`;
        confirmation.value = null;
        await loadProviders();
    } catch (exception) {
        error.value =
            exception instanceof Error ? exception.message : 'The provider could not be updated.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEscape);
    loadProviders();
});
onBeforeUnmount(() => window.removeEventListener('keydown', handleEscape));
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
        <section
            class="overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950 px-6 py-7 text-white shadow-sm sm:px-8"
        >
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">Payment rails</p>
            <h2 class="mt-3 text-2xl font-black sm:text-3xl">Kesediaan penyedia pembayaran</h2>
            <p class="mt-2 max-w-2xl leading-7 text-emerald-50/80">
                Aktifkan penyedia hanya selepas verification, webhook dan health check disahkan.
            </p>
        </section>

        <p
            v-if="success"
            role="status"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900"
        >
            {{ success }}
        </p>
        <div
            v-if="error && !loading"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            <p class="font-semibold">{{ error }}</p>
            <button
                type="button"
                class="mt-2 font-bold underline underline-offset-4"
                @click="loadProviders"
            >
                Try again
            </button>
        </div>

        <DashboardLoadingState v-if="loading" label="Loading payment providers" />

        <section v-else-if="providers.length" class="grid gap-5 xl:grid-cols-2">
            <article
                v-for="provider in providers"
                :key="provider.provider_key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold capitalize text-slate-950">
                            {{ provider.provider_key }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ provider.enabled ? 'Active for new attempts' : 'Inactive' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-if="provider.is_default"
                            class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800"
                        >
                            Default
                        </span>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-bold"
                            :class="
                                provider.ready
                                    ? 'bg-emerald-50 text-emerald-800'
                                    : 'bg-amber-50 text-amber-800'
                            "
                        >
                            {{ provider.ready ? 'Ready' : 'Not ready' }}
                        </span>
                    </div>
                </div>

                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-semibold text-slate-500">Verification</dt>
                        <dd class="mt-1 font-medium text-slate-900">
                            {{ provider.verification_passed ? 'Passed' : 'Required' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Webhook</dt>
                        <dd class="mt-1 font-medium text-slate-900">
                            {{ provider.webhook_configured ? 'Configured' : 'Required' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Provider readiness</dt>
                        <dd class="mt-1 font-medium text-slate-900">
                            {{ provider.provider_ready ? 'Ready' : 'Required' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Health</dt>
                        <dd class="mt-1 font-medium capitalize text-slate-900">
                            {{ provider.health?.status ?? 'Unavailable' }}
                        </dd>
                        <p v-if="provider.health" class="mt-1 text-xs text-slate-500">
                            {{
                                provider.health.accepting_new_attempts
                                    ? 'Accepting new attempts'
                                    : 'Not accepting new attempts'
                            }}
                        </p>
                    </div>
                </dl>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-900"
                        @click="ask(provider, 'assess')"
                    >
                        Assess readiness
                    </button>
                    <button
                        v-if="!provider.enabled"
                        type="button"
                        class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white"
                        @click="ask(provider, 'enable')"
                    >
                        Enable
                    </button>
                    <button
                        v-else
                        type="button"
                        class="min-h-11 rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-800"
                        @click="ask(provider, 'disable')"
                    >
                        Disable
                    </button>
                    <button
                        v-if="provider.enabled && !provider.is_default"
                        type="button"
                        class="min-h-11 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white"
                        @click="ask(provider, 'default')"
                    >
                        Set as default
                    </button>
                </div>
            </article>
        </section>

        <DashboardEmptyState
            v-else-if="!error"
            title="No payment providers"
            description="No provider configurations are available."
        />

        <Teleport to="body">
            <div
                v-if="confirmation"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                @click.self="cancel"
            >
                <section
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="provider-confirmation-title"
                    class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-7"
                >
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">
                        Tindakan penyedia
                    </p>
                    <h2
                        id="provider-confirmation-title"
                        class="mt-2 text-xl font-black text-slate-950"
                    >
                        Sahkan {{ confirmation.action }}
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Tindakan ini akan digunakan pada
                        <strong class="capitalize">{{ confirmation.providerKey }}</strong
                        >.
                    </p>
                    <div
                        v-if="confirmation.action === 'assess'"
                        class="mt-5 grid gap-3 sm:grid-cols-2"
                    >
                        <label
                            class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                        >
                            <input v-model="confirmation.webhookConfigured" type="checkbox" />
                            <span class="text-sm font-semibold">Webhook dikonfigurasi</span>
                        </label>
                        <label
                            class="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                        >
                            <input v-model="confirmation.providerReady" type="checkbox" />
                            <span class="text-sm font-semibold">Penyedia bersedia</span>
                        </label>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold"
                            :disabled="busy"
                            @click="cancel"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                            :disabled="busy"
                            @click="confirmAction"
                        >
                            {{ busy ? 'Melaksanakan…' : 'Sahkan tindakan' }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </DashboardShell>
</template>
