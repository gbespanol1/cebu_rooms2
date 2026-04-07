<script setup>
import { ref, computed, watchEffect } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faChevronLeft,
    faChevronRight,
    faCalendarDay,
    faCalendarWeek,
    faCalendar,
    faListUl,
    faPenToSquare,
    faTrash,
    faPlusCircle,
    faEye,
    faClock
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    data: { type: Array, default: () => [] },
    initialDate: { type: Date, default: () => new Date() },
    initialMode: { type: String, default: 'month' },
    MonthGridViewComponent: { type: Object, required: true },
    TimeGridViewComponent: { type: Object, required: true },
    ListViewComponent: { type: Object },
});

const emit = defineEmits([
    'update:date',
    'update:mode',
    'dateClicked',
    'selectEvent',
    'editEvent',
    'deleteEvent',
    'addAppointment',
    'dayClick'
]);

// --- Core State ---
const currentReferenceDate = ref(props.initialDate);
const currentMode = ref(props.initialMode);

// Sync with props when they change externally
watchEffect(() => {
    currentReferenceDate.value = props.initialDate;
    currentMode.value = props.initialMode;
});

// Icons
const listIcons = {
    edit: faPenToSquare,
    delete: faTrash,
    add: faPlusCircle,
    view: faEye,
    clock: faClock
};

