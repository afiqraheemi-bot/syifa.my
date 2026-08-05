<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppFooter from './Landing/AppFooter.vue';
import AppNavbar from './Landing/AppNavbar.vue';
import CTASection from './Landing/CTASection.vue';
import FAQSection from './Landing/FAQSection.vue';
import HeroSection from './Landing/HeroSection.vue';
import HowItWorksSection from './Landing/HowItWorksSection.vue';
import ProblemSection from './Landing/ProblemSection.vue';
import SolutionSection from './Landing/SolutionSection.vue';
import TemplatesSection from './Landing/TemplatesSection.vue';
import TestimonialSection from './Landing/TestimonialSection.vue';
import WhySyifaSection from './Landing/WhySyifaSection.vue';

const props = defineProps({
    loginUrl: { type: String, required: true },
    clinicRegistrationUrl: { type: String, required: true },
    privacyUrl: { type: String, required: true },
    termsUrl: { type: String, required: true },
    templatePreviewUrl: { type: String, required: true },
    carePreviewUrl: { type: String, required: true },
    specialistPreviewUrl: { type: String, required: true },
    aestheticPreviewUrl: { type: String, required: true },
});

// Index order matches copy.templates.items: [Essential, Care, Dental, Aesthetic, Specialist].
const previewUrls = computed(() => [
    null,
    props.carePreviewUrl,
    props.templatePreviewUrl,
    props.aestheticPreviewUrl,
    props.specialistPreviewUrl,
]);

const STORAGE_KEY = 'syifamy-marketing-lang';

function initialLang() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'en' ? 'en' : 'ms';
    } catch {
        return 'ms';
    }
}

const lang = ref(initialLang());

function setLang(next) {
    lang.value = next;
    try {
        window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // Persistence is a convenience only; the toggle still works without it.
    }
}

function refreshRestoredEntry(event) {
    const navigation = window.performance.getEntriesByType('navigation')[0];

    if (event?.persisted === true || navigation?.type === 'back_forward') {
        window.location.reload();
    }
}

let revealObserver = null;

onMounted(() => {
    window.addEventListener('pageshow', refreshRestoredEntry);
    refreshRestoredEntry();

    const targets = document.querySelectorAll('[data-reveal]');
    revealObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );
    targets.forEach((target) => revealObserver.observe(target));
});

onBeforeUnmount(() => {
    window.removeEventListener('pageshow', refreshRestoredEntry);
    revealObserver?.disconnect();
});

