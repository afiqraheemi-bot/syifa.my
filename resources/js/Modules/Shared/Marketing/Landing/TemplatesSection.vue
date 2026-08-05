<script setup>
import SectionHeader from './SectionHeader.vue';

defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, subtitle, note, viewPreview, items: [{ name, tagline }] }
    previewUrls: { type: Array, required: true }, // 5 entries, null where no preview exists yet
});

// Colors/radii mirror each template's real production tokens
// (resources/css/public-website.css [data-template='...']).
const templateStyles = [
    {
        frame: 'border-[#cbd7d1]',
        radius: 'rounded-xl',
        bodyBg: 'bg-[#fffdf9]',
        navText: 'text-[#18221f]',
        headingBar: 'bg-[#176b50]',
        ctaBar: 'bg-[#176b50]',
        imageBlob: 'bg-[#e8f0ea]',
        cardBg: 'bg-[#f4f7f3]',
        iconDot: 'bg-[#176b50]',
        font: '',
    },
    {
        frame: 'border-[#d7e4da]',
        radius: 'rounded-3xl',
        bodyBg: 'bg-white',
        navText: 'text-[#0b2a1f]',
        headingBar: 'bg-[#0b2a1f]',
        ctaBar: 'bg-[#0b2a1f]',
        imageBlob: 'bg-[#ddf0c3]',
        cardBg: 'bg-[#eef6f0]',
        iconDot: 'bg-[#bef264]',
        font: '',
    },
    {
        frame: 'border-[#bfd2d9]',
        radius: 'rounded-md',
        bodyBg: 'bg-white',
        navText: 'text-[#102d36]',
        headingBar: 'bg-[#0f6e96]',
        ctaBar: 'bg-[#0f6e96]',
        imageBlob: 'bg-[#e7f3f7]',
        cardBg: 'bg-[#f4f9fb]',
        iconDot: 'bg-[#0f6e96]',
        font: '',
    },
    {
        frame: 'border-[#d8cbc2]',
        radius: 'rounded-sm',
        bodyBg: 'bg-[#fdfbf8]',
        navText: 'text-[#2d2825]',
        headingBar: 'bg-[#302824]',
        ctaBar: 'bg-[#302824]',
        imageBlob: 'bg-[#ece3dc]',
        cardBg: 'bg-[#f5f0eb]',
        iconDot: 'bg-[#a8765d]',
        font: 'font-serif',
    },
    {
        frame: 'border-[#bdc9d3]',
        radius: 'rounded-sm',
        bodyBg: 'bg-[#fbfcfd]',
        navText: 'text-[#182735]',
        headingBar: 'bg-[#1d2c3b]',
        ctaBar: 'bg-[#1d2c3b]',
        imageBlob: 'bg-[#e2e8ee]',
        cardBg: 'bg-[#f0f3f6]',
        iconDot: 'bg-[#1d2c3b]',
        font: '',
    },
];
</script>

<template>
    <section id="templates" class="anchor-section py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <SectionHeader
                :eyebrow="copy.eyebrow"
                :title="copy.title"
                :subtitle="copy.subtitle"
                align="center"
            />

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <component
                    :is="previewUrls[index] ? 'a' : 'article'"
                    v-for="(item, index) in copy.items"
                    :key="item.name"
                    data-reveal
                    class="reveal overflow-hidden rounded-2xl border bg-white shadow-sm shadow-slate-900/5 transition"
                    :href="previewUrls[index] ?? undefined"
                    :class="[
                        templateStyles[index].frame,
                        previewUrls[index]
                            ? 'hover:-translate-y-1 hover:shadow-lg hover:shadow-slate-900/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600'
                            : '',
                    ]"
                >
                    <div
                        class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-100 px-3 py-2"
                    >
                        <span class="size-2 rounded-full bg-red-300" />
                        <span class="size-2 rounded-full bg-amber-300" />
                        <span class="size-2 rounded-full bg-emerald-300" />
                        <span
                            class="ml-2 flex-1 truncate rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] text-slate-400"
                        >
                            klinik-anda.syifa.my
                        </span>
                    </div>

                    <div class="p-4" :class="templateStyles[index].bodyBg">
                        <div class="flex items-center justify-between pb-4">
                            <span
                                class="text-[11px] font-bold tracking-wide"
                                :class="[templateStyles[index].navText, templateStyles[index].font]"
                            >
                                {{ item.name }}
                            </span>
                            <div class="flex gap-2">
                                <span class="h-1.5 w-5 rounded-full bg-slate-200" />
                                <span class="h-1.5 w-5 rounded-full bg-slate-200" />
                                <span class="h-1.5 w-5 rounded-full bg-slate-200" />
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="flex-1 space-y-2">
                                <span
                                    class="block h-2.5 w-4/5 rounded-full"
                                    :class="templateStyles[index].headingBar"
                                />
                                <span class="block h-2 w-3/5 rounded-full bg-slate-200" />
                                <span
                                    class="mt-2 inline-block h-5 w-16 rounded-full"
                                    :class="templateStyles[index].ctaBar"
                                />
                            </div>
                            <div
                                class="h-16 w-16 shrink-0"
                                :class="[
                                    templateStyles[index].imageBlob,
                                    templateStyles[index].radius,
                                ]"
                            />
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <div
                                v-for="n in 3"
                                :key="n"
                                class="p-2"
                                :class="[
                                    templateStyles[index].cardBg,
                                    templateStyles[index].radius,
                                ]"
                            >
                                <span
                                    class="block size-3 rounded-full"
                                    :class="templateStyles[index].iconDot"
                                />
                                <span class="mt-1.5 block h-1.5 w-full rounded-full bg-slate-200" />
                                <span class="mt-1 block h-1.5 w-2/3 rounded-full bg-slate-200" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 p-5">
                        <p
                            class="text-xs font-bold tracking-[0.14em] uppercase"
                            :class="previewUrls[index] ? 'text-emerald-700' : 'text-slate-400'"
                        >
                            {{ previewUrls[index] ? copy.viewPreview : copy.note }}
                        </p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">{{ item.name }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.tagline }}</p>
                    </div>
                </component>
            </div>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
