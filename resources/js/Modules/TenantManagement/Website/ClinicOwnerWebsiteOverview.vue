<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import { createOnboardingCheckpoints } from '../../../Shared/Onboarding/checkpoints.js';
import {
    createDashboardNavigation,
    createDashboardQuickActions,
    DashboardShell,
    WebsiteHealthCard,
    WebsiteInformationCard,
    WebsiteOverviewCard,
    WebsiteQuickActions,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    websiteStatus: { type: Object, required: true },
    publishStatus: { type: Object, required: true },
    domainStatus: { type: Object, required: true },
    themeInformation: { type: Object, required: true },
    seoStatus: { type: Object, required: true },
    quickActions: { type: Array, required: true },
    websiteApproval: { type: Object, default: null },
    websiteApprovalDecisionUrl: { type: String, required: true },
    websiteApprovalPreviewUrl: { type: String, required: true },
    onboardingTasks: { type: Object, default: null },
    onboardingTaskUrlTemplate: { type: String, default: null },
    launchReadiness: { type: Object, default: null },
});

const navigation = createDashboardNavigation(props.navigation);
const quickActions = createDashboardQuickActions(props.quickActions).map((action) =>
    action.key === 'edit'
        ? {
              ...action,
              label: 'Urus kandungan',
              description: 'Kemas kini maklumat dan kandungan website klinik.',
          }
        : action,
);
const primaryWebsiteAction = computed(() => quickActions.find((action) => action.key === 'edit'));
const decision = ref('');
const reason = ref('');
const busy = ref(false);
const approvalError = ref('');
const approvalSuccess = ref('');
const taskBusy = ref(null);
const taskError = ref('');
const onboardingCheckpoints = computed(() =>
    createOnboardingCheckpoints(props.onboardingTasks?.tasks ?? []),
);
const unmetLaunchConditions = computed(
    () => props.launchReadiness?.conditions?.filter((condition) => !condition.satisfied) ?? [],
);
const approvalIsPending = computed(() =>
    ['requested', 'resubmitted'].includes(props.websiteApproval?.approvalStatus),
);
const awaitingUpdatedSubmission = computed(
    () =>
        unmetLaunchConditions.value.some((condition) => condition.key === 'approval') &&
        props.websiteApproval?.approvalStatus === 'approved',
);

async function completeOwnerTask(task) {
    if (!props.onboardingTasks || !props.onboardingTaskUrlTemplate || taskBusy.value) return;
    const evidence = window.prompt('Describe the information or approval supplied:')?.trim();
    if (!evidence) return;
    if (!window.confirm(`Complete "${task.title}"?`)) return;

    taskBusy.value = task.id;
    taskError.value = '';
    const response = await browserHttpRequest(
        props.onboardingTaskUrlTemplate.replace('__TASK_ID__', task.id),
        {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                operation: 'complete',
                expected_version: props.onboardingTasks.jobVersion,
                evidence_reference: evidence,
            }),
        },
    );
    taskBusy.value = null;
    if (!response.ok) {
        taskError.value = response.body?.message ?? 'The onboarding task could not be updated.';
        return;
    }
    router.reload({
        only: ['onboardingTasks', 'launchReadiness', 'websiteApproval', 'publishStatus'],
    });
}

