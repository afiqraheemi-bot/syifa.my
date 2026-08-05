<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    homeUrl: { type: String, required: true },
});

const mobileMenuOpen = ref(false);
const scrolled = ref(false);
const activeSection = ref('');

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

const services = [
    {
        title: 'Pemeriksaan Kesihatan Am',
        body: 'Konsultasi harian, diagnosis awal, dan rawatan untuk keluarga anda.',
        icon: 'M12 4v16m8-8H4',
        bestFor: 'Semua peringkat umur',
        chip: 'bg-rose-100 text-rose-600',
    },
    {
        title: 'Rawatan Kanak-Kanak',
        body: 'Penjagaan mesra kanak-kanak daripada bayi hingga remaja.',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        bestFor: 'Bayi & kanak-kanak',
        chip: 'bg-amber-100 text-amber-600',
    },
    {
        title: 'Vaksinasi & Imunisasi',
        body: 'Jadual vaksinasi lengkap mengikut garis panduan KKM.',
        icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        bestFor: 'Jadual KKM lengkap',
        chip: 'bg-sky-100 text-sky-600',
    },
    {
        title: 'Pengurusan Penyakit Kronik',
        body: 'Pemantauan berterusan untuk diabetes, darah tinggi, dan kolesterol.',
        icon: 'M3 12h4l3 8 4-16 3 8h4',
        bestFor: 'Diabetes & darah tinggi',
        chip: 'bg-violet-100 text-violet-600',
    },
    {
        title: 'Pemeriksaan Kesihatan Wanita',
        body: 'Saringan kesihatan dan konsultasi khusus untuk wanita.',
        icon: 'M12 21a8 8 0 100-16 8 8 0 000 16zm0-4v-4m0 0V9',
        bestFor: 'Saringan tahunan',
        chip: 'bg-emerald-100 text-emerald-600',
    },
    {
        title: 'Rawatan Kecemasan Ringan',
        body: 'Rawatan segera untuk kecederaan dan keadaan tidak berjadual.',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        bestFor: 'Tanpa temujanji',
        chip: 'bg-blue-100 text-blue-600',
    },
];

const stats = [
    { value: '15+', label: 'Tahun Beroperasi' },
    { value: '20,000+', label: 'Pesakit Dirawat' },
    { value: '4.9/5', label: 'Penilaian Google' },
    { value: '7 Hari', label: 'Seminggu' },
];

const doctors = [
    { name: 'Dr. Amirul Hakim', credential: 'MBBS (Malaya), MRCGP', focus: 'Perubatan Keluarga' },
    {
        name: 'Dr. Siti Nur Aisyah',
        credential: 'MBBS (UKM), MAFP',
        focus: 'Kesihatan Wanita & Kanak-Kanak',
    },
    {
        name: 'Dr. Rajesh Kumar',
        credential: 'MBBS (IMU), MRCP',
        focus: 'Penyakit Kronik & Dalaman',
    },
];

const testimonials = [
    {
        quote: 'Klinik yang sangat mesra dan profesional. Doktor sabar terangkan setiap rawatan, dan waktu menunggu pun tak lama.',
        name: 'Nurul Izzah',
        meta: 'Pesakit sejak 2021',
    },
    {
        quote: 'Bawa anak untuk vaksinasi di sini, staff sangat baik dengan kanak-kanak. Klinik bersih dan teratur.',
        name: 'Wong Mei Ling',
        meta: 'Pesakit sejak 2019',
    },
    {
        quote: 'Dah 10 tahun jadi pesakit tetap. Rekod kesihatan saya sentiasa diambil berat, tak pernah rasa tergesa-gesa.',
        name: 'Kumaresan a/l Muthu',
        meta: 'Pesakit sejak 2016',
    },
];

const hours = [
    { day: 'Isnin – Jumaat', time: '8:00 pagi – 10:00 malam' },
    { day: 'Sabtu', time: '8:00 pagi – 6:00 petang' },
    { day: 'Ahad & Cuti Umum', time: '9:00 pagi – 1:00 tengah hari' },
];

