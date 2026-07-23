<script setup>
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
});

const navigation = createDashboardNavigation(props.navigation);
const quickActions = createDashboardQuickActions(props.quickActions);
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

        <WebsiteQuickActions :actions="quickActions" />
    </DashboardShell>
</template>
