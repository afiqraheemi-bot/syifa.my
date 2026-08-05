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

const categories = [
    {
        title: 'Pemeriksaan Am',
        body: 'Konsultasi harian, saringan kesihatan, dan rawatan rutin untuk seisi keluarga.',
        tone: 'lime',
    },
    {
        title: 'Rawatan Estetik',
        body: 'Penjagaan kulit, kecantikan, dan rawatan estetik yang selamat dan berlesen.',
        tone: 'white',
    },
    {
        title: 'Pemulihan & Kronik',
        body: 'Pengurusan penyakit kronik dan pemulihan berterusan dengan pemantauan rapi.',
        tone: 'purple',
    },
];

const stats = [
    { value: '15+', label: 'Tahun Beroperasi' },
    { value: '3,000+', label: 'Pesakit Gembira' },
    { value: '24/7', label: 'Sokongan Kecemasan' },
    { value: '1,000+', label: 'Rawatan Setahun' },
];

const specialties = [
    { key: 'umum', label: 'Am' },
    { key: 'kanak', label: 'Pediatrik' },
    { key: 'kardio', label: 'Kardiologi' },
    { key: 'kulit', label: 'Dermatologi' },
    { key: 'gigi', label: 'Pergigian' },
];

const doctors = [
    {
        name: 'Dr. Aina Zulaikha',
        focus: 'Pakar Bedah',
        tone: 'lime',
        photo: 'from-[#a7d9c0] via-[#6fb593] to-[#3a7c5c]',
    },
    {
        name: 'Dr. Farid Iskandar',
        focus: 'Psikiatri',
        tone: 'plain',
        photo: 'from-[#d9c7f0] via-[#b599df] to-[#7a4fb0]',
    },
    {
        name: 'Dr. Sherilyn Voon',
        focus: 'Ginekologi',
        tone: 'plain',
        photo: 'from-[#f5e7a3] via-[#e0c65a] to-[#b6952c]',
    },
    {
        name: 'Dr. Jenna Gomez',
        focus: 'Neurologi',
        tone: 'plain',
        photo: 'from-[#a7c9e8] via-[#6f9bc9] to-[#3a5c7c]',
    },
];

const testimonials = [
    {
        quote: 'Anak saya takut ke klinik dulu, tapi staff di sini sangat sabar dan mesra. Sekarang dia tak takut lagi nak jumpa doktor.',
        name: 'Puteri Ain',
        meta: 'Pesakit sejak 2020',
    },
    {
        quote: 'Suasana klinik ceria dan tak formal sangat, tapi rawatan tetap profesional. Rasa macam jumpa kawan, bukan sekadar pesakit.',
        name: 'Danish Haqim',
        meta: 'Pesakit sejak 2022',
    },
    {
        quote: 'Ibu saya perlukan pemantauan berkala untuk kencing manis. Klinik ni sangat prihatin, sentiasa follow-up.',
        name: 'Grace Anak Simon',
        meta: 'Pesakit sejak 2018',
    },
];

const whyUs = [
    { title: 'Pusat Kesihatan Komuniti', tone: 'photo' },
    {
        title: 'Kesihatan Komuniti',
        body: '24/7 Hospital & rawatan kecemasan untuk detik tidak dijangka.',
        tone: 'lime',
    },
    {
        title: 'Penyelarasan Penjagaan',
        body: 'Sokongan peribadi untuk navigasi perjalanan kesihatan anda.',
        tone: 'purple',
    },
];
</script>

