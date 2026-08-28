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
} from '../../../Shared/Dashboard/index.js';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    welcomeTitle: { type: String, required: true },
    welcomeMessage: { type: String, required: true },
    clinicName: { type: String, required: true },
    summaries: { type: Array, required: true },
    quickActions: { type: Array, required: true },
    recentActivity: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const summaryCopy = computed(() => ({
    clinic: {
        label: t('overview.summaryClinicLabel'),
        unavailable: t('overview.summaryClinicUnavailable'),
    },
    subscription: {
        label: t('overview.summarySubscriptionLabel'),
        unavailable: t('overview.summarySubscriptionUnavailable'),
    },
    bookings: { label: t('overview.summaryBookingsLabel') },
    website: { label: t('overview.summaryWebsiteLabel') },
}));
const actionCopy = computed(() => ({
    website: {
        label: t('overview.actionWebsiteLabel'),
        description: t('overview.actionWebsiteDescription'),
    },
    bookings: {
        label: t('overview.actionBookingsLabel'),
        description: t('overview.actionBookingsDescription'),
    },
    subscription: {
        label: t('overview.actionSubscriptionLabel'),
        description: t('overview.actionSubscriptionDescription'),
    },
}));
const summaries = computed(() =>
    createDashboardSummaries(props.summaries).map((summary) => ({
        ...summary,
        label: summaryCopy.value[summary.key]?.label ?? summary.label,
        value:
            summary.value === 'Not available'
                ? (summaryCopy.value[summary.key]?.unavailable ?? summary.value)
                : summary.value,
    })),
);
const quickActions = computed(() =>
    createDashboardQuickActions(props.quickActions).map((action) => ({
        ...action,
        label: actionCopy.value[action.key]?.label ?? action.label,
        description: actionCopy.value[action.key]?.description ?? action.description,
    })),
);
const recentActivity = createDashboardActivity(props.recentActivity);
const todayLabel = computed(() =>
    new Intl.DateTimeFormat(locale.value === 'ms' ? 'ms-MY' : 'en-MY', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date()),
);
const clinicIsReady = computed(
    () => summaries.value.find((item) => item.key === 'clinic')?.tone === 'positive',
);
const bookingSummary = computed(() => summaries.value.find((item) => item.key === 'bookings'));
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
        <div class="space-y-7">
            <section
                class="relative isolate overflow-hidden rounded-[1.75rem] bg-emerald-950 px-5 py-6 text-white shadow-xl shadow-emerald-950/10 sm:px-8 sm:py-8 lg:px-10"
                aria-labelledby="clinic-welcome-title"
            >
                <div
                    class="absolute -right-16 -top-20 -z-10 size-64 rounded-full bg-white/10 blur-2xl"
                    aria-hidden="true"
                />
                <div
                    class="absolute -bottom-24 right-24 -z-10 size-56 rounded-full bg-emerald-300/15 blur-3xl"
                    aria-hidden="true"
                />

                <div class="grid items-end gap-7 lg:grid-cols-[1fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold text-emerald-50 backdrop-blur"
                            >
                                <span
                                    :class="[
                                        'size-2 rounded-full',
                                        clinicIsReady ? 'bg-lime-300' : 'bg-amber-300',
                                    ]"
                                    aria-hidden="true"
                                />
                                {{
                                    clinicIsReady
                                        ? t('overview.statusReady')
                                        : t('overview.statusNeedsAttention')
                                }}
                            </span>
                            <span class="text-xs font-semibold text-emerald-100 capitalize">{{
                                todayLabel
                            }}</span>
                        </div>
                        <p
                            class="mt-5 text-xs font-black tracking-[0.16em] text-lime-300 uppercase"
                        >
                            {{ clinicName }}
                        </p>
                        <h1
                            id="clinic-welcome-title"
                            class="mt-2 max-w-3xl text-2xl font-black tracking-[-0.035em] sm:text-4xl"
                        >
                            {{ welcomeTitle }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50 sm:text-base">
                            {{ welcomeMessage }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:min-w-80">
                        <a
                            href="/dashboard/bookings"
                            class="group rounded-2xl bg-lime-300 p-4 text-emerald-950 shadow-lg transition hover:-translate-y-0.5 hover:bg-lime-200 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            <span class="block text-xs font-bold text-emerald-900/70">{{
                                t('overview.bookingsCardLabel')
                            }}</span>
                            <span class="mt-1 block text-2xl font-black">{{
                                bookingSummary?.value ?? '0'
                            }}</span>
                            <span class="mt-2 block text-xs font-bold text-emerald-950/70">{{
                                t('overview.bookingsCardCta')
                            }}</span>
                        </a>
                        <a
                            href="/dashboard/blog"
                            class="group rounded-2xl border border-white/20 bg-white/10 p-4 text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            <span class="block text-xs font-bold text-emerald-100">{{
                                t('overview.blogCardLabel')
                            }}</span>
                            <span class="mt-1 block text-lg font-black">{{
                                t('overview.blogCardTitle')
                            }}</span>
                            <span class="mt-3 block text-xs font-semibold text-emerald-100">{{
                                t('overview.blogCardCta')
                            }}</span>
                        </a>
                    </div>
                </div>
            </section>

            <section
                :aria-label="t('overview.summaryAriaLabel')"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <DashboardSummaryCard
                    v-for="summary in summaries"
                    :key="summary.key"
                    :label="summary.label"
                    :summary-key="summary.key"
                    :positive-label="t('overview.statusActive')"
                    :neutral-label="t('overview.statusReview')"
                    :value="summary.value"
                    :detail="summary.detail"
                    :tone="summary.tone"
                    :url="summary.url"
                    :action-label="summary.actionLabel"
                />
            </section>

            <div class="grid items-start gap-7 xl:grid-cols-[1.15fr_0.85fr]">
                <DashboardQuickActions
                    :actions="quickActions"
                    :title="t('overview.quickActionsTitle')"
                    :eyebrow="t('overview.quickActionsEyebrow')"
                />
                <DashboardRecentActivity
                    :activity="recentActivity"
                    :title="t('overview.recentActivityTitle')"
                    :eyebrow="t('overview.recentActivityEyebrow')"
                    :empty-title="t('overview.recentActivityEmptyTitle')"
                    :empty-description="t('overview.recentActivityEmptyDescription')"
                />
            </div>
        </div>
    </DashboardShell>
</template>
