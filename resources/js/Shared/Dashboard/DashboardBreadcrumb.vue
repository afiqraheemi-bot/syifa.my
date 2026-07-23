<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb">
        <ol class="flex min-w-0 items-center gap-2 text-sm text-slate-500">
            <li
                v-for="(item, index) in items"
                :key="item.key ?? item.label"
                class="flex min-w-0 items-center gap-2"
            >
                <svg
                    v-if="index > 0"
                    aria-hidden="true"
                    class="size-4 shrink-0 text-slate-300"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M7.22 14.53a.75.75 0 0 1 0-1.06L10.69 10 7.22 6.53a.75.75 0 0 1 1.06-1.06l4 4a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0Z"
                        clip-rule="evenodd"
                    />
                </svg>
                <a
                    v-if="item.href && index < items.length - 1"
                    :href="item.href"
                    class="truncate rounded-sm font-medium transition hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    {{ item.label }}
                </a>
                <span
                    v-else
                    :aria-current="index === items.length - 1 ? 'page' : undefined"
                    class="truncate"
                >
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
