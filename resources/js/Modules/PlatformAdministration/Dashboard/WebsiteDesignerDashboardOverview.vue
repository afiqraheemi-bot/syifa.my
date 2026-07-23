<script setup>
import {
    createDashboardActivity,
    createDashboardNavigation,
    createDashboardQuickActions,
    createDashboardSummaries,
    DashboardQuickActions,
    DashboardRecentActivity,
    DashboardShell,
    DashboardSummaryCard,
    DashboardWelcome,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    welcomeTitle: { type: String, required: true },
    welcomeMessage: { type: String, required: true },
    summaries: { type: Array, required: true },
    quickActions: { type: Array, required: true },
    recentAssignments: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const summaries = createDashboardSummaries(props.summaries);
const quickActions = createDashboardQuickActions(props.quickActions);
const recentAssignments = createDashboardActivity(props.recentAssignments);
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
        <div class="space-y-8">
            <DashboardWelcome :title="welcomeTitle" :message="welcomeMessage" />

            <section
                aria-label="Website Designer assignment overview"
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <DashboardSummaryCard
                    v-for="summary in summaries"
                    :key="summary.key"
                    :label="summary.label"
                    :value="summary.value"
                    :detail="summary.detail"
                    :tone="summary.tone"
                />
            </section>

            <DashboardQuickActions :actions="quickActions" />
            <DashboardRecentActivity
                :activity="recentAssignments"
                title="Recent assignments"
                empty-title="No recent assignments"
                empty-description="New onboarding assignments will appear here."
            />
        </div>
    </DashboardShell>
</template>
