<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

const props = defineProps({
    items: { type: Array, required: true },
    csrfToken: { type: String, required: true },
    returnToDetail: { type: Boolean, default: false },
});

const processingAction = ref(null);
const successMessage = ref('');
const errorMessage = ref('');

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
            successMessage.value = `${action.label} completed successfully.`;
        },
        onError: (errors) => {
            errorMessage.value =
                Object.values(errors)[0] ?? `${action.label} could not be completed.`;
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
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div
            class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-600"
            role="region"
            aria-label="Bookings table"
            tabindex="0"
        >
            <p class="sr-only">
                Scroll horizontally to review all booking information and actions.
            </p>
            <table class="min-w-[64rem] divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="label in [
                                'Reference',
                                'Appointment',
                                'Service',
                                'Source',
                                'Status',
                                'Actions',
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
                        <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-950">
                            {{ booking.reference }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                            {{ booking.appointmentDate }} · {{ booking.appointmentStart }}–{{
                                booking.appointmentEnd
                            }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                            {{ booking.serviceId || 'Not selected' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                            {{ booking.sourceLabel }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4">
                            <span
                                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700"
                            >
                                {{ booking.statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex min-w-56 flex-wrap gap-2">
                                <a
                                    v-if="booking.detailHref"
                                    :href="booking.detailHref"
                                    class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >
                                    View
                                </a>
                                <template v-for="action in booking.actions" :key="action.key">
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
                                                New appointment date
                                                <input
                                                    name="appointment_date"
                                                    type="date"
                                                    required
                                                    class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                                />
                                            </label>
                                            <label class="text-xs font-semibold text-slate-700">
                                                New appointment time
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
                                                        ? 'Saving…'
                                                        : 'Save new time'
                                                }}
                                            </button>
                                        </form>
                                    </details>
                                </template>
                                <span
                                    v-if="booking.actions.length === 0"
                                    class="text-xs text-slate-500"
                                >
                                    No actions available
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
        title="No bookings found"
        description="Bookings matching the current search and filters will appear here."
    />
</template>