<template>
    <div class="reveal-root bg-[#eef6f0] text-[#122019]">
        <div class="bg-[#0b2a1f] px-4 py-2 text-center text-xs font-medium text-emerald-100">
            Pratonton reka bentuk templat
            <span class="font-bold text-white">Syifa Care</span>
            oleh SYIFA.my — bukan laman klinik sebenar.
            <a
                :href="homeUrl"
                class="ml-1 font-bold text-lime-300 underline underline-offset-2 hover:text-lime-200"
            >
                Kembali ke SYIFA.my
            </a>
        </div>

        <header
            class="sticky top-0 z-40 border-b border-black/5 bg-[#eef6f0]/90 backdrop-blur-md transition-shadow duration-300"
            :class="scrolled ? 'shadow-sm shadow-black/5' : ''"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-10"
            >
                <a href="#top" class="flex items-center gap-2" @click="mobileMenuOpen = false">
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-lime-300 text-[#0b2a1f]"
                    >
                        <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 21s-6.5-4.35-9-8.5C1 8.5 3 5 6.5 5c2 0 3.5 1.2 5.5 3.5C14 6.2 15.5 5 17.5 5 21 5 23 8.5 21 12.5c-2.5 4.15-9 8.5-9 8.5z"
                            />
                        </svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight">Klinik Ceria</span>
                </a>

                <div
                    class="hidden items-center gap-8 text-sm font-semibold text-[#122019]/70 lg:flex"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="relative py-1 transition hover:text-[#122019]"
                        :class="activeSection === link.id ? 'text-[#122019]' : ''"
                    >
                        {{ link.label }}
                        <span
                            class="absolute -bottom-1 left-0 h-0.5 rounded-full bg-lime-400 transition-all"
                            :class="activeSection === link.id ? 'w-full' : 'w-0'"
                        />
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="#lokasi"
                        class="hidden min-h-11 items-center rounded-full bg-[#0b2a1f] px-5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#0f3d2e] lg:inline-flex"
                    >
                        Log Masuk
                    </a>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full transition hover:bg-black/5 lg:hidden"
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
                class="border-t border-black/5 bg-[#eef6f0] px-4 pt-2 pb-6 sm:px-6 lg:hidden"
            >
                <div class="flex flex-col text-base font-semibold text-[#122019]">
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
                    class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-full bg-[#0b2a1f] text-sm font-bold text-white"
                    @click="mobileMenuOpen = false"
                >
                    Log Masuk
                </a>
            </div>
        </header>

        <main id="top">
            <section class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 sm:pt-12 lg:px-10">
                <div class="grid gap-4 lg:grid-cols-[1.55fr_1fr]">
                    <div
                        data-reveal
                        class="reveal relative overflow-hidden rounded-[2rem] bg-white p-8 shadow-sm sm:p-10"
                    >
                        <p class="text-sm font-bold tracking-wide text-[#122019]/60">
                            Klinik Ceria — Rangkaian Komuniti Anda
                        </p>
                        <h1 class="mt-4 max-w-md text-3xl font-bold tracking-tight sm:text-4xl">
                            Penjagaan kesihatan mesra untuk keluarga anda
                        </h1>
                        <p class="mt-4 max-w-sm text-sm leading-6 text-[#122019]/70">
                            Klinik Ceria menyampaikan penjagaan kesihatan berkualiti kepada komuniti
                            yang memerlukannya.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a
                                href="#doktor"
                                class="inline-flex min-h-12 items-center rounded-full bg-[#0b2a1f] px-6 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#0f3d2e]"
                            >
                                Cari Doktor
                            </a>
                            <a
                                href="#perkhidmatan"
                                class="inline-flex min-h-12 items-center rounded-full border-2 border-[#122019]/15 px-6 text-sm font-bold text-[#122019] transition hover:-translate-y-0.5 hover:border-[#122019]/30"
                            >
                                Ketahui lebih
                            </a>
                        </div>

                        <div
                            class="relative mt-8 flex aspect-[16/9] items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-[#8fc9ac] via-[#5fa383] to-[#2f6b52]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(255,255,255,0.28),transparent_55%)]"
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
                    </div>

                    <div data-reveal class="reveal flex flex-col gap-4">
                        <div class="rounded-[2rem] bg-white p-5 shadow-sm">
                            <div
                                class="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-[#a7d9c0] via-[#6fb593] to-[#3a7c5c]"
                            >
                                <div
                                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.3),transparent_55%)]"
                                />
                                <svg
                                    viewBox="0 0 24 24"
                                    class="relative size-14 fill-none stroke-white/55 stroke-[1.25]"
                                >
                                    <circle cx="12" cy="8.5" r="3.5" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5"
                                    />
                                </svg>
                            </div>
                            <p class="mt-4 text-sm font-bold">Dr. Aina Zulaikha</p>
                            <p class="mt-1 text-xs leading-5 text-[#122019]/60">
                                Doktor berlesen dilatih untuk mendiagnosis dan merawat pelbagai
                                penyakit.
                            </p>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-[2rem] bg-[#0b2a1f] p-5 text-white"
                        >
                            <div>
                                <p class="text-xs font-semibold text-emerald-200">
                                    Klinik dibuka hari ini
                                </p>
                                <p class="mt-1 text-lg font-bold">8 pagi – 9 malam</p>
                            </div>
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-lime-300 text-[#0b2a1f]"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-5 fill-none stroke-current stroke-2"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path stroke-linecap="round" d="M12 7v5l3 2" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>

                <div id="perkhidmatan" data-reveal class="reveal mt-4 grid gap-4 sm:grid-cols-3">
                    <article
                        v-for="category in categories"
                        :key="category.title"
                        class="group rounded-[2rem] p-7 transition hover:-translate-y-1"
                        :class="{
                            'bg-lime-300 text-[#0b2a1f]': category.tone === 'lime',
                            'bg-white text-[#0b2a1f]': category.tone === 'white',
                            'bg-[#d9c7f0] text-[#2a1a45]': category.tone === 'purple',
                        }"
                    >
                        <div class="flex items-start justify-between">
                            <h3 class="text-xl font-bold">{{ category.title }}</h3>
                            <svg
                                viewBox="0 0 24 24"
                                class="size-6 shrink-0 fill-none stroke-current stroke-2 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 17L17 7M9 7h8v8"
                                />
                            </svg>
                        </div>
                        <p class="mt-4 text-sm leading-6 opacity-80">{{ category.body }}</p>
                    </article>
                </div>

                <div
                    data-reveal
                    class="reveal mt-4 flex flex-wrap items-center justify-around gap-4 rounded-[2rem] bg-white px-6 py-5 text-sm font-semibold text-[#122019]/70"
                >
                    <div v-for="stat in stats" :key="stat.label" class="flex items-center gap-2">
                        <span class="size-1.5 rounded-full bg-lime-400" />
                        {{ stat.value }} {{ stat.label }}
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10">
                <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                    <div data-reveal class="reveal">
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                            Klinik Ceria, Rangkaian Dipercayai Anda
                        </h2>
                        <p class="mt-4 text-sm leading-6 text-[#122019]/70">
                            Klinik Ceria menghubungkan lebih ramai pesakit dengan penjagaan primer,
                            khusus, dan kecemasan yang komprehensif — dekat dengan tempat tinggal
                            mereka.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center gap-4">
                            <a
                                href="#lokasi"
                                class="inline-flex min-h-12 items-center rounded-full bg-[#0b2a1f] px-6 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#0f3d2e]"
                            >
                                Mulakan
                            </a>
                            <a
                                href="tel:+60378901234"
                                class="text-sm font-bold text-[#0b2a1f] underline underline-offset-4"
                            >
                                +60 3-7890 1234
                            </a>
                        </div>
                    </div>
                    <div
                        data-reveal
                        class="reveal relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-[#c9e8b8] via-[#8fc9ac] to-[#3f7a5e]"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_70%_25%,rgba(255,255,255,0.3),transparent_55%)]"
                        />
                        <svg
                            viewBox="0 0 24 24"
                            class="relative size-16 fill-none stroke-white/55 stroke-[1.25]"
                        >
                            <circle cx="8.5" cy="8.5" r="2.75" />
                            <circle cx="16" cy="9.5" r="2.25" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.5 19c.9-3.3 3.4-5 5-5s3.6 1.4 4.4 3.2M13 19c.7-2.6 2.6-4 4-4s3.1 1.2 3.6 3.1"
                            />
                        </svg>
                        <span
                            class="absolute bottom-4 left-4 rounded-full bg-black/25 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                        >
                            Foto interaksi klinik &amp; pesakit
                        </span>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div
                    data-reveal
                    class="reveal rounded-[2.5rem] bg-[#0b2a1f] p-8 text-white sm:p-12"
                >
                    <p class="text-xs font-bold tracking-[0.2em] text-lime-300 uppercase">
                        Perkhidmatan Kami
                    </p>
                    <h2 class="mt-3 max-w-md text-2xl font-bold tracking-tight sm:text-3xl">
                        Penjagaan Lengkap, Dalam Satu Rangkaian
                    </h2>
                    <p class="mt-3 max-w-lg text-sm leading-6 text-emerald-100/80">
                        Lebih 15 tahun kami menyampaikan penjagaan bernilai tinggi kepada komuniti
                        kami.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div
                            class="relative flex aspect-[4/3] flex-col justify-end overflow-hidden rounded-3xl bg-gradient-to-br from-[#5eead4] via-[#2dd4bf] to-[#0f766e] p-5 sm:aspect-[4/5]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(255,255,255,0.28),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative mb-auto size-10 fill-none stroke-white/60 stroke-[1.25]"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 3v6a3.5 3.5 0 007 0V3M9.5 12.5v2.5a4 4 0 004 4 4 4 0 004-4v-1.5M18 9.5a2 2 0 10-.001-4.001A2 2 0 0018 9.5z"
                                />
                            </svg>
                            <p class="relative font-bold text-white">Penjagaan Primer</p>
                        </div>
                        <div
                            class="flex aspect-[4/3] flex-col sm:aspect-[4/5] justify-between rounded-3xl bg-lime-300 p-5 text-[#0b2a1f]"
                        >
                            <div />
                            <div>
                                <p class="text-lg font-bold">Hospital &amp; Kecemasan</p>
                                <p class="mt-2 text-xs leading-5 opacity-80">
                                    Rawatan hospital &amp; kecemasan 24/7 untuk detik tidak
                                    dijangka.
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex aspect-[4/3] flex-col sm:aspect-[4/5] justify-between rounded-3xl bg-white/10 p-5"
                        >
                            <div />
                            <div>
                                <p class="text-lg font-bold">Penyelarasan Penjagaan</p>
                                <p class="mt-2 text-xs leading-5 text-emerald-100/70">
                                    Sokongan peribadi untuk navigasi perjalanan kesihatan anda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="doktor" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10">
                <div data-reveal class="reveal flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                            Temui Doktor Dipercayai Kami
                        </h2>
                        <p class="mt-2 text-sm text-[#122019]/60">
                            Penjagaan peribadi daripada pakar berpengalaman.
                        </p>
                    </div>
                    <a
                        href="#perkhidmatan"
                        class="inline-flex min-h-11 items-center rounded-full border-2 border-[#122019]/15 px-5 text-sm font-bold text-[#122019]"
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
                        class="min-h-10 rounded-full px-4 text-sm font-semibold transition"
                        :class="
                            activeSpecialty === specialty.key
                                ? 'bg-[#0b2a1f] text-white'
                                : 'bg-white text-[#122019]/60 hover:text-[#122019]'
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
                        class="overflow-hidden rounded-3xl bg-white p-3 shadow-sm transition hover:-translate-y-1"
                        :class="doctor.tone === 'lime' ? 'ring-2 ring-lime-300' : ''"
                    >
                        <div
                            class="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br"
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
                        <p class="px-1 pb-1 text-xs text-[#122019]/55">{{ doctor.focus }}</p>
                    </article>
                </div>
            </section>

            <section id="testimoni" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10">
                <div data-reveal class="reveal max-w-2xl">
                    <p class="text-xs font-bold tracking-[0.2em] text-[#0b2a1f]/50 uppercase">
                        Testimoni Pesakit
                    </p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                        Dipercayai keluarga komuniti kami
                    </h2>
                </div>

                <div class="mt-8 grid gap-4 lg:grid-cols-3">
                    <article
                        v-for="(testimonial, index) in testimonials"
                        :key="testimonial.name"
                        data-reveal
                        class="reveal relative overflow-hidden rounded-[2rem] p-7 transition hover:-translate-y-1"
                        :class="
                            index === 1
                                ? 'bg-lime-300 text-[#0b2a1f]'
                                : 'bg-white text-[#122019] shadow-sm'
                        "
                    >
                        <span
                            class="pointer-events-none absolute -top-2 right-5 font-serif text-7xl select-none"
                            :class="index === 1 ? 'text-[#0b2a1f]/15' : 'text-[#0b2a1f]/10'"
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
                                class="flex size-10 items-center justify-center rounded-full bg-[#0b2a1f] text-xs font-bold text-white"
                                :class="index === 1 ? 'bg-white text-[#0b2a1f]' : ''"
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

            <section
                id="kenapa-kami"
                class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-10"
            >
                <div
                    data-reveal
                    class="reveal rounded-[2.5rem] border-2 border-[#0b2a1f]/10 bg-white p-8 sm:p-10"
                >
                    <p class="text-xs font-bold tracking-[0.2em] text-[#0b2a1f]/50 uppercase">
                        Kenapa Pilih Kami
                    </p>
                    <h2 class="mt-3 max-w-lg text-2xl font-bold tracking-tight sm:text-3xl">
                        Kesihatan komuniti yang boleh dipercayai
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[#122019]/65">
                        Lebih 15 tahun kami menyampaikan penjagaan bernilai tinggi kepada komuniti
                        kami.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div
                            v-for="item in whyUs"
                            :key="item.title"
                            class="relative flex aspect-[4/3] flex-col justify-end overflow-hidden rounded-3xl p-5 sm:aspect-[4/5]"
                            :class="{
                                'bg-gradient-to-br from-[#8fc9ac] via-[#5a9a7a] to-[#2f6b52] text-white':
                                    item.tone === 'photo',
                                'bg-lime-300 text-[#0b2a1f]': item.tone === 'lime',
                                'bg-[#d9c7f0] text-[#2a1a45]': item.tone === 'purple',
                            }"
                        >
                            <div
                                v-if="item.tone === 'photo'"
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(255,255,255,0.25),transparent_55%)]"
                            />
                            <p class="relative font-bold">{{ item.title }}</p>
                            <p v-if="item.body" class="relative mt-2 text-xs leading-5 opacity-80">
                                {{ item.body }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="lokasi" class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div
                    data-reveal
                    class="reveal relative overflow-hidden rounded-[2.5rem] bg-[#d9c7f0] p-8 text-[#2a1a45] sm:p-14"
                >
                    <h2 class="max-w-md text-3xl font-bold tracking-tight sm:text-4xl">
                        Mulakan penjagaan kesihatan anda hari ini
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-6 opacity-80">
                        Tempah janji temu dan alami perkhidmatan perubatan yang mesra dan boleh
                        dipercayai.
                    </p>
                    <a
                        href="tel:+60378901234"
                        class="mt-7 inline-flex min-h-12 items-center rounded-full bg-[#0b2a1f] px-7 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#0f3d2e]"
                    >
                        Cari klinik
                    </a>
                </div>
            </section>
        </main>

        <footer class="bg-[#0b2a1f] text-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-10">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span class="flex items-center gap-2">
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-lime-300 text-[#0b2a1f]"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 21s-6.5-4.35-9-8.5C1 8.5 3 5 6.5 5c2 0 3.5 1.2 5.5 3.5C14 6.2 15.5 5 17.5 5 21 5 23 8.5 21 12.5c-2.5 4.15-9 8.5-9 8.5z"
                                    />
                                </svg>
                            </span>
                            <span class="text-lg font-bold">Klinik Ceria</span>
                        </span>
                        <p class="mt-3 text-sm leading-6 text-emerald-100/70">
                            Cari doktor mengikut keperluan anda. Kami bantu tempah janji temu dan
                            uruskan penjagaan anda.
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-lime-300 uppercase">Pautan Pantas</p>
                        <ul class="mt-4 space-y-2 text-sm text-emerald-100/70">
                            <li v-for="link in navLinks" :key="link.id">
                                <a :href="`#${link.id}`" class="hover:text-white">{{
                                    link.label
                                }}</a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-lime-300 uppercase">Hubungi Kami</p>
                        <ul class="mt-4 space-y-2 text-sm text-emerald-100/70">
                            <li>47, Jalan Bestari 2, Petaling Jaya</li>
                            <li>+60 3-7890 1234</li>
                            <li>hello@klinikceria.example</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-lime-300 uppercase">Surat Berita</p>
                        <p class="mt-4 text-sm text-emerald-100/70">
                            Langgan untuk kemas kini kesihatan komuniti.
                        </p>
                        <div class="mt-3 flex overflow-hidden rounded-full bg-white/10 p-1">
                            <input
                                type="email"
                                placeholder="Emel anda"
                                disabled
                                class="min-w-0 flex-1 bg-transparent px-3 text-sm text-white placeholder-emerald-100/50 outline-none"
                            />
                            <span
                                class="flex shrink-0 items-center rounded-full bg-white px-4 text-xs font-bold text-[#0b2a1f]"
                            >
                                Langgan
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-10 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs text-emerald-100/60 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>&copy; 2026 Klinik Ceria. Reka bentuk pratonton oleh SYIFA.my.</p>
                    <a :href="homeUrl" class="font-semibold text-lime-300 hover:text-lime-200">
                        Kembali ke SYIFA.my →
                    </a>
                </div>
            </div>
        </footer>

        <a
            href="https://wa.me/60378901234"
            target="_blank"
            rel="noopener"
            aria-label="WhatsApp Klinik Ceria"
            class="fixed right-5 bottom-5 z-50 flex size-14 items-center justify-center rounded-full bg-[#0b2a1f] text-white shadow-xl transition hover:bg-[#0f3d2e] hover:shadow-2xl"
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
