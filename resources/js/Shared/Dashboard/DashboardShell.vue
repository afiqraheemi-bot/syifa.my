<script setup>
import { ref } from 'vue';
import DashboardBreadcrumb from './DashboardBreadcrumb.vue';
import DashboardPageHeader from './DashboardPageHeader.vue';
import DashboardLogoutAction from './DashboardLogoutAction.vue';
import DashboardSidebar from './DashboardSidebar.vue';
import DashboardTopNavigation from './DashboardTopNavigation.vue';

defineProps({
    navigation: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        required: true,
    },
    pageDescription: {
        type: String,
        default: null,
    },
    pageEyebrow: {
        type: String,
        default: null,
    },
    identityName: {
        type: String,
        default: null,
    },
    contextLabel: {
        type: String,
        default: null,
    },
    productName: {
        type: String,
        default: 'SYIFA.my',
    },
});

const collapsed = ref(false);
const mobileOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <a
            href="#dashboard-content"
            class="sr-only z-[60] rounded-md bg-white px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-600"
        >
            Skip to dashboard content
        </a>

        <DashboardSidebar
            :navigation="navigation"
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            :product-name="productName"
            @close-mobile="mobileOpen = false"
            @toggle-collapse="collapsed = !collapsed"
        />

        <div
            :class="[
                'min-h-screen transition-[padding] duration-200',
                collapsed ? 'lg:pl-20' : 'lg:pl-72',
            ]"
        >
            <DashboardTopNavigation
                :identity-name="identityName"
                :context-label="contextLabel"
                :navigation-open="mobileOpen"
                @open-navigation="mobileOpen = true"
            >
                <template #actions>
                    <slot name="top-actions" />
                    <DashboardLogoutAction />
                </template>
            </DashboardTopNavigation>

            <main
                id="dashboard-content"
                tabindex="-1"
                class="px-4 py-6 focus:outline-none sm:px-6 sm:py-8 lg:px-8"
            >
                <div class="mx-auto max-w-screen-2xl space-y-6 sm:space-y-8">
                    <DashboardBreadcrumb :items="breadcrumbs" />
                    <DashboardPageHeader
                        :title="pageTitle"
                        :description="pageDescription"
                        :eyebrow="pageEyebrow"
                    >
                        <template #actions><slot name="page-actions" /></template>
                    </DashboardPageHeader>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
