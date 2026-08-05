<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    homeUrl: { type: String, required: true },
});

const mobileMenuOpen = ref(false);
const scrolled = ref(false);
const activeSection = ref('');
const activeSpecialty = ref('umum');

const navLinks = [
    { id: 'perkhidmatan', label: 'Perkhidmatan' },
    { id: 'doktor', label: 'Doktor Kami' },
    { id: 'testimoni', label: 'Testimoni' },
    { id: 'lokasi', label: 'Lokasi' },
];
const sectionIds = navLinks.map((link) => link.id);

let observer = null;
let sectionObserver = null;
let ticking = false;

function handleAnchorClick(event) {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;

    const id = link.getAttribute('href')?.slice(1);
    const destination = id ? document.getElementById(id) : null;
    if (destination) {
        event.preventDefault();
        destination.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function handleScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
        scrolled.value = window.scrollY > 12;
        ticking = false;
    });
}

onMounted(() => {
    const targets = document.querySelectorAll('[data-reveal]');
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );
    targets.forEach((target) => observer.observe(target));

    sectionObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            }
        },
        { rootMargin: '-45% 0px -45% 0px' },
    );
    sectionIds.forEach((id) => {
        const section = document.getElementById(id);
        if (section) sectionObserver.observe(section);
    });

    document.addEventListener('click', handleAnchorClick);
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    observer?.disconnect();
    sectionObserver?.disconnect();
    document.removeEventListener('click', handleAnchorClick);
    window.removeEventListener('scroll', handleScroll);
});

const heroStats = [
    { value: '200+', label: 'Doktor Pakar' },
    { value: '400+', label: 'Pesakit Pulih' },
    { value: '98%', label: 'Kadar Kepuasan' },
];

const featureList = [
    {
        title: 'Penjagaan Pakar',
        body: 'Konsultasi khusus daripada doktor pakar bertauliah dalam pelbagai bidang.',
    },
    {
        title: 'Teknologi Termaju',
        body: 'Peralatan diagnostik terkini untuk ketepatan rawatan yang lebih tinggi.',
    },
    {
        title: 'Pelan Berpatutan',
        body: 'Pelbagai pelan rawatan yang telus, tanpa kos tersembunyi.',
    },
    {
        title: 'Kaunseling Pakar',
        body: 'Sesi kaunseling peribadi untuk merancang perjalanan rawatan anda.',
    },
];

const stats = [
    { value: '18+', label: 'Tahun Pengalaman' },
    { value: '12', label: 'Bidang Kepakaran' },
    { value: '24/7', label: 'Kaunter Kecemasan' },
    { value: '4,800+', label: 'Ulasan Pesakit' },
];

const specialties = [
    { key: 'umum', label: 'Am' },
    { key: 'jantung', label: 'Kardiologi' },
    { key: 'saraf', label: 'Neurologi' },
    { key: 'ortopedik', label: 'Ortopedik' },
    { key: 'onkologi', label: 'Onkologi' },
];

const doctors = [
    {
        name: 'Dr. Sharmin Sultana',
        focus: 'Pakar Kardiologi',
        photo: 'from-[#7fb3c9] via-[#4a89a3] to-[#1d4e63]',
    },
    {
        name: 'Dr. Sk Mushawir Alam',
        focus: 'Pakar Neurologi',
        photo: 'from-[#a7b8cc] via-[#6f85a3] to-[#3a4d6b]',
    },
    {
        name: 'Dr. Alia Mahmood',
        focus: 'Pakar Ortopedik',
        photo: 'from-[#c9b6d9] via-[#9a79b8] to-[#5e3f7c]',
    },
    {
        name: 'Dr. Mah Mohammad',
        focus: 'Pakar Onkologi',
        photo: 'from-[#b8c9a7] via-[#7fa35f] to-[#3f5c2f]',
    },
];

