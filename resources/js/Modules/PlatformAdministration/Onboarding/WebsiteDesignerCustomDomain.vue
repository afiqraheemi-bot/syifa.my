<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, required: true },
    contextLabel: { type: String, required: true },
    job: { type: Object, required: true },
    domain: { type: Object, default: null },
    operationsUrl: { type: String, required: true },
    backUrl: { type: String, required: true },
});

const hostname = ref('');
const busy = ref(false);
const feedback = ref('');
const error = ref('');
const statusLabel = computed(() => props.domain?.status?.replaceAll('_', ' ') ?? 'Not configured');
const navigation = createDashboardNavigation(props.navigation);

function submit(path, payload, confirmation = null) {
    if (busy.value || (confirmation && !window.confirm(confirmation))) return;

    busy.value = true;
    feedback.value = '';
    error.value = '';
    router.post(path, payload, {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = 'Custom domain updated successfully.';
            hostname.value = '';
        },
        onError: (errors) => {
            error.value =
                Object.values(errors)[0] ?? 'The custom domain operation could not be completed.';
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

function requestDomain() {
    submit(props.operationsUrl, { hostname: hostname.value });
}

function verifyDomain() {
    submit(`${props.operationsUrl}/verify`, {
        domain_id: props.domain.id,
        version: props.domain.version,
    });
}

function activateDomain() {
    submit(
        `${props.operationsUrl}/activate`,
        { domain_id: props.domain.id, version: props.domain.version },
        'Activate this verified custom domain for the public Website?',
    );
}

function detachDomain() {
    submit(
        `${props.operationsUrl}/detach`,
        { domain_id: props.domain.id, version: props.domain.version },
        'Detach this custom domain? Public routing through it will stop.',
    );
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
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="rounded-full bg-violet-100 px-4 py-2 text-sm font-semibold text-violet-900">
                Managed add-on service
            </div>
            <a
                :href="backUrl"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-800 transition hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
                Back to assigned job
            </a>
        </div>

        <p
            v-if="feedback"
            role="status"
            class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900"
        >
            {{ feedback }}
        </p>
        <p
            v-if="error"
            role="alert"
            class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"
        >
            {{ error }}
        </p>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6 max-w-2xl">
                <h2 class="text-xl font-semibold text-slate-950">Domain connection</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    The clinic's default SYIFA.my address remains available. Connect a custom domain
                    only after the add-on is confirmed and the clinic controls its DNS.
                </p>
            </div>

            <template v-if="!domain">
                <form
                    class="flex max-w-3xl flex-col gap-3 sm:flex-row"
                    @submit.prevent="requestDomain"
                >
                    <label class="flex-1">
                        <span class="text-sm font-medium text-slate-800">Domain hostname</span>
                        <input
                            v-model.trim="hostname"
                            required
                            autocomplete="url"
                            placeholder="www.yourclinic.my"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <button
                        type="submit"
                        :disabled="busy"
                        class="self-stretch rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white disabled:opacity-60 sm:self-end"
                    >
                        {{ busy ? 'Requesting…' : 'Request domain' }}
                    </button>
                </form>
            </template>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-slate-500">
                            Custom domain
                        </p>
                        <h2 class="mt-1 break-all text-xl font-semibold text-slate-950">
                            {{ domain.hostname }}
                        </h2>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium capitalize text-slate-700"
                    >
                        {{ statusLabel }}
                    </span>
                </div>

                <div
                    v-if="domain.status === 'verification_pending'"
                    class="mt-6 rounded-xl bg-amber-50 p-4"
                >
                    <p class="font-semibold text-amber-950">DNS ownership proof required</p>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-amber-800">TXT record name</dt>
                            <dd class="break-all font-mono text-amber-950">
                                {{ domain.verificationName }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-amber-800">TXT record value</dt>
                            <dd class="break-all font-mono text-amber-950">
                                {{ domain.verificationValue }}
                            </dd>
                        </div>
                    </dl>
                    <button
                        type="button"
                        :disabled="busy"
                        class="mt-4 rounded-xl bg-amber-900 px-4 py-2 font-semibold text-white disabled:opacity-60"
                        @click="verifyDomain"
                    >
                        {{ busy ? 'Checking…' : 'Verify DNS' }}
                    </button>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        v-if="domain.status === 'verified'"
                        type="button"
                        :disabled="busy"
                        class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-60"
                        @click="activateDomain"
                    >
                        Activate domain
                    </button>
                    <a
                        v-if="domain.status === 'active'"
                        :href="`https://${domain.hostname}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white"
                    >
                        Open Website
                    </a>
                    <button
                        v-if="['verified', 'active', 'failing'].includes(domain.status)"
                        type="button"
                        :disabled="busy"
                        class="rounded-xl border border-red-300 px-4 py-2 font-semibold text-red-700 disabled:opacity-60"
                        @click="detachDomain"
                    >
                        Detach domain
                    </button>
                </div>
            </template>
        </section>
    </DashboardShell>
</template>
