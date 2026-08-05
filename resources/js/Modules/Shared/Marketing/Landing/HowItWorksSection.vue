<script setup>
import SectionHeader from './SectionHeader.vue';
import StepCard from './StepCard.vue';

defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, steps: [{ id, title, body }] }
});

const icons = {
    form: 'M9 12h6M9 16h6M9 8h1M4 4h16v16H4z',
    call: 'M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 2 .6 2.9a2 2 0 01-.4 2.1L8 10a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.9.5 2.9.6a2 2 0 011.7 2.1z',
    collect: 'M3 7h5l2 3h11v9a1 1 0 01-1 1H4a1 1 0 01-1-1V7z',
    build: 'M4 4h16v12H4zM8 20h8M12 16v4',
    launch: 'M14.5 2.5c3 1 5 3 6 6-1 3-3 5-6 6M9.5 9.5L2 17l5 5 7.5-7.5M9.5 9.5l5 5M6 20l-1.5 1.5',
};
</script>

<template>
    <section id="how-it-works" class="anchor-section py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-10">
            <SectionHeader :eyebrow="copy.eyebrow" :title="copy.title" align="center" />

            <ol class="mt-14">
                <StepCard
                    v-for="(step, index) in copy.steps"
                    :key="step.id"
                    :number="index + 1"
                    :icon="icons[step.id]"
                    :title="step.title"
                    :body="step.body"
                    :last="index === copy.steps.length - 1"
                />
            </ol>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
