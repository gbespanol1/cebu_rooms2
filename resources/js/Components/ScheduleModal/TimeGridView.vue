<script setup>
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faTag, faLock, faClock } from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    viewMode: { type: String, required: true }, // 'day' or 'week'
    weekDays: { type: Array, required: false, default: () => [] },
    singleDay: { type: Object, required: false, default: () => ({}) },
    hourSlots: { type: Array, required: true },
    getEventStyle: { type: Function, required: true },
    dateToTimeString: { type: Function, required: true },
    formatEventTime: { type: Function, required: true },
    events: { type: Array, default: () => [] }
});

const emit = defineEmits(['selectEvent', 'selectDate', 'emitDateClick']);

const daysToRender = computed(() => {
    return props.viewMode === 'day' ? [props.singleDay] : props.weekDays;
});

// Format date for display
const formatDateDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Get day name for header
const getDayName = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { weekday: 'long' });
};

// Get month and day for header
const getMonthDay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Check if a specific time slot is occupied by any event
const isTimeSlotOccupied = (day, slot) => {
    const slotStart = new Date(day.date);
    slotStart.setHours(slot.hour, slot.minute, 0, 0);

    const slotEnd = new Date(slotStart);
    slotEnd.setMinutes(slotEnd.getMinutes() + 30); // Each slot is 30 minutes

    return day.events.some(event => {
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);

        return slotStart < eventEnd && slotEnd > eventStart;
    });
};

// Get overlapping events for a time slot
const getOverlappingEvents = (day, slot) => {
    const slotStart = new Date(day.date);
    slotStart.setHours(slot.hour, slot.minute, 0, 0);

    const slotEnd = new Date(slotStart);
    slotEnd.setMinutes(slotEnd.getMinutes() + 30);

    return day.events.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return slotStart < eventEnd && slotEnd > eventStart;
    });
};

// Get event color based on type
const getEventColor = (event) => {
    const type = event.extendedProps?.type?.toLowerCase() || event.list?.toLowerCase() || '';
    if (type.includes('class')) return 'bg-blue-500 border-blue-600';
    if (type.includes('meeting')) return 'bg-green-500 border-green-600';
    if (type.includes('event')) return 'bg-purple-500 border-purple-600';
    return 'bg-red-500 border-red-600';
};

// Get occupancy color for time slots
const getOccupancyColor = (day, slot) => {
    const overlappingEvents = getOverlappingEvents(day, slot);

    if (overlappingEvents.length === 0) {
        return 'hover:bg-blue-100/50';
    } else if (overlappingEvents.length === 1) {
        const eventType = overlappingEvents[0].extendedProps?.type?.toLowerCase() || '';
        if (eventType.includes('class')) return 'bg-blue-100/70 hover:bg-blue-200/70';
        if (eventType.includes('meeting')) return 'bg-green-100/70 hover:bg-green-200/70';
        if (eventType.includes('event')) return 'bg-purple-100/70 hover:bg-purple-200/70';
        return 'bg-red-100/70 hover:bg-red-200/70';
    } else {
        return 'bg-yellow-100/70 hover:bg-yellow-200/70';
    }
};

const isTodayHeader = (day) => {
    const today = new Date();
    const dayDate = new Date(day.date);
    return dayDate.toDateString() === today.toDateString();
};

// Accurate time slot click handler
const handleTimeSlotClick = (day, slot) => {
    if (isTimeSlotOccupied(day, slot)) {
        const overlappingEvents = getOverlappingEvents(day, slot);
        if (overlappingEvents.length > 0) {
            const eventNames = overlappingEvents.map(e => e.title || e.extendedProps?.subject).join(', ');
            const eventRooms = overlappingEvents.map(e => e.extendedProps?.room).join(', ');
            alert(`This time slot is already occupied by:\n\n${eventNames}\nRooms: ${eventRooms}\n\nPlease choose another time.`);
            return;
        }
    }

    const exactDate = new Date(day.date);
    exactDate.setHours(slot.hour, slot.minute, 0, 0);
    emit('emitDateClick', exactDate, slot.hour, slot.minute);
};

