<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppFooter from './Landing/AppFooter.vue';
import AppNavbar from './Landing/AppNavbar.vue';
import CTASection from './Landing/CTASection.vue';
import FAQSection from './Landing/FAQSection.vue';
import HeroSection from './Landing/HeroSection.vue';
import HowItWorksSection from './Landing/HowItWorksSection.vue';
import ProblemSection from './Landing/ProblemSection.vue';
import PricingSection from './Landing/PricingSection.vue';
import SolutionSection from './Landing/SolutionSection.vue';
import TemplatesSection from './Landing/TemplatesSection.vue';
import TestimonialSection from './Landing/TestimonialSection.vue';
import WhySyifaSection from './Landing/WhySyifaSection.vue';

defineProps({
    loginUrl: { type: String, required: true },
    clinicRegistrationUrl: { type: String, required: true },
    privacyUrl: { type: String, required: true },
    termsUrl: { type: String, required: true },
    essentialPreviewUrl: { type: String, required: true },
    templatePreviewUrl: { type: String, required: true },
    carePreviewUrl: { type: String, required: true },
    specialistPreviewUrl: { type: String, required: true },
    aestheticPreviewUrl: { type: String, required: true },
    packages: { type: Array, required: true },
    packagePreview: { type: Boolean, default: false },
});

