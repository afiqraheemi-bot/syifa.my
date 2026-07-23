<script setup>
import DashboardQuickActions from './DashboardQuickActions.vue';

defineProps({
    actions: { type: Array, required: true },
});
</script>

<template>
    <DashboardQuickActions :actions="actions" />
</template>