const copy = {
    ms: {
        nav: {
            login: 'Log Masuk',
            register: 'Daftar Klinik',
            links: [
                { id: 'features', label: 'Ciri' },
                { id: 'how-it-works', label: 'Cara Ia Berfungsi' },
                { id: 'templates', label: 'Templat' },
                { id: 'pricing', label: 'Harga' },
                { id: 'faq', label: 'Soalan Lazim' },
            ],
        },
        hero: {
            eyebrow: 'WEBSITE · BOOKING · GROWTH',
            title: 'Anda fokus merawat pesakit.',
            titleAccent: 'Kami uruskan website dan sistem tempahan anda.',
            body: 'SYIFA.my ialah perkhidmatan laman web terurus yang direka khas untuk klinik — templat profesional, onboarding dipandu Pereka Laman Web, dan asas sistem tempahan pesakit, semuanya dalam satu ruang kerja selamat.',
            primaryCta: 'Daftar Klinik',
            secondaryCta: 'Log Masuk',
            trustNote: 'Percuma setup untuk pendaftaran terawal.',
        },
        trustBar: {
            title: 'Sesuai Untuk Semua Jenis Klinik',
            items: [
                { id: 'health', label: 'Klinik Kesihatan' },
                { id: 'dental', label: 'Klinik Pergigian' },
                { id: 'specialist', label: 'Klinik Pakar' },
                { id: 'women', label: 'Klinik Wanita' },
                { id: 'children', label: 'Klinik Kanak-kanak' },
                { id: 'physio', label: 'Klinik Fisioterapi' },
                { id: 'aesthetic', label: 'Klinik Estetik' },
                { id: 'more', label: 'Dan lain-lain' },
            ],
        },
        problem: {
            eyebrow: 'Masalah Yang Sering Dihadapi',
            title: 'Jangan Biarkan Peluang Mendapatkan Pesakit Baharu Terlepas.',
            problems: [
                'Belum mempunyai website yang profesional.',
                'Pesakit hanya bergantung kepada WhatsApp atau panggilan telefon.',
                'Sukar ditemui melalui carian Google.',
                'Maklumat klinik tidak lengkap atau tidak dikemas kini.',
                'Terlepas peluang mendapatkan pesakit baharu.',
            ],
        },
        solution: {
            eyebrow: 'Penyelesaian',
            title: 'Syifa.my Menyelesaikan Semua Masalah Ini.',
            subtitle:
                'Platform Website-as-a-Service (WaaS) yang membantu klinik membina kehadiran digital profesional dengan website premium dan sistem booking online.',
            items: [
                {
                    id: 'website',
                    title: 'Website Premium',
                    body: 'Direka untuk membina keyakinan pesakit terhadap klinik anda.',
                },
                {
                    id: 'booking',
                    title: 'Sistem Booking Online',
                    body: 'Pesakit boleh membuat tempahan pada bila-bila masa dengan lebih mudah.',
                },
                {
                    id: 'seo',
                    title: 'SEO Ready',
                    body: 'Klinik anda lebih mudah ditemui melalui carian Google.',
                },
                {
                    id: 'hosting',
                    title: 'Hosting & SSL',
                    body: 'Laman web anda pantas, selamat, dan sentiasa dalam talian.',
                },
                {
                    id: 'mobile',
                    title: 'Mobile Friendly',
                    body: 'Kelihatan kemas dan profesional pada semua peranti.',
                },
                {
                    id: 'free',
                    title: 'Setup Percuma',
                    body: 'Tiada kos permulaan untuk pendaftaran klinik terawal.',
                },
            ],
        },
        how: {
            eyebrow: 'Cara Ia Berfungsi',
            title: '5 Langkah Mudah Untuk Klinik Anda Bersinar Secara Online.',
            steps: [
                {
                    id: 'form',
                    title: 'Isi Borang Minat',
                    body: 'Kongsi maklumat asas klinik anda.',
                },
                {
                    id: 'call',
                    title: 'Pasukan Kami Hubungi',
                    body: 'Kami akan menghubungi anda untuk memahami keperluan.',
                },
                {
                    id: 'collect',
                    title: 'Kumpul Maklumat',
                    body: 'Kami bantu kumpulkan maklumat dan kandungan klinik anda.',
                },
                {
                    id: 'build',
                    title: 'Kami Sediakan Website',
                    body: 'Kami bina website dan sediakan sistem booking untuk anda.',
                },
                {
                    id: 'launch',
                    title: 'Go Live',
                    body: 'Website anda diterbitkan dan mula menerima tempahan!',
                },
            ],
        },
        why: {
            eyebrow: 'Kenapa Pilih Syifa.my?',
            title: 'Kami Lebih Daripada Sekadar Membina Website.',
            checklist: [
                {
                    title: 'Direka khas untuk klinik',
                    body: 'Bukan template generik untuk semua jenis perniagaan.',
                },
                {
                    title: 'Kami uruskan semuanya',
                    body: 'Anda tidak perlu risau tentang hosting, kemas kini atau aspek teknikal.',
                },
                {
                    title: 'Fokus kepada pertumbuhan klinik',
                    body: 'Website yang dibina bukan sekadar cantik, tetapi membantu meningkatkan keyakinan dan memudahkan pesakit.',
                },
                {
                    title: 'Mudah digunakan',
                    body: 'Tidak memerlukan pengalaman teknikal.',
                },
            ],
            quote: 'Fokus merawat pesakit, biar kami uruskan kehadiran digital anda.',
        },
        templates: {
            eyebrow: 'Templat Kami',
            title: 'Lima Templat Premium Terurus',
            subtitle:
                'Pratonton konsep reka bentuk — setiap klinik memilih satu personaliti templat semasa onboarding, dikonfigurasikan oleh Pereka Laman Web anda.',
            note: 'Akan datang',
            viewPreview: 'Lihat pratonton →',
            items: [
                {
                    name: 'Syifa Essential',
                    tagline: 'Jelas, tenang, dan sesuai untuk kebanyakan klinik.',
                },
                {
                    name: 'Syifa Care',
                    tagline: 'Mesra dan menenangkan, sesuai untuk klinik keluarga.',
                },
                {
                    name: 'Syifa Dental',
                    tagline: 'Tepat, terang, dan tersusun untuk klinik pergigian.',
                },
                {
                    name: 'Syifa Aesthetic',
                    tagline: 'Halus dan bergaya editorial untuk klinik estetik.',
                },
                {
                    name: 'Syifa Specialist',
                    tagline: 'Berwibawa dan padat maklumat untuk klinik pakar.',
                },
            ],
        },
        testimonial: {
            eyebrow: 'Testimoni',
            title: 'Dipercayai oleh pemilik klinik',
            quote: 'Sejak guna Syifa.my, tempahan online kami meningkat dan pesakit lebih mudah hubungi kami. Sangat memudahkan!',
            name: 'Dr. Aisyah Rahman',
            clinicName: 'Klinik Aisyah',
        },
        faq: {
            eyebrow: 'Soalan Lazim',
            title: 'Soalan Lazim',
            items: [
                {
                    id: 'cost',
                    question: 'Berapa kos setup?',
                    answer: 'Setup adalah percuma untuk pendaftaran terawal. Kami akan kongsikan pelan harga penuh semasa sesi konsultasi bersama pasukan kami.',
                },
                {
                    id: 'timeline',
                    question: 'Berapa lama proses penyediaan?',
                    answer: 'Secara purata, website dan sistem tempahan klinik anda sedia dalam masa beberapa hari kerja selepas maklumat klinik lengkap dikumpulkan.',
                },
                {
                    id: 'domain',
                    question: 'Saya belum mempunyai domain.',
                    answer: 'Tiada masalah — setiap klinik akan mendapat alamat website (contoh: klinikanda.syifa.my) secara automatik semasa pendaftaran.',
                },
                {
                    id: 'existing-website',
                    question: 'Saya sudah mempunyai website.',
                    answer: 'Kami boleh bantu anda berpindah ke platform Syifa.my dengan lancar, tanpa menjejaskan operasi harian klinik anda.',
                },
                {
                    id: 'self-update',
                    question: 'Bolehkah saya kemas kini website sendiri?',
                    answer: 'Boleh. Pereka Laman Web kami akan bantu anda kemas kini kandungan, dan sokongan berterusan disediakan sepanjang penggunaan platform.',
                },
            ],
        },
        cta: {
            eyebrow: 'Sedia Membina Klinik Anda',
            title: 'Bersedia Membawa Klinik Anda Ke Tahap Seterusnya?',
            body: 'Jangan biarkan bakal pesakit memilih klinik lain kerana maklumat klinik anda sukar ditemui atau proses tempahan yang menyusahkan. Serahkan urusan website kepada kami, supaya anda boleh fokus kepada perkara yang paling penting — merawat pesakit.',
            primaryCta: 'Daftar Klinik',
            secondaryCta: 'Log Masuk',
            note: 'Setup adalah PERCUMA untuk pendaftaran awal.',
        },
        footer: {
            blurb: 'Platform Website-as-a-Service (WaaS) khas untuk klinik. Kami uruskan website dan sistem booking anda, supaya anda boleh fokus merawat pesakit.',
            navTitle: 'Pautan Pantas',
            legalTitle: 'Legal',
            privacy: 'Dasar Privasi',
            terms: 'Terma Perkhidmatan',
            rights: 'Hak cipta terpelihara.',
        },
        seo: {
            title: 'SYIFA.my — Website & Sistem Tempahan Terurus Untuk Klinik',
            description:
                'SYIFA.my ialah perkhidmatan Website-as-a-Service untuk klinik — templat premium, onboarding terurus, dan sistem tempahan pesakit bersepadu.',
        },
    },
    en: {
        nav: {
            login: 'Log In',
            register: 'Register Clinic',
            links: [
                { id: 'features', label: 'Features' },
                { id: 'how-it-works', label: 'How It Works' },
                { id: 'templates', label: 'Templates' },
                { id: 'pricing', label: 'Pricing' },
                { id: 'faq', label: 'FAQ' },
            ],
        },
        hero: {
            eyebrow: 'WEBSITE · BOOKING · GROWTH',
            title: 'You focus on patient care.',
            titleAccent: 'We manage your website and booking system.',
            body: 'SYIFA.my is a managed website service built specifically for clinics — professional templates, onboarding guided by a dedicated Website Designer, and an integrated patient booking foundation, all in one secure workspace.',
            primaryCta: 'Register Clinic',
            secondaryCta: 'Log In',
            trustNote: 'Free setup for early registrations.',
        },
        trustBar: {
            title: 'Suitable For Every Type Of Clinic',
            items: [
                { id: 'health', label: 'Health Clinics' },
                { id: 'dental', label: 'Dental Clinics' },
                { id: 'specialist', label: 'Specialist Clinics' },
                { id: 'women', label: "Women's Clinics" },
                { id: 'children', label: "Children's Clinics" },
                { id: 'physio', label: 'Physiotherapy Clinics' },
                { id: 'aesthetic', label: 'Aesthetic Clinics' },
                { id: 'more', label: 'And more' },
            ],
        },
        problem: {
            eyebrow: 'Common Problems Clinics Face',
            title: "Don't Let New Patient Opportunities Slip Away.",
            problems: [
                "You don't have a professional website yet.",
                'Patients only reach you through WhatsApp or phone calls.',
                'Hard to find through Google search.',
                'Clinic information is incomplete or outdated.',
                'You lose potential new patients.',
            ],
        },
        solution: {
            eyebrow: 'The Solution',
            title: 'Syifa.my Solves All These Problems.',
            subtitle:
                'A Website-as-a-Service (WaaS) platform that helps clinics build a professional digital presence with a premium website and online booking system.',
            items: [
                {
                    id: 'website',
                    title: 'Premium Website',
                    body: "Designed to build patients' confidence in your clinic.",
                },
                {
                    id: 'booking',
                    title: 'Online Booking System',
                    body: 'Patients can book appointments anytime, more easily.',
                },
                {
                    id: 'seo',
                    title: 'SEO Ready',
                    body: 'Your clinic is easier to find through Google search.',
                },
                {
                    id: 'hosting',
                    title: 'Hosting & SSL',
                    body: 'Your website is fast, secure, and always online.',
                },
                {
                    id: 'mobile',
                    title: 'Mobile Friendly',
                    body: 'Looks clean and professional on every device.',
                },
                {
                    id: 'free',
                    title: 'Free Setup',
                    body: 'No upfront cost for the earliest registered clinics.',
                },
            ],
        },
        how: {
            eyebrow: 'How It Works',
            title: '5 Simple Steps To Get Your Clinic Shining Online.',
            steps: [
                {
                    id: 'form',
                    title: 'Fill In The Interest Form',
                    body: 'Share basic information about your clinic.',
                },
                {
                    id: 'call',
                    title: 'Our Team Reaches Out',
                    body: 'We contact you to understand your needs.',
                },
                {
                    id: 'collect',
                    title: 'We Gather Information',
                    body: "We help collect your clinic's information and content.",
                },
                {
                    id: 'build',
                    title: 'We Build Your Website',
                    body: 'We build your website and set up your booking system.',
                },
                {
                    id: 'launch',
                    title: 'Go Live',
                    body: 'Your website is published and starts accepting bookings!',
                },
            ],
        },
        why: {
            eyebrow: 'Why Choose Syifa.my?',
            title: "We're More Than Just A Website Builder.",
            checklist: [
                {
                    title: 'Purpose-built for clinics',
                    body: 'Not a generic template for every kind of business.',
                },
                {
                    title: 'We handle everything',
                    body: "You don't need to worry about hosting, updates, or technical details.",
                },
                {
                    title: 'Focused on clinic growth',
                    body: 'A website built to build confidence and make things easier for patients — not just look nice.',
                },
                {
                    title: 'Easy to use',
                    body: 'No technical experience required.',
                },
            ],
            quote: 'Focus on patient care — let us manage your digital presence.',
        },
        templates: {
            eyebrow: 'Our Templates',
            title: 'Five Governed Premium Templates',
            subtitle:
                'A concept preview — each clinic selects one template personality during onboarding, configured by your Website Designer.',
            note: 'Coming soon',
            viewPreview: 'View preview →',
            items: [
                {
                    name: 'Syifa Essential',
                    tagline: 'Clear, calm, and broadly suitable for most clinics.',
                },
                {
                    name: 'Syifa Care',
                    tagline: 'Warm and reassuring, ideal for family-oriented clinics.',
                },
                {
                    name: 'Syifa Dental',
                    tagline: 'Precise, bright, and structured for dental practices.',
                },
                {
                    name: 'Syifa Aesthetic',
                    tagline: 'Refined and editorial for aesthetic clinics.',
                },
                {
                    name: 'Syifa Specialist',
                    tagline: 'Authoritative and information-led for specialist clinics.',
                },
            ],
        },
        testimonial: {
            eyebrow: 'Testimonial',
            title: 'Trusted by clinic owners',
            quote: "Since using Syifa.my, our online bookings have grown and patients find it much easier to reach us. It's made everything so much simpler!",
            name: 'Dr. Aisyah Rahman',
            clinicName: 'Klinik Aisyah',
        },
        faq: {
            eyebrow: 'FAQ',
            title: 'Frequently Asked Questions',
            items: [
                {
                    id: 'cost',
                    question: 'How much does setup cost?',
                    answer: "Setup is free for early registrations. We'll share the full pricing plan during a consultation with our team.",
                },
                {
                    id: 'timeline',
                    question: 'How long does setup take?',
                    answer: 'On average, your clinic website and booking system are ready within a few business days once your clinic information is complete.',
                },
                {
                    id: 'domain',
                    question: "I don't have a domain yet.",
                    answer: 'No problem — every clinic automatically gets a website address (e.g. yourclinic.syifa.my) during registration.',
                },
                {
                    id: 'existing-website',
                    question: 'I already have a website.',
                    answer: "We can help you move to the Syifa.my platform smoothly, without disrupting your clinic's daily operations.",
                },
                {
                    id: 'self-update',
                    question: 'Can I update the website myself?',
                    answer: 'Yes. Our Website Designers help you update content, and ongoing support is available throughout your time on the platform.',
                },
            ],
        },
        cta: {
            eyebrow: 'Ready To Build Your Clinic',
            title: 'Ready To Take Your Clinic To The Next Level?',
            body: "Don't let potential patients choose another clinic because your information is hard to find or booking is a hassle. Leave your website to us, so you can focus on what matters most — patient care.",
            primaryCta: 'Register Clinic',
            secondaryCta: 'Log In',
            note: 'Setup is FREE for early registrations.',
        },
        footer: {
            blurb: 'A Website-as-a-Service (WaaS) platform built for clinics. We manage your website and booking system, so you can focus on patient care.',
            navTitle: 'Quick Links',
            legalTitle: 'Legal',
            privacy: 'Privacy Policy',
            terms: 'Terms of Service',
            rights: 'All rights reserved.',
        },
        seo: {
            title: 'SYIFA.my — Managed Websites & Booking Systems For Clinics',
            description:
                'SYIFA.my is a Website-as-a-Service platform for clinics — premium templates, managed onboarding, and an integrated patient booking system.',
        },
    },
};

