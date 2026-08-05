<script setup>
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';

defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, body, primaryCta, secondaryCta, note }
    registerUrl: { type: String, required: true },
    loginUrl: { type: String, required: true },
    sectionId: { type: String, default: null },
});
</script>

<template>
    <section :id="sectionId" class="anchor-section px-4 py-20 sm:px-6 sm:py-28 lg:px-10">
        <div
            data-reveal
            class="reveal relative mx-auto max-w-6xl overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-emerald-700 via-emerald-800 to-emerald-950 px-6 py-16 text-center shadow-2xl shadow-emerald-900/30 sm:px-12 sm:py-20"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(255,255,255,0.12),transparent_50%)]"
            />
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_85%_85%,rgba(255,255,255,0.1),transparent_50%)]"
            />

            <div class="relative mx-auto max-w-2xl">
                <AppBadge tone="inverse">{{ copy.eyebrow }}</AppBadge>
                <h2
                    class="mt-6 text-3xl font-black tracking-tight text-white sm:text-4xl lg:text-5xl"
                >
                    {{ copy.title }}
                </h2>
                <p class="mt-5 text-base leading-7 text-emerald-100/85 sm:text-lg">
                    {{ copy.body }}
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-4">
                    <AppButton
                        :href="registerUrl"
                        variant="secondary"
                        size="lg"
                        class="!bg-white !text-emerald-800 hover:!bg-emerald-50"
                    >
                        {{ copy.primaryCta }}
                    </AppButton>
                    <AppButton :href="loginUrl" variant="ghost-inverse" size="lg">
                        {{ copy.secondaryCta }}
                    </AppButton>
                </div>

                <p class="mt-6 text-sm font-semibold text-emerald-100/80">
                    {{ copy.note }}
                </p>
            </div>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
