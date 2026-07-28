<script setup>
import {
    BookingTable,
    createDashboardNavigation,
    DashboardEmptyState,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    backHref: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    booking: { type: Object, required: true },
    history: { type: Array, required: true },
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
        <div class="space-y-8">
            <a
                :href="backHref"
                class="inline-flex min-h-11 items-center font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Back to bookings
            </a>

            <BookingTable :items="[booking]" :csrf-token="csrfToken" :return-to-detail="true" />

            <section class="grid gap-4 lg:grid-cols-2" aria-label="Booking details">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-lg font-bold text-slate-950">Patient details</h2>
                    <dl class="mt-4 grid gap-4 text-sm">
                        <div>
                            <dt class="font-semibold text-slate-500">Name</dt>
                            <dd class="mt-1 text-slate-950">{{ booking.patientName }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Phone</dt>
                            <dd class="mt-1 text-slate-950">{{ booking.patientPhone }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Email</dt>
                            <dd class="mt-1 text-slate-950">
                                {{ booking.patientEmail || 'Not provided' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Notes</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-950">
                                {{ booking.notes || 'Not provided' }}
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-lg font-bold text-slate-950">Booking snapshot</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold text-slate-500">Service</dt>
                            <dd class="mt-1 text-slate-950">
                                {{ booking.serviceName || 'Not selected' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Source</dt>
                            <dd class="mt-1 text-slate-950">{{ booking.sourceLabel }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Local schedule</dt>
                            <dd class="mt-1 text-slate-950">
                                {{ booking.appointmentDate }} · {{ booking.appointmentStart }}–{{
                                    booking.appointmentEnd
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Timezone</dt>
                            <dd class="mt-1 text-slate-950">{{ booking.timezone }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">UTC snapshot</dt>
                            <dd class="mt-1 break-words text-slate-950">
                                {{ booking.startsAtUtc }} – {{ booking.endsAtUtc }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Created</dt>
                            <dd class="mt-1 break-words text-slate-950">{{ booking.createdAt }}</dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section aria-labelledby="booking-history-title">
                <h2 id="booking-history-title" class="text-xl font-bold text-slate-950">
                    Booking history
                </h2>
                <ol v-if="history.length" class="mt-4 space-y-4">
                    <li
                        v-for="entry in history"
                        :key="entry.id"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-950">{{ entry.eventLabel }}</p>
                                <p class="mt-1 text-sm text-slate-600">
                                    Actor: {{ entry.actorLabel }}
                                    <span v-if="entry.actorId">· {{ entry.actorId }}</span>
                                </p>
                            </div>
                            <time :datetime="entry.occurredAt" class="text-sm text-slate-600">
                                {{ entry.occurredAt }}
                            </time>
                        </div>
                        <dl
                            v-if="Object.keys(entry.payload).length"
                            class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2"
                        >
                            <div v-for="(value, key) in entry.payload" :key="key">
                                <dt class="font-semibold text-slate-500">
                                    {{ String(key).replaceAll('_', ' ') }}
                                </dt>
                                <dd class="mt-1 break-words text-slate-950">
                                    {{ value === null ? 'Not provided' : value }}
                                </dd>
                            </div>
                        </dl>
                    </li>
                </ol>
                <DashboardEmptyState
                    v-else
                    title="No booking history"
                    description="Lifecycle events for this booking will appear here."
                />
            </section>
        </div>
    </DashboardShell>
</template>
