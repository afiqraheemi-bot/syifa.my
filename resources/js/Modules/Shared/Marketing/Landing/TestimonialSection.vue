<script setup>
defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, quote, name, clinicName }
});
</script>

<template>
    <section class="bg-slate-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-10">
            <p
                data-reveal
                class="reveal text-xs font-bold tracking-[0.2em] text-emerald-700 uppercase"
            >
                {{ copy.eyebrow }}
            </p>
            <h2
                data-reveal
                class="reveal mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl"
            >
                {{ copy.title }}
            </h2>

            <div
                data-reveal
                class="reveal mt-12 rounded-3xl border border-slate-100 bg-white p-8 shadow-lg shadow-slate-900/5 sm:p-12"
            >
                <div class="flex items-center justify-center gap-0.5 text-amber-500">
                    <svg
                        v-for="star in 5"
                        :key="star"
                        viewBox="0 0 20 20"
                        class="size-5 fill-current"
                    >
                        <path
                            d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9-4.3-4.2 5.9-.8L10 1.5z"
                        />
                    </svg>
                </div>
                <p class="mt-6 text-lg leading-8 font-semibold text-slate-800 sm:text-xl">
                    &ldquo;{{ copy.quote }}&rdquo;
                </p>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white"
                    >
                        {{
                            copy.name
                                .split(' ')
                                .map((part) => part[0])
                                .slice(0, 2)
                                .join('')
                        }}
                    </span>
                    <div class="text-left">
                        <p class="text-sm font-bold text-slate-950">{{ copy.name }}</p>
                        <p class="text-xs text-slate-500">{{ copy.clinicName }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
