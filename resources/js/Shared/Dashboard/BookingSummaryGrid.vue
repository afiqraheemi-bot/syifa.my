<script setup>
defineProps({
    statusSummary: { type: Object, required: true },
    sourceSummary: { type: Object, required: true },
});
</script>

<template>
    <section aria-labelledby="booking-summary-heading">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-700">Booking summary</p>
                <h2 id="booking-summary-heading" class="mt-1 text-xl font-bold text-slate-950">
                    {{ statusSummary.total }} total bookings
                </h2>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="item in statusSummary.items"
                :key="item.key"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-sm font-semibold text-slate-600">{{ item.label }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-950">{{ item.count }}</p>
            </article>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-950">Booking sources</h3>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="item in sourceSummary.items" :key="item.key">
                    <dt class="text-sm text-slate-600">{{ item.label }}</dt>
                    <dd class="mt-1 font-bold text-slate-950">{{ item.count }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
