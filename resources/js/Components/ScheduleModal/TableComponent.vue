<script setup>
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faEye, faPenToSquare, faTrash, faClock, faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons';

const icons = {
    eye: faEye,
    edit: faPenToSquare,
    delete: faTrash,
    clock: faClock,
    chevronLeft: faChevronLeft,
    chevronRight: faChevronRight,
};

const props = defineProps({
    events: {
        type: Array,
        required: true
    }
});

const emit = defineEmits([
    'view-details',
    'edit-event',
    'delete-event',
    'row-clicked'
]);

// Pagination state
const currentPage = ref(1);
const itemsPerPage = ref(10);

const handleAction = (eventName, eventObject, e) => {
    e.stopPropagation();
    emit(eventName, eventObject);
};

const handleRowClick = (eventObject) => {
    emit('row-clicked', eventObject);
};

const formatTime = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatDate = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatEndDate = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatTimeSlot = (event) => {
    if (event.allDay) {
        return 'All Day';
    } else {
        const startTime = formatTime(event.start);
        const endTime = event.end ? formatTime(event.end) : '';
        return `${startTime} ${endTime ? '- ' + endTime : ''}`;
    }
};

const getEventType = (event) => {
    const type = event.extendedProps?.type?.toLowerCase() || event.list?.toLowerCase() || '';
    if (type.includes('event')) return 'Event';
    if (type.includes('class')) return 'Class';
    if (type.includes('meeting')) return 'Meeting';
    return 'Other Activity';
};

// Original sorted events
const sortedEvents = computed(() => {
    return [...props.events].sort((a, b) =>
        new Date(a.start).getTime() - new Date(b.start).getTime()
    );
});

// Process events for table
const processedEvents = computed(() => {
    return sortedEvents.value.map(event => {
        const extendedProps = event.extendedProps || {};

        return {
            id: event.id,
            title: event.title,
            room: extendedProps.room || event.title,
            building: extendedProps.building || 'N/A',
            college: extendedProps.college || 'N/A',
            subject: extendedProps.subject || event.title,
            startDate: formatDate(event.start),
            endDate: event.end ? formatEndDate(event.end) : formatDate(event.start),
            timeSlot: formatTimeSlot(event),
            isRecurring: event.rrule ? 'Yes' : 'No',
            eventType: getEventType(event),
            eventObject: event,
            allDay: event.allDay || false,
            type: event.list || 'Event',
            isOccupied: true,
            requester: extendedProps.requester || 'N/A',
            description: extendedProps.description || ''
        };
    });
});

// Pagination computed properties
const totalPages = computed(() => {
    return Math.ceil(processedEvents.value.length / itemsPerPage.value);
});

const paginatedEvents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return processedEvents.value.slice(start, end);
});

const showingRange = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value + 1;
    const end = Math.min(currentPage.value * itemsPerPage.value, processedEvents.value.length);
    const total = processedEvents.value.length;
    return { start, end, total };
});

// Pagination functions
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// Reset pagination when events change
const resetPagination = () => {
    currentPage.value = 1;
};
</script>

