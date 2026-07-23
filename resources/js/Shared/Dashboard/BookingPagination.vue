<script setup>
defineProps({
    pagination: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="pagination.hasMore" class="flex justify-end" aria-label="Booking pagination">
        <a
            :href="pagination.nextHref"
            class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
            Next page
        </a>
    </nav>
</template>
