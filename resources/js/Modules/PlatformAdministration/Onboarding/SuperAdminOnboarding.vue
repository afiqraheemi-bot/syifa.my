<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import { createOnboardingCheckpoints } from '../../../Shared/Onboarding/checkpoints.js';
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
const hasFilters = computed(() => Boolean(props.filters.search || props.filters.status));
const activeJobs = computed(
    () =>
        props.onboarding.jobs.filter((job) => !['completed', 'cancelled'].includes(job.status))
            .length,
);

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
    ownerForms[job.id] ??= { name: job.ownerName ?? '', email: job.ownerEmail ?? '' };
    return ownerForms[job.id];
}

function checkpoints(job) {
    return createOnboardingCheckpoints(job.tasks);
}

function nextAction(job) {
    if (!job.designerName) return 'Assign a Website Designer';
    if (!job.ownerVerified) return 'Complete Clinic Owner access';
    const current = checkpoints(job).find((checkpoint) => checkpoint.state === 'current');
    if (current) return `${current.responsibilityLabel}: ${current.label}`;
    if (!job.launchReadiness?.ready) return 'Complete review and launch evidence';

    return 'Complete onboarding';
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
        <section
            class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-emerald-950 to-emerald-800 px-5 py-6 text-white shadow-lg sm:px-7 sm:py-7"
            aria-label="Ringkasan onboarding"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-lime-300">
                        Operasi onboarding
                    </p>
                    <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        Setiap klinik, satu tindakan seterusnya yang jelas
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-50/80">
                        Pantau pemilik, tugasan designer dan bukti pelancaran tanpa mengaburkan
                        status sebenar workflow.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-3">
                        <p class="text-2xl font-black">{{ onboarding.jobs.length }}</p>
                        <p class="text-xs font-bold text-emerald-50/75">Dipaparkan</p>
                    </div>
                    <div class="rounded-2xl border border-lime-300/25 bg-lime-300/10 px-5 py-3">
                        <p class="text-2xl font-black text-lime-300">{{ activeJobs }}</p>
                        <p class="text-xs font-bold text-emerald-50/75">Masih aktif</p>
                    </div>
                </div>
            </div>
        </section>

        <form
            method="get"
            class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_14rem_auto_auto]"
        >
            <input
                name="search"
                :value="filters.search ?? ''"
                placeholder="Search clinic, owner, designer or reference"
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
                <option value="blocked">Blocked</option>
                <option value="in_review">In review</option>
                <option value="correction_required">Correction required</option>
                <option value="ready_for_launch">Ready for launch</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="reopened">Reopened</option>
            </select>
            <button class="min-h-11 rounded-xl bg-slate-900 px-5 font-semibold text-white">
                Filter
            </button>
            <a
                v-if="hasFilters"
                href="?"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 font-semibold text-slate-700"
            >
                Reset
            </a>
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
        <div v-else class="grid gap-6">
            <article
                v-for="job in onboarding.jobs"
                :id="`job-${job.id}`"
                :key="job.id"
                class="scroll-mt-24 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-7">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">
                                {{ job.status.replaceAll('_', ' ') }}
                            </p>
                            <h2 class="mt-1 text-xl font-bold text-slate-950">
                                {{ job.clinicName }}
                            </h2>
                            <p class="mt-2 text-sm text-slate-600">
                                Next: <strong class="text-slate-900">{{ nextAction(job) }}</strong>
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-slate-200"
                        >
                            <span class="font-bold text-slate-950">
                                {{ job.taskSummary.completed }}/{{ job.taskSummary.total }}
                            </span>
                            <span class="text-slate-600"> tasks complete</span>
                        </div>
                    </div>
                </div>

                <div
                    class="grid gap-8 px-5 py-6 sm:px-7 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]"
                >
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">
                            Onboarding checkpoints
                        </h3>
                        <ol class="mt-5 space-y-0">
                            <li
                                v-for="(checkpoint, index) in checkpoints(job)"
                                :key="checkpoint.key"
                                class="relative grid grid-cols-[2rem_1fr] gap-3 pb-6 last:pb-0"
                            >
                                <span
                                    v-if="index < checkpoints(job).length - 1"
                                    class="absolute left-[0.9375rem] top-8 h-[calc(100%-1.25rem)] w-px bg-slate-200"
                                    aria-hidden="true"
                                />
                                <span
                                    class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ring-4 ring-white"
                                    :class="{
                                        'bg-emerald-700 text-white':
                                            checkpoint.state === 'complete',
                                        'bg-amber-100 text-amber-800 ring-amber-50':
                                            checkpoint.state === 'current',
                                        'bg-slate-100 text-slate-500':
                                            checkpoint.state === 'upcoming',
                                    }"
                                >
                                    {{ checkpoint.state === 'complete' ? '✓' : index + 1 }}
                                </span>
                                <div class="pt-1">
                                    <p class="font-semibold text-slate-950">
                                        {{ checkpoint.label }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">
                                        {{ checkpoint.description }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <aside class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">
                            Action required now
                        </p>
                        <h3 class="mt-2 text-lg font-bold text-slate-950">{{ nextAction(job) }}</h3>

                        <div v-if="!job.designerName" class="mt-5 grid gap-3">
                            <p class="text-sm leading-6 text-slate-700">
                                Assign one Website Designer to own delivery from setup to launch.
                                Clinic Owner access continues separately and does not block this
                                assignment.
                            </p>
                            <select
                                v-model="selections[job.id]"
                                :disabled="busyJob === job.id"
                                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"
                                :aria-label="`Website Designer for ${job.clinicName}`"
                            >
                                <option value="">Select Website Designer</option>
                                <option
                                    v-for="designer in onboarding.designers"
                                    :key="designer.id"
                                    :value="designer.id"
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
                                {{ busyJob === job.id ? 'Assigning…' : 'Assign Website Designer' }}
                            </button>
                        </div>

                        <div v-else-if="!job.ownerAuthorityId" class="mt-5 grid gap-3">
                            <p class="text-sm leading-6 text-slate-700">
                                Confirm the Clinic Owner who will access this tenant workspace.
                            </p>
                            <input
                                v-model="ownerForm(job).name"
                                maxlength="120"
                                placeholder="Clinic Owner name"
                                class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                            />
                            <input
                                v-model="ownerForm(job).email"
                                type="email"
                                maxlength="254"
                                placeholder="Clinic Owner email"
                                class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                            />
                            <button
                                type="button"
                                :disabled="
                                    !ownerForm(job).name ||
                                    !ownerForm(job).email ||
                                    busyJob !== null
                                "
                                class="min-h-11 rounded-xl bg-emerald-700 px-5 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="establishOwner(job)"
                            >
                                {{
                                    busyJob === `owner:${job.id}` ? 'Sending…' : 'Send secure setup'
                                }}
                            </button>
                        </div>

                        <div v-else-if="!job.ownerVerified" class="mt-5">
                            <div
                                class="rounded-xl bg-white p-4 text-sm text-slate-700 ring-1 ring-amber-200"
                            >
                                <strong class="block text-slate-950">{{ job.ownerName }}</strong>
                                <span>{{ job.ownerEmail }}</span>
                                <span class="mt-2 block font-semibold text-amber-700">
                                    Secure account setup is pending
                                </span>
                            </div>
                            <button
                                type="button"
                                :disabled="busyJob !== null"
                                class="mt-3 min-h-11 w-full rounded-xl border border-emerald-700 bg-white px-5 font-semibold text-emerald-800 disabled:opacity-50"
                                @click="establishOwner(job)"
                            >
                                {{
                                    busyJob === `owner:${job.id}`
                                        ? 'Sending…'
                                        : 'Resend setup email'
                                }}
                            </button>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-xl bg-white p-4 text-sm leading-6 text-slate-700 ring-1 ring-slate-200"
                        >
                            <strong class="block text-slate-950">No admin action required</strong>
                            {{ nextAction(job) }}. Progress will update from the authoritative
                            workflow.
                        </div>
                    </aside>
                </div>

                <details class="border-t border-slate-200 px-5 py-4 sm:px-7">
                    <summary class="cursor-pointer font-semibold text-slate-700">
                        View tasks and advanced controls
                    </summary>
                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        <section>
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-bold text-slate-950">Detailed tasks</h3>
                                <span class="text-sm text-slate-600">
                                    {{ job.taskSummary.completed }}/{{ job.taskSummary.total }}
                                    complete
                                </span>
                            </div>
                            <div class="mt-3 grid gap-2">
                                <div
                                    v-for="item in job.tasks"
                                    :key="item.id"
                                    class="flex flex-col gap-2 rounded-xl bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ item.title }}
                                        </p>
                                        <p class="text-xs capitalize text-slate-600">
                                            {{ item.responsibility.replaceAll('_', ' ') }} ·
                                            {{ item.status.replaceAll('_', ' ') }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="
                                            !['completed', 'waived', 'cancelled'].includes(
                                                item.status,
                                            )
                                        "
                                        type="button"
                                        :disabled="busyJob !== null"
                                        class="min-h-9 rounded-lg border border-amber-400 px-3 text-sm font-semibold text-amber-800 disabled:opacity-50"
                                        @click="waiveTask(job, item)"
                                    >
                                        {{ busyJob === `task:${item.id}` ? 'Waiving…' : 'Waive' }}
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-950">Assignment and lifecycle</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Use these controls only for reassignment or exception handling.
                            </p>
                            <div v-if="job.assignmentId" class="mt-4 grid gap-2">
                                <p class="text-sm font-semibold text-slate-800">
                                    Assigned to {{ job.designerName }}
                                </p>
                                <select
                                    v-model="selections[job.id]"
                                    :disabled="busyJob === job.id"
                                    class="min-h-11 w-full rounded-xl border border-slate-300 px-3"
                                    :aria-label="`Website Designer for ${job.clinicName}`"
                                >
                                    <option value="">Select replacement designer</option>
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
                                    class="min-h-10 rounded-xl border border-slate-400 px-4 text-sm font-semibold text-slate-800 disabled:opacity-50"
                                    @click="assign(job)"
                                >
                                    {{ busyJob === job.id ? 'Reassigning…' : 'Reassign designer' }}
                                </button>
                            </div>
                            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
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
                        </section>
                    </div>
                </details>
            </article>
        </div>
    </DashboardShell>
</template>