const whatsappUrl = computed(() => {
    const message =
        lang.value === 'en'
            ? 'Hi SYIFA.my, I would like to know more about the clinic packages.'
            : 'Hi SYIFA.my, saya ingin tahu lebih lanjut tentang pakej untuk klinik.';

    return `https://wa.me/60134079388?text=${encodeURIComponent(message)}`;
});

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
            title: 'Website klinik yang meyakinkan.',
            titleAccent: 'Tempahan yang memudahkan.',
            body: 'SYIFA.my menyediakan website profesional dan sistem tempahan online dalam satu platform. Dari setup hingga pengurusan teknikal, pasukan kami membantu klinik anda tampil lebih diyakini secara digital.',
            primaryCta: 'Mulakan Website Klinik',
            secondaryCta: 'Lihat Proses Kami',
            trustNote: 'Setup dibantu sepenuhnya · Tiada kemahiran teknikal diperlukan',
            benefits: ['Direka untuk klinik', 'Tempahan online 24/7', 'Sokongan pasukan tempatan'],
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
            imageAlt:
                'Pengurus klinik menyemak telefon, laptop dan buku tempahan yang perlu diselaraskan secara manual.',
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
            imageAlt:
                'Pemilik klinik dan pakar website bekerjasama menyemak pengurusan digital klinik melalui laptop.',
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
            title: 'Rekaan Untuk Klinik Sebenar',
            subtitle:
                'Lihat bagaimana identiti dan mesej klinik anda boleh tampil secara profesional melalui setiap gaya rekaan SYIFA.',
            note: 'Mockup ringkas',
            viewPreview: 'Mockup ringkas',
            livePreviews: 'mockup templat',
            previewReady: 'Preview terus',
            livePreviewTitle: 'Preview sebenar templat',
            livePreviewLabel: 'Paparan live',
            managedNote: 'Responsif pada telefon, tablet dan desktop',
            managedTemplate: 'Templat terurus',
            chooseTemplate: 'Pilih templat ini',
            selectedTemplate: 'Pilihan reka bentuk',
            bestFor: 'Paling sesuai untuk',
            desktop: 'Desktop',
            tablet: 'Tablet',
            mobile: 'Mobile',
            devicePreview: 'Saiz preview',
            responsiveNote: 'Preview responsif sebenar',
            templateSelector: 'Pilih templat website',
            clinicReady: 'Maklumat klinik lengkap',
            mobileReady: 'Mesra telefon',
            selectionNote:
                'Tidak pasti templat mana paling sesuai? Pereka Laman Web kami akan membantu anda memilih dan menyesuaikannya semasa onboarding.',
            items: [
                {
                    name: 'Syifa Essential',
                    tagline: 'Jelas, tenang, dan sesuai untuk kebanyakan klinik.',
                    demoClinic: 'Klinik Keluarga Ihsan',
                    demoDomain: 'klinikihsan.syifa.my',
                    demoEyebrow: 'Penjagaan keluarga dipercayai',
                    demoHeadline: 'Kesihatan keluarga, keutamaan kami',
                    demoBody:
                        'Rawatan perubatan yang menyeluruh, mesra dan mudah diakses untuk setiap peringkat usia.',
                    demoCta: 'Buat Temu Janji',
                    demoSecondary: 'Hubungi Klinik',
                    demoImageAlt: 'Konsultasi keluarga di klinik moden',
                    bestFor: 'Klinik am, klinik komuniti dan amalan perubatan baharu',
                    features: [
                        'Susunan maklumat yang jelas dan mudah difahami',
                        'Fokus pada servis, doktor dan tempahan',
                        'Gaya profesional yang fleksibel untuk pelbagai klinik',
                    ],
                },
                {
                    name: 'Syifa Care',
                    tagline: 'Mesra dan menenangkan, sesuai untuk klinik keluarga.',
                    demoClinic: 'Klinik Ceria',
                    demoDomain: 'klinikceria.syifa.my',
                    demoEyebrow: 'Penjagaan untuk setiap generasi',
                    demoHeadline: 'Membesar sihat bersama keluarga',
                    demoBody:
                        'Penjagaan yang sabar dan menenangkan untuk bayi, kanak-kanak, ibu bapa dan warga emas.',
                    demoCta: 'Buat Temu Janji',
                    demoSecondary: 'Chat WhatsApp',
                    demoImageAlt: 'Doktor memeriksa kanak-kanak bersama ibunya',
                    bestFor: 'Klinik keluarga, pediatrik dan penjagaan primer',
                    features: [
                        'Visual mesra untuk pesakit dan keluarga',
                        'Perjalanan tempahan yang mudah dan meyakinkan',
                        'Ruang kandungan yang hangat dan mudah didekati',
                    ],
                },
                {
                    name: 'Syifa Dental',
                    tagline: 'Tepat, terang, dan tersusun untuk klinik pergigian.',
                    demoClinic: 'Klinik Pergigian Senyum',
                    demoDomain: 'senyum.syifa.my',
                    demoEyebrow: 'Pergigian moden dan selesa',
                    demoHeadline: 'Senyuman sihat bermula di sini',
                    demoBody:
                        'Rawatan pergigian teliti dengan teknologi moden dan pengalaman yang selesa untuk setiap pesakit.',
                    demoCta: 'Tempah Pemeriksaan',
                    demoSecondary: 'Lihat Rawatan',
                    demoImageAlt: 'Konsultasi pesakit di klinik pergigian moden',
                    bestFor: 'Klinik pergigian, ortodontik dan pusat rawatan oral',
                    features: [
                        'Presentation servis yang terang dan tersusun',
                        'Profil doktor yang membina keyakinan pesakit',
                        'CTA tempahan jelas pada setiap peranti',
                    ],
                },
                {
                    name: 'Syifa Aesthetic',
                    tagline: 'Halus dan bergaya editorial untuk klinik estetik.',
                    demoClinic: 'Klinik Estetika Aura',
                    demoDomain: 'aura.syifa.my',
                    demoEyebrow: 'Estetik dipandu kepakaran',
                    demoHeadline: 'Keyakinan yang terasa semula jadi',
                    demoBody:
                        'Rundingan peribadi dan rawatan estetik yang dirancang dengan teliti untuk hasil halus dan elegan.',
                    demoCta: 'Tempah Rundingan',
                    demoSecondary: 'Terokai Rawatan',
                    demoImageAlt: 'Rundingan peribadi di klinik estetik premium',
                    bestFor: 'Klinik estetik, pusat kulit dan wellness',
                    features: [
                        'Typography editorial dengan rasa premium',
                        'Presentation rawatan berasaskan visual',
                        'Pengalaman konsultasi yang elegan dan peribadi',
                    ],
                },
                {
                    name: 'Syifa Specialist',
                    tagline: 'Berwibawa dan padat maklumat untuk klinik pakar.',
                    demoClinic: 'Klinik Pakar Utama',
                    demoDomain: 'pakarutama.syifa.my',
                    demoEyebrow: 'Kepakaran klinikal berfokus',
                    demoHeadline: 'Jawapan tepat untuk penjagaan kompleks',
                    demoBody:
                        'Penilaian pakar yang menyeluruh, jelas dan berasaskan bukti untuk membantu anda membuat keputusan terbaik.',
                    demoCta: 'Jumpa Pakar',
                    demoSecondary: 'Bidang Kepakaran',
                    demoImageAlt: 'Pakar perubatan menerangkan keputusan kepada pesakit',
                    bestFor: 'Klinik pakar, pusat diagnostik dan konsultasi khusus',
                    features: [
                        'Hierarchy klinikal yang kukuh dan meyakinkan',
                        'Maklumat kompleks dipersembahkan dengan jelas',
                        'Profil pakar dan kemudahan diberi penekanan',
                    ],
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
        whatsapp: 'Tanya di WhatsApp',
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
            title: 'A clinic website that inspires trust.',
            titleAccent: 'Booking made effortless.',
            body: 'SYIFA.my brings a professional website and online booking into one managed platform. From setup to technical upkeep, our team helps your clinic present a more credible digital experience.',
            primaryCta: 'Start My Clinic Website',
            secondaryCta: 'See Our Process',
            trustNote: 'Fully assisted setup · No technical skills required',
            benefits: ['Built for clinics', '24/7 online booking', 'Local team support'],
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
            imageAlt:
                'A clinic manager checking a phone, laptop and appointment book that require manual coordination.',
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
            imageAlt:
                'A clinic owner and website specialist collaborating on the clinic digital experience using a laptop.',
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
            title: 'Designed For Real Clinics',
            subtitle:
                'See how your clinic identity and message can come to life professionally through each SYIFA design style.',
            note: 'Simple mockup',
            viewPreview: 'Simple mockup',
            livePreviews: 'template mockups',
            previewReady: 'Instant preview',
            livePreviewTitle: 'Real template preview',
            livePreviewLabel: 'Live view',
            managedNote: 'Responsive across mobile, tablet and desktop',
            managedTemplate: 'Managed template',
            chooseTemplate: 'Choose this template',
            selectedTemplate: 'Selected design',
            bestFor: 'Best suited for',
            desktop: 'Desktop',
            tablet: 'Tablet',
            mobile: 'Mobile',
            devicePreview: 'Preview size',
            responsiveNote: 'True responsive preview',
            templateSelector: 'Choose a website template',
            clinicReady: 'Complete clinic information',
            mobileReady: 'Mobile friendly',
            selectionNote:
                'Not sure which template fits best? Your Website Designer will help you select and configure it during onboarding.',
            items: [
                {
                    name: 'Syifa Essential',
                    tagline: 'Clear, calm, and broadly suitable for most clinics.',
                    demoClinic: 'Klinik Keluarga Ihsan',
                    demoDomain: 'klinikihsan.syifa.my',
                    demoEyebrow: 'Trusted family healthcare',
                    demoHeadline: 'Your family’s health comes first',
                    demoBody:
                        'Complete, approachable and accessible medical care for every stage of life.',
                    demoCta: 'Book Appointment',
                    demoSecondary: 'Contact Clinic',
                    demoImageAlt: 'Family consultation in a modern clinic',
                    bestFor: 'General practices, community clinics and new medical practices',
                    features: [
                        'Clear information hierarchy that is easy to understand',
                        'Focused presentation of services, doctors and booking',
                        'A flexible professional style for a wide range of clinics',
                    ],
                },
                {
                    name: 'Syifa Care',
                    tagline: 'Warm and reassuring, ideal for family-oriented clinics.',
                    demoClinic: 'Klinik Ceria',
                    demoDomain: 'klinikceria.syifa.my',
                    demoEyebrow: 'Care for every generation',
                    demoHeadline: 'Growing healthier, together',
                    demoBody:
                        'Patient and reassuring care for babies, children, parents and older family members.',
                    demoCta: 'Book Appointment',
                    demoSecondary: 'Chat on WhatsApp',
                    demoImageAlt: 'Doctor examining a child with her mother',
                    bestFor: 'Family clinics, paediatrics and primary care practices',
                    features: [
                        'Warm visuals designed for patients and families',
                        'A simple and reassuring booking journey',
                        'Approachable content presentation throughout',
                    ],
                },
                {
                    name: 'Syifa Dental',
                    tagline: 'Precise, bright, and structured for dental practices.',
                    demoClinic: 'Klinik Pergigian Senyum',
                    demoDomain: 'senyum.syifa.my',
                    demoEyebrow: 'Modern, comfortable dentistry',
                    demoHeadline: 'A healthier smile starts here',
                    demoBody:
                        'Thoughtful dental treatment supported by modern technology and a comfortable patient experience.',
                    demoCta: 'Book a Check-up',
                    demoSecondary: 'View Treatments',
                    demoImageAlt: 'Patient consultation in a modern dental clinic',
                    bestFor: 'Dental clinics, orthodontics and oral care centres',
                    features: [
                        'Bright and structured treatment presentation',
                        'Confidence-building clinician profiles',
                        'Clear appointment actions across every device',
                    ],
                },
                {
                    name: 'Syifa Aesthetic',
                    tagline: 'Refined and editorial for aesthetic clinics.',
                    demoClinic: 'Klinik Estetika Aura',
                    demoDomain: 'aura.syifa.my',
                    demoEyebrow: 'Expert-led aesthetics',
                    demoHeadline: 'Confidence that feels natural',
                    demoBody:
                        'Personal consultations and carefully planned aesthetic treatments for subtle, elegant results.',
                    demoCta: 'Book Consultation',
                    demoSecondary: 'Explore Treatments',
                    demoImageAlt: 'Private consultation in a premium aesthetic clinic',
                    bestFor: 'Aesthetic clinics, skin centres and wellness practices',
                    features: [
                        'Premium editorial typography',
                        'Image-led treatment presentation',
                        'An elegant and personal consultation journey',
                    ],
                },
                {
                    name: 'Syifa Specialist',
                    tagline: 'Authoritative and information-led for specialist clinics.',
                    demoClinic: 'Klinik Pakar Utama',
                    demoDomain: 'pakarutama.syifa.my',
                    demoEyebrow: 'Focused clinical expertise',
                    demoHeadline: 'Clear answers for complex care',
                    demoBody:
                        'Thorough, evidence-led specialist assessment to help you make confident healthcare decisions.',
                    demoCta: 'See a Specialist',
                    demoSecondary: 'Our Specialties',
                    demoImageAlt: 'Medical specialist explaining results to a patient',
                    bestFor: 'Specialist clinics, diagnostic centres and consultant practices',
                    features: [
                        'Strong and credible clinical hierarchy',
                        'Complex information presented with clarity',
                        'Prominent specialist and facility profiles',
                    ],
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
        whatsapp: 'Chat on WhatsApp',
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
    <div class="reveal-root min-h-screen bg-white">
        <div
            v-if="packagePreview"
            class="sticky top-0 z-[60] flex items-center justify-center bg-amber-300 px-4 py-2 text-center text-sm font-bold text-amber-950 shadow-sm"
        >
            Admin preview — only active and purchasable packages are shown. Registration buttons
            open the real registration flow.
        </div>
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
            <TemplatesSection
                :copy="t.templates"
                :register-url="clinicRegistrationUrl"
                :preview-urls="[
                    essentialPreviewUrl,
                    carePreviewUrl,
                    templatePreviewUrl,
                    aestheticPreviewUrl,
                    specialistPreviewUrl,
                ]"
            />
            <TestimonialSection :copy="t.testimonial" />
            <PricingSection
                :packages="packages"
                :register-url="clinicRegistrationUrl"
                :lang="lang"
            />
            <FAQSection :copy="t.faq" />
            <CTASection
                section-id="register"
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

        <a
            :href="whatsappUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="group fixed right-4 bottom-5 z-40 inline-flex min-h-14 items-center gap-2.5 rounded-full bg-[#1f9d55] p-2 text-white shadow-xl shadow-emerald-950/20 ring-1 ring-black/5 transition hover:-translate-y-1 hover:bg-[#168346] hover:shadow-2xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-600 sm:right-6 sm:bottom-6 sm:pr-5"
            :aria-label="`${t.whatsapp}: +60 13-407 9388 (opens in a new tab)`"
        >
            <span
                class="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-white/15"
                aria-hidden="true"
            >
                <svg viewBox="0 0 24 24" class="size-6 fill-none stroke-current stroke-[1.8]">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20 11.5a8 8 0 01-11.8 7L4 20l1.5-4.1A8 8 0 1120 11.5z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9.1 8.2c.2-.4.4-.4.7-.4h.4c.2 0 .3.1.4.4l.7 1.6c.1.3.1.4-.1.7l-.5.7c-.2.2-.1.4 0 .6.5.9 1.3 1.7 2.2 2.2.2.1.4.2.6 0l.8-1c.2-.2.4-.3.7-.2l1.7.8c.3.1.4.3.4.5 0 .5-.2 1.3-.6 1.7-.4.5-1.2.8-2 .8-1 0-2.8-.6-4.6-2.2-1.5-1.3-2.7-3.2-3-4.5-.2-.8 0-1.4.3-1.7z"
                    />
                </svg>
                <span
                    class="absolute right-0 top-0 size-2.5 rounded-full bg-lime-300 ring-2 ring-[#1f9d55]"
                />
            </span>
            <span class="hidden text-left sm:block">
                <span class="block text-[10px] font-bold tracking-[0.1em] text-emerald-50 uppercase"
                    >WhatsApp</span
                >
                <span class="block text-sm font-black">{{ t.whatsapp }}</span>
            </span>
        </a>
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