<template>
    <div class="bg-white shadow-lg rounded-xl overflow-auto">
        <!-- Table Header with Pagination Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-b border-yellow-400">
            <div class="text-lg font-semibold text-[#7A0C23] mb-2 sm:mb-0">
                Appointments ({{ processedEvents.length }})
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Show:</span>
                    <select v-model="itemsPerPage" @change="resetPagination"
                            class="text-sm border border-gray-300 rounded px-2 py-1 bg-white">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm text-gray-600">entries</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-yellow-500" style="table-layout: fixed; width: 100%;">
                <colgroup>
                    <col style="width: 20%;"> <!-- APPOINTMENT -->
                    <col style="width: 12%;"> <!-- ROOM -->
                    <col style="width: 12%;"> <!-- BUILDING -->
                    <col style="width: 12%;"> <!-- COLLEGE -->
                    <col style="width: 10%;"> <!-- START DATE -->
                    <col style="width: 10%;"> <!-- TIME SLOT -->
                    <col style="width: 10%;"> <!-- EVENT TYPE -->
                    <col style="width: 14%;"> <!-- ACTIONS -->
                </colgroup>
                <thead class="bg-[#7A0C23] text-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">APPOINTMENT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">ROOM</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">BUILDING</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">COLLEGE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">START DATE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">TIME SLOT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">EVENT TYPE</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">ACTIONS</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-yellow-400 bg-white">
                    <tr
                        v-for="item in paginatedEvents"
                        :key="item.id"
                        @click="handleRowClick(item.eventObject)"
                        class="transition duration-150 cursor-pointer hover:bg-blue-50"
                    >
                        <!-- APPOINTMENT Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm font-bold text-[#7A0C23] truncate" :title="item.title">{{ item.title }}</div>
                            <div class="text-xs text-gray-500 mt-1 truncate" :title="item.description">{{ item.description }}</div>
                        </td>

                        <!-- ROOM Column (Only show room name, no "Occupied" badge) -->
                        <td class="px-4 py-4">
                            <div class="text-sm font-medium text-gray-900 truncate" :title="item.room">
                                {{ item.room }}
                            </div>
                        </td>

                        <!-- BUILDING Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.building">{{ item.building }}</div>
                        </td>

                        <!-- COLLEGE Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.college">{{ item.college }}</div>
                        </td>

                        <!-- START DATE Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.startDate">{{ item.startDate }}</div>
                        </td>

                        <!-- TIME SLOT Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.timeSlot">{{ item.timeSlot }}</div>
                        </td>

                        <!-- EVENT TYPE Column -->
                        <td class="px-4 py-4">
                            <span :class="[
                                'px-2 py-1 rounded-full text-xs font-medium truncate inline-block max-w-full',
                                item.eventType === 'Class' ? 'bg-blue-100 text-blue-800' :
                                item.eventType === 'Meeting' ? 'bg-green-100 text-green-800' :
                                item.eventType === 'Event' ? 'bg-purple-100 text-purple-800' :
                                'bg-yellow-100 text-yellow-800'
                            ]" :title="item.eventType">
                                {{ item.eventType }}
                            </span>
                        </td>

                        <!-- ACTIONS Column -->
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center space-x-3" @click.stop>
                                <button
                                    @click="handleAction('view-details', item.eventObject, $event)"
                                    title="View Details"
                                    class="text-blue-500 hover:text-blue-700 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.eye" class="h-5 w-5" />
                                </button>
                                <button
                                    @click="handleAction('edit-event', item.eventObject, $event)"
                                    title="Edit Event"
                                    class="text-green-600 hover:text-green-800 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.edit" class="h-5 w-5" />
                                </button>
                                <button
                                    @click="handleAction('delete-event', item.eventObject, $event)"
                                    title="Delete Event"
                                    class="text-red-600 hover:text-red-800 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.delete" class="h-5 w-5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="processedEvents.length === 0">
                        <td :colspan="8" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-lg font-medium text-gray-600">No appointments scheduled</p>
                                <p class="text-sm text-gray-500 mt-1">Click "New Appointment" to schedule one</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div v-if="processedEvents.length > 0" class="flex flex-col sm:flex-row items-center justify-between p-4 border-t border-gray-200 bg-gray-50">
            <div class="text-sm text-gray-600 mb-2 sm:mb-0">
                Showing {{ showingRange.start }} to {{ showingRange.end }} of {{ showingRange.total }} entries
            </div>

            <div class="flex items-center space-x-2 mb-2 sm:mb-0">
                <!-- Previous Button -->
                <button
                    @click="prevPage"
                    :disabled="currentPage === 1"
                    :class="[
                        'flex items-center px-3 py-1.5 rounded border text-sm transition-colors',
                        currentPage === 1
                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-300'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                    ]"
                >
                    <FontAwesomeIcon :icon="icons.chevronLeft" class="h-3 w-3 mr-1" />
                    Previous
                </button>

                <!-- Page Numbers -->
                <div class="flex items-center space-x-1">
                    <button
                        v-for="page in totalPages"
                        :key="page"
                        @click="goToPage(page)"
                        :class="[
                            'px-3 py-1.5 rounded border text-sm min-w-[36px] transition-colors',
                            currentPage === page
                                ? 'bg-[#7A0C23] text-white border-[#7A0C23]'
                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                        ]"
                    >
                        {{ page }}
                    </button>
                </div>

                <!-- Next Button -->
                <button
                    @click="nextPage"
                    :disabled="currentPage === totalPages"
                    :class="[
                        'flex items-center px-3 py-1.5 rounded border text-sm transition-colors',
                        currentPage === totalPages
                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-300'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                    ]"
                >
                    Next
                    <FontAwesomeIcon :icon="icons.chevronRight" class="h-3 w-3 ml-1" />
                </button>
            </div>

            <div class="text-sm text-gray-600">
                Page {{ currentPage }} of {{ totalPages }}
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Fixed table layout */
table {
    table-layout: fixed;
}

/* Ensure columns don't resize */
th, td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Allow truncation with tooltip */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Fixed column widths */
td:first-child, th:first-child { width: 20%; }
td:nth-child(2), th:nth-child(2) { width: 12%; }
td:nth-child(3), th:nth-child(3) { width: 12%; }
td:nth-child(4), th:nth-child(4) { width: 12%; }
td:nth-child(5), th:nth-child(5) { width: 10%; }
td:nth-child(6), th:nth-child(6) { width: 10%; }
td:nth-child(7), th:nth-child(7) { width: 10%; }
td:nth-child(8), th:nth-child(8) { width: 14%; }

/* Action buttons container */
.flex.items-center.justify-center.space-x-3 {
    min-width: 120px;
}

/* Hover effects for buttons */
button.transition-transform:hover:not(:disabled) {
    transform: scale(1.1);
}

/* Prevent text selection on buttons */
button {
    user-select: none;
}

/* Center align text in action column */
td:last-child {
    text-align: center;
}

/* Ensure consistent icon sizing */
.h-5.w-5 {
    height: 1.25rem;
    width: 1.25rem;
}

/* Better spacing for action buttons */
.space-x-3 > * + * {
    margin-left: 0.75rem;
}

/* Row hover effect */
tr:hover {
    background-color: #f0f9ff;
}

/* Pagination button styles */
button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .min-w-full {
        min-width: 800px; /* Allow horizontal scroll on mobile */
    }

    .px-4 {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}
</style>
