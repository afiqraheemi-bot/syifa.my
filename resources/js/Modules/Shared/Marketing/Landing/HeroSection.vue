<script setup>
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import HeroMockup from './HeroMockup.vue';

defineProps({
    copy: { type: Object, required: true },
    registerUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
});
</script>

<template>
    <section id="top" class="relative overflow-hidden bg-white pt-14 pb-20 sm:pt-20 sm:pb-28">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[36rem] bg-gradient-to-b from-emerald-50 to-white"
        />

        <div
            class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-8 lg:px-10"
        >
            <div data-reveal class="reveal">
                <AppBadge>{{ copy.eyebrow }}</AppBadge>
                <h1
                    class="mt-6 max-w-xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl lg:text-[3.25rem] lg:leading-[1.08]"
                >
                    {{ copy.title }}
                    <span class="block text-emerald-700">{{ copy.titleAccent }}</span>
                </h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-slate-600">
                    {{ copy.body }}
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    <AppButton :href="registerUrl" variant="primary" size="lg">
                        {{ copy.primaryCta }}
                        <svg
                            viewBox="0 0 24 24"
                            class="size-4 fill-none stroke-current stroke-[2.5]"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12h14M13 6l6 6-6 6"
                            />
                        </svg>
                    </AppButton>
                    <AppButton :href="loginUrl" variant="secondary" size="lg">
                        {{ copy.secondaryCta }}
                    </AppButton>
                </div>

                <p class="mt-5 flex items-center gap-2 text-sm font-semibold text-slate-500">
                    <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-emerald-600 stroke-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ copy.trustNote }}
                </p>
            </div>

            <div data-reveal class="reveal">
                <HeroMockup />
            </div>
        </div>
    </section>
</template>
