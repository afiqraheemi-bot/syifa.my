<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DashboardEmptyState from './DashboardEmptyState.vue';

const { t } = useI18n();
const page = usePage();
const currentLocale = computed(() => page.props.locale);

const props = defineProps({
    items: { type: Array, required: true },
    csrfToken: { type: String, required: true },
    returnToDetail: { type: Boolean, default: false },
});

const processingAction = ref(null);
const successMessage = ref('');
const errorMessage = ref('');

const formatAppointmentDate = (value) => {
    if (!value) return '';

    const date = new Date(`${value}T00:00:00`);
    return new Intl.DateTimeFormat(currentLocale.value === 'ms' ? 'ms-MY' : 'en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const shortReference = (reference) => reference.replace(/^BOOK-/, '').slice(-8);

const statusClasses = (status) => {
    if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'cancelled') return 'bg-red-100 text-red-800';
    if (status === 'completed') return 'bg-blue-100 text-blue-800';

    return 'bg-amber-100 text-amber-800';
};

const submitAction = (event, booking, action) => {
    event.preventDefault();

    const actionKey = `${booking.id}:${action.key}`;
    if (processingAction.value !== null) {
        return;
    }

    if (action.confirmation && !window.confirm(action.confirmation)) {
        return;
    }

    const formData = new FormData(event.currentTarget);
    const data = action.requiresSchedule
        ? {
              appointment_date: formData.get('appointment_date'),
              appointment_time: formData.get('appointment_time'),
          }
        : {};
    if (props.returnToDetail) {
        data.return_to_detail = true;
    }

    successMessage.value = '';
    errorMessage.value = '';

    router.visit(action.href, {
        method: action.method,
        data,
        preserveScroll: true,
        onStart: () => {
            processingAction.value = actionKey;
        },
        onSuccess: () => {
            successMessage.value = t('bookingTable.actionCompleted', { action: action.label });
        },
        onError: (errors) => {
            errorMessage.value =
                Object.values(errors)[0] ??
                t('bookingTable.actionFailed', { action: action.label });
        },
        onFinish: () => {
            processingAction.value = null;
        },
    });
};
</script>

<template>
    <p
        v-if="successMessage"
        class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900"
        role="status"
    >
        {{ successMessage }}
    </p>
    <p
        v-if="errorMessage"
        class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-900"
        role="alert"
    >
        {{ errorMessage }}
    </p>
    <div
        v-if="items.length > 0"
        class="min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div v-if="!returnToDetail" class="divide-y divide-slate-100 md:hidden">
            <article v-for="booking in items" :key="booking.id" class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-black text-slate-950">
                            {{ booking.patientName || t('bookingTable.patientNameUnavailable') }}
                        </p>
                        <p class="mt-1 text-xs font-medium text-slate-500">
                            {{ t('bookingTable.reference') }}{{ shortReference(booking.reference) }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold',
                            statusClasses(booking.status),
                        ]"
                        >{{ booking.statusLabel }}</span
                    >
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                    <div>
                        <p class="text-xs text-slate-500">{{ t('bookingTable.date') }}</p>
                        <p class="mt-1 font-bold text-slate-900">
                            {{ formatAppointmentDate(booking.appointmentDate) }}
                        </p>
                        <p class="text-xs text-slate-600">
                            {{ booking.appointmentStart }}–{{ booking.appointmentEnd }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">{{ t('bookingTable.service') }}</p>
                        <p class="mt-1 font-bold text-slate-900">
                            {{ booking.serviceName || t('bookingTable.generalAppointment') }}
                        </p>
                    </div>
                </div>
                <a
                    v-if="booking.detailHref"
                    :href="booking.detailHref"
                    class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-800 px-4 text-sm font-bold text-white hover:bg-emerald-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >{{ t('bookingTable.viewBookingDetails') }}</a
                >
            </article>
        </div>
        <div
            :class="[
                'max-w-full overflow-x-auto overscroll-x-contain focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-600',
                !returnToDetail ? 'hidden md:block' : 'block',
            ]"
            role="region"
            :aria-label="t('bookingTable.bookingTableRegion')"
            tabindex="0"
        >
            <p class="sr-only">{{ t('bookingTable.scrollHint') }}</p>
            <table class="w-full min-w-[64rem] table-fixed divide-y divide-slate-200">
                <colgroup>
                    <col class="w-[17%]" />
                    <col class="w-[17%]" />
                    <col class="w-[21%]" />
                    <col class="w-[22%]" />
                    <col class="w-[23%]" />
                </colgroup>
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="label in [
                                t('bookingTable.columnPatient'),
                                t('bookingTable.columnAppointment'),
                                t('bookingTable.columnService'),
                                t('bookingTable.columnStatus'),
                                t('bookingTable.columnDetails'),
                            ]"
                            :key="label"
                            scope="col"
                            class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600"
                        >
                            {{ label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="booking in items" :key="booking.id">
                        <td class="px-5 py-4">
                            <p class="font-bold text-slate-950">
                                {{
                                    booking.patientName || t('bookingTable.patientNameUnavailable')
                                }}
                            </p>
                            <p
                                class="mt-1 text-xs font-medium text-slate-500"
                                :title="booking.reference"
                            >
                                {{ t('bookingTable.reference')
                                }}{{ shortReference(booking.reference) }}
                            </p>
                        </td>
                        <td class="whitespace-nowrap px-5 py-4">
                            <p class="font-semibold text-slate-900">
                                {{ formatAppointmentDate(booking.appointmentDate) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ booking.appointmentStart }}–{{ booking.appointmentEnd }}
                            </p>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-700">
                            {{ booking.serviceName || t('bookingTable.generalAppointment') }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4">
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    statusClasses(booking.status),
                                ]"
                            >
                                {{ booking.statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-if="booking.detailHref"
                                    :href="booking.detailHref"
                                    class="ml-auto inline-flex min-h-11 items-center whitespace-nowrap rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-800 transition hover:border-emerald-700 hover:bg-emerald-50 hover:text-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >
                                    {{ t('bookingTable.viewBookingDetails') }}
                                </a>
                                <template
                                    v-for="action in returnToDetail ? booking.actions : []"
                                    :key="action.key"
                                >
                                    <form
                                        v-if="!action.requiresSchedule"
                                        :action="action.href"
                                        method="post"
                                        @submit="submitAction($event, booking, action)"
                                    >
                                        <input type="hidden" name="_token" :value="csrfToken" />
                                        <button
                                            type="submit"
                                            :disabled="processingAction !== null"
                                            :class="[
                                                'min-h-11 rounded-lg border px-3 py-2 text-xs font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
                                                processingAction !== null
                                                    ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500'
                                                    : action.tone === 'danger'
                                                      ? 'border-red-300 text-red-800 hover:bg-red-50'
                                                      : 'border-slate-900 bg-slate-950 text-white hover:bg-slate-800',
                                            ]"
                                        >
                                            {{ action.label }}
                                        </button>
                                    </form>
                                    <details v-else class="relative">
                                        <summary
                                            class="flex min-h-11 cursor-pointer list-none items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                        >
                                            {{ action.label }}
                                        </summary>
                                        <form
                                            :action="action.href"
                                            method="post"
                                            class="mt-2 grid min-w-56 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3"
                                            @submit="submitAction($event, booking, action)"
                                        >
                                            <input
                                                type="hidden"
                                                name="_method"
                                                :value="action.method"
                                            />
                                            <input
                                                type="hidden"
                                                name="_token"
                                                :value="props.csrfToken"
                                            />
                                            <label class="text-xs font-semibold text-slate-700">
                                                {{ t('bookingTable.newAppointmentDate') }}
                                                <input
                                                    name="appointment_date"
                                                    type="date"
                                                    required
                                                    class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                                />
                                            </label>
                                            <label class="text-xs font-semibold text-slate-700">
                                                {{ t('bookingTable.newAppointmentTime') }}
                                                <input
                                                    name="appointment_time"
                                                    type="time"
                                                    required
                                                    class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                                />
                                            </label>
                                            <button
                                                type="submit"
                                                :disabled="processingAction !== null"
                                                class="min-h-11 rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:bg-slate-300"
                                            >
                                                {{
                                                    processingAction ===
                                                    `${booking.id}:${action.key}`
                                                        ? t('bookingTable.savingTime')
                                                        : t('bookingTable.saveNewTime')
                                                }}
                                            </button>
                                        </form>
                                    </details>
                                </template>
                                <span
                                    v-if="returnToDetail && booking.actions.length === 0"
                                    class="text-xs text-slate-500"
                                >
                                    {{ t('bookingTable.noActionsAvailable') }}
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <DashboardEmptyState
        v-else
        :title="t('bookingTable.noBookingsFound')"
        :description="t('bookingTable.noBookingsFoundDescription')"
    />
</template>
