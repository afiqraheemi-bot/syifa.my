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
});

const navigation = createDashboardNavigation(props.navigation);
const selections = reactive({});
const busyJob = ref(null);
const error = ref('');
const success = ref('');

async function assign(job) {
    const designerId = selections[job.id];
    if (!designerId || busyJob.value) return;
    if (!window.confirm(`Assign the selected Website Designer to ${job.clinicName}?`)) return;

    busyJob.value = job.id;
    error.value = '';
    success.value = '';
    const response = await browserHttpRequest(
        props.assignUrlTemplate.replace('__JOB_ID__', job.id),
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
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

    success.value = 'Website Designer assigned successfully.';
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
                    </div>
                    <div v-if="!job.assignmentId" class="flex flex-col gap-2 sm:flex-row">
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
                            {{ busyJob === job.id ? 'Assigning…' : 'Assign' }}
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </DashboardShell>
</template>
