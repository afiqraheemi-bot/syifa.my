<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    manualBooking: { type: Object, required: true },
    bookingSchedule: { type: Object, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const showManualBooking = ref(false);
const showBookingSettings = ref(false);
const showBusinessHoursSettings = ref(false);
const successMessage = ref('');
const scheduleSaved = ref(false);
const businessHoursSaved = ref(false);
const overrideSaved = ref(false);
const form = useForm({
    source: 'phone',
    patient_name: '',
    phone: '',
    email: '',
    notes: '',
    appointment_date: '',
    appointment_time: '',
    service_id: '',
});
const weekdayOptions = [
    [1, 'Monday'],
    [2, 'Tuesday'],
    [3, 'Wednesday'],
    [4, 'Thursday'],
    [5, 'Friday'],
    [6, 'Saturday'],
    [7, 'Sunday'],
];
const configuredIntervals = props.bookingSchedule.operating_intervals.reduce((byDay, interval) => {
    const sessions = byDay.get(interval.day) ?? [];
    sessions.push({ opens_at: interval.opens_at, closes_at: interval.closes_at });
    byDay.set(interval.day, sessions);
    return byDay;
}, new Map());
const configuredBusinessHours = props.bookingSchedule.business_hours_intervals.reduce(
    (byDay, interval) => {
        const periods = byDay.get(interval.day) ?? [];
        periods.push({ opens_at: interval.opens_at, closes_at: interval.closes_at });
        byDay.set(interval.day, periods);
        return byDay;
    },
    new Map(),
);
const dayNames = new Map(weekdayOptions);
const formatScheduleSummary = (intervals) => {
    if (!intervals?.length) {
        return 'No days configured';
    }

    const days = [...new Set(intervals.map((interval) => dayNames.get(interval.day)))];
    if (days.length <= 3) {
        return days.join(', ');
    }

    return `${days.slice(0, 2).join(', ')} + ${days.length - 2} more days`;
};
const businessHoursSummary = computed(() =>
    formatScheduleSummary(props.bookingSchedule.business_hours_intervals),
);
const bookingHoursSummary = computed(() =>
    formatScheduleSummary(
        scheduleForm.days
            .filter((day) => day.enabled)
            .flatMap((day) => day.sessions.map(() => ({ day: day.day }))),
    ),
);
const scheduleForm = useForm({
    version: props.bookingSchedule.version,
    timezone: props.bookingSchedule.timezone,
    appointment_duration_minutes: props.bookingSchedule.appointment_duration_minutes ?? 30,
    booking_capacity_per_slot: props.bookingSchedule.booking_capacity_per_slot ?? 1,
    days: weekdayOptions.map(([day, label]) => ({
        day,
        label,
        enabled: configuredIntervals.has(day),
        sessions: configuredIntervals.get(day) ?? [{ opens_at: '09:00', closes_at: '12:00' }],
    })),
});
const businessHoursForm = useForm({
    version: props.bookingSchedule.version,
    timezone: props.bookingSchedule.timezone,
    days: weekdayOptions.map(([day, label]) => ({
        day,
        label,
        enabled: configuredBusinessHours.has(day),
        periods: configuredBusinessHours.get(day) ?? [{ opens_at: '09:00', closes_at: '18:00' }],
    })),
});
const dateOverrideForm = useForm({
    local_date: '',
    closed: true,
    version: 0,
    intervals: [{ opens_at: '09:00', closes_at: '12:00' }],
});
const today = new Date();
const minimumOverrideDate = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0'),
].join('-');

const addBusinessPeriod = (day) => {
    if (day.periods.length < 5) {
        day.periods.push({ opens_at: '09:00', closes_at: '18:00' });
    }
};

const removeBusinessPeriod = (day, index) => {
    if (day.periods.length === 1) {
        day.enabled = false;
        return;
    }

    day.periods.splice(index, 1);
};

const editDateOverride = (override) => {
    dateOverrideForm.local_date = override.local_date;
    dateOverrideForm.closed = override.closed;
    dateOverrideForm.version = override.version;
    dateOverrideForm.intervals = override.intervals.length
        ? override.intervals.map((interval) => ({ ...interval }))
        : [{ opens_at: '09:00', closes_at: '12:00' }];
};

