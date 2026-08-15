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
        title: 'Penjagaan Kulit Wajah',
        body: 'Rawatan fasial disesuaikan untuk tekstur dan keperluan kulit anda.',
    },
    {
        title: 'Rawatan Anti-Penuaan',
        body: 'Penyelesaian halus untuk kulit yang lebih segar dan bercahaya.',
    },
    {
        title: 'Konturan Badan',
        body: 'Prosedur tanpa pembedahan untuk bentuk badan yang anda idamkan.',
    },
    {
        title: 'Konsultasi Estetik',
        body: 'Sesi peribadi bersama pakar untuk merancang pelan rawatan anda.',
    },
];

const doctors = [
    {
        name: 'Dr. Elina Rosman',
        focus: 'Pakar Dermatologi Estetik',
        photo: 'from-[#c9b6a3] via-[#a8846b] to-[#6b4a38]',
    },
    {
        name: 'Dr. Farhan Adly',
        focus: 'Pakar Perubatan Estetik',
        photo: 'from-[#d9c7b6] via-[#b89a7a] to-[#7a5a3f]',
    },
    {
        name: 'Dr. Nur Sabrina',
        focus: 'Pakar Konturan Badan',
        photo: 'from-[#cbb8a8] via-[#a3806a] to-[#654634]',
    },
];

const testimonials = [
    {
        quote: 'Sesi konsultasi sangat terperinci dan tiada tekanan untuk membuat keputusan segera. Saya rasa didengari sepanjang proses rawatan.',
        name: 'Nadia Iman',
    },
    {
        quote: 'Hasil yang halus dan semula jadi — tepat seperti yang saya mahukan. Suasana klinik juga sangat menenangkan.',
        name: 'Reza Fahmi',
    },
];

const gallery = [
    'from-[#d9c7b6] via-[#b89a7a] to-[#7a5a3f]',
    'from-[#cbb8a8] via-[#a3806a] to-[#654634]',
    'from-[#c9b6a3] via-[#a8846b] to-[#6b4a38]',
    'from-[#e0d3c8] via-[#c2a98f] to-[#8a6a4f]',
    'from-[#d9c7b6] via-[#9d7f63] to-[#5e4530]',
    'from-[#cbb8a8] via-[#ac8b6d] to-[#71543e]',
];
</script>

