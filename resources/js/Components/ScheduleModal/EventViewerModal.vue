<script setup>
import { formatDateDisplay, formatTimeDisplay } from './utils/scheduleHelpers';

defineProps({
  isVisible: Boolean,
  event: Object
});

defineEmits(['close', 'edit', 'delete']);
</script>

<template>
  <div v-if="isVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
      <div class="flex justify-between items-center mb-4 sticky top-0 bg-white z-10 p-1 -m-1">
        <h3 class="text-xl font-bold">Appointment Details</h3>
        <button @click="$emit('close')" class="text-xl font-semibold">X</button>
      </div>

      <div v-if="event" class="space-y-4">
        <!-- Basic Info -->
        <div class="bg-gray-50 p-4 rounded-lg">
          <h4 class="text-lg font-bold text-[#7A0C23] mb-2">{{ event.title }}</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-semibold text-gray-600">Room:</p>
              <p class="text-base">{{ event.extendedProps?.room || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-600">Building:</p>
              <p class="text-base">{{ event.extendedProps?.building || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-600">College/Office:</p>
              <p class="text-base">{{ event.extendedProps?.college || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-600">Type:</p>
              <p class="text-base">{{ event.extendedProps?.type || event.list || 'Event' }}</p>
            </div>
          </div>
        </div>

        <!-- Date & Time -->
        <div class="bg-blue-50 p-4 rounded-lg">
          <h5 class="font-semibold text-blue-800 mb-2">Date & Time</h5>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-semibold text-blue-600">Date:</p>
              <p class="text-base">{{ formatDateDisplay(event.start) }}</p>
            </div>
            <div v-if="!event.allDay">
              <p class="text-sm font-semibold text-blue-600">Time:</p>
              <p class="text-base">{{ formatTimeDisplay(event.start) }} - {{ formatTimeDisplay(event.end) }}</p>
            </div>
            <div v-else>
              <p class="text-sm font-semibold text-blue-600">Time:</p>
              <p class="text-base">All Day</p>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div v-if="event.extendedProps?.description" class="bg-green-50 p-4 rounded-lg">
          <h5 class="font-semibold text-green-800 mb-2">Description</h5>
          <p class="text-base">{{ event.extendedProps.description }}</p>
        </div>

        <!-- Requester -->
        <div v-if="event.extendedProps?.requester" class="bg-yellow-50 p-4 rounded-lg">
          <h5 class="font-semibold text-yellow-800 mb-2">Requester</h5>
          <p class="text-base">{{ event.extendedProps.requester }}</p>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex justify-end space-x-3">
          <button @click="$emit('close')" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
            Close
          </button>
          <button @click="$emit('edit', event)" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
            Edit
          </button>
          <button @click="$emit('delete', event)" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
