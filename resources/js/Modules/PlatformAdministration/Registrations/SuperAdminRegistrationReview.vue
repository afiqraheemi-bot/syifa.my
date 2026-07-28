<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import {
    createDashboardNavigation,
    DashboardEmptyState,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    registrations: { type: Array, required: true },
    filters: { type: Object, required: true },
    reviewUrlTemplate: { type: String, required: true },
    decisionUrlTemplate: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const forms = reactive({});
const busy = ref(null);
const error = ref('');
const success = ref('');

function decisionForm(registration) {
    forms[registration.id] ??= {
        outcome: 'approved',
        reasonCategory: 'eligible_clinic',
        correctionInstructions: '',
    };
    return forms[registration.id];
}

async function mutate(registration, url, body, confirmation) {
    if (busy.value || !window.confirm(confirmation)) return false;
    busy.value = registration.id;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    busy.value = null;
    if (!response.ok) {
        error.value =
            response.body?.message ??
            Object.values(response.body?.errors ?? {}).flat()[0] ??
            'The registration action could not be completed.';
        return false;
    }
    return true;
}

async function startReview(registration) {
    const completed = await mutate(
        registration,
        props.reviewUrlTemplate.replace('__REGISTRATION_ID__', registration.id),
        { expected_version: registration.version },
        `Start accountable review for ${registration.clinicName}?`,
    );
    if (completed) {
        success.value = 'Registration review started.';
        router.reload({ only: ['registrations'] });
    }
}

async function decide(registration) {
    const form = decisionForm(registration);
    const label = form.outcome.replaceAll('_', ' ');
    const completed = await mutate(
        registration,
        props.decisionUrlTemplate.replace('__REGISTRATION_ID__', registration.id),
        {
            outcome: form.outcome,
            reason_category: form.reasonCategory,
            correction_instructions:
                form.outcome === 'correction_requested' ? form.correctionInstructions : null,
            expected_version: registration.version,
        },
        `Record “${label}” as the accountable decision for ${registration.clinicName}?`,
    );
    if (completed) {
        success.value = 'Registration decision recorded.';
        router.reload({ only: ['registrations'] });
    }
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
        <form
            method="get"
            class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row"
        >
            <select
                name="status"
                :value="filters.status ?? ''"
                class="min-h-11 flex-1 rounded-xl border border-slate-300 px-4"
            >
                <option value="">All statuses</option>
                <option value="submitted">Submitted</option>
                <option value="under_review">Under review</option>
                <option value="correction_requested">Correction requested</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="provisioned">Provisioned</option>
            </select>
            <button class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white">
                Filter
            </button>
        </form>

        <p v-if="error" role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-800">
            {{ error }}
        </p>
        <p v-if="success" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">
            {{ success }}
        </p>

        <DashboardEmptyState
            v-if="registrations.length === 0"
            title="No clinic registrations"
            description="Submitted prospective clinics will appear here for accountable review."
        />
        <div v-else class="grid gap-4">
            <article
                v-for="registration in registrations"
                :key="registration.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">
                            {{ registration.status.replaceAll('_', ' ') }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold text-slate-950">
                            {{ registration.clinicName ?? 'Unnamed clinic' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ registration.clinicEmail }} · {{ registration.clinicPhone }}
                        </p>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ registration.clinicAddress }}
                        </p>
                    </div>
                    <button
                        v-if="registration.status === 'submitted'"
                        type="button"
                        :disabled="busy !== null"
                        class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white disabled:opacity-50"
                        @click="startReview(registration)"
                    >
                        {{ busy === registration.id ? 'Starting…' : 'Start review' }}
                    </button>
                </div>

                <div
                    v-if="registration.status === 'under_review'"
                    class="mt-5 grid gap-3 border-t border-slate-200 pt-5 lg:grid-cols-[14rem_1fr_auto]"
                >
                    <select
                        v-model="decisionForm(registration).outcome"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    >
                        <option value="approved">Approve</option>
                        <option value="correction_requested">Request correction</option>
                        <option value="rejected">Reject</option>
                    </select>
                    <div class="grid gap-2">
                        <input
                            v-model="decisionForm(registration).reasonCategory"
                            maxlength="100"
                            placeholder="Governed reason category"
                            class="min-h-11 rounded-xl border border-slate-300 px-3"
                        />
                        <textarea
                            v-if="decisionForm(registration).outcome === 'correction_requested'"
                            v-model="decisionForm(registration).correctionInstructions"
                            maxlength="2000"
                            placeholder="Required correction instructions"
                            class="min-h-24 rounded-xl border border-slate-300 p-3"
                        />
                    </div>
                    <button
                        type="button"
                        :disabled="busy !== null"
                        class="min-h-11 rounded-xl bg-emerald-700 px-5 font-semibold text-white disabled:opacity-50"
                        @click="decide(registration)"
                    >
                        {{ busy === registration.id ? 'Recording…' : 'Record decision' }}
                    </button>
                </div>

                <div
                    v-if="registration.currentDecisionOutcome"
                    class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-700"
                >
                    Current decision:
                    <strong>{{ registration.currentDecisionOutcome.replaceAll('_', ' ') }}</strong>
                    · {{ registration.currentDecisionReasonCategory }}
                    <p v-if="registration.currentCorrectionInstructions" class="mt-2">
                        {{ registration.currentCorrectionInstructions }}
                    </p>
                </div>
            </article>
        </div>
    </DashboardShell>
</template>
