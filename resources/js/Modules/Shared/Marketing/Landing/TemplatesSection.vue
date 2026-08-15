<script setup>
import { computed, ref } from 'vue';
import AppButton from './AppButton.vue';
import SectionHeader from './SectionHeader.vue';

const props = defineProps({
    copy: { type: Object, required: true },
    registerUrl: { type: String, required: true },
});

const activeIndex = ref(0);
const heroImages = [
    '/images/template-previews/syifa-essential-hero.webp',
    '/images/template-previews/syifa-care-hero.webp',
    '/images/template-previews/syifa-dental-hero.webp',
    '/images/template-previews/syifa-aesthetic-hero.webp',
    '/images/template-previews/syifa-specialist-hero.webp',
];
const themes = [
    { primary: '#0f766e', soft: '#e8f4f2', ink: '#123b38' },
    { primary: '#15803d', soft: '#edf7ee', ink: '#173d25' },
    { primary: '#0369a1', soft: '#eaf4fa', ink: '#123b54' },
    { primary: '#9d174d', soft: '#f9edf2', ink: '#4d1d30' },
    { primary: '#1e3a8a', soft: '#edf1fa', ink: '#172554' },
];

const activeTemplate = computed(() => props.copy.items[activeIndex.value] ?? null);
const activeTheme = computed(() => themes[activeIndex.value]);
const activeImage = computed(() => heroImages[activeIndex.value]);
const previewStyle = computed(() => ({
    '--preview-primary': activeTheme.value.primary,
    '--preview-soft': activeTheme.value.soft,
    '--preview-ink': activeTheme.value.ink,
}));
</script>