const t = computed(() => copy[lang.value]);
</script>

<template>
    <Head>
        <title>{{ t.seo.title }}</title>
        <meta name="description" :content="t.seo.description" />
        <link rel="canonical" href="https://syifa.my/" />
        <meta name="robots" content="index, follow" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="t.seo.title" />
        <meta property="og:description" :content="t.seo.description" />
        <meta property="og:url" content="https://syifa.my/" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="t.seo.title" />
        <meta name="twitter:description" :content="t.seo.description" />
    </Head>

    <div class="reveal-root min-h-screen bg-white">
        <AppNavbar
            :links="t.nav.links"
            :login-url="loginUrl"
            :register-url="clinicRegistrationUrl"
            :login-label="t.nav.login"
            :register-label="t.nav.register"
            :lang="lang"
            @set-lang="setLang"
        />

        <main>
            <HeroSection
                :copy="t.hero"
                :register-url="clinicRegistrationUrl"
                :login-url="loginUrl"
            />
            <TrustBar :copy="t.trustBar" />
            <ProblemSection :copy="t.problem" />
            <SolutionSection :copy="t.solution" />
            <HowItWorksSection :copy="t.how" />
            <WhySyifaSection :copy="t.why" />
            <TemplatesSection :copy="t.templates" :preview-urls="previewUrls" />
            <TestimonialSection :copy="t.testimonial" />
            <FAQSection :copy="t.faq" />
            <CTASection
                section-id="pricing"
                :copy="t.cta"
                :register-url="clinicRegistrationUrl"
                :login-url="loginUrl"
            />
        </main>

        <AppFooter
            :copy="t.footer"
            :links="t.nav.links"
            :privacy-url="privacyUrl"
            :terms-url="termsUrl"
            :privacy-label="t.footer.privacy"
            :terms-label="t.footer.terms"
        />
    </div>
</template>

<style>
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
