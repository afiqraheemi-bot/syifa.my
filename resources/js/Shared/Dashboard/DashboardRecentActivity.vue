<script setup>
import DashboardEmptyState from './DashboardEmptyState.vue';

defineProps({
    activity: {
        type: Array,
        required: true,
    },
    title: {
        type: String,
        default: 'Recent activity',
    },
    eyebrow: {
        type: String,
        default: 'Workspace record',
    },
    emptyTitle: {
        type: String,
        default: 'No recent activity',
    },
    emptyDescription: {
        type: String,
        default: 'Activity from your workspace will appear here when it becomes available.',
    },
});
</script>

<template>
    <section aria-labelledby="recent-activity-title">
        <p class="text-xs font-black tracking-[0.14em] text-emerald-700 uppercase">
            {{ eyebrow }}
        </p>
        <h2
            id="recent-activity-title"
            class="mt-1 text-xl font-black tracking-tight text-slate-950"
        >
            {{ title }}
        </h2>
        <ul
            v-if="activity.length"
            class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <li v-for="item in activity" :key="item.key" class="flex gap-3 px-5 py-4 sm:px-6">
                <span
                    class="mt-1 size-2 shrink-0 rounded-full bg-emerald-500 ring-4 ring-emerald-50"
                />
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ item.title }}</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ item.description }}</p>
                    <time
                        v-if="item.occurredAt"
                        :datetime="item.occurredAt"
                        class="mt-2 block text-xs font-medium text-slate-500"
                    >
                        {{ item.occurredAtLabel }}
                    </time>
                </div>
            </li>
        </ul>
        <DashboardEmptyState
            v-else
            class="mt-4"
            :title="emptyTitle"
            :description="emptyDescription"
        />
    </section>
</template>