const testimonials = [
    {
        quote: 'Saya dahulu takut untuk berjumpa pakar, tapi klinik ini mengubah perspektif saya sepenuhnya. Penjagaan dan perhatian yang saya terima jauh melebihi jangkaan.',
        name: 'Mark H.',
        meta: 'Pesakit Kardiologi',
    },
    {
        quote: 'Pengalaman rawatan pakar terbaik yang pernah saya alami. Cepat, tiada kesakitan, dan hasilnya sangat memberangsangkan. Saya kini lebih yakin dengan kesihatan saya.',
        name: 'Rehana T.',
        meta: 'Pesakit Ortopedik',
    },
    {
        quote: 'Doktor menerangkan setiap langkah rawatan dengan jelas. Saya rasa dihormati sebagai pesakit, bukan sekadar nombor giliran.',
        name: 'Aidil Zafran',
        meta: 'Pesakit Neurologi',
    },
];

const gallery = [
    'from-[#8fc9d9] via-[#5a9ab3] to-[#2d5c6b]',
    'from-[#a7b8cc] via-[#6f85a3] to-[#3a4d6b]',
    'from-[#c9b6d9] via-[#9a79b8] to-[#5e3f7c]',
    'from-[#b8c9a7] via-[#7fa35f] to-[#3f5c2f]',
];
</script>

