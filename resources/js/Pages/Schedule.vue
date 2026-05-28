<script setup>
import Layout from '@/Layouts/schedulelayout.vue';

defineProps({
    schedules: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    faculty: { type: Array, default: () => [] },
    requesters: { type: Array, default: () => [] },
    currentRequester: { type: String, default: '' },
    terms: { type: Array, default: () => [] },
});
</script>

<template>
    <Layout
        :schedules="schedules"
        :rooms="rooms"
        :faculty="faculty"
        :requesters="requesters"
        :current-requester="currentRequester"
        :terms="terms"
    />
</template>