const trustBadges = [
    'Berdaftar Dengan KKM',
    'Panel Insurans Berlesen',
    'Korporat Diterima',
    'Bayaran Tunai / Kad / E-Wallet',
];
</script>

<template>
    <div class="reveal-root bg-[#faf8f4] text-stone-900">
        <div class="bg-stone-900 px-4 py-2 text-center text-xs font-medium text-stone-300">
            Pratonton reka bentuk templat
            <span class="font-bold text-white">Syifa Dental</span>
            oleh SYIFA.my — bukan laman klinik sebenar.
            <a
                :href="homeUrl"
                class="ml-1 font-bold text-blue-400 underline underline-offset-2 hover:text-blue-300"
            >
                Kembali ke SYIFA.my
            </a>
        </div>

        <header
            class="sticky top-0 z-40 border-b border-stone-200/70 bg-[#faf8f4]/85 backdrop-blur-md transition-shadow duration-300"
            :class="scrolled ? 'shadow-sm shadow-stone-950/5' : ''"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-10"
            >
                <a href="#top" class="flex items-baseline gap-1" @click="mobileMenuOpen = false">
                    <span class="text-xl font-bold tracking-tight text-slate-900">Klinik</span>
                    <span class="text-xl font-bold italic tracking-tight text-blue-700"
                        >Mediva</span
                    >
                </a>

                <div class="hidden items-center gap-9 text-sm font-semibold text-stone-600 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="relative py-1 transition hover:text-slate-900"
                        :class="activeSection === link.id ? 'text-slate-900' : ''"
                    >
                        {{ link.label }}
                        <span
                            class="absolute -bottom-1 left-0 h-0.5 rounded-full bg-blue-600 transition-all"
                            :class="activeSection === link.id ? 'w-full' : 'w-0'"
                        />
                    </a>
                </div>

                <div class="flex items-center gap-3 sm:gap-4">
                    <a href="#lokasi" class="hidden text-sm font-bold text-slate-900 sm:block">
                        +60 3-7890 1234
                    </a>
                    <a
                        href="#lokasi"
                        class="hidden min-h-11 items-center rounded-full bg-blue-600 px-5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-600/25 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 lg:inline-flex"
                    >
                        Tempah Janji Temu
                    </a>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full text-slate-900 transition hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 lg:hidden"
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
                class="border-t border-stone-200/70 bg-[#faf8f4] px-4 pt-2 pb-6 sm:px-6 lg:hidden"
            >
                <div class="flex flex-col text-base font-semibold text-stone-700">
                    <a
                        v-for="(link, index) in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="py-3.5"
                        :class="[
                            index < navLinks.length - 1 ? 'border-b border-stone-200/70' : '',
                            activeSection === link.id ? 'text-blue-700' : '',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                </div>

                <div class="mt-4 flex flex-col gap-3">
                    <a
                        href="tel:+60378901234"
                        class="inline-flex min-h-12 items-center justify-center rounded-full border-2 border-stone-300 text-sm font-bold text-stone-800"
                        @click="mobileMenuOpen = false"
                    >
                        +60 3-7890 1234
                    </a>
                    <a
                        href="#lokasi"
                        class="inline-flex min-h-12 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white"
                        @click="mobileMenuOpen = false"
                    >
                        Tempah Janji Temu
                    </a>
                </div>
            </div>
        </header>

        <main id="top">
            <section class="relative overflow-hidden">
                <div
                    class="pointer-events-none absolute -top-24 -right-24 h-[32rem] w-[32rem] rounded-full bg-blue-100/60 blur-3xl"
                />
                <div
                    class="relative mx-auto grid max-w-7xl gap-12 px-6 py-16 sm:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-8 lg:px-10 lg:py-28"
                >
                    <div data-reveal class="reveal">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-1.5 text-xs font-bold tracking-wide text-blue-700"
                        >
                            <svg viewBox="0 0 20 20" class="size-3.5 fill-current">
                                <path
                                    d="M10 1.5l2.7 1.4 3 .3 1 2.8 2 2.2-1 2.8 1 2.8-2 2.2-1 2.8-3 .3-2.7 1.4-2.7-1.4-3-.3-1-2.8-2-2.2 1-2.8-1-2.8 2-2.2 1-2.8 3-.3z"
                                />
                                <path
                                    d="M7.5 10.2l1.8 1.8 3.2-3.6"
                                    stroke="white"
                                    stroke-width="1.4"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            Berdaftar Dengan KKM
                        </span>

                        <h1
                            class="mt-7 text-4xl leading-[1.1] font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl"
                        >
                            Penjagaan mesra untuk
                            <span class="inline-block rounded-lg bg-blue-100 px-2 text-blue-700"
                                >keluarga anda</span
                            >, bila-bila masa.
                        </h1>

                        <p class="mt-6 max-w-lg text-lg leading-8 text-stone-600">
                            Rawatan perubatan menyeluruh dengan sentuhan mesra, untuk setiap
                            peringkat umur dalam keluarga anda — dari bayi hingga datuk nenek.
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-4">
                            <a
                                href="#lokasi"
                                class="inline-flex min-h-13 items-center rounded-full bg-blue-600 px-7 text-base font-bold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-xl hover:shadow-blue-600/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                            >
                                Tempah Janji Temu
                            </a>
                            <a
                                href="tel:+60378901234"
                                class="inline-flex min-h-13 items-center rounded-full border-2 border-stone-200 bg-white px-6 text-base font-bold text-stone-800 transition hover:-translate-y-0.5 hover:border-blue-300"
                            >
                                +60 3-7890 1234
                            </a>
                        </div>

                        <div class="mt-10 flex items-center gap-4">
                            <div class="flex -space-x-3">
                                <span
                                    v-for="initial in ['NA', 'WM', 'KM', 'RS']"
                                    :key="initial"
                                    class="flex size-10 items-center justify-center rounded-full border-2 border-[#faf8f4] bg-blue-600 text-xs font-bold text-white"
                                >
                                    {{ initial }}
                                </span>
                            </div>
                            <div>
                                <div class="flex items-center gap-0.5 text-amber-500">
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
                                <p class="mt-0.5 text-sm font-semibold text-stone-600">
                                    4.9/5 daripada 500+ pesakit
                                </p>
                            </div>
                        </div>
                    </div>

                    <div data-reveal class="reveal relative mx-2 sm:mx-4 lg:mx-0">
                        <div
                            class="flex aspect-[4/5] flex-col items-center justify-center gap-3 rounded-[2rem] border-2 border-dashed border-blue-200 bg-blue-50/60 text-center"
                        >
                            <span
                                class="flex size-14 items-center justify-center rounded-full bg-white text-blue-600 shadow-sm"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-7 fill-none stroke-current stroke-2"
                                >
                                    <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
                                    <circle cx="9" cy="10.5" r="1.5" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 16l4.5-4 3 3 3.5-4.5L20 16"
                                    />
                                </svg>
                            </span>
                            <p class="max-w-[14rem] text-sm font-semibold text-blue-800">
                                Foto klinik atau doktor anda dipaparkan di sini
                            </p>
                        </div>

                        <div
                            class="absolute -bottom-6 -left-2 w-40 rounded-2xl border border-stone-100 bg-white p-4 shadow-xl sm:-bottom-8 sm:-left-6 sm:w-56 sm:p-5"
                        >
                            <p class="text-2xl font-bold text-slate-900 sm:text-3xl">15+</p>
                            <p class="mt-1 text-xs font-semibold text-stone-500 sm:text-sm">
                                Tahun Pengalaman Merawat Keluarga Malaysia
                            </p>
                        </div>
                        <div
                            class="absolute top-4 -right-2 flex items-center gap-2 rounded-full border border-stone-100 bg-white py-1.5 pr-3 pl-1.5 shadow-xl sm:top-8 sm:-right-6 sm:py-2 sm:pr-5 sm:pl-2"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 sm:size-9"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2 sm:size-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </span>
                            <span class="text-xs font-bold text-slate-900 sm:text-sm"
                                >Panel Doktor Berdaftar KKM</span
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-stone-200 bg-white">
                <div class="mx-auto max-w-7xl px-6 py-8 lg:px-10">
                    <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                        <div
                            v-for="badge in trustBadges"
                            :key="badge"
                            class="flex items-center gap-2 text-sm font-semibold text-stone-500"
                        >
                            <span class="size-1.5 shrink-0 rounded-full bg-blue-600" />
                            {{ badge }}
                        </div>
                    </div>
                </div>
            </section>

            <section id="perkhidmatan" class="mx-auto max-w-7xl px-6 py-20 sm:py-28 lg:px-10">
                <div data-reveal class="reveal max-w-2xl">
                    <span
                        class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3.5 py-1 text-xs font-bold tracking-wide text-blue-700"
                    >
                        Perkhidmatan Kami
                    </span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Rawatan menyeluruh untuk setiap peringkat umur
                    </h2>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="(service, index) in services"
                        :key="service.title"
                        data-reveal
                        class="reveal group relative overflow-hidden rounded-3xl border border-stone-200 bg-white p-6 transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-blue-900/5"
                        :style="{ transitionDelay: `${index * 60}ms` }"
                    >
                        <div class="flex items-start justify-between">
                            <span
                                class="flex size-12 items-center justify-center rounded-2xl transition"
                                :class="service.chip"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-6 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        :d="service.icon"
                                    />
                                </svg>
                            </span>
                            <svg
                                viewBox="0 0 24 24"
                                class="size-5 fill-none stroke-stone-300 stroke-2 opacity-0 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 17L17 7M9 7h8v8"
                                />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-slate-900">{{ service.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">{{ service.body }}</p>
                        <div class="mt-5 flex items-center gap-1.5 border-t border-stone-100 pt-4">
                            <span class="text-xs font-semibold text-stone-400">Sesuai untuk:</span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                                :class="service.chip"
                                >{{ service.bestFor }}</span
                            >
                        </div>
                    </article>
                </div>
            </section>

            <section class="bg-blue-950 py-20 text-white sm:py-24">
                <div
                    class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-6 sm:grid-cols-4 sm:gap-10 lg:px-10"
                >
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        data-reveal
                        class="reveal text-center lg:text-left"
                    >
                        <p class="font-serif text-4xl font-bold sm:text-5xl">{{ stat.value }}</p>
                        <p class="mt-2 text-sm font-semibold tracking-wide text-slate-200">
                            {{ stat.label }}
                        </p>
                    </div>
                </div>
            </section>

            <section id="doktor" class="mx-auto max-w-7xl px-6 py-20 sm:py-28 lg:px-10">
                <div data-reveal class="reveal max-w-2xl">
                    <p class="text-sm font-bold tracking-[0.2em] text-blue-700 uppercase">
                        Pasukan Perubatan
                    </p>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Doktor berpengalaman &amp; peramah
                    </h2>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-3">
                    <article
                        v-for="doctor in doctors"
                        :key="doctor.name"
                        data-reveal
                        class="reveal group overflow-hidden rounded-3xl border border-stone-200 bg-white transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-blue-900/5"
                    >
                        <div
                            class="flex aspect-[4/3] items-center justify-center border-b-2 border-dashed border-blue-200 bg-blue-50/60"
                        >
                            <span
                                class="flex size-14 items-center justify-center rounded-full bg-white text-blue-600 shadow-sm transition group-hover:scale-105"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-7 fill-none stroke-current stroke-2"
                                >
                                    <circle cx="12" cy="8.5" r="3.5" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-slate-900">{{ doctor.name }}</h3>
                            <p class="mt-1 text-sm font-semibold text-blue-700">
                                {{ doctor.focus }}
                            </p>
                            <p class="mt-1 text-sm text-stone-500">{{ doctor.credential }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <section id="testimoni" class="bg-white py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-10">
                    <div data-reveal class="reveal max-w-2xl">
                        <p class="text-sm font-bold tracking-[0.2em] text-blue-700 uppercase">
                            Testimoni Pesakit
                        </p>
                        <h2
                            class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl"
                        >
                            Dipercayai keluarga Malaysia
                        </h2>
                    </div>

                    <div class="mt-14 grid gap-6 lg:grid-cols-3">
                        <article
                            v-for="testimonial in testimonials"
                            :key="testimonial.name"
                            data-reveal
                            class="reveal relative overflow-hidden rounded-3xl border border-stone-200 bg-[#faf8f4] p-8 transition hover:border-blue-200 hover:shadow-lg hover:shadow-blue-900/5"
                        >
                            <span
                                class="pointer-events-none absolute -top-3 right-6 font-serif text-8xl text-blue-700/10 select-none"
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
                            <p
                                class="relative mt-5 font-serif text-lg leading-7 text-stone-700 italic"
                            >
                                “{{ testimonial.quote }}”
                            </p>
                            <div class="mt-6 flex items-center gap-3">
                                <span
                                    class="flex size-10 items-center justify-center rounded-full bg-slate-700 text-xs font-bold text-white"
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
                                    <p class="text-sm font-bold text-slate-900">
                                        {{ testimonial.name }}
                                    </p>
                                    <p class="text-xs text-stone-500">{{ testimonial.meta }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="lokasi" class="mx-auto max-w-7xl px-6 py-20 sm:py-28 lg:px-10">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
                    <div data-reveal class="reveal">
                        <p class="text-sm font-bold tracking-[0.2em] text-blue-700 uppercase">
                            Lokasi &amp; Waktu Operasi
                        </p>
                        <h2
                            class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl"
                        >
                            Kunjungi kami hari ini
                        </h2>
                        <p class="mt-5 max-w-md text-stone-600">
                            47, Jalan Bestari 2, Seksyen 9, 46000 Petaling Jaya, Selangor, Malaysia.
                        </p>

                        <dl class="mt-8 space-y-3 border-t border-stone-200 pt-6">
                            <div
                                v-for="slot in hours"
                                :key="slot.day"
                                class="flex items-center justify-between text-sm"
                            >
                                <dt class="font-semibold text-stone-700">{{ slot.day }}</dt>
                                <dd class="font-bold text-slate-900">{{ slot.time }}</dd>
                            </div>
                        </dl>

                        <div class="mt-8 flex flex-wrap gap-4">
                            <a
                                href="https://wa.me/60378901234"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex min-h-12 items-center rounded-full bg-blue-600 px-6 text-sm font-bold text-white shadow-md shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-600/30"
                            >
                                Tempah Melalui WhatsApp
                            </a>
                            <a
                                href="tel:+60378901234"
                                class="inline-flex min-h-12 items-center rounded-full border-2 border-stone-300 px-6 text-sm font-bold text-stone-800 transition hover:-translate-y-0.5 hover:border-slate-900"
                            >
                                +60 3-7890 1234
                            </a>
                        </div>
                    </div>

                    <div
                        data-reveal
                        class="reveal flex aspect-[4/3] items-center justify-center rounded-3xl border-2 border-dashed border-blue-200 bg-blue-50/60 lg:aspect-auto lg:h-full"
                    >
                        <div class="text-center">
                            <span
                                class="mx-auto flex size-14 items-center justify-center rounded-full bg-white text-blue-600 shadow-sm"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-7 fill-none stroke-current stroke-2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 21s-7-6.5-7-11.5A7 7 0 0119 9.5C19 14.5 12 21 12 21z"
                                    />
                                    <circle
                                        cx="12"
                                        cy="9.5"
                                        r="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <p class="mt-4 text-sm font-semibold text-blue-800">
                                Peta lokasi klinik dipaparkan di sini
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 pb-20 lg:px-10">
                <div
                    data-reveal
                    class="reveal overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-900 via-slate-800 to-blue-700 px-8 py-16 text-center text-white sm:px-16"
                >
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Sedia jaga kesihatan keluarga anda?
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-slate-100">
                        Tempah janji temu hari ini dan alami perkhidmatan perubatan yang peramah,
                        telus, dan boleh dipercayai.
                    </p>
                    <a
                        href="#lokasi"
                        class="mt-8 inline-flex min-h-13 items-center rounded-full bg-white px-8 text-base font-bold text-slate-900 shadow-lg shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-blue-50 hover:shadow-xl"
                    >
                        Tempah Janji Temu Sekarang
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-stone-200 bg-white">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span class="flex items-baseline gap-1">
                            <span class="text-lg font-bold text-slate-900">Klinik</span>
                            <span class="text-lg font-bold text-blue-700 italic">Mediva</span>
                        </span>
                        <p class="mt-3 text-sm leading-6 text-stone-500">
                            Klinik keluarga yang mesra dan dipercayai, melayani komuniti Petaling
                            Jaya sejak 2010.
                        </p>
                        <div class="mt-5 flex items-center gap-3">
                            <a
                                href="#"
                                aria-label="Facebook Klinik Mediva"
                                class="flex size-9 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition hover:bg-blue-600 hover:text-white"
                            >
                                <svg viewBox="0 0 24 24" class="size-4 fill-current">
                                    <path
                                        d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.87.24-1.46 1.49-1.46H16.5V4.36C16.24 4.32 15.35 4.25 14.31 4.25c-2.16 0-3.64 1.32-3.64 3.75V10.5H8.5v3h2.17V21h2.83z"
                                    />
                                </svg>
                            </a>
                            <a
                                href="#"
                                aria-label="Instagram Klinik Mediva"
                                class="flex size-9 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition hover:bg-blue-600 hover:text-white"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-none stroke-current stroke-2"
                                >
                                    <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" />
                                    <circle cx="12" cy="12" r="3.6" />
                                    <circle
                                        cx="17.2"
                                        cy="6.8"
                                        r="0.6"
                                        fill="currentColor"
                                        stroke="none"
                                    />
                                </svg>
                            </a>
                            <a
                                href="#"
                                aria-label="TikTok Klinik Mediva"
                                class="flex size-9 items-center justify-center rounded-full bg-stone-100 text-stone-500 transition hover:bg-blue-600 hover:text-white"
                            >
                                <svg viewBox="0 0 24 24" class="size-4 fill-current">
                                    <path
                                        d="M15.5 3h2.4a4.6 4.6 0 004.1 4.05v2.5a7.1 7.1 0 01-4.1-1.3v6.6a5.75 5.75 0 11-5.75-5.75c.2 0 .4.01.6.04v2.6a3.15 3.15 0 103.15 3.15V3z"
                                    />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Pautan Pantas</p>
                        <ul class="mt-4 space-y-2 text-sm text-stone-500">
                            <li v-for="link in navLinks" :key="link.id">
                                <a :href="`#${link.id}`" class="hover:text-slate-900">{{
                                    link.label
                                }}</a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Perkhidmatan</p>
                        <ul class="mt-4 space-y-2 text-sm text-stone-500">
                            <li v-for="service in services.slice(0, 4)" :key="service.title">
                                {{ service.title }}
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Hubungi Kami</p>
                        <ul class="mt-4 space-y-2 text-sm text-stone-500">
                            <li>47, Jalan Bestari 2, Petaling Jaya</li>
                            <li>+60 3-7890 1234</li>
                            <li>hello@klinikmediva.example</li>
                        </ul>
                    </div>
                </div>

                <div
                    class="mt-12 flex flex-col gap-4 border-t border-stone-200 pt-8 text-xs text-stone-400 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>&copy; 2026 Klinik Mediva. Reka bentuk pratonton oleh SYIFA.my.</p>
                    <a :href="homeUrl" class="font-semibold text-blue-700 hover:text-blue-800">
                        Kembali ke SYIFA.my →
                    </a>
                </div>
            </div>
        </footer>

        <a
            href="https://wa.me/60378901234"
            target="_blank"
            rel="noopener"
            aria-label="WhatsApp Klinik Mediva"
            class="fixed right-5 bottom-5 z-50 flex size-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-xl transition hover:bg-blue-700 hover:shadow-2xl"
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
