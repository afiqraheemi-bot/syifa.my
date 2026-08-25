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
const summaryCopy = {
    clinic: { label: 'Profil klinik', unavailable: 'Belum tersedia' },
    subscription: { label: 'Langganan', unavailable: 'Belum tersedia' },
    bookings: { label: 'Jumlah tempahan' },
    website: { label: 'Website klinik' },
};
const actionCopy = {
    website: {
        label: 'Urus website',
        description: 'Kemas kini maklumat, servis dan kandungan klinik.',
    },
    bookings: {
        label: 'Semak tempahan',
        description: 'Lihat dan urus permintaan tempahan pesakit.',
    },
    subscription: {
        label: 'Lihat langganan',
        description: 'Semak pelan semasa dan status pembaharuan.',
    },
};
const summaries = createDashboardSummaries(props.summaries).map((summary) => ({
    ...summary,
    label: summaryCopy[summary.key]?.label ?? summary.label,
    value:
        summary.value === 'Not available'
            ? (summaryCopy[summary.key]?.unavailable ?? summary.value)
            : summary.value,
}));
const quickActions = createDashboardQuickActions(props.quickActions).map((action) => ({
    ...action,
    label: actionCopy[action.key]?.label ?? action.label,
    description: actionCopy[action.key]?.description ?? action.description,
}));
const recentActivity = createDashboardActivity(props.recentActivity);
const todayLabel = new Intl.DateTimeFormat('ms-MY', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());
const clinicIsReady = computed(
    () => summaries.find((item) => item.key === 'clinic')?.tone === 'positive',
);
const bookingSummary = computed(() => summaries.find((item) => item.key === 'bookings'));
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
                                        ? 'Klinik sedia beroperasi'
                                        : 'Setup perlu perhatian'
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
                            <span class="block text-xs font-bold text-emerald-900/70"
                                >Tempahan pesakit</span
                            >
                            <span class="mt-1 block text-2xl font-black">{{
                                bookingSummary?.value ?? '0'
                            }}</span>
                            <span class="mt-2 block text-xs font-bold text-emerald-950/70"
                                >Semak sekarang →</span
                            >
                        </a>
                        <a
                            href="/dashboard/blog"
                            class="group rounded-2xl border border-white/20 bg-white/10 p-4 text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            <span class="block text-xs font-bold text-emerald-100"
                                >Blog klinik</span
                            >
                            <span class="mt-1 block text-lg font-black">Kongsi info</span>
                            <span class="mt-3 block text-xs font-semibold text-emerald-100"
                                >Urus artikel →</span
                            >
                        </a>
                    </div>
                </div>
            </section>

            <section aria-label="Ringkasan klinik" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <DashboardSummaryCard
                    v-for="summary in summaries"
                    :key="summary.key"
                    :label="summary.label"
                    :summary-key="summary.key"
                    positive-label="Aktif"
                    neutral-label="Semak"
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
                    title="Tindakan pantas"
                    eyebrow="Akses segera"
                />
                <DashboardRecentActivity
                    :activity="recentActivity"
                    title="Aktiviti terkini"
                    eyebrow="Rekod ruang kerja"
                    empty-title="Belum ada aktiviti terkini"
                    empty-description="Perubahan dan aktiviti penting klinik anda akan dipaparkan di sini."
                />
            </div>
        </div>
    </DashboardShell>
</template>
