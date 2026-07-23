<script setup>
defineProps({
    search: { type: Object, required: true },
    filters: { type: Object, required: true },
    perPage: { type: Number, required: true },
});
</script>

<template>
    <form
        :action="search.action"
        method="get"
        class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-[1fr_14rem_14rem_auto]"
    >
        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Search</span>
            <input
                name="search"
                type="search"
                :value="search.value ?? ''"
                :placeholder="search.placeholder"
                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-950 focus-visible:border-emerald-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            />
        </label>
        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Status</span>
            <select
                name="status"
                :value="filters.status.value ?? ''"
                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-950 focus-visible:border-emerald-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                <option value="">All statuses</option>
                <option
                    v-for="option in filters.status.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </label>
        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Source</span>
            <select
                name="source"
                :value="filters.source.value ?? ''"
                class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-950 focus-visible:border-emerald-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                <option value="">All sources</option>
                <option
                    v-for="option in filters.source.options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </label>
        <input type="hidden" name="per_page" :value="perPage" />
        <div class="flex self-end gap-2">
            <a
                :href="search.action"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Reset
            </a>
            <button
                type="submit"
                class="min-h-11 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            >
                Apply
            </button>
        </div>
    </form>
</template>