async function decideWebsiteApproval(selectedDecision) {
    if (!props.websiteApproval || busy.value) return;
    if (selectedDecision === 'request_correction' && reason.value.trim().length < 5) {
        approvalError.value = 'Describe the required correction before sending it.';
        return;
    }
    const label =
        selectedDecision === 'approve' ? 'approve this Website for launch' : 'request corrections';
    if (!window.confirm(`Are you sure you want to ${label}?`)) return;

    busy.value = true;
    decision.value = selectedDecision;
    approvalError.value = '';
    approvalSuccess.value = '';
    const response = await browserHttpRequest(props.websiteApprovalDecisionUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            job_id: props.websiteApproval.jobId,
            expected_version: props.websiteApproval.jobVersion,
            decision: selectedDecision,
            reason: selectedDecision === 'request_correction' ? reason.value.trim() : null,
        }),
    });
    busy.value = false;
    decision.value = '';

    if (!response.ok) {
        approvalError.value =
            response.body?.message ??
            response.body?.errors?.reason?.[0] ??
            'The Website approval decision could not be recorded.';
        return;
    }

    approvalSuccess.value = response.body?.message ?? 'Website approval updated.';
    router.reload({
        only: ['onboardingTasks', 'launchReadiness', 'websiteApproval', 'publishStatus'],
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
        <div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div>
                <WebsiteOverviewCard :status="websiteStatus" :action="primaryWebsiteAction" />
            </div>
            <WebsiteHealthCard :status="publishStatus" />
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <WebsiteInformationCard :status="domainStatus" />
            <WebsiteInformationCard :status="themeInformation" />
            <WebsiteInformationCard :status="seoStatus" />
        </div>

        <section
            v-if="
                websiteApproval &&
                ['requested', 'resubmitted'].includes(websiteApproval.approvalStatus)
            "
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5"
            aria-labelledby="website-approval-title"
        >
            <p class="text-xs font-bold uppercase tracking-wider text-amber-800">
                Your approval is required
            </p>
            <h2 id="website-approval-title" class="mt-1 text-xl font-bold text-slate-950">
                Review the prepared Website
            </h2>
            <p class="mt-2 max-w-3xl text-sm text-slate-700">
                Approve the prepared Website for its initial launch, or describe the corrections the
                assigned Website Designer must make.
            </p>
            <p
                v-if="approvalError"
                role="alert"
                class="mt-4 rounded-xl bg-red-100 p-3 text-sm text-red-800"
            >
                {{ approvalError }}
            </p>
            <p
                v-if="approvalSuccess"
                role="status"
                class="mt-4 rounded-xl bg-emerald-100 p-3 text-sm text-emerald-800"
            >
                {{ approvalSuccess }}
            </p>
            <label class="mt-4 block text-sm font-semibold text-slate-800">
                Correction details
                <textarea
                    v-model="reason"
                    rows="3"
                    maxlength="2000"
                    :disabled="busy"
                    placeholder="Required only when requesting a correction"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal"
                />
            </label>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <a
                    :href="websiteApprovalPreviewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-700 bg-white px-5 font-semibold text-slate-900"
                >
                    Preview submitted Website
                </a>
                <button
                    type="button"
                    :disabled="busy"
                    class="min-h-11 rounded-xl bg-emerald-700 px-5 font-semibold text-white disabled:opacity-50"
                    @click="decideWebsiteApproval('approve')"
                >
                    {{ busy && decision === 'approve' ? 'Approving…' : 'Approve for launch' }}
                </button>
                <button
                    type="button"
                    :disabled="busy"
                    class="min-h-11 rounded-xl border border-amber-700 px-5 font-semibold text-amber-900 disabled:opacity-50"
                    @click="decideWebsiteApproval('request_correction')"
                >
                    {{
                        busy && decision === 'request_correction'
                            ? 'Sending…'
                            : 'Request correction'
                    }}
                </button>
            </div>
        </section>

        <section
            v-if="onboardingTasks"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            aria-labelledby="clinic-onboarding-tasks"
        >
            <h2 id="clinic-onboarding-tasks" class="text-xl font-bold text-slate-950">
                Onboarding checkpoints
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Follow the same delivery sequence used by Super Admin and your Website Designer.
            </p>
            <p v-if="taskError" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-red-800">
                {{ taskError }}
            </p>
            <div class="mt-4 grid gap-3">
                <article
                    v-for="(task, index) in onboardingCheckpoints"
                    :key="task.key"
                    class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                    :class="
                        task.state === 'complete'
                            ? 'border-emerald-200 bg-emerald-50'
                            : task.state === 'current'
                              ? 'border-amber-300 bg-amber-50'
                              : 'border-slate-200 bg-slate-50'
                    "
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                            :class="
                                task.state === 'complete'
                                    ? 'bg-emerald-700 text-white'
                                    : task.state === 'current'
                                      ? 'bg-amber-200 text-amber-900'
                                      : 'bg-slate-200 text-slate-600'
                            "
                            aria-hidden="true"
                        >
                            {{ task.state === 'complete' ? '✓' : index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-950">{{ task.title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ task.responsibilityLabel }} · {{ task.statusLabel }}
                            </p>
                        </div>
                    </div>
                    <button
                        v-if="
                            task.responsibility === 'clinic_owner' &&
                            task.state === 'current' &&
                            !['completed', 'waived', 'cancelled'].includes(task.status)
                        "
                        type="button"
                        :disabled="taskBusy !== null"
                        class="min-h-10 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                        @click="completeOwnerTask(task)"
                    >
                        {{ taskBusy === task.id ? 'Updating…' : 'Mark complete' }}
                    </button>
                </article>
            </div>
        </section>

        <section
            v-if="launchReadiness"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            aria-labelledby="owner-launch-readiness"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="owner-launch-readiness" class="text-xl font-bold text-slate-950">
                        Launch readiness
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Live assessment of the requirements for your clinic Website launch.
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-sm font-bold"
                    :class="
                        launchReadiness.ready
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-900'
                    "
                >
                    {{ launchReadiness.ready ? 'Ready' : 'Blocked' }}
                </span>
            </div>
            <div
                v-if="approvalIsPending"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-slate-800"
            >
                <strong>Action required:</strong> Review and approve the latest Website version in
                the approval panel above.
            </div>
            <div
                v-else-if="awaitingUpdatedSubmission"
                class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-slate-800"
            >
                <strong>No action is required from you yet.</strong> The Website changed after your
                previous approval. The Website Designer must submit the updated version first; a new
                approval panel will then appear on this page.
            </div>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                <li
                    v-for="condition in launchReadiness.conditions"
                    :key="condition.key"
                    class="rounded-lg bg-slate-50 p-3 text-sm text-slate-800"
                >
                    <strong>{{ condition.satisfied ? 'Complete' : 'Required' }}:</strong>
                    {{ condition.label }}
                </li>
            </ul>
        </section>

        <WebsiteQuickActions :actions="quickActions" />
    </DashboardShell>
</template>
