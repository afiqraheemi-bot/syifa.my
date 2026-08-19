<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
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
    indexUrl: { type: String, required: true },
    reviewUrlTemplate: { type: String, required: true },
    decisionUrlTemplate: { type: String, required: true },
    updateUrlTemplate: { type: String, required: true },
    archiveUrlTemplate: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const decisionForms = reactive({});
const editForms = reactive({});
const busy = ref(null);
const error = ref('');
const success = ref('');

watch(
    () => props.registrations,
    (registrations) => {
        for (const registration of registrations) {
            editForms[registration.id] = {
                clinicName: registration.clinicName ?? '',
                clinicEmail: registration.clinicEmail ?? '',
                clinicPhone: registration.clinicPhone ?? '',
                clinicAddress: registration.clinicAddress ?? '',
            };
            decisionForms[registration.id] ??= {
                outcome: 'approved',
                reasonCategory: 'eligible_clinic',
                correctionInstructions: '',
            };
        }
    },
    { immediate: true },
);

function decisionForm(registration) {
    decisionForms[registration.id] ??= {
        outcome: 'approved',
        reasonCategory: 'eligible_clinic',
        correctionInstructions: '',
    };
    return decisionForms[registration.id];
}

function editForm(registration) {
    return editForms[registration.id];
}

function registrationUrl(template, registration) {
    return template.replace('__REGISTRATION_ID__', registration.id);
}

function humanStatus(status) {
    return status.replaceAll('_', ' ').replace(/^./, (character) => character.toUpperCase());
}

