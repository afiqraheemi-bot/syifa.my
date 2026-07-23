<script setup>
defineProps({
    actions: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section aria-labelledby="quick-actions-title">
        <h2 id="quick-actions-title" class="text-lg font-bold text-slate-950">Quick actions</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <template v-for="action in actions" :key="action.key">
                <a
                    v-if="action.available && action.href"
                    :href="action.href"
                    class="rounded-xl border border-slate-200 bg-white p-5 transition hover:border-emerald-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <span class="font-semibold text-slate-950">{{ action.label }}</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-600">{{
                        action.description
                    }}</span>
                </a>
                <div
                    v-else
                    class="rounded-xl border border-slate-200 bg-slate-100 p-5"
                    aria-disabled="true"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-slate-700">{{ action.label }}</span>
                        <span
                            class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600"
                        >
                            Coming later
                        </span>
                    </div>
                    <span class="mt-1 block text-sm leading-6 text-slate-600">{{
                        action.description
                    }}</span>
                </div>
            </template>
        </div>
    </section>
</template>
