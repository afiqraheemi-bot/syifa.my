<script setup>
defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, problems: [string] }
});
</script>

<template>
    <section class="py-20 sm:py-28">
        <div
            class="mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-10"
        >
            <div data-reveal class="reveal">
                <p class="text-xs font-bold tracking-[0.2em] text-emerald-700 uppercase">
                    {{ copy.eyebrow }}
                </p>
                <h2
                    class="mt-3 max-w-lg text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl"
                >
                    {{ copy.title }}
                </h2>

                <ul class="mt-8 space-y-4">
                    <li
                        v-for="problem in copy.problems"
                        :key="problem"
                        class="flex items-start gap-3 rounded-xl border border-red-100 bg-red-50/60 p-4"
                    >
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                class="size-3.5 fill-none stroke-current stroke-[3]"
                            >
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </span>
                        <span class="text-sm leading-6 font-medium text-slate-700">{{
                            problem
                        }}</span>
                    </li>
                </ul>
            </div>

            <div data-reveal class="reveal relative">
                <div
                    class="relative aspect-[4/5] overflow-hidden rounded-[2rem] bg-emerald-50 shadow-xl shadow-slate-900/10"
                >
                    <img
                        :src="'/images/marketing/clinic-booking-challenges.webp'"
                        :alt="copy.imageAlt"
                        width="960"
                        height="1199"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover object-center"
                    />
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/15 via-transparent to-white/5" />
                </div>

                <div
                    class="absolute -top-3 -left-3 flex max-w-[calc(100%-1.5rem)] items-center gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-lg shadow-slate-900/10 sm:px-4 sm:py-3 lg:-left-8"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-full bg-red-100 text-red-600"
                    >
                        <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a1 1 0 00.9 1.5h18.6a1 1 0 00.9-1.5L13.7 3.9a1 1 0 00-1.7 0z"
                            />
                        </svg>
                    </span>
                    <p class="text-xs font-bold text-slate-700">Pesakit tak jumpa website</p>
                </div>

                <div
                    class="absolute -right-3 -bottom-3 flex max-w-[calc(100%-1.5rem)] items-center gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-lg shadow-slate-900/10 sm:px-4 sm:py-3 lg:-right-8"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-full bg-amber-100 text-amber-600"
                    >
                        <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M12 7v5l3 2" />
                        </svg>
                    </span>
                    <p class="text-xs font-bold text-slate-700">Tempahan tertangguh</p>
                </div>
            </div>
        </div>
    </section>
</template>
