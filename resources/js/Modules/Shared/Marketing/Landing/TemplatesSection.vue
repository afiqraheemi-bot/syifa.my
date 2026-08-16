<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from './AppButton.vue';
import SectionHeader from './SectionHeader.vue';

const props = defineProps({
    copy: { type: Object, required: true },
    registerUrl: { type: String, required: true },
    previewUrls: { type: Array, required: true },
});

const activeIndex = ref(0);
const activeTemplate = computed(() => props.copy.items[activeIndex.value] ?? null);
const activePreviewUrl = computed(() => props.previewUrls[activeIndex.value] ?? null);
const previewViewport = ref(null);
const previewScale = ref(1);
let previewObserver;

function resizePreview() {
    previewScale.value = Math.min(1, (previewViewport.value?.clientWidth ?? 1440) / 1440);
}

onMounted(async () => {
    await nextTick();
    resizePreview();
    previewObserver = new ResizeObserver(resizePreview);
    previewObserver.observe(previewViewport.value);
});

onBeforeUnmount(() => previewObserver?.disconnect());
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
            >
                <div class="real-site-showcase">
                    <div class="browser-chrome">
                        <div class="browser-dots" aria-hidden="true"><span /><span /><span /></div>
                        <div class="browser-address">
                            {{ activeTemplate.demoDomain ?? 'klinikaafiyah.syifa.my' }}
                        </div>
                        <span class="real-preview-label">{{ copy.desktop }}</span>
                    </div>
                    <div
                        ref="previewViewport"
                        class="real-preview-viewport"
                        :style="{ height: `${900 * previewScale}px` }"
                    >
                        <iframe
                            v-if="activePreviewUrl"
                            :key="activePreviewUrl"
                            :src="activePreviewUrl"
                            :title="`Paparan desktop sebenar ${activeTemplate.name}`"
                            tabindex="-1"
                            loading="lazy"
                            :style="{ transform: `scale(${previewScale})` }"
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
.real-site-showcase {
    overflow: hidden;
    background: #fff;
}
.real-preview-label {
    justify-self: end;
    color: #047857;
    font-size: 0.58rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.real-preview-viewport {
    position: relative;
    width: 100%;
    overflow: hidden;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}
.real-preview-viewport iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 1440px;
    height: 900px;
    border: 0;
    transform-origin: top left;
    pointer-events: none;
}
.device-showcase {
    display: grid;
    grid-template-columns: minmax(0, 1fr) clamp(10.5rem, 18vw, 13.5rem);
    align-items: center;
    gap: clamp(1rem, 2.5vw, 2rem);
    padding: clamp(1rem, 2.5vw, 2rem);
    background:
        radial-gradient(circle at 88% 15%, color-mix(in srgb, var(--preview-primary) 12%, transparent), transparent 30rem),
        #f8fafc;
}
.desktop-device {
    width: 100%;
    overflow: hidden;
    border: 1px solid #dbe3ec;
    border-radius: 1.15rem;
    background: #fff;
    box-shadow: 0 2rem 4rem -2.2rem rgb(15 23 42 / 45%);
}
.browser-chrome {
    display: grid;
    min-height: 2.4rem;
    grid-template-columns: 4rem minmax(0, 1fr) 4rem;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 0.45rem 0.75rem;
}
.browser-dots {
    display: flex;
    gap: 0.3rem;
}
.browser-dots span {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: #cbd5e1;
}
.browser-dots span:first-child { background: #fca5a5; }
.browser-dots span:nth-child(2) { background: #fcd34d; }
.browser-dots span:last-child { background: #86efac; }
.browser-address {
    width: min(100%, 22rem);
    justify-self: center;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 0.45rem;
    background: #fff;
    padding: 0.25rem 0.8rem;
    color: #64748b;
    font-size: 0.58rem;
    font-weight: 700;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.device-label,
.phone-label {
    color: var(--preview-primary);
    font-size: 0.58rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.device-label { justify-self: end; }
.demo-nav {
    display: flex;
    min-height: 3.75rem;
    align-items: center;
    gap: 2rem;
    padding: 0.75rem clamp(1rem, 2vw, 1.75rem);
    color: var(--preview-ink);
}
.demo-nav strong {
    margin-right: auto;
    font-size: clamp(0.85rem, 1.5vw, 1.1rem);
}
.clinic-wordmark {
    display: flex;
    flex-direction: column;
    color: #111827;
    font-weight: 900;
    letter-spacing: 0.17em;
    line-height: 0.9;
    text-transform: uppercase;
}
.clinic-wordmark small {
    font-size: 0.46em;
    letter-spacing: 0.32em;
}
.demo-nav__links {
    display: flex;
    gap: 1.5rem;
    font-size: 0.64rem;
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
    padding: 0.55rem 0.8rem;
    font-size: 0.62rem;
}
.demo-hero {
    display: grid;
    min-height: clamp(22rem, 40vw, 29rem);
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
    background: var(--preview-soft);
}
.demo-hero__copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(1.5rem, 3.5vw, 3.5rem);
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
    font-size: clamp(1.75rem, 3.35vw, 3.25rem);
    font-weight: 900;
    letter-spacing: -0.045em;
    line-height: 0.98;
}
.demo-hero__body {
    max-width: 34rem;
    margin-top: 1rem;
    color: #52606d;
    font-size: clamp(0.72rem, 1vw, 0.9rem);
    line-height: 1.6;
}
.demo-hero__actions {
    display: flex;
    align-items: center;
    gap: 1.15rem;
    margin-top: 1.1rem;
}
.demo-hero__actions > span {
    padding: 0.65rem 0.9rem;
    font-size: 0.66rem;
}
.demo-hero__actions small {
    color: var(--preview-ink);
    font-weight: 800;
}
.demo-hero__trust {
    display: flex;
    overflow: hidden;
    border-radius: 0.65rem;
    background: var(--preview-ink);
    margin-top: 1.15rem;
    color: #fff;
    font-size: 0.58rem;
    font-weight: 700;
}
.demo-hero__trust span {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 0.7rem;
}
.demo-hero__trust span + span { border-left: 1px solid rgb(255 255 255 / 18%); }
.demo-hero__trust b { color: color-mix(in srgb, var(--preview-primary) 35%, white); }
.demo-hero__media {
    min-height: clamp(22rem, 40vw, 29rem);
    overflow: hidden;
}
.demo-hero__media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.mobile-device {
    position: relative;
    width: 100%;
    justify-self: center;
    border: 0.38rem solid #172033;
    border-radius: 1.7rem;
    background: #172033;
    padding: 0.32rem;
    box-shadow: 0 2rem 4rem -1.6rem rgb(15 23 42 / 65%);
}
.phone-speaker {
    position: absolute;
    z-index: 2;
    top: 0.62rem;
    left: 50%;
    width: 28%;
    height: 0.28rem;
    transform: translateX(-50%);
    border-radius: 999px;
    background: #172033;
}
.phone-screen {
    overflow: hidden;
    border-radius: 1.15rem;
    background: var(--preview-soft);
}
.mobile-demo-nav {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 0.35rem;
    background: #fff;
    padding: 1rem 0.65rem 0.65rem;
    color: var(--preview-ink);
    font-size: 0.42rem;
}
.mobile-demo-nav strong { font-size: 0.5rem; }
.mobile-demo-nav .clinic-wordmark small { font-size: 0.4em; }
.mobile-demo-nav span {
    border-radius: 999px;
    background: var(--preview-primary);
    padding: 0.3rem 0.4rem;
    color: #fff;
    font-weight: 900;
}
.mobile-demo-nav i { font-style: normal; font-size: 0.58rem; }
.mobile-demo-copy { padding: 1rem 0.8rem 0.8rem; }
.mobile-demo-copy p {
    color: var(--preview-primary);
    font-size: 0.38rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.mobile-demo-copy h3 {
    margin-top: 0.42rem;
    color: var(--preview-ink);
    font-size: clamp(0.95rem, 1.8vw, 1.25rem);
    font-weight: 900;
    letter-spacing: -0.045em;
    line-height: 1;
}
.mobile-demo-copy > div {
    margin-top: 0.5rem;
    color: #52606d;
    font-size: 0.42rem;
    line-height: 1.5;
}
.mobile-demo-cta {
    display: inline-flex;
    margin-top: 0.55rem;
    border-radius: 999px;
    background: var(--preview-primary);
    padding: 0.35rem 0.5rem;
    color: #fff;
    font-size: 0.4rem;
    font-weight: 900;
}
.mobile-demo-contact {
    display: grid;
    gap: 0.25rem;
    margin-top: 0.6rem;
    border-top: 1px solid color-mix(in srgb, var(--preview-primary) 20%, transparent);
    padding-top: 0.55rem;
}
.mobile-demo-contact small {
    display: block;
    color: var(--preview-ink);
    font-size: 0.38rem;
    font-weight: 700;
}
.phone-screen > img {
    display: block;
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
}
.phone-label {
    position: absolute;
    right: 0.8rem;
    bottom: 0.7rem;
    border-radius: 999px;
    background: rgb(255 255 255 / 88%);
    padding: 0.25rem 0.4rem;
    backdrop-filter: blur(0.4rem);
}
@media (max-width: 47.999rem) {
    .device-showcase {
        grid-template-columns: 1fr;
        gap: 1.25rem;
        padding: 0.75rem 0.75rem 1.25rem;
    }
    .desktop-device { width: 100%; border-radius: 0.85rem; }
    .browser-chrome { grid-template-columns: 2.75rem minmax(0, 1fr) 2.75rem; }
    .demo-nav { min-height: 2.6rem; gap: 0.5rem; padding: 0.55rem 0.75rem; }
    .demo-nav strong { font-size: 0.68rem; }
    .demo-nav__links { display: none; }
    .demo-nav__button { padding: 0.4rem 0.55rem; font-size: 0.48rem; }
    .demo-hero { min-height: 13.5rem; grid-template-columns: 0.95fr 1.05fr; }
    .demo-hero__copy { padding: 0.85rem; }
    .demo-hero__copy > p { font-size: 0.42rem; }
    .demo-hero__copy h3 { margin-top: 0.4rem; font-size: clamp(0.9rem, 4.4vw, 1.3rem); }
    .demo-hero__body { margin-top: 0.5rem; font-size: 0.46rem; line-height: 1.45; }
    .demo-hero__actions { margin-top: 0.55rem; }
    .demo-hero__actions > span { padding: 0.4rem 0.5rem; font-size: 0.45rem; }
    .demo-hero__trust { display: none; }
    .demo-hero__media { min-height: 13.5rem; }
    .mobile-device {
        width: min(15rem, 72vw);
        margin: 0 auto;
    }
}
</style>
