<script setup>
defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, checklist: [string], quote }
});
</script>

<template>
    <section class="bg-slate-50/60 py-20 sm:py-28">
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

                <ul class="mt-8 space-y-5">
                    <li
                        v-for="item in copy.checklist"
                        :key="item.title"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                class="size-3.5 fill-none stroke-current stroke-[3]"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </span>
                        <div>
                            <p class="font-bold text-slate-950">{{ item.title }}</p>
                            <p class="mt-0.5 text-sm leading-6 text-slate-600">{{ item.body }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div data-reveal class="reveal relative">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-[2rem] bg-emerald-50 shadow-xl shadow-emerald-900/15"
                >
                    <img
                        :src="'/images/marketing/clinic-digital-collaboration.webp'"
                        :alt="copy.imageAlt"
                        width="1200"
                        height="900"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover object-center"
                    />
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-emerald-950/15 via-transparent to-white/5" />
                </div>

                <div
                    class="absolute -bottom-8 left-1/2 w-[calc(100%-2rem)] -translate-x-1/2 rounded-2xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-900/10 sm:w-4/5"
                >
                    <svg viewBox="0 0 24 24" class="size-6 fill-emerald-200">
                        <path
                            d="M7.5 6C4.5 6 2 8.5 2 11.5S4.5 17 7.5 17c.4 0 .7 0 1-.1-.6 1.4-1.8 2.5-3.3 3.1a1 1 0 00.4 1.9c4-.5 7.4-3.9 7.4-8.4C13 9.4 10.6 6 7.5 6zm9 0c-3 0-5.5 2.5-5.5 5.5S13.5 17 16.5 17c.4 0 .7 0 1-.1-.6 1.4-1.8 2.5-3.3 3.1a1 1 0 00.4 1.9c4-.5 7.4-3.9 7.4-8.4 0-4.1-2.4-7.5-5.5-7.5z"
                        />
                    </svg>
                    <p class="mt-2 text-sm leading-6 font-semibold text-slate-800">
                        {{ copy.quote }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
