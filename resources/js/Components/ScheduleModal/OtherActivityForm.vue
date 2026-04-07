<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    formData: Object
});
</script>

<template>
  <div>
    <h4 class="text-md font-semibold mb-2 text-gray-800">Activity Details</h4>
    <label for="name" class="block text-sm font-medium text-gray-700">Name*</label>
    <input type="text" id="name" v-model="formData.name" required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="requester" class="block text-sm font-medium text-gray-700 mt-4">Requester*</label>
    <input type="text" id="requester" v-model="formData.requester" required placeholder="Search"
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    </div>
</template>
