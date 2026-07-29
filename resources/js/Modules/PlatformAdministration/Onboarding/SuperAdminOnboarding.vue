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
    onboarding: { type: Object, required: true },
    filters: { type: Object, required: true },
    assignUrlTemplate: { type: String, required: true },
    reassignUrlTemplate: { type: String, required: true },
    lifecycleUrlTemplate: { type: String, required: true },
    taskUrlTemplate: { type: String, required: true },
    ownerUrlTemplate: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const selections = reactive({});
const ownerForms = reactive({});
const busyJob = ref(null);
const error = ref('');
const success = ref('');

async function assign(job) {
    const designerId = selections[job.id];
    if (!designerId || busyJob.value) return;
    const reassigning = Boolean(job.assignmentId);
    if (
        !window.confirm(
            `${reassigning ? 'Reassign' : 'Assign'} the selected Website Designer to ${job.clinicName}?`,
        )
    )
        return;

    busyJob.value = job.id;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(
        (reassigning ? props.reassignUrlTemplate : props.assignUrlTemplate).replace(
            '__JOB_ID__',
            job.id,
        ),
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ...(reassigning ? { current_assignment_id: job.assignmentId } : {}),
                platform_identity_id: designerId,
                expected_version: job.version,
            }),
        },
    );
    busyJob.value = null;

    if (!response.ok) {
        error.value =
            response.body?.message ??
            response.body?.errors?.platform_identity_id?.[0] ??
            'The assignment could not be completed.';
        return;
    }

    success.value = `Website Designer ${reassigning ? 'reassigned' : 'assigned'} successfully.`;
    router.reload({ only: ['onboarding'] });
}

function ownerForm(job) {
    ownerForms[job.id] ??= { name: '', email: '' };
    return ownerForms[job.id];
}

async function establishOwner(job) {
    const form = ownerForm(job);
    if (!form.name || !form.email || busyJob.value) return;
    if (
        !window.confirm(
            `Establish ${form.name} as the verified authority candidate for ${job.clinicName}?`,
        )
    )
        return;

    busyJob.value = `owner:${job.id}`;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(
        props.ownerUrlTemplate.replace('__TENANT_ID__', job.tenantId),
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form),
        },
    );
    busyJob.value = null;

    if (!response.ok) {
        error.value =
            response.body?.message ??
            response.body?.errors?.email?.[0] ??
            'Clinic Owner setup could not be started.';
        return;
    }

    success.value = 'Clinic Owner setup email issued successfully.';
    router.reload({ only: ['onboarding'] });
}

function lifecycleOperations(job) {
    if (job.status === 'ready_for_launch') return ['complete', 'cancel'];
    if (['completed', 'cancelled'].includes(job.status)) return ['reopen'];
    return ['cancel'];
}

function lifecycleLabel(operation) {
    return { complete: 'Complete', cancel: 'Cancel', reopen: 'Reopen' }[operation];
}

async function manageLifecycle(job, operation) {
    if (busyJob.value) return;
    if (!window.confirm(`${lifecycleLabel(operation)} onboarding for ${job.clinicName}?`)) return;
    const reason =
        operation === 'complete'
            ? null
            : window.prompt(`Reason to ${operation} this Onboarding Job:`)?.trim();
    if (operation !== 'complete' && (!reason || reason.length < 5)) {
        error.value = 'A clear reason of at least five characters is required.';
        return;
    }

    busyJob.value = `lifecycle:${job.id}`;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(
        props.lifecycleUrlTemplate.replace('__JOB_ID__', job.id),
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                operation,
                reason,
                expected_version: job.version,
            }),
        },
    );
    busyJob.value = null;

    if (!response.ok) {
        error.value = response.body?.message ?? 'The Onboarding Job could not be updated.';
        return;
    }
    success.value = response.body?.message ?? 'Onboarding Job updated successfully.';
    router.reload({ only: ['onboarding'] });
}

