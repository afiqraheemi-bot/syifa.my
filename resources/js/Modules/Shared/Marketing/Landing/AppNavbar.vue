<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import AppButton from './AppButton.vue';

const props = defineProps({
    links: { type: Array, required: true }, // [{ id, label }]
    loginUrl: { type: String, required: true },
    registerUrl: { type: String, required: true },
    loginLabel: { type: String, required: true },
    registerLabel: { type: String, required: true },
    lang: { type: String, required: true },
});

const emit = defineEmits(['set-lang']);

const mobileMenuOpen = ref(false);
const scrolled = ref(false);
let ticking = false;

function handleScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
        scrolled.value = window.scrollY > 12;
        ticking = false;
    });
}

onMounted(() => window.addEventListener('scroll', handleScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', handleScroll));
</script>

<template>
    <header
        class="sticky top-0 z-50 transition-all duration-300"
        :class="
            scrolled
                ? 'border-b border-slate-200/70 bg-white/80 shadow-sm shadow-slate-900/5 backdrop-blur-lg'
                : 'border-b border-transparent bg-white/0'
        "
    >
        <nav
            class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10"
            aria-label="Navigasi utama"
        >
            <a
                href="#top"
                class="flex shrink-0 items-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                @click="mobileMenuOpen = false"
            >
                <img :src="'/images/marketing/syifa-logo.webp'" alt="SYIFA.my" class="h-7 w-auto" width="1836" height="857" />
            </a>

            <div class="hidden items-center gap-8 text-sm font-semibold text-slate-600 lg:flex">
                <a
                    v-for="link in props.links"
                    :key="link.id"
                    :href="`#${link.id}`"
                    class="rounded-sm transition hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    {{ link.label }}
                </a>
            </div>

            <div class="flex items-center gap-2.5 sm:gap-3">
                <div
                    class="flex min-h-9 shrink-0 items-center rounded-full border border-slate-200 p-0.5 text-xs font-semibold"
                    role="group"
                    aria-label="Language / Bahasa"
                >
                    <button
                        type="button"
                        :aria-pressed="lang === 'ms'"
                        class="min-h-8 rounded-full px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        :class="
                            lang === 'ms'
                                ? 'bg-emerald-700 text-white'
                                : 'text-slate-600 hover:text-slate-950'
                        "
                        @click="emit('set-lang', 'ms')"
                    >
                        BM
                    </button>
                    <button
                        type="button"
                        :aria-pressed="lang === 'en'"
                        class="min-h-8 rounded-full px-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        :class="
                            lang === 'en'
                                ? 'bg-emerald-700 text-white'
                                : 'text-slate-600 hover:text-slate-950'
                        "
                        @click="emit('set-lang', 'en')"
                    >
                        EN
                    </button>
                </div>

                <div class="hidden lg:block">
                    <AppButton :href="loginUrl" variant="ghost" class="!px-3">
                        {{ loginLabel }}
                    </AppButton>
                </div>
                <div class="hidden lg:block">
                    <AppButton :href="registerUrl" variant="primary">
                        {{ registerLabel }}
                    </AppButton>
                </div>

                <button
                    type="button"
                    class="flex size-11 items-center justify-center rounded-xl text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 lg:hidden"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="mobile-nav-menu"
                    aria-label="Buka menu navigasi"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg
                        v-if="!mobileMenuOpen"
                        viewBox="0 0 24 24"
                        class="size-6 fill-none stroke-current stroke-2"
                    >
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    <svg
                        v-else
                        viewBox="0 0 24 24"
                        class="size-6 fill-none stroke-current stroke-2"
                    >
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
        </nav>

        <div
            v-if="mobileMenuOpen"
            id="mobile-nav-menu"
            class="border-t border-slate-200 bg-white px-4 pt-2 pb-6 sm:px-6 lg:hidden"
        >
            <div class="flex flex-col text-base font-semibold text-slate-800">
                <a
                    v-for="(link, index) in props.links"
                    :key="link.id"
                    :href="`#${link.id}`"
                    class="rounded-sm py-3.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    :class="index < props.links.length - 1 ? 'border-b border-slate-100' : ''"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </a>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <AppButton :href="loginUrl" variant="secondary" class="flex-1">
                    {{ loginLabel }}
                </AppButton>
                <AppButton :href="registerUrl" variant="primary" class="flex-1">
                    {{ registerLabel }}
                </AppButton>
            </div>
        </div>
    </header>
</template>
