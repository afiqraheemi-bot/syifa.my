<script setup>
defineProps({
    paymentStatus: { type: String, required: true },
    statusTone: { type: String, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    formattedAmount: { type: String, required: true },
    lastChangedAt: { type: String, required: true },
    offersUrl: { type: String, required: true },
    refreshUrl: { type: String, required: true },
    homeUrl: { type: String, required: true },
});
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-8 text-slate-950 sm:px-6 sm:py-12">
        <section class="mx-auto max-w-2xl rounded-3xl bg-white p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-bold tracking-[0.18em] text-emerald-700">SYIFA.MY</p>
            <div
                class="mt-6 rounded-2xl border p-5"
                :class="{
                    'border-emerald-200 bg-emerald-50': statusTone === 'success',
                    'border-amber-200 bg-amber-50': statusTone === 'pending',
                    'border-red-200 bg-red-50': statusTone === 'error',
                }"
                role="status"
                aria-live="polite"
            >
                <p class="text-sm font-bold uppercase tracking-wide">
                    {{ paymentStatus.replaceAll('_', ' ') }}
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ title }}</h1>
                <p class="mt-3 leading-7 text-slate-700">{{ message }}</p>
            </div>

            <dl class="mt-6 grid gap-4 rounded-2xl border border-slate-200 p-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-slate-500">Amount</dt>
                    <dd class="mt-1 text-lg font-bold">{{ formattedAmount }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-slate-500">Last verified update</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-800">
                        {{ new Date(lastChangedAt).toLocaleString('en-MY') }}
                    </dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a
                    v-if="statusTone === 'pending'"
                    :href="refreshUrl"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-5 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Refresh payment status
                </a>
                <a
                    v-if="statusTone === 'error'"
                    :href="offersUrl"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-5 font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Return to annual offer
                </a>
                <a
                    :href="homeUrl"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 px-5 font-bold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >
                    Return home
                </a>
            </div>
        </section>
    </main>
</template>
