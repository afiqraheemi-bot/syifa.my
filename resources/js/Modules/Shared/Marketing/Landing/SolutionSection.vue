<script setup>
import FeatureCard from './FeatureCard.vue';
import SectionHeader from './SectionHeader.vue';

defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, subtitle, items: [{ id, title, body }] }
});

const icons = {
    website: 'M4 4h16v16H4zM4 9h16M9 9v11',
    booking:
        'M8 3v4M16 3v4M4 9h16M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2zM8 14h3',
    seo: 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3',
    hosting: 'M12 2l8 4v6c0 5-3.4 9-8 10-4.6-1-8-5-8-10V6l8-4zM9 12l2 2 4-4',
    mobile: 'M9 3h6a2 2 0 012 2v14a2 2 0 01-2 2H9a2 2 0 01-2-2V5a2 2 0 012-2zM11 18h2',
    free: 'M20 7h-3.5a2.5 2.5 0 100-5 4 4 0 00-3.5 2M4 7h3.5a2.5 2.5 0 110-5 4 4 0 013.5 2M4 7h16v5H4zM6 12v7a1 1 0 001 1h10a1 1 0 001-1v-7',
};
</script>

<template>
    <section id="features" class="anchor-section bg-slate-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <SectionHeader
                :eyebrow="copy.eyebrow"
                :title="copy.title"
                :subtitle="copy.subtitle"
                align="center"
            />

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <FeatureCard
                    v-for="item in copy.items"
                    :key="item.id"
                    :icon="icons[item.id]"
                    :title="item.title"
                    :body="item.body"
                />
            </div>
        </div>
    </section>
</template>

<style scoped>
.anchor-section {
    scroll-margin-top: 5.5rem;
}
</style>
