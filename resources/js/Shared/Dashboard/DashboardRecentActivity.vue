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
        <h2 id="recent-activity-title" class="text-lg font-bold text-slate-950">{{ title }}</h2>
        <ul
            v-if="activity.length"
            class="mt-4 divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white"
        >
            <li v-for="item in activity" :key="item.key" class="px-5 py-4 sm:px-6">
                <p class="font-semibold text-slate-900">{{ item.title }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                <time
                    v-if="item.occurredAt"
                    :datetime="item.occurredAt"
                    class="mt-2 block text-xs text-slate-500"
                >
                    {{ item.occurredAtLabel }}
                </time>
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
