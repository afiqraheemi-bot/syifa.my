<script setup>
import { computed } from 'vue';
import AppBadge from './AppBadge.vue';
import SectionHeader from './SectionHeader.vue';

const props = defineProps({
    copy: { type: Object, required: true },
    previewUrls: { type: Array, required: true },
});

const liveTemplates = computed(() =>
    props.copy.items
        .map((item, index) => ({ ...item, previewUrl: props.previewUrls[index] }))
        .filter((item) => Boolean(item.previewUrl)),
);
</script>

<template>
    <section id="templates" class="anchor-section overflow-hidden bg-slate-50/70 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <SectionHeader
                :eyebrow="copy.eyebrow"
                :title="copy.title"
                :subtitle="copy.subtitle"
                align="center"
            />

            <div data-reveal class="reveal mt-7 flex flex-wrap items-center justify-center gap-3">
                <AppBadge>{{ liveTemplates.length }} {{ copy.livePreviews }}</AppBadge>
                <span class="text-sm font-semibold text-slate-500">{{ copy.managedNote }}</span>
            </div>

            <div class="mt-12 grid gap-7 lg:grid-cols-2">
                <article
                    v-for="item in liveTemplates"
                    :key="item.name"
                    data-reveal
                    class="reveal flex min-h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-900/5"
                >
                    <div
                        class="flex items-center gap-1.5 border-b border-slate-200 bg-slate-100/90 px-3 py-2.5"
                    >
                        <span class="size-2 rounded-full bg-red-300" />
                        <span class="size-2 rounded-full bg-amber-300" />
                        <span class="size-2 rounded-full bg-emerald-300" />
                        <span
                            class="ml-2 flex-1 truncate rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] text-slate-400"
                            >klinik-anda.syifa.my</span
                        >
                        <span
                            class="ml-1 rounded-full bg-emerald-100 px-2 py-1 text-[9px] font-black tracking-wider text-emerald-800 uppercase"
                            >{{ copy.previewReady }}</span
                        >
                    </div>

                    <div class="relative aspect-[16/10] overflow-hidden bg-white">
                        <iframe
                            :src="item.previewUrl"
                            :title="`${copy.livePreviewTitle}: ${item.name}`"
                            loading="lazy"
                            tabindex="-1"
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 h-[720px] w-full border-0"
                        />
                        <div
                            class="pointer-events-none absolute inset-0 ring-1 ring-inset ring-slate-900/5"
                        />
                    </div>

                    <div class="flex flex-1 flex-col border-t border-slate-100 p-5 sm:p-6">
                        <h3 class="text-xl font-bold text-slate-950">{{ item.name }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">
                            {{ item.tagline }}
                        </p>
                        <div
                            class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"
                        >
                            <span
                                class="text-xs font-bold tracking-[0.12em] text-slate-400 uppercase"
                                >{{ copy.managedTemplate }}</span
                            >
                            <span
                                class="inline-flex min-h-9 items-center rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800"
                                >{{ copy.livePreviewLabel }}</span
                            >
                        </div>
                    </div>
                </article>
            </div>

            <p
                data-reveal
                class="reveal mx-auto mt-10 max-w-2xl text-center text-sm leading-6 text-slate-500"
            >
                {{ copy.selectionNote }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
