<script setup>
import {
    createDashboardNavigation,
    DashboardEmptyState,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: {
        type: Array,
        required: true,
    },
    breadcrumbs: {
        type: Array,
        required: true,
    },
    pageTitle: {
        type: String,
        required: true,
    },
    pageDescription: {
        type: String,
        required: true,
    },
    identityName: {
        type: String,
        default: null,
    },
    contextLabel: {
        type: String,
        required: true,
    },
});

const navigation = createDashboardNavigation(props.navigation);
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
        <DashboardEmptyState
            title="Your workspace is ready"
            description="Dashboard capabilities will appear here as they become available."
        />
    </DashboardShell>
</template>
