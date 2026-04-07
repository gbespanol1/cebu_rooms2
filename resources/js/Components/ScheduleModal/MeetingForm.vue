<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    formData: Object
});
</script>

<template>
  <div>
    <h4 class="text-md font-semibold mb-2 text-gray-800">Meeting Details</h4>
    <label for="agenda" class="block text-sm font-medium text-gray-700">Agenda*</label>
    <input type="text" id="agenda" v-model="formData.agenda" required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="requester" class="block text-sm font-medium text-gray-700 mt-4">Requester*</label>
    <input type="text" id="requester" v-model="formData.requester" required placeholder="Search"
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    </div>
</template>