<template>
    <div class="reveal-root bg-[#f0f3f6] text-[#182735]">
        <div class="bg-[#111e2a] px-4 py-2 text-center text-xs font-medium text-slate-200">
            Pratonton reka bentuk templat
            <span class="font-bold text-white">Syifa Specialist</span>
            oleh SYIFA.my — bukan laman klinik sebenar.
            <a
                :href="homeUrl"
                class="ml-1 font-bold text-amber-300 underline underline-offset-2 hover:text-amber-200"
            >
                Kembali ke SYIFA.my
            </a>
        </div>

        <header
            class="sticky top-0 z-40 border-b border-black/5 bg-[#f0f3f6]/90 backdrop-blur-md transition-shadow duration-300"
            :class="scrolled ? 'shadow-sm shadow-black/5' : ''"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-10"
            >
                <a href="#top" class="flex items-center gap-2" @click="mobileMenuOpen = false">
                    <span
                        class="flex size-9 items-center justify-center rounded-md bg-[#1d2c3b] text-white"
                    >
                        <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3l8 4v5c0 4.5-3.4 8.4-8 9.5-4.6-1.1-8-5-8-9.5V7l8-4z"
                            />
                            <path stroke-linecap="round" d="M9.5 12h5M12 9.5v5" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight">Klinik Pakar Utama</span>
                </a>

                <div
                    class="hidden items-center gap-8 text-sm font-semibold text-[#182735]/70 lg:flex"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="relative py-1 transition hover:text-[#182735]"
                        :class="activeSection === link.id ? 'text-[#182735]' : ''"
                    >
                        {{ link.label }}
                        <span
                            class="absolute -bottom-1 left-0 h-0.5 rounded-full bg-[#1d2c3b] transition-all"
                            :class="activeSection === link.id ? 'w-full' : 'w-0'"
                        />
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="#lokasi"
                        class="hidden min-h-11 items-center rounded-md bg-[#1d2c3b] px-5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#16222e] lg:inline-flex"
                    >
                        Tempah Janji Temu
                    </a>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-md transition hover:bg-black/5 lg:hidden"
                        :aria-expanded="mobileMenuOpen"
                        aria-controls="mobile-menu"
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
                id="mobile-menu"
                class="border-t border-black/5 bg-[#f0f3f6] px-4 pt-2 pb-6 sm:px-6 lg:hidden"
            >
                <div class="flex flex-col text-base font-semibold text-[#182735]">
                    <a
                        v-for="(link, index) in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="py-3.5"
                        :class="index < navLinks.length - 1 ? 'border-b border-black/5' : ''"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                </div>
                <a
                    href="#lokasi"
                    class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-md bg-[#1d2c3b] text-sm font-bold text-white"
                    @click="mobileMenuOpen = false"
                >
                    Tempah Janji Temu
                </a>
            </div>
        </header>

        <main id="top">
            <section class="mx-auto max-w-7xl px-4 pt-10 sm:px-6 sm:pt-16 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                    <div data-reveal class="reveal">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#bdc9d3] bg-white px-3 py-1 text-xs font-bold text-[#182735]"
                        >
                            <svg viewBox="0 0 20 20" class="size-3.5 fill-amber-500">
                                <path
                                    d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9-4.3-4.2 5.9-.8L10 1.5z"
                                />
                            </svg>
                            5.0 (4,824 Ulasan)
                        </span>
                        <h1 class="mt-5 max-w-xl text-3xl font-bold tracking-tight sm:text-5xl">
                            Penjagaan Pakar Yang Anda Boleh Percaya, Di Setiap Langkah
                        </h1>
                        <p class="mt-5 max-w-md text-sm leading-6 text-[#405365]">
                            Kesihatan anda layak mendapat yang terbaik. Alami penyelesaian perubatan
                            pakar dengan sentuhan mesra, disesuaikan dengan keperluan anda.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a
                                href="#lokasi"
                                class="inline-flex min-h-12 items-center rounded-md bg-[#1d2c3b] px-6 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#16222e]"
                            >
                                Tempah Janji Temu
                            </a>
                            <a
                                href="#perkhidmatan"
                                class="inline-flex min-h-12 items-center rounded-md border-2 border-[#182735]/15 px-6 text-sm font-bold text-[#182735] transition hover:-translate-y-0.5 hover:border-[#182735]/30"
                            >
                                Mula Sekarang
                            </a>
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-2">
                            <div
                                class="flex items-center gap-3 rounded-2xl bg-amber-50 p-4 text-amber-900"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/70"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="size-4.5 fill-none stroke-current stroke-2"
                                    >
                                        <circle cx="12" cy="12" r="9" />
                                        <path stroke-linecap="round" d="M12 7v5l3 2" />
                                    </svg>
                                </span>
                                <span class="text-sm font-bold">24/7 Perkhidmatan</span>
                            </div>
                            <div
                                class="flex items-center gap-3 rounded-2xl bg-indigo-50 p-4 text-indigo-900"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/70"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="size-4.5 fill-none stroke-current stroke-2"
                                    >
                                        <circle cx="11" cy="11" r="7" />
                                        <path stroke-linecap="round" d="M20 20l-3.5-3.5" />
                                    </svg>
                                </span>
                                <span class="text-sm font-bold">Cari Doktor Terbaik</span>
                            </div>
                        </div>
                    </div>

                    <div data-reveal class="reveal relative">
                        <div
                            class="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#7fb3c9] via-[#4a89a3] to-[#1d4e63] shadow-lg sm:aspect-[5/4]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_28%_22%,rgba(255,255,255,0.28),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative size-20 fill-none stroke-white/55 stroke-[1.25]"
                            >
                                <circle cx="12" cy="8.5" r="3.5" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.5 20c1.2-4.2 4.8-6.5 7.5-6.5s6.3 2.3 7.5 6.5"
                                />
                            </svg>
                            <span
                                class="absolute bottom-4 left-4 rounded-full bg-black/25 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                            >
                                Foto klinik/doktor anda di sini
                            </span>
                        </div>

                        <div
                            class="absolute -bottom-5 left-3 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-lg sm:left-6"
                        >
                            <span class="text-lg font-bold text-[#1d2c3b]">{{
                                heroStats[0].value
                            }}</span>
                            <span class="text-xs leading-4 font-semibold text-[#607181]">{{
                                heroStats[0].label
                            }}</span>
                        </div>
                        <div
                            class="absolute top-6 -right-3 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-lg sm:-right-6"
                        >
                            <span class="text-lg font-bold text-[#1d2c3b]">{{
                                heroStats[1].value
                            }}</span>
                            <span class="text-xs leading-4 font-semibold text-[#607181]">{{
                                heroStats[1].label
                            }}</span>
                        </div>
                        <div
                            class="absolute right-6 -bottom-5 flex items-center gap-3 rounded-2xl bg-[#1d2c3b] px-4 py-3 text-white shadow-lg"
                        >
                            <span class="text-lg font-bold">{{ heroStats[2].value }}</span>
                            <span class="text-xs leading-4 font-semibold text-slate-300">{{
                                heroStats[2].label
                            }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="perkhidmatan"
                class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-10"
            >
                <div class="grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:items-start">
                    <div data-reveal class="reveal">
                        <p class="text-xs font-bold tracking-[0.2em] text-[#1d2c3b]/50 uppercase">
                            Ciri &amp; Perkhidmatan
                        </p>
                        <h2 class="mt-3 max-w-md text-2xl font-bold tracking-tight sm:text-3xl">
                            Segala yang anda perlukan, dalam satu pusat pakar
                        </h2>
                        <ul class="mt-6 divide-y divide-[#bdc9d3]/60 border-y border-[#bdc9d3]/60">
                            <li
                                v-for="item in featureList"
                                :key="item.title"
                                class="group flex items-center justify-between gap-4 py-4"
                            >
                                <div>
                                    <p class="font-bold">{{ item.title }}</p>
                                    <p class="mt-1 text-sm leading-6 text-[#607181]">
                                        {{ item.body }}
                                    </p>
                                </div>
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5 shrink-0 fill-none stroke-current stroke-2 text-[#1d2c3b]/40 transition group-hover:translate-x-0.5 group-hover:text-[#1d2c3b]"
                                >
                                    <path stroke-linecap="round" d="M9 6l6 6-6 6" />
                                </svg>
                            </li>
                        </ul>
                    </div>

                    <div
                        data-reveal
                        class="reveal relative overflow-hidden rounded-[1.5rem] bg-[#1d2c3b] p-6 text-white shadow-lg sm:p-8"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold tracking-[0.2em] text-slate-300 uppercase">
                                Konsultasi Dalam Talian
                            </p>
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-white/10 text-slate-200"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </span>
                        </div>

                        <div
                            class="relative mt-4 flex aspect-video items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-[#3a5468] via-[#243b4d] to-[#111e2a]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,rgba(255,255,255,0.18),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative size-12 fill-none stroke-white/50 stroke-[1.25]"
                            >
                                <rect x="3" y="6" width="13" height="12" rx="2" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16 10l5-3v10l-5-3"
                                />
                            </svg>
                            <span
                                class="absolute top-3 left-3 flex items-center gap-1.5 rounded-full bg-black/35 px-2.5 py-1 text-[10px] font-bold text-white"
                            >
                                <span class="size-1.5 animate-pulse rounded-full bg-red-400" />
                                11:15 MIN
                            </span>
                        </div>

                        <h3 class="mt-5 text-lg font-bold">
                            Laluan Pintar Anda Ke Telehealth Mesra Pesakit
                        </h3>

                        <ul class="mt-4 space-y-3 text-sm font-semibold text-slate-200">
                            <li class="flex items-center justify-between">
                                Selamat &amp; Terlindung
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path stroke-linecap="round" d="M7 17L17 7M9 7h8v8" />
                                </svg>
                            </li>
                            <li class="flex items-center justify-between">
                                Perkhidmatan 24/7
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path stroke-linecap="round" d="M7 17L17 7M9 7h8v8" />
                                </svg>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    data-reveal
                    class="reveal mt-10 flex flex-wrap items-center justify-around gap-4 rounded-2xl bg-white px-6 py-5 text-sm font-semibold text-[#405365] shadow-sm"
                >
                    <div v-for="stat in stats" :key="stat.label" class="flex items-center gap-2">
                        <span class="size-1.5 rounded-full bg-[#1d2c3b]" />
                        {{ stat.value }} {{ stat.label }}
                    </div>
                </div>
            </section>

            <section id="doktor" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10">
                <div data-reveal class="reveal flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-[#1d2c3b]/50 uppercase">
                            Doktor Pakar Kami
                        </p>
                        <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                            Temui Pasukan Doktor Pakar Kami
                        </h2>
                    </div>
                    <a
                        href="#perkhidmatan"
                        class="inline-flex min-h-11 items-center rounded-md border-2 border-[#182735]/15 px-5 text-sm font-bold text-[#182735]"
                    >
                        Lihat Semua Perkhidmatan
                    </a>
                </div>

                <div
                    data-reveal
                    class="reveal mt-6 flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Tapis mengikut kepakaran"
                >
                    <button
                        v-for="specialty in specialties"
                        :key="specialty.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeSpecialty === specialty.key"
                        class="min-h-10 rounded-md px-4 text-sm font-semibold transition"
                        :class="
                            activeSpecialty === specialty.key
                                ? 'bg-[#1d2c3b] text-white'
                                : 'bg-white text-[#405365] hover:text-[#182735]'
                        "
                        @click="activeSpecialty = specialty.key"
                    >
                        {{ specialty.label }}
                    </button>
                </div>

                <div data-reveal class="reveal mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article
                        v-for="doctor in doctors"
                        :key="doctor.name"
                        class="overflow-hidden rounded-2xl bg-white p-3 shadow-sm transition hover:-translate-y-1"
                    >
                        <div
                            class="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br"
                            :class="doctor.photo"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.3),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative size-11 fill-none stroke-white/55 stroke-[1.25]"
                            >
                                <circle cx="12" cy="8.5" r="3.5" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5"
                                />
                            </svg>
                        </div>
                        <p class="mt-3 px-1 text-sm font-bold">{{ doctor.name }}</p>
                        <p class="px-1 pb-1 text-xs text-[#607181]">{{ doctor.focus }}</p>
                    </article>
                </div>
            </section>

            <section id="testimoni" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10">
                <div data-reveal class="reveal max-w-2xl">
                    <p class="text-xs font-bold tracking-[0.2em] text-[#1d2c3b]/50 uppercase">
                        Testimoni Pesakit
                    </p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                        Pengalaman sebenar daripada pesakit kami
                    </h2>
                </div>

                <div class="mt-8 grid gap-4 lg:grid-cols-3">
                    <article
                        v-for="(testimonial, index) in testimonials"
                        :key="testimonial.name"
                        data-reveal
                        class="reveal relative overflow-hidden rounded-2xl p-7 shadow-sm transition hover:-translate-y-1"
                        :class="index === 1 ? 'bg-[#1d2c3b] text-white' : 'bg-white text-[#182735]'"
                    >
                        <span
                            class="pointer-events-none absolute -top-2 right-5 font-serif text-7xl select-none"
                            :class="index === 1 ? 'text-white/10' : 'text-[#1d2c3b]/10'"
                            aria-hidden="true"
                            >”</span
                        >
                        <div class="relative flex items-center gap-0.5 text-amber-500">
                            <svg
                                v-for="star in 5"
                                :key="star"
                                viewBox="0 0 20 20"
                                class="size-4 fill-current"
                            >
                                <path
                                    d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9-4.3-4.2 5.9-.8L10 1.5z"
                                />
                            </svg>
                        </div>
                        <p class="relative mt-4 text-sm leading-6 italic opacity-90">
                            “{{ testimonial.quote }}”
                        </p>
                        <div class="relative mt-6 flex items-center gap-3">
                            <span
                                class="flex size-10 items-center justify-center rounded-full bg-[#1d2c3b] text-xs font-bold text-white"
                                :class="index === 1 ? 'bg-white text-[#1d2c3b]' : ''"
                            >
                                {{
                                    testimonial.name
                                        .split(' ')
                                        .map((part) => part[0])
                                        .slice(0, 2)
                                        .join('')
                                }}
                            </span>
                            <div>
                                <p class="text-sm font-bold">{{ testimonial.name }}</p>
                                <p class="text-xs opacity-60">{{ testimonial.meta }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div data-reveal class="reveal">
                    <p class="text-xs font-bold tracking-[0.2em] text-[#1d2c3b]/50 uppercase">
                        Komited Kepada Kesihatan Anda
                    </p>
                    <h2 class="mt-3 max-w-md text-2xl font-bold tracking-tight sm:text-3xl">
                        Persekitaran klinik yang tenang dan profesional
                    </h2>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        v-for="(tone, index) in gallery"
                        :key="index"
                        class="relative aspect-square overflow-hidden rounded-xl bg-gradient-to-br"
                        :class="tone"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.25),transparent_55%)]"
                        />
                    </div>
                </div>
            </section>

            <section id="lokasi" class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div
                    data-reveal
                    class="reveal relative overflow-hidden rounded-[1.5rem] bg-[#1d2c3b] p-8 text-white sm:p-14"
                >
                    <h2 class="max-w-md text-3xl font-bold tracking-tight sm:text-4xl">
                        Mulakan penjagaan pakar anda hari ini
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-300">
                        Tempah janji temu dengan doktor pakar dan alami rawatan yang tepat serta
                        boleh dipercayai.
                    </p>
                    <a
                        href="tel:+60321345678"
                        class="mt-7 inline-flex min-h-12 items-center rounded-md bg-white px-7 text-sm font-bold text-[#1d2c3b] transition hover:-translate-y-0.5 hover:bg-slate-100"
                    >
                        Cari Klinik
                    </a>
                </div>
            </section>
        </main>

        <footer class="bg-[#111e2a] text-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-10">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span class="flex items-center gap-2">
                            <span
                                class="flex size-8 items-center justify-center rounded-md bg-white/10 text-white"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3l8 4v5c0 4.5-3.4 8.4-8 9.5-4.6-1.1-8-5-8-9.5V7l8-4z"
                                    />
                                </svg>
                            </span>
                            <span class="text-lg font-bold">Klinik Pakar Utama</span>
                        </span>
                        <p class="mt-3 text-sm leading-6 text-slate-300">
                            Cari doktor pakar mengikut keperluan anda. Kami bantu tempah janji temu
                            dan uruskan penjagaan anda.
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-amber-300 uppercase">Pautan Pantas</p>
                        <ul class="mt-4 space-y-2 text-sm text-slate-300">
                            <li v-for="link in navLinks" :key="link.id">
                                <a :href="`#${link.id}`" class="hover:text-white">{{
                                    link.label
                                }}</a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-amber-300 uppercase">Hubungi Kami</p>
                        <ul class="mt-4 space-y-2 text-sm text-slate-300">
                            <li>21, Jalan Sultan Ismail, Kuala Lumpur</li>
                            <li>+60 3-2134 5678</li>
                            <li>hello@klinikpakarutama.example</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-amber-300 uppercase">Surat Berita</p>
                        <p class="mt-4 text-sm text-slate-300">
                            Langgan untuk kemas kini kesihatan pakar.
                        </p>
                        <div class="mt-3 flex overflow-hidden rounded-md bg-white/10 p-1">
                            <input
                                type="email"
                                placeholder="Emel anda"
                                disabled
                                class="min-w-0 flex-1 bg-transparent px-3 text-sm text-white placeholder-slate-400 outline-none"
                            />
                            <span
                                class="flex shrink-0 items-center rounded-md bg-white px-4 text-xs font-bold text-[#1d2c3b]"
                            >
                                Langgan
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-10 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>&copy; 2026 Klinik Pakar Utama. Reka bentuk pratonton oleh SYIFA.my.</p>
                    <a :href="homeUrl" class="font-semibold text-amber-300 hover:text-amber-200">
                        Kembali ke SYIFA.my →
                    </a>
                </div>
            </div>
        </footer>

        <a
            href="https://wa.me/60321345678"
            target="_blank"
            rel="noopener"
            aria-label="WhatsApp Klinik Pakar Utama"
            class="fixed right-5 bottom-5 z-50 flex size-14 items-center justify-center rounded-full bg-[#1d2c3b] text-white shadow-xl transition hover:bg-[#16222e] hover:shadow-2xl"
        >
            <svg viewBox="0 0 24 24" class="size-7 fill-current">
                <path
                    d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.77.46 3.44 1.28 4.89L2 22l5.35-1.38a9.87 9.87 0 004.7 1.2h.01c5.46 0 9.9-4.45 9.9-9.9C21.96 6.44 17.5 2 12.04 2zm5.8 14.06c-.24.68-1.4 1.3-1.94 1.38-.5.08-1.12.11-1.8-.11-.42-.13-.95-.31-1.64-.6-2.9-1.25-4.79-4.16-4.94-4.35-.14-.2-1.18-1.57-1.18-3 0-1.42.75-2.12 1.01-2.41.27-.28.58-.35.78-.35.2 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2 .9 2.15.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.18-.31.4-.44.53-.15.15-.3.31-.13.6.17.3.76 1.25 1.62 2.03 1.12 1 2.05 1.31 2.35 1.46.3.15.48.13.65-.07.18-.2.75-.87.95-1.17.2-.3.4-.25.66-.15.27.1 1.7.8 2 .95.28.15.47.22.54.34.07.13.07.75-.17 1.44z"
                />
            </svg>
        </a>
    </div>
</template>

<style scoped>
.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition:
        opacity 0.6s ease,
        transform 0.6s ease;
}

.reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

@media (prefers-reduced-motion: reduce) {
    .reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