// Get occupancy tooltip text
const getOccupancyTooltip = (day, slot) => {
    const overlappingEvents = getOverlappingEvents(day, slot);

    if (overlappingEvents.length === 0) {
        return `Available at ${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}\nClick to schedule`;
    }

    const eventsInfo = overlappingEvents.map(event => {
        const time = props.formatEventTime(event);
        const title = event.title || event.extendedProps?.subject || 'Untitled';
        const room = event.extendedProps?.room || 'Unknown Room';
        const type = event.extendedProps?.type || 'Event';
        return `${time}: ${title} (${room}) - ${type}`;
    }).join('\n');

    return `Occupied at ${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}\n\n${eventsInfo}`;
};

// Format time for slot labels
const formatSlotTime = (hour, minute) => {
    const time = new Date(0, 0, 0, hour, minute);
    return time.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};
</script>

<template>
    <div class="time-grid-view border border-yellow-300 rounded-lg shadow-lg bg-white">
        <!-- Header with day labels -->
        <div class="bg-[#7A0C23] flex border-b border-yellow-600">
            <div class="w-20 shrink-0 bg-[#7A0C23]"></div>
            <div class="flex-grow grid" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <div
                    v-for="(day, index) in daysToRender"
                    :key="index"
                    @click="viewMode === 'week' ? emit('selectDate', day.date) : null"
                    :class="[
                        'p-3 text-center border-r border-yellow-500 last:border-r-0 cursor-pointer transition duration-150',
                        isTodayHeader(day) ? 'bg-red-800 text-white' : 'bg-[#7A0C23] text-white',
                        viewMode === 'week' ? 'hover:bg-[#8A1C33]' : ''
                    ]"
                >
                    <div class="font-bold text-lg">{{ getDayName(day.date) }}</div>
                    <div class="text-sm opacity-90">{{ getMonthDay(day.date) }}</div>
                    <div v-if="viewMode === 'day'" class="text-xs mt-1 font-normal">
                        {{ formatDateDisplay(day.date) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- All Day Events Section -->
        <div class="bg-[#7A0C23] flex border-b border-yellow-600">
            <div class="bg-[#7A0C23] w-20 shrink-0 border-r border-yellow-500 py-2 px-1 text-xs font-bold text-white flex items-center justify-center">
                All Day
            </div>
            <div class="flex-grow grid" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <div
                    v-for="(day, index) in daysToRender"
                    :key="index"
                    class="p-1 border-r border-yellow-500 last:border-r-0 min-h-[40px]"
                >
                    <div
                        v-for="event in day.allDayEvents"
                        :key="event.id"
                        @click="emit('selectEvent', event)"
                        class="text-xs p-2 mb-1 rounded-md cursor-pointer bg-indigo-100 text-indigo-800 hover:bg-indigo-200 transition truncate border-l-4 border-indigo-600"
                        :title="event.title || event.extendedProps?.subject"
                    >
                        <FontAwesomeIcon :icon="faTag" class="w-3 h-3 mr-1 inline" />
                        {{ event.title || event.extendedProps?.subject }}
                    </div>
                    <div
                        v-if="day.allDayEvents.length === 0"
                        class="text-xs text-yellow-400 italic py-2 text-center"
                    >
                        No all-day events
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Grid -->
        <div class="flex h-[calc(100vh-300px)] overflow-y-auto relative">
            <!-- Time Labels (Left Side) -->
            <div class="w-20 shrink-0 border-r border-yellow-500 bg-gray-800 sticky left-0 z-30">
                <div class="relative w-full h-full">
                    <div
                        v-for="(slot, index) in hourSlots"
                        :key="index"
                        class="bg-[#7A0C23] absolute w-full text-xs text-white text-right pr-2 border-b border-gray-700"
                        :style="`top: ${index * 2.5}rem; height: 2.5rem;`"
                    >
                        <!-- Show time label at 00 and 30 minutes -->
                        <span
                            v-if="slot.minute === 0 || slot.minute === 30"
                            class="absolute top-2 right-1 font-bold"
                        >
                            {{ formatSlotTime(slot.hour, slot.minute) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Time Grid Content -->
            <div class="flex-grow grid relative" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <!-- Time Slots for each day -->
                <div
                    v-for="(day, dayIndex) in daysToRender"
                    :key="dayIndex"
                    class="border-r border-yellow-500 last:border-r-0 relative"
                >
                    <!-- Time Slot Grid -->
                    <div
                        v-for="(slot, slotIndex) in hourSlots"
                        :key="slotIndex"
                        @click="handleTimeSlotClick(day, slot)"
                        :class="[
                            'h-10 border-b border-gray-300 transition duration-100 cursor-pointer relative group',
                            getOccupancyColor(day, slot),
                            slot.minute === 0 ? 'border-t border-gray-400' : ''
                        ]"
                        :title="getOccupancyTooltip(day, slot)"
                    >
                        <!-- Occupied indicator -->
                        <div
                            v-if="isTimeSlotOccupied(day, slot)"
                            class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <div class="bg-black/50 text-white text-xs px-2 py-1 rounded flex items-center">
                                <FontAwesomeIcon :icon="faLock" class="w-3 h-3 mr-1" />
                                Occupied
                            </div>
                        </div>

                        <!-- Half-hour indicator -->
                        <div
                            v-if="slot.minute === 30"
                            class="absolute top-0 left-0 right-0 border-t border-dashed border-gray-300"
                        ></div>
                    </div>

                    <!-- Events -->
                    <div
                        v-for="event in day.events"
                        :key="event.id"
                        @click="emit('selectEvent', event)"
                        class="absolute left-1 right-1 mx-0.5 p-2 rounded-md cursor-pointer text-white shadow-lg z-20 overflow-hidden border-l-4"
                        :class="[getEventColor(event)]"
                        :style="props.getEventStyle(event)"
                        :title="`${props.formatEventTime(event)}: ${event.title || event.extendedProps?.subject} - ${event.extendedProps?.room || 'Unknown Room'}`"
                    >
                        <p class="text-xs font-bold leading-tight truncate">
                            {{ event.title || event.extendedProps?.subject }}
                        </p>
                        <p class="text-[0.7rem] leading-tight opacity-90">
                            {{ props.formatEventTime(event) }}
                        </p>
                        <p v-if="event.extendedProps?.room" class="text-[0.6rem] leading-tight opacity-75 truncate">
                            {{ event.extendedProps.room }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="bg-gray-50 border-t border-gray-200 p-3">
            <div class="flex flex-wrap items-center justify-center gap-4 text-xs">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-100/70 rounded mr-2"></div>
                    <span>Class Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-100/70 rounded mr-2"></div>
                    <span>Meeting Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-purple-100/70 rounded mr-2"></div>
                    <span>Event Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-100/70 rounded mr-2"></div>
                    <span>Other Activity of</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-white border border-gray-300 rounded mr-2"></div>
                    <span>Available</span>
                </div>
                <div class="flex items-center ml-4 text-gray-600">
                    <FontAwesomeIcon :icon="faLock" class="w-3 h-3 mr-1" />
                    <span>Hover over occupied slots for details</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.time-grid-view {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.w-20 {
    min-width: 5rem;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Ensure events are properly positioned */
.absolute {
    position: absolute;
}

/* Group hover effects */
.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

/* Occupied slot styling */
.bg-blue-100\/70 {
    background-color: rgba(219, 234, 254, 0.7);
}
.bg-green-100\/70 {
    background-color: rgba(209, 250, 229, 0.7);
}
.bg-purple-100\/70 {
    background-color: rgba(233, 213, 255, 0.7);
}
.bg-red-100\/70 {
    background-color: rgba(254, 226, 226, 0.7);
}
.bg-yellow-100\/70 {
    background-color: rgba(254, 249, 195, 0.7);
}

/* Available slot hover */
.hover\:bg-blue-100\/50:hover {
    background-color: rgba(219, 234, 254, 0.5);
}
</style>
