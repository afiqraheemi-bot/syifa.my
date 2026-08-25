<script setup>
defineProps({
    status: { type: Object, required: true },
    action: { type: Object, default: null },
});
</script>

<template>
    <article
        class="relative isolate h-full overflow-hidden rounded-[1.75rem] bg-emerald-950 p-6 text-white shadow-xl shadow-emerald-950/10 sm:p-8"
    >
        <div
            class="absolute -right-14 -top-20 -z-10 size-60 rounded-full border-[48px] border-emerald-800/40"
        />
        <div class="flex h-full flex-col justify-between gap-8 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-black tracking-[0.16em] text-lime-300 uppercase">
                    Website klinik
                </p>
                <h2 class="mt-3 text-2xl font-black tracking-[-0.035em] sm:text-3xl">
                    {{ status.title }}
                </h2>
                <div
                    class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold"
                >
                    <span
                        :class="[
                            'size-2 rounded-full',
                            status.tone === 'positive' ? 'bg-lime-300' : 'bg-amber-300',
                        ]"
                    />{{ status.value }}
                </div>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-emerald-100/80">
                    {{ status.detail }}
                </p>
            </div>
            <a
                v-if="action?.available && action?.href"
                :href="action.href"
                class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-lime-300 px-5 text-sm font-black text-emerald-950 transition hover:-translate-y-0.5 hover:bg-lime-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >{{ action.label }} <span aria-hidden="true">→</span></a
            >
        </div>
    </article>
</template>
