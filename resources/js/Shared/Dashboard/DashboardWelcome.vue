<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl bg-emerald-800 px-6 py-7 text-white sm:px-8 sm:py-9"
    >
        <p class="text-sm font-semibold text-emerald-100">Clinic Owner workspace</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ title }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-emerald-50 sm:text-base">{{ message }}</p>
    </section>
</template>
