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
                <p class="text-xs font-black tracking-[0.14em] text-emerald-700 uppercase">
                    Ringkasan tempahan
                </p>
                <h2
                    id="booking-summary-heading"
                    class="mt-1 text-xl font-black tracking-tight text-slate-950"
                >
                    {{ statusSummary.total }} jumlah tempahan
                </h2>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="item in statusSummary.items"
                :key="item.key"
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md"
            >
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-bold text-slate-600">{{ item.label }}</p>
                    <span class="size-2 rounded-full bg-emerald-400 ring-4 ring-emerald-50" />
                </div>
                <p class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-950">
                    {{ item.count }}
                </p>
            </article>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
            <h3 class="text-sm font-black text-slate-950">Sumber tempahan</h3>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="item in sourceSummary.items" :key="item.key">
                    <dt class="text-sm text-slate-600">{{ item.label }}</dt>
                    <dd class="mt-1 text-lg font-black text-slate-950">{{ item.count }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
