<script setup>
import { ref } from 'vue';
import FAQAccordion from './FAQAccordion.vue';
import SectionHeader from './SectionHeader.vue';

defineProps({
    copy: { type: Object, required: true }, // { eyebrow, title, items: [{ id, question, answer }] }
});

const openId = ref(null);

function toggle(id) {
    openId.value = openId.value === id ? null : id;
}
</script>

<template>
    <section id="faq" class="anchor-section py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-10">
            <SectionHeader :eyebrow="copy.eyebrow" :title="copy.title" align="center" />

            <div class="mt-12 border-t border-slate-100">
                <FAQAccordion
                    v-for="item in copy.items"
                    :key="item.id"
                    :question="item.question"
                    :answer="item.answer"
                    :open="openId === item.id"
                    :panel-id="`faq-panel-${item.id}`"
                    @toggle="toggle(item.id)"
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
