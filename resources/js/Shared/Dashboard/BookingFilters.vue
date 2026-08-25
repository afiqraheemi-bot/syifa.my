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
        class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_14rem_14rem_auto] lg:p-5"
    >
        <label class="block">
            <span class="text-sm font-bold text-slate-700">Cari tempahan</span>
            <input
                name="search"
                type="search"
                :value="search.value ?? ''"
                :placeholder="search.placeholder"
                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50/60 px-3 py-2 text-sm text-slate-950 transition focus:border-emerald-700 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
            />
        </label>
        <label class="block">
            <span class="text-sm font-bold text-slate-700">Status</span>
            <select
                name="status"
                :value="filters.status.value ?? ''"
                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50/60 px-3 py-2 text-sm text-slate-950 focus:border-emerald-700 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
            >
                <option value="">Semua status</option>
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
            <span class="text-sm font-bold text-slate-700">Sumber</span>
            <select
                name="source"
                :value="filters.source.value ?? ''"
                class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-slate-50/60 px-3 py-2 text-sm text-slate-950 focus:border-emerald-700 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100"
            >
                <option value="">Semua sumber</option>
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
                Tapis
            </button>
        </div>
    </form>
</template>
