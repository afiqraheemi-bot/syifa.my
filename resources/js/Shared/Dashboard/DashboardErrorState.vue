<script setup>
defineProps({
    title: {
        type: String,
        default: 'Something went wrong',
    },
    description: {
        type: String,
        default: 'The content could not be loaded. Please try again.',
    },
});
</script>

<template>
    <section class="rounded-2xl border border-red-200 bg-red-50 p-6" role="alert">
        <div class="flex items-start gap-4">
            <svg
                aria-hidden="true"
                class="mt-0.5 size-6 shrink-0 text-red-700"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-width="2"
                    d="M12 9v4m0 4h.01M10.3 4.4 3.5 17a2 2 0 0 0 1.8 3h13.4a2 2 0 0 0 1.8-3L13.7 4.4a2 2 0 0 0-3.4 0Z"
                />
            </svg>
            <div>
                <h2 class="font-semibold text-red-950">{{ title }}</h2>
                <p class="mt-1 text-sm leading-6 text-red-800">{{ description }}</p>
                <div v-if="$slots.action" class="mt-4"><slot name="action" /></div>
            </div>
        </div>
    </section>
</template>