function formatDate(value) {
    if (!value) return 'Not recorded';
    return new Intl.DateTimeFormat('en-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

async function mutate(registration, action, url, method, body, confirmation = null) {
    if (busy.value || (confirmation && !window.confirm(confirmation))) return false;

    busy.value = `${action}:${registration.id}`;
    error.value = '';
    success.value = '';

    try {
        const response = await browserHttpRequest(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const responseMessage =
                typeof response.body?.message === 'string' &&
                response.body.message.trim().toLowerCase() !== 'server error'
                    ? response.body.message
                    : null;
            error.value =
                responseMessage ??
                Object.values(response.body?.errors ?? {}).flat()[0] ??
                'The server could not complete this registration action. Refresh once and try again; if it persists, check the production release and application log.';
            return false;
        }

        return true;
    } catch {
        error.value = 'The registration action could not be completed. Please try again.';
        return false;
    } finally {
        busy.value = null;
    }
}

function refreshRegistrations() {
    router.reload({ only: ['registrations'] });
}

async function startReview(registration) {
    const completed = await mutate(
        registration,
        'review',
        registrationUrl(props.reviewUrlTemplate, registration),
        'POST',
        { expected_version: registration.version },
        `Start accountable review for ${registration.clinicName}?`,
    );
    if (completed) {
        success.value = 'Registration review started.';
        refreshRegistrations();
    }
}

async function decide(registration) {
    const form = decisionForm(registration);
    const completed = await mutate(
        registration,
        'decision',
        registrationUrl(props.decisionUrlTemplate, registration),
        'POST',
        {
            outcome: form.outcome,
            reason_category: form.reasonCategory,
            correction_instructions:
                form.outcome === 'correction_requested' ? form.correctionInstructions : null,
            expected_version: registration.version,
        },
        `Record “${humanStatus(form.outcome)}” for ${registration.clinicName}?`,
    );
    if (completed) {
        success.value = 'Registration decision recorded.';
        refreshRegistrations();
    }
}

async function updateRegistration(registration) {
    const form = editForm(registration);
    const completed = await mutate(
        registration,
        'update',
        registrationUrl(props.updateUrlTemplate, registration),
        'PATCH',
        {
            clinic_name: form.clinicName,
            clinic_email: form.clinicEmail,
            clinic_phone: form.clinicPhone,
            clinic_address: form.clinicAddress,
            expected_version: registration.version,
        },
    );
    if (completed) {
        success.value = 'Registration details updated.';
        refreshRegistrations();
    }
}

async function archiveRegistration(registration) {
    const completed = await mutate(
        registration,
        'archive',
        registrationUrl(props.archiveUrlTemplate, registration),
        'DELETE',
        { expected_version: registration.version },
        `Remove ${registration.clinicName} from active registrations? The audit record will be preserved.`,
    );
    if (completed) {
        success.value =
            'Registration removed from the active list. Its audit history remains preserved.';
        refreshRegistrations();
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
            :action="indexUrl"
            class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(16rem,1fr)_13rem_13rem_12rem_auto_auto]"
        >
            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                Search registration
                <input
                    name="search"
                    :value="filters.search ?? ''"
                    maxlength="100"
                    placeholder="Clinic, email, phone or ID"
                    class="min-h-11 rounded-xl border border-slate-300 px-4 font-normal"
                />
            </label>
            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                Registered during
                <select
                    name="period"
                    :value="filters.period ?? ''"
                    class="min-h-11 rounded-xl border border-slate-300 px-4 font-normal"
                >
                    <option value="">Any time</option>
                    <option value="week">This week</option>
                    <option value="month">This month</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                Status
                <select
                    name="status"
                    :value="filters.status ?? ''"
                    class="min-h-11 rounded-xl border border-slate-300 px-4 font-normal"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="under_review">Under review</option>
                    <option value="correction_requested">Correction requested</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="provisioned">Provisioned</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                Records
                <select
                    name="scope"
                    :value="filters.scope ?? 'active'"
                    class="min-h-11 rounded-xl border border-slate-300 px-4 font-normal"
                >
                    <option value="active">Active only</option>
                    <option value="archived">Removed only</option>
                    <option value="all">All records</option>
                </select>
            </label>
            <button class="min-h-11 self-end rounded-xl bg-slate-900 px-5 font-semibold text-white">
                Apply
            </button>
            <a
                :href="indexUrl"
                class="flex min-h-11 items-center justify-center self-end rounded-xl border border-slate-300 px-5 font-semibold text-slate-700"
            >
                Clear
            </a>
        </form>

        <p v-if="error" role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-800">
            {{ error }}
        </p>
        <p v-if="success" role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">
            {{ success }}
        </p>

        <DashboardEmptyState
            v-if="registrations.length === 0"
            title="No matching clinic registrations"
            description="Adjust the search, date or status filters to find another registration."
        />
        <div v-else class="grid gap-4">
            <article
                v-for="registration in registrations"
                :key="registration.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800"
                            >
                                {{ humanStatus(registration.status) }}
                            </span>
                            <span
                                v-if="registration.archivedAt"
                                class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-700"
                            >
                                Removed
                            </span>
                        </div>
                        <h2 class="mt-3 text-xl font-bold text-slate-950">
                            {{ registration.clinicName ?? 'Incomplete clinic registration' }}
                        </h2>
                        <p class="mt-1 break-words text-sm text-slate-600">
                            {{ registration.clinicEmail ?? 'No email' }} ·
                            {{ registration.clinicPhone ?? 'No phone' }}
                        </p>
                        <p class="mt-2 max-w-3xl text-sm text-slate-600">
                            {{ registration.clinicAddress ?? 'No clinic address entered' }}
                        </p>
                        <p class="mt-3 text-xs text-slate-500">
                            Registered {{ formatDate(registration.createdAt) }} · Reference
                            {{ registration.id.slice(0, 8).toUpperCase() }}
                        </p>
                    </div>
                    <div v-if="!registration.archivedAt" class="flex flex-wrap gap-2">
                        <button
                            v-if="registration.status === 'submitted'"
                            type="button"
                            :disabled="busy !== null"
                            class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white disabled:opacity-50"
                            @click="startReview(registration)"
                        >
                            {{
                                busy === `review:${registration.id}` ? 'Starting…' : 'Start review'
                            }}
                        </button>
                        <button
                            v-if="registration.canArchive"
                            type="button"
                            :disabled="busy !== null"
                            class="min-h-11 rounded-xl border border-red-300 px-5 font-semibold text-red-700 disabled:opacity-50"
                            @click="archiveRegistration(registration)"
                        >
                            {{ busy === `archive:${registration.id}` ? 'Removing…' : 'Remove' }}
                        </button>
                    </div>
                </div>

                <details
                    v-if="registration.canEdit"
                    class="mt-5 rounded-xl border border-slate-200"
                >
                    <summary class="cursor-pointer p-4 font-semibold text-slate-800">
                        Edit registration details
                    </summary>
                    <form
                        v-if="editForms[registration.id]"
                        class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2"
                        @submit.prevent="updateRegistration(registration)"
                    >
                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Clinic name
                            <input
                                v-model="editForms[registration.id].clinicName"
                                required
                                maxlength="200"
                                class="min-h-11 rounded-xl border border-slate-300 px-3 font-normal"
                            />
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Clinic email
                            <input
                                v-model="editForms[registration.id].clinicEmail"
                                required
                                type="email"
                                maxlength="254"
                                class="min-h-11 rounded-xl border border-slate-300 px-3 font-normal"
                            />
                        </label>
                        <label class="grid gap-1 text-sm font-semibold text-slate-700">
                            Clinic phone
                            <input
                                v-model="editForms[registration.id].clinicPhone"
                                required
                                maxlength="40"
                                class="min-h-11 rounded-xl border border-slate-300 px-3 font-normal"
                            />
                        </label>
                        <label
                            class="grid gap-1 text-sm font-semibold text-slate-700 md:col-span-2"
                        >
                            Clinic address
                            <textarea
                                v-model="editForms[registration.id].clinicAddress"
                                required
                                maxlength="1000"
                                class="min-h-24 rounded-xl border border-slate-300 p-3 font-normal"
                            />
                        </label>
                        <button
                            type="submit"
                            :disabled="busy !== null"
                            class="min-h-11 justify-self-start rounded-xl bg-emerald-700 px-5 font-semibold text-white disabled:opacity-50"
                        >
                            {{ busy === `update:${registration.id}` ? 'Saving…' : 'Save changes' }}
                        </button>
                    </form>
                </details>

                <div
                    v-if="registration.status === 'under_review'"
                    class="mt-5 grid gap-3 border-t border-slate-200 pt-5 lg:grid-cols-[14rem_1fr_auto]"
                >
                    <select
                        v-model="decisionForm(registration).outcome"
                        aria-label="Registration decision"
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
                        {{
                            busy === `decision:${registration.id}`
                                ? 'Recording…'
                                : 'Record decision'
                        }}
                    </button>
                </div>

                <div
                    v-if="registration.currentDecisionOutcome"
                    class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-700"
                >
                    Current decision:
                    <strong>{{ humanStatus(registration.currentDecisionOutcome) }}</strong>
                    · {{ registration.currentDecisionReasonCategory }}
                    <p v-if="registration.currentCorrectionInstructions" class="mt-2">
                        {{ registration.currentCorrectionInstructions }}
                    </p>
                </div>

                <p
                    v-if="
                        !registration.canEdit &&
                        !registration.canArchive &&
                        !registration.archivedAt
                    "
                    class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"
                >
                    This registration is protected because it has reached an approved, financial or
                    provisioned stage. Its authoritative history cannot be edited or removed here.
                </p>
            </article>
        </div>
    </DashboardShell>
</template>
