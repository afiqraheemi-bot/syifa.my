<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
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
});

const navigation = createDashboardNavigation(props.navigation);
const quickActions = createDashboardQuickActions(props.quickActions);
const decision = ref('');
const reason = ref('');
const busy = ref(false);
const approvalError = ref('');
const approvalSuccess = ref('');

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
    router.reload({ only: ['websiteApproval', 'publishStatus'] });
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
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <WebsiteOverviewCard :status="websiteStatus" />
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

        <WebsiteQuickActions :actions="quickActions" />
    </DashboardShell>
</template>
