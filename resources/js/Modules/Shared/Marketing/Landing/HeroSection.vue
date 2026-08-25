<script setup>
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import HeroMockup from './HeroMockup.vue';

defineProps({
    copy: { type: Object, required: true },
    registerUrl: { type: String, required: true },
});
</script>

<template>
    <section
        id="top"
        class="relative overflow-hidden bg-[#f7f8f3] pt-12 pb-18 sm:pt-18 sm:pb-24 lg:min-h-[760px] lg:pt-20"
    >
        <div
            class="pointer-events-none absolute -top-48 -left-48 size-[38rem] rounded-full bg-lime-200/25 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -right-40 bottom-0 size-[34rem] rounded-full bg-emerald-100/50 blur-3xl"
        />

        <div
            class="mx-auto grid max-w-[90rem] items-center gap-16 px-5 sm:px-8 lg:grid-cols-[0.94fr_1.06fr] lg:gap-10 lg:px-12"
        >
            <div data-reveal class="reveal relative z-10">
                <AppBadge>{{ copy.eyebrow }}</AppBadge>
                <h1
                    class="mt-7 max-w-2xl text-[2.75rem] leading-[1.02] font-black tracking-[-0.045em] text-slate-950 sm:text-6xl lg:text-[4.65rem]"
                >
                    {{ copy.title }}
                    <span class="block text-emerald-700">{{ copy.titleAccent }}</span>
                </h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-slate-600 sm:text-xl">
                    {{ copy.body }}
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
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
                    <AppButton href="#how-it-works" variant="secondary" size="lg">{{
                        copy.secondaryCta
                    }}</AppButton>
                </div>

                <p class="mt-5 flex items-center gap-2 text-sm font-semibold text-slate-500">
                    <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-emerald-600 stroke-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ copy.trustNote }}
                </p>

                <div
                    class="mt-8 flex flex-wrap gap-x-6 gap-y-3 border-t border-emerald-950/10 pt-6"
                >
                    <div
                        v-for="benefit in copy.benefits"
                        :key="benefit"
                        class="flex items-center gap-2 text-sm font-bold text-slate-700"
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full bg-lime-300 text-emerald-950"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                class="size-3 fill-none stroke-current stroke-[2.4]"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 10.5l3.5 3.5L16 6"
                                />
                            </svg>
                        </span>
                        {{ benefit }}
                    </div>
                </div>
            </div>

            <div data-reveal class="reveal lg:pl-4"><HeroMockup /></div>
        </div>
    </section>
</template>