// --- Utilities ---
const dateToTimeString = (date) => {
    if (!date || isNaN(date)) return '';
    const d = new Date(date);
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

const dateToDayString = (date) => {
    if (!date || isNaN(date)) return '';
    const d = new Date(date);
    return d.toISOString().split('T')[0];
};

const formatDay = (date) => {
    const d = new Date(date);
    if (isNaN(d)) return '';
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const getRequestType = (event) => {
    const type = event.extendedProps?.type?.toLowerCase() || event.list?.toLowerCase() || '';
    if (type.includes('class')) return 'Class';
    if (type.includes('meeting')) return 'Meeting';
    if (type.includes('event')) return 'Event';
    return 'Appointment';
};

// --- Event Handlers ---
const changeView = (mode) => {
    currentMode.value = mode;
    emit('update:mode', mode);
};

const handleNavigation = (unit, direction) => {
    const newDate = new Date(currentReferenceDate.value);

    if (currentMode.value === 'day' || unit === 'day') {
        newDate.setDate(newDate.getDate() + direction);
    } else if (currentMode.value === 'week' || unit === 'week') {
        newDate.setDate(newDate.getDate() + direction * 7);
    } else if (currentMode.value === 'month' || unit === 'month') {
        newDate.setMonth(newDate.getMonth() + direction);
    }

    currentReferenceDate.value = newDate;
    emit('update:date', newDate);
};

const goToToday = () => {
    const today = new Date();
    currentReferenceDate.value = today;
    emit('update:date', today);
};

const handleDateClick = (date, hour = null, minute = null) => {
    let exactDate = new Date(date);

    if (hour !== null && minute !== null) {
        exactDate.setHours(hour, minute, 0, 0);
    } else {
        exactDate.setHours(9, 0, 0, 0);
    }

    emit('dateClicked', exactDate, hour, minute);
};

const handleDayClick = (date) => {
    emit('dayClick', date);
};

const handleEventSelected = (event) => {
    emit('selectEvent', event);
};

const handleEditEvent = (event, e = null) => {
    if (e) e.stopPropagation();
    emit('editEvent', event);
};

const handleDeleteEvent = (event, e = null) => {
    if (e) e.stopPropagation();
    emit('deleteEvent', event);
};

const handleAddRowClick = () => {
    const defaultDate = new Date();
    defaultDate.setHours(9, 0, 0, 0);
    emit('addAppointment', defaultDate);
};

// --- Computed Properties ---
const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const formattedTitle = computed(() => {
    const date = currentReferenceDate.value;

    if (currentMode.value === 'day' || currentMode.value === 'list') {
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } else if (currentMode.value === 'week') {
        const startOfWeek = new Date(date);
        startOfWeek.setDate(date.getDate() - date.getDay());
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);

        return `${startOfWeek.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })} - ${endOfWeek.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
    } else {
        return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }
});

const monthGrid = computed(() => {
    const date = currentReferenceDate.value;
    const year = date.getFullYear();
    const month = date.getMonth();
    const today = new Date();
    const todayStr = dateToDayString(today);

    const firstDayOfMonth = new Date(year, month, 1);
    const startingDay = firstDayOfMonth.getDay();
    const dateGrid = [];
    let dayCounter = 1 - startingDay;

    for (let week = 0; week < 6; week++) {
        const weekDays = [];
        for (let day = 0; day < 7; day++) {
            const currentDate = new Date(year, month, dayCounter);
            const dayStr = dateToDayString(currentDate);

            const dayEvents = props.data.filter(event => {
                const eventStart = new Date(event.start);
                const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
                const current = new Date(currentDate);
                eventStart.setHours(0, 0, 0, 0);
                eventEnd.setHours(0, 0, 0, 0);
                current.setHours(0, 0, 0, 0);
                return current >= eventStart && current <= eventEnd;
            });

            weekDays.push({
                date: currentDate,
                isToday: dayStr === todayStr,
                dayClass: currentDate.getMonth() === month ? '' : 'text-gray-400',
                allDayEvents: dayEvents.filter(e => e.allDay),
                events: dayEvents.filter(e => !e.allDay),
                hasMultipleEvents: dayEvents.length > 3,
                eventCount: dayEvents.length,
                isOccupied: dayEvents.length > 0
            });
            dayCounter++;
        }
        dateGrid.push(weekDays);
    }
    return dateGrid;
});

const timeGridData = computed(() => {
    const date = currentReferenceDate.value;
    const today = new Date();
    const todayStr = dateToDayString(today);
    let datesToRender = [];

    if (currentMode.value === 'day') {
        datesToRender.push(date);
    } else {
        const startOfWeek = new Date(date);
        startOfWeek.setDate(date.getDate() - date.getDay());
        for (let i = 0; i < 7; i++) {
            const day = new Date(startOfWeek);
            day.setDate(startOfWeek.getDate() + i);
            datesToRender.push(day);
        }
    }

    return datesToRender.map(currentDate => {
        const dayStr = dateToDayString(currentDate);
        const dayEvents = props.data.filter(event => {
            const eventDate = new Date(event.start);
            return dateToDayString(eventDate) === dayStr;
        });

        return {
            date: currentDate,
            label: currentDate.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            }),
            fullDate: currentDate.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }),
            isToday: dayStr === todayStr,
            allDayEvents: dayEvents.filter(e => e.allDay),
            events: dayEvents.filter(e => !e.allDay),
            isOccupied: dayEvents.length > 0
        };
    });
});

const hourSlots = computed(() => {
    const slots = [];
    for (let h = 6; h < 22; h++) {
        for (let m = 0; m < 60; m += 30) {
            const time = new Date(0, 0, 0, h, m);
            slots.push({
                hour: h,
                minute: m,
                label: time.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }),
            });
        }
    }
    return slots;
});

const getTimeEventStyle = (event) => {
    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : new Date(start.getTime() + 60 * 60000);

    const startHour = start.getHours();
    const startMinute = start.getMinutes();
    const endHour = end.getHours();
    const endMinute = end.getMinutes();

    const startMinutesFrom6AM = (startHour - 6) * 60 + startMinute;
    const endMinutesFrom6AM = (endHour - 6) * 60 + endMinute;
    const durationMinutes = Math.max(0, endMinutesFrom6AM - startMinutesFrom6AM);

    const pxPerMinute = 40 / 30;

    const topPx = startMinutesFrom6AM * pxPerMinute;
    const heightPx = Math.max(25, durationMinutes * pxPerMinute);

    return {
        top: `${topPx}px`,
        height: `${heightPx}px`,
        left: '4px',
        right: '4px',
        zIndex: '20',
    };
};

const formatEventTimeForDisplay = (event) => {
    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : new Date(start.getTime() + 60 * 60000);
    return `${dateToTimeString(start)} - ${dateToTimeString(end)}`;
};

const tableEvents = computed(() => {
    return [...props.data]
        .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
        .map(event => {
            const start = new Date(event.start);
            const end = event.end ? new Date(event.end) : new Date(start.getTime() + 60 * 60000);

            return {
                id: event.id,
                appointment: event.title || 'Untitled Appointment',
                day: formatDay(event.start),
                time: event.allDay ? 'All Day' : formatEventTimeForDisplay(event),
                requestType: getRequestType(event),
                eventObject: event,
                room: event.extendedProps?.room || 'N/A',
                building: event.extendedProps?.building || 'N/A',
                college: event.extendedProps?.college || 'N/A',
                isOccupied: true
            };
        });
});
</script>

<template>
    <div class="flex flex-col bg-white rounded-xl shadow-lg">
        <div class="p-4 flex items-center justify-between border-b border-yellow-200">
            <div class="flex items-center space-x-2">
                <button @click="handleNavigation(currentMode, -1)" class="p-2 text-gray-700 bg-gray-100 rounded-full hover:bg-gray-200 transition">
                    <FontAwesomeIcon :icon="faChevronLeft" />
                </button>
                <button @click="handleNavigation(currentMode, 1)" class="p-2 text-gray-700 bg-gray-100 rounded-full hover:bg-gray-200 transition">
                    <FontAwesomeIcon :icon="faChevronRight" />
                </button>
                <button @click="goToToday" class="px-3 py-1 text-sm font-semibold border rounded-lg bg-[#7A0C23] text-white hover:bg-red-800 transition">
                    Today
                </button>
            </div>

            <h2 class="text-xl font-bold text-gray-800">{{ formattedTitle }}</h2>

            <div class="flex space-x-1 p-1 bg-yellow-400 rounded-lg">
                <button v-for="mode in ['list', 'day', 'week', 'month']" :key="mode"
                    @click="changeView(mode)"
                    :class="[
                        'p-2 rounded-lg text-sm font-medium transition',
                        currentMode === mode ? 'bg-white text-[#7A0C23] shadow' : 'text-white hover:bg-yellow-500'
                    ]">
                    <FontAwesomeIcon :icon="mode === 'list' ? faListUl : mode === 'day' ? faCalendarDay : mode === 'week' ? faCalendarWeek : faCalendar" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div class="flex-grow p-4">
            <div v-if="currentMode === 'list'" class="bg-white rounded-xl overflow-hidden border border-yellow-600">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-[#7A0C23] text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">APPOINTMENT</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">ROOM</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">BUILDING</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">DAY</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">TIME</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase">TYPE</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase w-40">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-yellow-400">
                        <tr v-for="item in tableEvents" :key="item.id" @click="handleEventSelected(item.eventObject)" class="transition hover:bg-blue-50 cursor-pointer">
                            <td class="px-6 py-4 text-sm font-bold text-[#7A0C23]">{{ item.appointment }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ item.room }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ item.building }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ item.day }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <div class="flex items-center">
                                    <span>{{ item.time }}</span>
                                    <span v-if="item.isOccupied" class="ml-2 text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                                        <FontAwesomeIcon :icon="listIcons.clock" class="w-3 h-3 mr-1" />
                                        Occupied
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <span :class="['px-2 py-1 rounded-full text-xs font-medium', item.requestType === 'Class' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800']">
                                    {{ item.requestType }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-center">
                                <div class="flex items-center justify-center space-x-3" @click.stop>
                                    <button
                                        @click="handleEventSelected(item.eventObject)"
                                        class="text-blue-600 hover:text-blue-800 transition-transform hover:scale-110"
                                        title="View Details"
                                    >
                                        <FontAwesomeIcon :icon="listIcons.view" class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="handleEditEvent(item.eventObject, $event)"
                                        class="text-green-600 hover:text-green-800 transition-transform hover:scale-110"
                                        title="Edit"
                                    >
                                        <FontAwesomeIcon :icon="listIcons.edit" class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="handleDeleteEvent(item.eventObject, $event)"
                                        class="text-red-600 hover:text-red-800 transition-transform hover:scale-110"
                                        title="Delete"
                                    >
                                        <FontAwesomeIcon :icon="listIcons.delete" class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr @click="handleAddRowClick" class="bg-green-50/50 hover:bg-green-100 cursor-pointer transition">
                            <td colspan="7" class="px-6 py-4 text-center text-green-700 font-semibold text-base">
                                <FontAwesomeIcon :icon="listIcons.add" class="mr-2" /> Schedule new appointment...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <component
                v-else-if="currentMode === 'month'"
                :is="MonthGridViewComponent"
                :dateGrid="monthGrid"
                :days="days"
                @emitDateClick="handleDateClick"
                @selectEvent="handleEventSelected"
                @dayClick="handleDayClick"
            />

            <component
                v-else-if="currentMode === 'day' || currentMode === 'week'"
                :is="TimeGridViewComponent"
                :viewMode="currentMode"
                :weekDays="timeGridData"
                :singleDay="timeGridData[0]"
                :hourSlots="hourSlots"
                :getEventStyle="getTimeEventStyle"
                :dateToTimeString="dateToTimeString"
                :formatEventTime="formatEventTimeForDisplay"
                :events="data"
                @selectEvent="handleEventSelected"
                @emitDateClick="handleDateClick"
                @selectDate="(date) => {
                    currentReferenceDate = date;
                    changeView('day');
                }"
            />
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for better alignment */
table {
    table-layout: fixed;
}

td {
    vertical-align: middle;
}

/* Action buttons container */
.flex.items-center.justify-center.space-x-3 {
    min-width: 120px;
}

/* Ensure icons are properly sized */
.w-4.h-4 {
    width: 1rem;
    height: 1rem;
}

/* Hover effects for buttons */
button.transition-transform:hover {
    transform: scale(1.1);
}

/* Prevent text selection on buttons */
button {
    user-select: none;
}
</style>
