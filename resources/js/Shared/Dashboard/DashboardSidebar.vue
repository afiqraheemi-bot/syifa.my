<script setup>
defineProps({
    navigation: {
        type: Array,
        default: () => [],
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    mobileOpen: {
        type: Boolean,
        default: false,
    },
    productName: {
        type: String,
        default: 'SYIFA.my',
    },
});

defineEmits(['close-mobile', 'toggle-collapse']);
</script>

<template>
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[1px] lg:hidden"
        aria-hidden="true"
        @click="$emit('close-mobile')"
    />

    <aside
        id="dashboard-navigation"
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-800 bg-slate-950 text-white transition-[transform,width] duration-200 lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
            collapsed ? 'lg:w-20' : 'lg:w-72',
        ]"
        :aria-label="`${productName} dashboard navigation`"
        @keydown.esc="$emit('close-mobile')"
    >
        <div class="flex min-h-16 items-center gap-3 border-b border-white/10 px-4">
            <span
                aria-hidden="true"
                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-lg font-black text-slate-950"
            >
                S
            </span>
            <span v-show="!collapsed" class="truncate text-lg font-bold tracking-tight">{{
                productName
            }}</span>
            <button
                type="button"
                class="ml-auto inline-flex size-10 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 lg:hidden"
                aria-label="Close navigation"
                @click="$emit('close-mobile')"
            >
                <svg
                    aria-hidden="true"
                    class="size-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Dashboard">
            <template v-for="entry in navigation" :key="entry.key">
                <div v-if="entry.kind === 'group'" class="mb-6">
                    <p
                        v-show="!collapsed"
                        class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400"
                    >
                        {{ entry.label }}
                    </p>
                    <a
                        v-for="item in entry.items"
                        :key="item.key"
                        :href="item.href"
                        :aria-current="item.current ? 'page' : undefined"
                        :title="collapsed ? item.label : undefined"
                        :class="[
                            'mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400',
                            item.current
                                ? 'bg-emerald-500 text-slate-950'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white',
                            collapsed ? 'lg:justify-center' : '',
                        ]"
                    >
                        <span v-if="item.icon" aria-hidden="true" class="shrink-0">{{
                            item.icon
                        }}</span>
                        <span v-show="!collapsed" class="truncate">{{ item.label }}</span>
                    </a>
                </div>
                <a
                    v-else
                    :href="entry.href"
                    :aria-current="entry.current ? 'page' : undefined"
                    :title="collapsed ? entry.label : undefined"
                    :class="[
                        'mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400',
                        entry.current
                            ? 'bg-emerald-500 text-slate-950'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white',
                        collapsed ? 'lg:justify-center' : '',
                    ]"
                >
                    <span v-if="entry.icon" aria-hidden="true" class="shrink-0">{{
                        entry.icon
                    }}</span>
                    <span v-show="!collapsed" class="truncate">{{ entry.label }}</span>
                </a>
            </template>
        </nav>

        <div class="hidden border-t border-white/10 p-3 lg:block">
            <button
                type="button"
                class="flex min-h-11 w-full items-center justify-center gap-3 rounded-lg text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
                :aria-label="collapsed ? 'Expand navigation' : 'Collapse navigation'"
                :aria-expanded="!collapsed"
                @click="$emit('toggle-collapse')"
            >
                <svg
                    aria-hidden="true"
                    :class="['size-5 transition-transform', collapsed ? 'rotate-180' : '']"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-width="2" d="m15 18-6-6 6-6" />
                </svg>
                <span v-show="!collapsed">Collapse navigation</span>
            </button>
        </div>
    </aside>
</template>
