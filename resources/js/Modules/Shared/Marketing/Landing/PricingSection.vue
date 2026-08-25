<script setup>
import { computed } from 'vue';
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import SectionHeader from './SectionHeader.vue';

const props = defineProps({
    packages: { type: Array, required: true },
    registerUrl: { type: String, required: true },
    lang: { type: String, required: true },
});

const copy = computed(() =>
    props.lang === 'en'
        ? {
              eyebrow: 'Simple pricing',
              title: 'Choose the right package for your clinic.',
              body: 'Every package includes guided onboarding and a managed clinic website. Final availability is confirmed during registration.',
              cycle: 'billing cycle',
              included: 'Managed setup included',
              cta: 'Register with this package',
              trialCta: 'Start free trial',
              trial: 'Free trial',
              recommended: 'Recommended',
              noCharge: 'No charge during trial',
              empty: 'Package pricing will be available soon.',
              compareTitle: 'Basic or Pro?',
              compareBody:
                  'Both cover the essentials. All 5 templates and Clinic Blog publishing are available only with Pro.',
              basicLabel: 'Start with the essentials',
              basicSummary: 'Managed website + online booking',
              proLabel: 'Grow with more capability',
              proSummary: 'Everything in Basic + AI + custom domain + clinic Blog',
              bestFor: 'Best for',
              includes: 'What you get',
              everythingBasic: 'Everything in Basic, plus:',
              basicAudience:
                  'Clinics that need a professional website and organised online bookings.',
              proAudience: 'Clinics ready to automate work and strengthen their online brand.',
              trialAudience: 'Clinics that want to explore the core workflow before subscribing.',
              basicFeatures: [
                  'Managed clinic website',
                  'Online booking system',
                  'Content and branding management',
                  '1 website template',
              ],
              proFeatures: [
                  'SYIFA AI Assistant',
                  'Your own custom domain',
                  'SEO-friendly clinic Blog with article metadata and sitemap',
                  'Managed website and online booking',
                  'All 5 website templates',
              ],
              trialFeatures: [
                  'Explore the managed website',
                  'Test the online booking flow',
                  'No charge during the trial',
                  '1 website template',
              ],
          }
        : {
              eyebrow: 'Harga yang jelas',
              title: 'Pilih pakej yang sesuai untuk klinik anda.',
              body: 'Setiap pakej merangkumi onboarding berpandu dan website klinik terurus. Ketersediaan akhir disahkan semasa pendaftaran.',
              cycle: 'kitaran bil',
              included: 'Setup terurus disertakan',
              cta: 'Daftar dengan pakej ini',
              trialCta: 'Mulakan percubaan percuma',
              trial: 'Percubaan percuma',
              recommended: 'Disyorkan',
              noCharge: 'Tiada caj sepanjang tempoh percubaan',
              empty: 'Harga pakej akan tersedia tidak lama lagi.',
              compareTitle: 'Basic atau Pro?',
              compareBody:
                  'Kedua-duanya merangkumi keperluan utama. Semua 5 templat dan Blog klinik hanya tersedia dalam Pro.',
              basicLabel: 'Mulakan dengan keperluan utama',
              basicSummary: 'Website terurus + tempahan online',
              proLabel: 'Berkembang dengan lebih keupayaan',
              proSummary: 'Semua dalam Basic + AI + custom domain + Blog klinik',
              bestFor: 'Paling sesuai untuk',
              includes: 'Apa yang anda dapat',
              everythingBasic: 'Semua dalam Basic, ditambah:',
              basicAudience:
                  'Klinik yang perlukan website profesional dan tempahan online yang teratur.',
              proAudience:
                  'Klinik yang bersedia mengautomasikan kerja dan mengukuhkan jenama online.',
              trialAudience: 'Klinik yang mahu mencuba aliran utama sebelum melanggan.',
              basicFeatures: [
                  'Website klinik terurus',
                  'Sistem tempahan online',
                  'Pengurusan kandungan dan penjenamaan',
                  '1 templat website',
              ],
              proFeatures: [
                  'SYIFA AI Assistant',
                  'Custom domain milik anda',
                  'Blog klinik mesra SEO dengan metadata artikel dan sitemap',
                  'Website terurus dan tempahan online',
                  'Semua 5 templat website',
              ],
              trialFeatures: [
                  'Cuba website terurus',
                  'Uji aliran tempahan online',
                  'Tiada caj sepanjang percubaan',
                  '1 templat website',
              ],
          },
);

function packageKind(item) {
    const name = String(item.name).toLowerCase();
    if (item.isTrial || name.includes('trial')) return 'trial';
    if (name.includes('pro')) return 'pro';
    if (name.includes('basic')) return 'basic';
    return 'other';
}

function isFeatured(item) {
    return packageKind(item) === 'pro';
}

