<script setup>
import {
    BookingFilters,
    BookingPagination,
    BookingSummaryGrid,
    BookingTable,
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
    bookingList: { type: Object, required: true },
    statusSummary: { type: Object, required: true },
    sourceSummary: { type: Object, required: true },
    csrfToken: { type: String, required: true },
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
        <BookingSummaryGrid :status-summary="statusSummary" :source-summary="sourceSummary" />
        <BookingFilters
            :search="bookingList.search"
            :filters="bookingList.filters"
            :per-page="bookingList.pagination.perPage"
        />
        <BookingTable :items="bookingList.items" :csrf-token="csrfToken" />
        <BookingPagination :pagination="bookingList.pagination" />
    </DashboardShell>
</template>
