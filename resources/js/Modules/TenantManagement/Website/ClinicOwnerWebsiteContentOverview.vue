<script setup>
import {
    ContentHealthSummary,
    ContentSectionSummary,
    createDashboardNavigation,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    contentHealth: { type: Object, required: true },
    contentSections: { type: Array, required: true },
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
        <ContentHealthSummary :health="contentHealth" />
        <section aria-labelledby="content-sections-heading">
            <h2 id="content-sections-heading" class="mb-4 text-xl font-bold text-slate-950">
                Content sections
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <ContentSectionSummary
                    v-for="section in contentSections"
                    :key="section.key"
                    :section="section"
                />
            </div>
        </section>
    </DashboardShell>
</template>