function audience(item) {
    return copy.value[`${packageKind(item)}Audience`] ?? item.description;
}

function features(item) {
    return copy.value[`${packageKind(item)}Features`] ?? [copy.value.included];
}
</script>

<template>
    <section id="pricing" class="anchor-section bg-emerald-50/55 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <SectionHeader
                :eyebrow="copy.eyebrow"
                :title="copy.title"
                :subtitle="copy.body"
                align="center"
            />

            <div
                v-if="
                    packages.some((item) => packageKind(item) === 'basic') &&
                    packages.some((item) => packageKind(item) === 'pro')
                "
                class="mx-auto mt-10 max-w-4xl overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm shadow-emerald-900/5"
            >
                <div class="border-b border-emerald-100 px-5 py-5 text-center sm:px-8">
                    <h3 class="text-lg font-black text-slate-950">{{ copy.compareTitle }}</h3>
                    <p class="mx-auto mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                        {{ copy.compareBody }}
                    </p>
                </div>
                <div class="grid md:grid-cols-[1fr_auto_1fr] md:items-stretch">
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-black tracking-[0.14em] text-slate-500 uppercase">
                            Basic
                        </p>
                        <p class="mt-2 font-bold text-slate-950">{{ copy.basicLabel }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ copy.basicSummary }}</p>
                    </div>
                    <div class="relative flex items-center justify-center px-5 md:px-0">
                        <span class="h-px w-full bg-emerald-100 md:h-full md:w-px" />
                        <span
                            class="absolute flex size-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-black text-white ring-4 ring-white"
                            aria-hidden="true"
                            >+</span
                        >
                    </div>
                    <div class="bg-emerald-50/70 p-5 sm:p-6">
                        <p class="text-xs font-black tracking-[0.14em] text-emerald-700 uppercase">
                            Pro
                        </p>
                        <p class="mt-2 font-bold text-slate-950">{{ copy.proLabel }}</p>
                        <p class="mt-1 text-sm font-semibold text-emerald-800">
                            {{ copy.proSummary }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="packages.length"
                class="mt-10 grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="item in packages"
                    :key="item.id"
                    data-reveal
                    class="reveal relative flex flex-col overflow-hidden rounded-2xl border bg-white p-6 shadow-sm shadow-slate-900/5 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-900/10 sm:p-7"
                    :class="
                        isFeatured(item)
                            ? 'border-emerald-500 ring-2 ring-emerald-100'
                            : 'border-emerald-100'
                    "
                >
                    <div v-if="isFeatured(item)" class="absolute right-5 top-5">
                        <AppBadge>{{ copy.recommended }}</AppBadge>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">
                        {{ item.isTrial ? copy.trial : item.billingCycle }}
                    </p>
                    <h3 class="mt-3 pr-24 text-2xl font-black text-slate-950">{{ item.name }}</h3>
                    <p class="mt-3 min-h-12 text-sm leading-6 text-slate-600">
                        {{ item.description }}
                    </p>
                    <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                        <p
                            class="text-[11px] font-black tracking-[0.12em] text-slate-500 uppercase"
                        >
                            {{ copy.bestFor }}
                        </p>
                        <p class="mt-1.5 text-sm font-semibold leading-5 text-slate-800">
                            {{ audience(item) }}
                        </p>
                    </div>
                    <div class="mt-7 border-t border-slate-100 pt-6">
                        <p class="text-4xl font-black tracking-tight text-slate-950">
                            {{ item.price }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-500">
                            {{
                                item.isTrial
                                    ? copy.noCharge
                                    : `${item.billingCycle} · ${copy.cycle}`
                            }}
                        </p>
                    </div>
                    <div class="mt-6 flex-1">
                        <p class="text-xs font-black tracking-[0.12em] text-slate-500 uppercase">
                            {{ packageKind(item) === 'pro' ? copy.everythingBasic : copy.includes }}
                        </p>
                        <ul class="mt-3 space-y-3">
                            <li
                                v-for="feature in features(item)"
                                :key="feature"
                                class="flex gap-2.5 text-sm font-semibold leading-5 text-slate-700"
                            >
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700"
                                    aria-hidden="true"
                                    >✓</span
                                >
                                <span>{{ feature }}</span>
                            </li>
                        </ul>
                    </div>
                    <AppButton
                        :href="registerUrl"
                        :variant="isFeatured(item) ? 'primary' : 'secondary'"
                        size="lg"
                        class="mt-7 w-full justify-center"
                    >
                        {{ item.isTrial ? copy.trialCta : copy.cta }}
                    </AppButton>
                </article>
            </div>
            <p
                v-else
                class="mx-auto mt-10 max-w-xl rounded-2xl border border-emerald-100 bg-white p-6 text-center text-slate-600 shadow-sm"
            >
                {{ copy.empty }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