const resetDateOverride = (clearStatus = true) => {
    dateOverrideForm.reset();
    dateOverrideForm.clearErrors();
    if (clearStatus) overrideSaved.value = false;
};

const addOverrideSession = () => {
    if (dateOverrideForm.intervals.length < 5) {
        dateOverrideForm.intervals.push({ opens_at: '15:00', closes_at: '17:00' });
    }
};

const removeOverrideSession = (index) => {
    if (dateOverrideForm.intervals.length > 1) {
        dateOverrideForm.intervals.splice(index, 1);
    }
};

const addSession = (day) => {
    if (day.sessions.length >= 5) {
        return;
    }

    day.sessions.push({ opens_at: '15:00', closes_at: '17:00' });
};

const removeSession = (day, index) => {
    if (day.sessions.length === 1) {
        day.enabled = false;
        return;
    }

    day.sessions.splice(index, 1);
};

const submitManualBooking = () => {
    if (form.processing) {
        return;
    }

    successMessage.value = '';
    form.post(props.manualBooking.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Booking created successfully.';
            form.reset();
            form.source = 'phone';
            showManualBooking.value = false;
        },
    });
};

const saveBookingSchedule = () => {
    if (scheduleForm.processing) {
        return;
    }

    scheduleSaved.value = false;
    scheduleForm
        .transform((data) => ({
            ...data,
            operating_intervals: data.days
                .filter((day) => day.enabled)
                .flatMap((day) =>
                    day.sessions.map(({ opens_at, closes_at }) => ({
                        day: day.day,
                        opens_at,
                        closes_at,
                    })),
                ),
        }))
        .patch(props.bookingSchedule.updateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const current = page.props.bookingSchedule;
                if (current) {
                    scheduleForm.version = current.version;
                    businessHoursForm.version = current.version;
                }
                scheduleSaved.value = true;
            },
        });
};

const saveBusinessHours = () => {
    if (businessHoursForm.processing) {
        return;
    }

    businessHoursSaved.value = false;
    businessHoursForm
        .transform((data) => ({
            ...data,
            operating_intervals: data.days
                .filter((day) => day.enabled)
                .flatMap((day) =>
                    day.periods.map(({ opens_at, closes_at }) => ({
                        day: day.day,
                        opens_at,
                        closes_at,
                    })),
                ),
        }))
        .patch(props.bookingSchedule.businessHoursUpdateUrl, {
            preserveScroll: true,
            onSuccess: (page) => {
                const current = page.props.bookingSchedule;
                if (current) {
                    businessHoursForm.version = current.version;
                    scheduleForm.version = current.version;
                }
                businessHoursSaved.value = true;
            },
        });
};

const saveDateOverride = () => {
    if (dateOverrideForm.processing) return;
    overrideSaved.value = false;
    dateOverrideForm.post(props.bookingSchedule.dateOverrideStoreUrl, {
        preserveScroll: true,
        onSuccess: () => {
            resetDateOverride(false);
            overrideSaved.value = true;
        },
    });
};

