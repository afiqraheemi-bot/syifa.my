<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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
});

const navigation = createDashboardNavigation(props.navigation);
const showManualBooking = ref(false);
const successMessage = ref('');
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
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="max-w-2xl text-sm leading-6 text-slate-600">
                    Create appointments received by phone, WhatsApp, walk-in, or clinic staff.
                </p>
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