async function waiveTask(job, task) {
    if (busyJob.value) return;
    const reason = window.prompt(`Reason to waive "${task.title}":`)?.trim();
    if (!reason || reason.length < 5) {
        error.value = 'A waiver reason of at least five characters is required.';
        return;
    }
    if (!window.confirm(`Waive "${task.title}" for ${job.clinicName}?`)) return;

    busyJob.value = `task:${task.id}`;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(
        props.taskUrlTemplate.replace('__JOB_ID__', job.id).replace('__TASK_ID__', task.id),
        {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                operation: 'waive',
                waiver_reason: reason,
                expected_version: job.version,
            }),
        },
    );
    busyJob.value = null;
    if (!response.ok) {
        error.value = response.body?.message ?? 'The Onboarding Task could not be waived.';
        return;
    }
    success.value = 'Onboarding Task waived with durable audit evidence.';
    router.reload({ only: ['onboarding'] });
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
            class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_14rem_auto]"
        >
            <input
                name="search"
                :value="filters.search ?? ''"
                placeholder="Search clinic or onboarding job"
                class="min-h-11 rounded-xl border border-slate-300 px-4"
            />
            <select
                name="status"
                :value="filters.status ?? ''"
                class="min-h-11 rounded-xl border border-slate-300 px-4"
            >
                <option value="">All statuses</option>
                <option value="planned">Planned</option>
                <option value="awaiting_inputs">Awaiting inputs</option>
                <option value="assigned">Assigned</option>
                <option value="in_progress">In progress</option>
                <option value="in_review">In review</option>
                <option value="ready_for_launch">Ready for launch</option>
                <option value="completed">Completed</option>
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
            v-if="onboarding.jobs.length === 0"
            title="No onboarding jobs"
            description="Provisioned clinic onboarding jobs will appear here."
        />
        <div v-else class="grid gap-4">
            <article
                v-for="job in onboarding.jobs"
                :key="job.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">
                            {{ job.status.replaceAll('_', ' ') }}
                        </p>
                        <h2 class="mt-1 text-lg font-bold text-slate-950">{{ job.clinicName }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{
                                job.designerName ? `Assigned to ${job.designerName}` : 'Unassigned'
                            }}
                        </p>
                        <p
                            v-if="job.launchReadiness"
                            class="mt-2 text-sm font-semibold"
                            :class="
                                job.launchReadiness.ready ? 'text-emerald-700' : 'text-amber-700'
                            "
                        >
                            Launch readiness:
                            {{ job.launchReadiness.ready ? 'Ready' : 'Blocked' }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <select
                            v-model="selections[job.id]"
                            :disabled="busyJob === job.id"
                            class="min-h-11 min-w-64 rounded-xl border border-slate-300 px-3"
                            :aria-label="`Website Designer for ${job.clinicName}`"
                        >
                            <option value="">Select Website Designer</option>
                            <option
                                v-for="designer in onboarding.designers"
                                :key="designer.id"
                                :value="designer.id"
                                :disabled="designer.id === job.designerId"
                            >
                                {{ designer.name }} — {{ designer.email }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="!selections[job.id] || busyJob !== null"
                            class="min-h-11 rounded-xl bg-emerald-700 px-5 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            @click="assign(job)"
                        >
                            {{
                                busyJob === job.id
                                    ? job.assignmentId
                                        ? 'Reassigning…'
                                        : 'Assigning…'
                                    : job.assignmentId
                                      ? 'Reassign'
                                      : 'Assign'
                            }}
                        </button>
                    </div>
                </div>
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <div v-if="job.ownerAuthorityId" class="text-sm text-slate-700">
                        <strong>{{ job.ownerName }}</strong>
                        <span class="ml-2">{{ job.ownerEmail }}</span>
                        <span
                            class="ml-2 rounded-full px-2 py-1 text-xs font-bold"
                            :class="
                                job.ownerVerified
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-800'
                            "
                        >
                            {{ job.ownerVerified ? 'Access active' : 'Setup pending' }}
                        </span>
                    </div>
                    <div v-else class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <input
                            v-model="ownerForm(job).name"
                            maxlength="120"
                            placeholder="Verified Clinic Owner name"
                            class="min-h-11 rounded-xl border border-slate-300 px-3"
                        />
                        <input
                            v-model="ownerForm(job).email"
                            type="email"
                            maxlength="254"
                            placeholder="Verified Clinic Owner email"
                            class="min-h-11 rounded-xl border border-slate-300 px-3"
                        />
                        <button
                            type="button"
                            :disabled="
                                !ownerForm(job).name || !ownerForm(job).email || busyJob !== null
                            "
                            class="min-h-11 rounded-xl border border-emerald-700 px-5 font-semibold text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="establishOwner(job)"
                        >
                            {{ busyJob === `owner:${job.id}` ? 'Sending…' : 'Set up owner' }}
                        </button>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-200 pt-5">
                    <button
                        v-for="operation in lifecycleOperations(job)"
                        :key="operation"
                        type="button"
                        :disabled="busyJob !== null"
                        class="min-h-10 rounded-xl border px-4 text-sm font-semibold disabled:opacity-50"
                        :class="
                            operation === 'complete'
                                ? 'border-emerald-700 text-emerald-800'
                                : operation === 'cancel'
                                  ? 'border-red-300 text-red-700'
                                  : 'border-slate-400 text-slate-800'
                        "
                        @click="manageLifecycle(job, operation)"
                    >
                        {{
                            busyJob === `lifecycle:${job.id}`
                                ? 'Updating…'
                                : `${lifecycleLabel(operation)} job`
                        }}
                    </button>
                </div>
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-slate-950">Onboarding tasks</h3>
                        <span class="text-sm font-semibold text-slate-600">
                            {{ job.taskSummary.completed }}/{{ job.taskSummary.total }} complete
                        </span>
                    </div>
                    <div class="mt-3 grid gap-2">
                        <div
                            v-for="task in job.tasks"
                            :key="task.id"
                            class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ task.title }}</p>
                                <p class="text-xs text-slate-600">
                                    {{ task.responsibility.replaceAll('_', ' ') }} ·
                                    {{ task.status.replaceAll('_', ' ') }}
                                </p>
                            </div>
                            <button
                                v-if="!['completed', 'waived', 'cancelled'].includes(task.status)"
                                type="button"
                                :disabled="busyJob !== null"
                                class="min-h-9 rounded-lg border border-amber-400 px-3 text-sm font-semibold text-amber-800 disabled:opacity-50"
                                @click="waiveTask(job, task)"
                            >
                                {{ busyJob === `task:${task.id}` ? 'Waiving…' : 'Waive' }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </DashboardShell>
</template>