<template>
    <div class="reveal-root bg-[#fdfbf8] font-serif text-[#2d2825]">
        <div class="bg-[#211c19] px-4 py-2 text-center text-xs font-medium text-[#ead9ce]">
            Pratonton reka bentuk templat
            <span class="font-bold text-white">Syifa Aesthetic</span>
            oleh SYIFA.my — bukan laman klinik sebenar.
            <a
                :href="homeUrl"
                class="ml-1 font-bold text-[#c9a17e] underline underline-offset-2 hover:text-[#dcb896]"
            >
                Kembali ke SYIFA.my
            </a>
        </div>

        <header
            class="sticky top-0 z-40 border-b border-[#2d2825]/10 bg-[#fdfbf8]/90 backdrop-blur-md transition-shadow duration-300"
            :class="scrolled ? 'shadow-sm shadow-black/5' : ''"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-10"
            >
                <a
                    href="#top"
                    class="text-lg font-bold tracking-tight"
                    @click="mobileMenuOpen = false"
                >
                    Klinik Estetika Aura
                </a>

                <div
                    class="hidden items-center gap-9 text-sm font-semibold tracking-wide text-[#2d2825]/70 lg:flex"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="relative py-1 transition hover:text-[#2d2825]"
                        :class="activeSection === link.id ? 'text-[#2d2825]' : ''"
                    >
                        {{ link.label }}
                        <span
                            class="absolute -bottom-1 left-0 h-px bg-[#a8765d] transition-all"
                            :class="activeSection === link.id ? 'w-full' : 'w-0'"
                        />
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="#lokasi"
                        class="hidden min-h-11 items-center rounded-sm bg-[#302824] px-5 text-sm font-bold text-white transition hover:bg-[#241e1b] lg:inline-flex"
                    >
                        Tempah Temujanji
                    </a>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-sm transition hover:bg-black/5 lg:hidden"
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
                class="border-t border-[#2d2825]/10 bg-[#fdfbf8] px-4 pt-2 pb-6 sm:px-6 lg:hidden"
            >
                <div class="flex flex-col text-base font-semibold text-[#2d2825]">
                    <a
                        v-for="(link, index) in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="py-3.5"
                        :class="index < navLinks.length - 1 ? 'border-b border-[#2d2825]/10' : ''"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                </div>
                <a
                    href="#lokasi"
                    class="mt-4 inline-flex min-h-12 w-full items-center justify-center rounded-sm bg-[#302824] text-sm font-bold text-white"
                    @click="mobileMenuOpen = false"
                >
                    Tempah Temujanji
                </a>
            </div>
        </header>

        <main id="top">
            <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 sm:pt-24 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
                    <div data-reveal class="reveal">
                        <p class="text-xs font-bold tracking-[0.24em] text-[#a8765d] uppercase">
                            Aura Aesthetic Clinic
                        </p>
                        <h1
                            class="mt-5 max-w-lg text-4xl leading-[1.1] font-medium tracking-tight sm:text-5xl"
                        >
                            Refined beauty, thoughtfully cared for
                        </h1>
                        <p class="mt-6 max-w-md text-sm leading-7 text-[#625954]">
                            We believe in understated beauty — expert-led aesthetic care tailored to
                            your natural features.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-6">
                            <a
                                href="#lokasi"
                                class="inline-flex min-h-12 items-center rounded-sm bg-[#302824] px-7 text-sm font-bold text-white transition hover:bg-[#241e1b]"
                            >
                                Tempah Temujanji
                            </a>
                            <a
                                href="#perkhidmatan"
                                class="text-sm font-bold text-[#2d2825] underline decoration-[#a8765d] underline-offset-4"
                            >
                                Terokai Perkhidmatan
                            </a>
                        </div>
                    </div>

                    <div data-reveal class="reveal relative">
                        <div
                            class="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-sm bg-gradient-to-br from-[#c9b6a3] via-[#a8846b] to-[#6b4a38]"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_22%,rgba(255,255,255,0.22),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative size-16 fill-none stroke-white/55 stroke-[1.1]"
                            >
                                <circle cx="12" cy="8.5" r="3.5" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.5 20c1.2-4.2 4.8-6.5 7.5-6.5s6.3 2.3 7.5 6.5"
                                />
                            </svg>
                            <span
                                class="absolute bottom-4 left-4 rounded-sm bg-black/25 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                            >
                                Foto klinik/doktor anda di sini
                            </span>
                        </div>
                        <p class="mt-3 text-xs tracking-wide text-[#7d716a] italic">
                            Ruang rawatan Klinik Estetika Aura, Bangsar
                        </p>
                    </div>
                </div>
            </section>

            <section
                id="perkhidmatan"
                class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-10"
            >
                <div data-reveal class="reveal max-w-xl">
                    <p class="text-xs font-bold tracking-[0.24em] text-[#a8765d] uppercase">
                        Services
                    </p>
                    <h2 class="mt-3 text-2xl font-medium tracking-tight sm:text-3xl">
                        Thoughtfully selected treatments
                    </h2>
                </div>

                <div
                    data-reveal
                    class="reveal mt-10 grid gap-x-8 gap-y-10 border-t border-[#d8cbc2] sm:grid-cols-2"
                >
                    <div
                        v-for="service in services"
                        :key="service.title"
                        class="border-b border-[#d8cbc2] pt-8 pb-8"
                    >
                        <h3 class="text-lg font-medium">{{ service.title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-[#625954]">{{ service.body }}</p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                    <div
                        data-reveal
                        class="reveal relative order-2 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-sm bg-gradient-to-br from-[#e0d3c8] via-[#c2a98f] to-[#8a6a4f] lg:order-1"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_70%_25%,rgba(255,255,255,0.22),transparent_55%)]"
                        />
                        <svg
                            viewBox="0 0 24 24"
                            class="relative size-14 fill-none stroke-white/55 stroke-[1.1]"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M8 12.5l2.5 2.5L16 9" />
                        </svg>
                    </div>
                    <div data-reveal class="reveal order-1 lg:order-2">
                        <p class="text-xs font-bold tracking-[0.24em] text-[#a8765d] uppercase">
                            Our Philosophy
                        </p>
                        <h2 class="mt-3 max-w-md text-2xl font-medium tracking-tight sm:text-3xl">
                            Beauty that honours your individuality
                        </h2>
                        <p class="mt-4 max-w-md text-sm leading-7 text-[#625954]">
                            Aura Aesthetic Clinic combines medical expertise with a personal touch —
                            every treatment plan is created with you, not simply for you.
                        </p>
                    </div>
                </div>
            </section>

            <section id="doktor" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-10">
                <div data-reveal class="reveal max-w-xl">
                    <p class="text-xs font-bold tracking-[0.24em] text-[#a8765d] uppercase">
                        Our Doctors
                    </p>
                    <h2 class="mt-3 text-2xl font-medium tracking-tight sm:text-3xl">
                        Guided by certified experts
                    </h2>
                </div>

                <div data-reveal class="reveal mt-10 grid gap-6 sm:grid-cols-3">
                    <article v-for="doctor in doctors" :key="doctor.name">
                        <div
                            class="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-sm bg-gradient-to-br"
                            :class="doctor.photo"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.22),transparent_55%)]"
                            />
                            <svg
                                viewBox="0 0 24 24"
                                class="relative size-12 fill-none stroke-white/55 stroke-[1.1]"
                            >
                                <circle cx="12" cy="8.5" r="3.5" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5"
                                />
                            </svg>
                        </div>
                        <p class="mt-4 text-base font-medium">{{ doctor.name }}</p>
                        <p class="mt-1 text-xs tracking-wide text-[#7d716a] uppercase">
                            {{ doctor.focus }}
                        </p>
                    </article>
                </div>
            </section>

            <section id="testimoni" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-10">
                <div
                    data-reveal
                    class="reveal grid gap-12 border-t border-[#d8cbc2] pt-12 sm:grid-cols-2"
                >
                    <blockquote
                        v-for="testimonial in testimonials"
                        :key="testimonial.name"
                        class="max-w-md"
                    >
                        <p class="text-lg leading-8 font-medium tracking-tight italic">
                            “{{ testimonial.quote }}”
                        </p>
                        <cite
                            class="mt-4 block text-xs tracking-wide text-[#7d716a] not-italic uppercase"
                        >
                            — {{ testimonial.name }}
                        </cite>
                    </blockquote>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-10">
                <div data-reveal class="reveal max-w-xl">
                    <p class="text-xs font-bold tracking-[0.24em] text-[#a8765d] uppercase">
                        Gallery
                    </p>
                    <h2 class="mt-3 text-2xl font-medium tracking-tight sm:text-3xl">
                        A calm and private environment
                    </h2>
                </div>
                <div class="mt-8 grid grid-cols-2 items-start gap-3 sm:grid-cols-3">
                    <div
                        v-for="(tone, index) in gallery"
                        :key="index"
                        class="relative aspect-[3/4] overflow-hidden rounded-sm bg-gradient-to-br"
                        :class="[tone, index % 2 === 1 ? 'mt-8' : '']"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_22%,rgba(255,255,255,0.2),transparent_55%)]"
                        />
                    </div>
                </div>
            </section>

            <section id="lokasi" class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-10">
                <div data-reveal class="reveal rounded-sm bg-[#302824] p-8 text-[#ead9ce] sm:p-16">
                    <p class="text-xs font-bold tracking-[0.24em] text-[#c9a17e] uppercase">
                        Begin Your Journey
                    </p>
                    <h2
                        class="mt-4 max-w-md text-3xl font-medium tracking-tight text-white sm:text-4xl"
                    >
                        Book your private consultation today
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-7">
                        Speak with our experts to discover the treatment best suited to your needs.
                    </p>
                    <a
                        href="tel:+60321987654"
                        class="mt-8 inline-flex min-h-12 items-center rounded-sm bg-white px-7 text-sm font-bold text-[#302824] transition hover:bg-[#f5ece4]"
                    >
                        Cari Klinik
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#2d2825]/10 bg-[#fdfbf8]">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-10">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <span class="text-lg font-bold tracking-tight">Klinik Estetika Aura</span>
                        <p class="mt-3 text-sm leading-6 text-[#625954]">
                            Rawatan estetik yang halus, peribadi, dan dipandu oleh pakar bertauliah.
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-[#a8765d] uppercase">
                            Pautan Pantas
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-[#625954]">
                            <li v-for="link in navLinks" :key="link.id">
                                <a :href="`#${link.id}`" class="hover:text-[#2d2825]">{{
                                    link.label
                                }}</a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-[#a8765d] uppercase">
                            Hubungi Kami
                        </p>
                        <ul class="mt-4 space-y-2 text-sm text-[#625954]">
                            <li>8, Jalan Telawi, Bangsar, Kuala Lumpur</li>
                            <li>+60 3-2198 7654</li>
                            <li>hello@klinikestetikaaura.example</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-bold tracking-[0.2em] text-[#a8765d] uppercase">
                            Surat Berita
                        </p>
                        <p class="mt-4 text-sm text-[#625954]">
                            Langgan untuk kemas kini rawatan estetik terkini.
                        </p>
                        <div
                            class="mt-3 flex overflow-hidden rounded-sm border border-[#d8cbc2] p-1"
                        >
                            <input
                                type="email"
                                placeholder="Emel anda"
                                disabled
                                class="min-w-0 flex-1 bg-transparent px-3 text-sm text-[#2d2825] placeholder-[#7d716a] outline-none"
                            />
                            <span
                                class="flex shrink-0 items-center rounded-sm bg-[#302824] px-4 text-xs font-bold text-white"
                            >
                                Langgan
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-10 flex flex-col gap-4 border-t border-[#2d2825]/10 pt-6 text-xs text-[#7d716a] sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>&copy; 2026 Klinik Estetika Aura. Reka bentuk pratonton oleh SYIFA.my.</p>
                    <a :href="homeUrl" class="font-semibold text-[#a8765d] hover:text-[#8f6249]">
                        Kembali ke SYIFA.my →
                    </a>
                </div>
            </div>
        </footer>

        <a
            href="https://wa.me/60321987654"
            target="_blank"
            rel="noopener"
            aria-label="WhatsApp Klinik Estetika Aura"
            class="fixed right-5 bottom-5 z-50 flex size-14 items-center justify-center rounded-full bg-[#302824] text-white shadow-xl transition hover:bg-[#241e1b] hover:shadow-2xl"
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