<template>
    <section id="templates" class="anchor-section overflow-hidden bg-slate-50 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <SectionHeader
                :eyebrow="copy.eyebrow"
                :title="copy.title"
                :subtitle="copy.subtitle"
                align="center"
            />

            <div
                class="mt-10 flex snap-x gap-2 overflow-x-auto pb-2 sm:mt-12 sm:grid sm:grid-cols-5 sm:overflow-visible"
                role="tablist"
                :aria-label="copy.templateSelector"
            >
                <button
                    v-for="(item, index) in copy.items"
                    :key="item.name"
                    type="button"
                    role="tab"
                    :aria-selected="activeIndex === index"
                    class="min-h-12 shrink-0 snap-start rounded-xl border px-5 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:px-3"
                    :class="
                        activeIndex === index
                            ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/15'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:text-emerald-800'
                    "
                    @click="activeIndex = index"
                >
                    {{ item.name }}
                </button>
            </div>

            <div
                v-if="activeTemplate"
                data-reveal
                class="reveal mt-5 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_32px_90px_-46px_rgba(15,23,42,0.42)] sm:mt-7"
                :style="previewStyle"
            >
                <div class="flex h-11 items-center border-b border-slate-200 bg-slate-50 px-4">
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-red-300" />
                        <span class="size-2.5 rounded-full bg-amber-300" />
                        <span class="size-2.5 rounded-full bg-emerald-300" />
                    </div>
                    <div
                        class="mx-auto -translate-x-5 rounded-md bg-white px-5 py-1 text-[10px] font-semibold tracking-wide text-slate-400 shadow-sm ring-1 ring-slate-200"
                    >
                        {{ activeTemplate.demoDomain }}
                    </div>
                </div>

                <div class="demo-nav">
                    <strong>{{ activeTemplate.demoClinic }}</strong>
                    <div class="demo-nav__links" aria-hidden="true">
                        <span>About</span><span>Services</span><span>Doctors</span>
                    </div>
                    <span class="demo-nav__button">{{ activeTemplate.demoCta }}</span>
                </div>

                <div class="demo-hero">
                    <div class="demo-hero__copy">
                        <p>{{ activeTemplate.demoEyebrow }}</p>
                        <h3>{{ activeTemplate.demoHeadline }}</h3>
                        <div class="demo-hero__body">{{ activeTemplate.demoBody }}</div>
                        <div class="demo-hero__actions">
                            <span>{{ activeTemplate.demoCta }}</span>
                            <small>{{ activeTemplate.demoSecondary }}</small>
                        </div>
                        <div class="demo-hero__trust">
                            <span>✓ {{ copy.clinicReady }}</span>
                            <span>✓ {{ copy.mobileReady }}</span>
                        </div>
                    </div>
                    <div class="demo-hero__media">
                        <img
                            :src="activeImage"
                            :alt="activeTemplate.demoImageAlt"
                            width="1586"
                            height="992"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-7 grid items-center gap-5 md:grid-cols-[1fr_auto]">
                <div>
                    <p class="text-xs font-black tracking-[0.14em] text-emerald-700 uppercase">
                        {{ copy.bestFor }}
                    </p>
                    <p class="mt-2 text-base font-bold text-slate-900">
                        {{ activeTemplate.bestFor }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-slate-500">
                        {{ activeTemplate.tagline }}
                    </p>
                </div>
                <AppButton :href="registerUrl" variant="primary" size="lg">
                    {{ copy.chooseTemplate }}
                </AppButton>
            </div>

            <p class="mx-auto mt-8 max-w-3xl text-center text-sm leading-6 text-slate-500">
                {{ copy.selectionNote }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
.demo-nav {
    display: flex;
    min-height: 4.5rem;
    align-items: center;
    gap: 2rem;
    padding: 1rem clamp(1.25rem, 3vw, 2.5rem);
    color: var(--preview-ink);
}
.demo-nav strong {
    margin-right: auto;
    font-size: clamp(1rem, 2vw, 1.35rem);
}
.demo-nav__links {
    display: flex;
    gap: 1.5rem;
    font-size: 0.78rem;
    font-weight: 700;
}
.demo-nav__button,
.demo-hero__actions > span {
    border-radius: 999px;
    background: var(--preview-primary);
    color: #fff;
    font-weight: 800;
}
.demo-nav__button {
    padding: 0.65rem 1rem;
    font-size: 0.72rem;
}
.demo-hero {
    display: grid;
    min-height: 30rem;
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
    background: var(--preview-soft);
}
.demo-hero__copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(2rem, 5vw, 5rem);
}
.demo-hero__copy > p {
    color: var(--preview-primary);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.demo-hero__copy h3 {
    max-width: 12ch;
    margin-top: 1rem;
    color: var(--preview-ink);
    font-size: clamp(2.25rem, 4.2vw, 4.5rem);
    font-weight: 900;
    letter-spacing: -0.045em;
    line-height: 0.98;
}
.demo-hero__body {
    max-width: 34rem;
    margin-top: 1.4rem;
    color: #52606d;
    font-size: clamp(0.95rem, 1.3vw, 1.15rem);
    line-height: 1.7;
}
.demo-hero__actions {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    margin-top: 1.8rem;
}
.demo-hero__actions > span {
    padding: 0.85rem 1.25rem;
    font-size: 0.8rem;
}
.demo-hero__actions small {
    color: var(--preview-ink);
    font-weight: 800;
}
.demo-hero__trust {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 2rem;
    color: #64748b;
    font-size: 0.7rem;
    font-weight: 700;
}
.demo-hero__media {
    min-height: 30rem;
    overflow: hidden;
}
.demo-hero__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
@media (max-width: 47.999rem) {
    .demo-nav {
        min-height: 3.75rem;
    }
    .demo-nav__links {
        display: none;
    }
    .demo-hero {
        min-height: 0;
        grid-template-columns: 1fr;
    }
    .demo-hero__copy {
        padding: 2rem 1.5rem 2.25rem;
    }
    .demo-hero__copy h3 {
        font-size: clamp(2.15rem, 11vw, 3.5rem);
    }
    .demo-hero__media {
        min-height: 18rem;
        order: -1;
    }
    .demo-nav__button {
        display: none;
    }
}
</style>
