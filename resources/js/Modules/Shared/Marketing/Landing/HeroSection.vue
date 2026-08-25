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
        class="relative overflow-hidden border-b border-slate-200 bg-[#fafbf8] py-12 sm:py-14 lg:flex lg:min-h-[calc(100svh-7rem)] lg:max-h-[740px] lg:items-center lg:py-12"
    >
        <div
            class="pointer-events-none absolute -top-48 -left-48 size-[38rem] rounded-full bg-emerald-100/20 blur-3xl"
        />
        <div
            class="pointer-events-none absolute -right-40 bottom-0 size-[34rem] rounded-full bg-emerald-100/25 blur-3xl"
        />

        <div
            class="mx-auto grid w-full max-w-[84rem] items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1fr_0.96fr] lg:gap-16 lg:px-12"
        >
            <div data-reveal class="reveal relative z-10">
                <AppBadge>{{ copy.eyebrow }}</AppBadge>
                <h1
                    class="mt-6 max-w-2xl text-[2.5rem] leading-[1.07] font-extrabold tracking-[-0.035em] text-slate-950 sm:text-[3.35rem] lg:text-[3.45rem] xl:text-[3.7rem]"
                >
                    {{ copy.title }}
                    <span class="mt-1 block text-emerald-800">{{ copy.titleAccent }}</span>
                </h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                    {{ copy.body }}
                </p>

                <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
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

                <p class="mt-4 flex items-center gap-2 text-sm font-semibold text-slate-600">
                    <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-emerald-600 stroke-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ copy.trustNote }}
                </p>

                <div class="mt-7 grid max-w-xl gap-3 border-t border-slate-200 pt-5 sm:grid-cols-3">
                    <div
                        v-for="benefit in copy.benefits"
                        :key="benefit"
                        class="flex items-center gap-2 text-sm font-semibold leading-5 text-slate-600"
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-800"
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
