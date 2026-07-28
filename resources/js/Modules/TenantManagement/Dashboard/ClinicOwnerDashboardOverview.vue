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
    recentActivity: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const summaries = createDashboardSummaries(props.summaries);
const quickActions = createDashboardQuickActions(props.quickActions);
const recentActivity = createDashboardActivity(props.recentActivity);
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

            <section aria-label="Clinic overview" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <DashboardSummaryCard
                    v-for="summary in summaries"
                    :key="summary.key"
                    :label="summary.label"
                    :value="summary.value"
                    :detail="summary.detail"
                    :tone="summary.tone"
                    :url="summary.url"
                    :action-label="summary.actionLabel"
                />
            </section>

            <DashboardQuickActions :actions="quickActions" />
            <DashboardRecentActivity :activity="recentActivity" />
        </div>
    </DashboardShell>
</template>
