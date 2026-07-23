<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <section
        class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center sm:px-10"
    >
        <div
            class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500"
        >
            <slot name="icon">
                <svg
                    aria-hidden="true"
                    class="size-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-width="1.8" d="M5 7h14v12H5zM8 4h8v3H8z" />
                </svg>
            </slot>
        </div>
        <h2 class="mt-4 text-base font-semibold text-slate-950">{{ title }}</h2>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-6"><slot name="action" /></div>
    </section>
</template>