const deleteDateOverride = (override) => {
    if (
        dateOverrideForm.processing ||
        !window.confirm(`Remove the exception for ${override.local_date}?`)
    )
        return;
    dateOverrideForm.version = override.version;
    dateOverrideForm.delete(
        props.bookingSchedule.dateOverrideDeleteUrlTemplate.replace(
            '__DATE__',
            override.local_date,
        ),
        {
            preserveScroll: true,
            onSuccess: () => resetDateOverride(),
        },
    );
};
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
        <div class="space-y-6">
            <section
                class="grid gap-4 lg:grid-cols-2"
                aria-labelledby="hours-and-availability-title"
            >
                <h2 id="hours-and-availability-title" class="sr-only">
                    Business Hours and Booking Hours
                </h2>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div
                        class="flex flex-col items-stretch gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">
                                Clinic operations
                            </p>
                            <h3 class="mt-2 text-lg font-bold text-slate-950">Business Hours</h3>
                            <p class="mt-2 font-semibold text-slate-800">
                                {{ businessHoursSummary }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 lg:w-auto"
                            :aria-expanded="showBusinessHoursSettings"
                            aria-controls="business-hours-settings"
                            @click="showBusinessHoursSettings = !showBusinessHoursSettings"
                        >
                            {{
                                showBusinessHoursSettings ? 'Close settings' : 'Set Business Hours'
                            }}
                        </button>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        When the clinic normally operates. It does not limit the appointment
                        sessions below.
                    </p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
                    <div
                        class="flex flex-col items-stretch gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700"
                            >
                                Patient appointments
                            </p>
                            <h3 class="mt-2 text-lg font-bold text-slate-950">Booking Hours</h3>
                            <p class="mt-2 font-semibold text-slate-800">
                                {{ bookingHoursSummary }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 lg:w-auto"
                            :aria-expanded="showBookingSettings"
                            aria-controls="booking-settings"
                            @click="showBookingSettings = !showBookingSettings"
                        >
                            {{ showBookingSettings ? 'Close settings' : 'Set Booking Hours' }}
                        </button>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-700">
                        Set one or more sessions for each day. These may differ from Business Hours.
                        A full slot closes automatically according to its capacity.
                    </p>
                </article>
            </section>

            <section
                v-if="showBusinessHoursSettings"
                id="business-hours-settings"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                aria-labelledby="business-hours-settings-title"
            >
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">
                        Clinic operations
                    </p>
                    <h2
                        id="business-hours-settings-title"
                        class="mt-2 text-xl font-bold text-slate-950"
                    >
                        Set Business Hours
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Set when the clinic is normally open. These hours appear on the clinic
                        Website and remain separate from online Booking Hours.
                    </p>
                </div>

                <form class="mt-6 space-y-5" novalidate @submit.prevent="saveBusinessHours">
                    <div class="space-y-3">
                        <div
                            v-for="day in businessHoursForm.days"
                            :key="day.day"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <label
                                class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900"
                            >
                                <input
                                    v-model="day.enabled"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                />
                                {{ day.label }}
                            </label>
                            <div v-if="day.enabled" class="mt-3 space-y-3">
                                <div
                                    v-for="(period, periodIndex) in day.periods"
                                    :key="periodIndex"
                                    class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[6rem_1fr_1fr_auto] sm:items-end"
                                >
                                    <p class="pb-3 text-sm font-bold text-slate-700">
                                        Period {{ periodIndex + 1 }}
                                    </p>
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700">
                                        Opens
                                        <input
                                            v-model="period.opens_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                        />
                                    </label>
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700">
                                        Closes
                                        <input
                                            v-model="period.closes_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="min-h-11 rounded-xl border border-red-200 px-3 text-sm font-bold text-red-700 hover:bg-red-50"
                                        @click="removeBusinessPeriod(day, periodIndex)"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    :disabled="day.periods.length >= 5"
                                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-800 hover:bg-slate-100 disabled:opacity-50"
                                    @click="addBusinessPeriod(day)"
                                >
                                    + Add another opening period
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="businessHoursForm.hasErrors"
                        role="alert"
                        class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                    >
                        <p class="font-bold">Please review your Business Hours.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="(message, field) in businessHoursForm.errors" :key="field">
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                    <p
                        v-if="businessHoursSaved"
                        role="status"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"
                    >
                        Business Hours saved successfully.
                    </p>
                    <button
                        type="submit"
                        :disabled="businessHoursForm.processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            businessHoursForm.processing
                                ? 'Saving Business Hours…'
                                : 'Save Business Hours'
                        }}
                    </button>
                </form>
            </section>

            <section
                v-if="showBookingSettings"
                id="booking-settings"
                class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm sm:p-6"
                aria-labelledby="booking-settings-title"
            >
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">
                        Online Booking
                    </p>
                    <h2 id="booking-settings-title" class="mt-2 text-xl font-bold text-slate-950">
                        Appointment availability
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Control when patients can book online, how long each appointment lasts, and
                        how many patients may take the same time slot.
                    </p>
                    <p
                        class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-950"
                    >
                        <strong>Separate from Business Hours:</strong> your clinic may choose
                        morning, afternoon, or evening appointment sessions independently of its
                        normal operating hours.
                    </p>
                </div>

                <form class="mt-6 space-y-6" novalidate @submit.prevent="saveBookingSchedule">
                    <div
                        class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950"
                    >
                        <p class="font-bold">How online availability works</p>
                        <ol class="mt-2 grid list-decimal gap-2 pl-5 leading-6 sm:grid-cols-2">
                            <li>The weekly schedule is divided into appointment slots.</li>
                            <li>Appointment duration determines each slot's start and end time.</li>
                            <li>Capacity controls how many bookings a slot accepts.</li>
                            <li>
                                A full slot is hidden automatically and cannot be double-booked.
                            </li>
                        </ol>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                            Step 1
                        </p>
                        <h3 class="mt-1 font-bold text-slate-950">Set appointment rules</h3>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm font-semibold text-slate-800">
                            Duration of each appointment
                            <select
                                v-model.number="scheduleForm.appointment_duration_minutes"
                                class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            >
                                <option :value="15">15 minutes</option>
                                <option :value="20">20 minutes</option>
                                <option :value="30">30 minutes</option>
                                <option :value="45">45 minutes</option>
                                <option :value="60">60 minutes</option>
                            </select>
                        </label>
                        <label class="grid gap-2 text-sm font-semibold text-slate-800">
                            Maximum bookings per time slot
                            <select
                                v-model.number="scheduleForm.booking_capacity_per_slot"
                                class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            >
                                <option v-for="capacity in 10" :key="capacity" :value="capacity">
                                    {{ capacity }}
                                </option>
                            </select>
                            <span class="font-normal leading-5 text-slate-500">
                                <template v-if="scheduleForm.booking_capacity_per_slot === 1">
                                    Once booked, that time slot is no longer available.
                                </template>
                                <template v-else>
                                    The slot remains available until
                                    {{ scheduleForm.booking_capacity_per_slot }} bookings are
                                    received.
                                </template>
                            </span>
                            <span class="font-normal leading-5 text-amber-800">
                                Capacity changes apply to new, unreserved slots. A slot that already
                                has a booking keeps its original capacity so confirmed patients are
                                never displaced.
                            </span>
                        </label>
                    </div>

                    <div class="border-t border-slate-200"></div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                            Step 2
                        </p>
                        <h3 class="mt-1 font-bold text-slate-950">Choose weekly booking times</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Enable a day, then add every appointment session offered on that day.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="day in scheduleForm.days"
                            :key="day.day"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <label
                                class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900"
                            >
                                <input
                                    v-model="day.enabled"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                />
                                {{ day.label }}
                            </label>
                            <div v-if="day.enabled" class="mt-3 space-y-3">
                                <div
                                    v-for="(session, sessionIndex) in day.sessions"
                                    :key="sessionIndex"
                                    class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-[5rem_1fr_1fr_auto] sm:items-end"
                                >
                                    <p class="pb-3 text-sm font-bold text-emerald-800">
                                        Session {{ sessionIndex + 1 }}
                                    </p>
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700">
                                        Starts
                                        <input
                                            v-model="session.opens_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                        />
                                    </label>
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700">
                                        Ends
                                        <input
                                            v-model="session.closes_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="min-h-11 rounded-xl border border-red-200 px-3 text-sm font-bold text-red-700 hover:bg-red-50"
                                        @click="removeSession(day, sessionIndex)"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    :disabled="day.sessions.length >= 5"
                                    class="min-h-11 rounded-xl border border-emerald-300 bg-white px-4 text-sm font-bold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50"
                                    @click="addSession(day)"
                                >
                                    + Add another session
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="scheduleForm.hasErrors"
                        role="alert"
                        class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                    >
                        <p class="font-bold">Please review your Booking settings.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="(message, field) in scheduleForm.errors" :key="field">
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                    <p
                        v-if="scheduleSaved"
                        role="status"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"
                    >
                        Booking availability saved successfully.
                    </p>
                    <button
                        type="submit"
                        :disabled="scheduleForm.processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            scheduleForm.processing
                                ? 'Saving availability…'
                                : 'Save booking availability'
                        }}
                    </button>
                </form>
            </section>

            <section
                class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6"
                aria-labelledby="date-overrides-title"
            >
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                            Calendar exceptions
                        </p>
                        <h2 id="date-overrides-title" class="mt-2 text-xl font-bold text-slate-950">
                            Closures and special Booking Hours
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Close one specific date or replace its normal weekly Booking Hours. The
                            public calendar uses this exception immediately for that date.
                        </p>

                        <form class="mt-5 space-y-4" novalidate @submit.prevent="saveDateOverride">
                            <label class="grid gap-2 text-sm font-semibold text-slate-800">
                                Date
                                <input
                                    v-model="dateOverrideForm.local_date"
                                    type="date"
                                    :min="minimumOverrideDate"
                                    class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                />
                            </label>
                            <label
                                class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900"
                            >
                                <input
                                    v-model="dateOverrideForm.closed"
                                    type="checkbox"
                                    class="size-4 accent-amber-700"
                                />
                                Clinic does not accept online bookings on this date
                            </label>
                            <div v-if="!dateOverrideForm.closed" class="space-y-3">
                                <div
                                    v-for="(interval, index) in dateOverrideForm.intervals"
                                    :key="index"
                                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                                >
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700"
                                        >Starts<input
                                            v-model="interval.opens_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                    /></label>
                                    <label class="grid gap-2 text-sm font-semibold text-slate-700"
                                        >Ends<input
                                            v-model="interval.closes_at"
                                            type="time"
                                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                                    /></label>
                                    <button
                                        type="button"
                                        :disabled="dateOverrideForm.intervals.length === 1"
                                        class="min-h-11 rounded-xl border border-red-200 px-3 text-sm font-bold text-red-700 disabled:opacity-40"
                                        @click="removeOverrideSession(index)"
                                    >
                                        Remove
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    :disabled="dateOverrideForm.intervals.length >= 5"
                                    class="min-h-11 rounded-xl border border-amber-300 px-4 text-sm font-bold text-amber-900 disabled:opacity-40"
                                    @click="addOverrideSession"
                                >
                                    + Add session
                                </button>
                            </div>
                            <div
                                v-if="dateOverrideForm.hasErrors"
                                role="alert"
                                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                            >
                                <ul class="list-disc space-y-1 pl-5">
                                    <li
                                        v-for="(message, field) in dateOverrideForm.errors"
                                        :key="field"
                                    >
                                        {{ message }}
                                    </li>
                                </ul>
                            </div>
                            <p
                                v-if="overrideSaved"
                                role="status"
                                class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"
                            >
                                Calendar exception saved.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="submit"
                                    :disabled="dateOverrideForm.processing"
                                    class="min-h-11 rounded-xl bg-amber-700 px-5 py-3 text-sm font-bold text-white hover:bg-amber-800 disabled:opacity-60"
                                >
                                    {{ dateOverrideForm.processing ? 'Saving…' : 'Save exception' }}
                                </button>
                                <button
                                    v-if="dateOverrideForm.version > 0"
                                    type="button"
                                    class="min-h-11 rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700"
                                    @click="resetDateOverride"
                                >
                                    Cancel edit
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-xl bg-amber-50 p-4 sm:p-5">
                        <h3 class="font-bold text-slate-950">Upcoming exceptions</h3>
                        <p
                            v-if="!props.bookingSchedule.dateOverrides.length"
                            class="mt-3 text-sm leading-6 text-slate-600"
                        >
                            No closures or special dates configured.
                        </p>
                        <ul v-else class="mt-3 space-y-3">
                            <li
                                v-for="override in props.bookingSchedule.dateOverrides"
                                :key="override.local_date"
                                class="rounded-xl border border-amber-200 bg-white p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-950">
                                            {{ override.local_date }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{
                                                override.closed
                                                    ? 'Closed for online booking'
                                                    : `${override.intervals.length} special session(s)`
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="text-sm font-bold text-amber-800 underline"
                                            @click="editDateOverride(override)"
                                        >
                                            Edit</button
                                        ><button
                                            type="button"
                                            class="text-sm font-bold text-red-700 underline"
                                            @click="deleteDateOverride(override)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="max-w-2xl text-sm leading-6 text-slate-600">
                    Create appointments received by phone, WhatsApp, walk-in, or clinic staff.
                </p>
                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        :aria-expanded="showManualBooking"
                        aria-controls="manual-booking-form"
                        @click="showManualBooking = !showManualBooking"
                    >
                        {{ showManualBooking ? 'Close' : 'New Booking' }}
                    </button>
                </div>
            </div>

            <p
                v-if="successMessage"
                class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"
                role="status"
            >
                {{ successMessage }}
            </p>

            <form
                v-if="showManualBooking"
                id="manual-booking-form"
                class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 sm:p-6"
                @submit.prevent="submitManualBooking"
            >
                <div
                    v-if="form.errors.manual_booking"
                    class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900 sm:col-span-2"
                    role="alert"
                >
                    {{ form.errors.manual_booking }}
                </div>

                <label class="grid gap-2 text-sm font-semibold text-slate-800">
                    Booking source
                    <select
                        v-model="form.source"
                        required
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <option
                            v-for="source in manualBooking.sources"
                            :key="source.value"
                            :value="source.value"
                        >
                            {{ source.label }}
                        </option>
                    </select>
                    <span v-if="form.errors.source" class="text-sm text-red-700" role="alert">{{
                        form.errors.source
                    }}</span>
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-800">
                    Patient name
                    <input
                        v-model="form.patient_name"
                        type="text"
                        maxlength="200"
                        required
                        autocomplete="name"
                        class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span
                        v-if="form.errors.patient_name"
                        class="text-sm text-red-700"
                        role="alert"
                        >{{ form.errors.patient_name }}</span
                    >
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-800">
                    Phone
                    <input
                        v-model="form.phone"
                        type="tel"
                        maxlength="40"
                        required
                        autocomplete="tel"
                        class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span v-if="form.errors.phone" class="text-sm text-red-700" role="alert">{{
                        form.errors.phone
                    }}</span>
                </label>

                <label
                    v-if="manualBooking.serviceSelectionEnabled"
                    class="grid gap-2 text-sm font-semibold text-slate-800"
                >
                    Service
                    <select
                        v-model="form.service_id"
                        :required="manualBooking.serviceSelectionRequired"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <option value="">
                            {{
                                manualBooking.serviceSelectionRequired
                                    ? 'Select a service'
                                    : 'No service selected'
                            }}
                        </option>
                        <option
                            v-for="service in manualBooking.services"
                            :key="service.id"
                            :value="service.id"
                        >
                            {{ service.name }}
                        </option>
                    </select>
                    <span v-if="form.errors.service_id" class="text-sm text-red-700" role="alert">{{
                        form.errors.service_id
                    }}</span>
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-800">
                    Appointment date
                    <input
                        v-model="form.appointment_date"
                        type="date"
                        required
                        class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span
                        v-if="form.errors.appointment_date"
                        class="text-sm text-red-700"
                        role="alert"
                        >{{ form.errors.appointment_date }}</span
                    >
                </label>

                <label class="grid gap-2 text-sm font-semibold text-slate-800">
                    Appointment time
                    <input
                        v-model="form.appointment_time"
                        type="time"
                        required
                        class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span
                        v-if="form.errors.appointment_time"
                        class="text-sm text-red-700"
                        role="alert"
                        >{{ form.errors.appointment_time }}</span
                    >
                </label>

                <label
                    v-if="manualBooking.emailEnabled"
                    class="grid gap-2 text-sm font-semibold text-slate-800"
                >
                    Email
                    <input
                        v-model="form.email"
                        type="email"
                        maxlength="254"
                        autocomplete="email"
                        class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span v-if="form.errors.email" class="text-sm text-red-700" role="alert">{{
                        form.errors.email
                    }}</span>
                </label>

                <label
                    v-if="manualBooking.notesEnabled"
                    class="grid gap-2 text-sm font-semibold text-slate-800 sm:col-span-2"
                >
                    Notes
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="rounded-xl border border-slate-300 px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    />
                    <span v-if="form.errors.notes" class="text-sm text-red-700" role="alert">{{
                        form.errors.notes
                    }}</span>
                </label>

                <div class="flex flex-wrap justify-end gap-3 sm:col-span-2">
                    <button
                        type="button"
                        class="min-h-11 rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        :disabled="form.processing"
                        @click="showManualBooking = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating…' : 'Create Booking' }}
                    </button>
                </div>
            </form>
        </div>

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
