<script setup>
// ==============================
// PROPS
// ==============================
// dateGrid: 6x7 array representing calendar weeks & days
// days: ["Sunday", "Monday", ...]
const props = defineProps({
    dateGrid: Array,
    days: Array
})

// ==============================
// EMITS
// ==============================
// emitDateClick → create new event
// selectEvent → open existing event
// dayClick → highlight/select day
const emit = defineEmits(['emitDateClick', 'selectEvent', 'dayClick'])

// ==============================
// HANDLE DAY CLICK
// ==============================
// Fires when user clicks a calendar cell
// Emits the clicked date with default time (9:00 AM)
const handleDateClick = (date) => {
    emit('dayClick', date) // notify parent of day click

    const exactDate = new Date(date)
    exactDate.setHours(9, 0, 0, 0) // default appointment time

    emit('emitDateClick', exactDate)
}

// ==============================
// CHECK IF DAY IS IN CURRENT MONTH
// ==============================
const isSameMonth = (date) => {
    const d = new Date(date)
    const now = new Date()
    return d.getMonth() === now.getMonth() &&
           d.getFullYear() === now.getFullYear()
}

// ==============================
// FORMAT TIME (HH:MM AM/PM)
// ==============================
const formatTime = (date) => {
    const d = new Date(date)
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    })
}

// ==============================
// TRUNCATE TEXT (EVENT TITLES)
// ==============================
const truncateText = (text, maxLength) => {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}
</script>

<template>
    <div class="w-full overflow-hidden">
        <!-- MAIN CALENDAR TABLE -->
        <table class="w-full table-fixed border-collapse border border-yellow-600">

            <!-- ================= HEADER ================= -->
            <thead>
                <tr class="bg-[#7A0C23]">
                    <th
                        v-for="day in days"
                        :key="day"
                        class="w-[14.28%] px-2 py-3 text-xs font-medium text-white uppercase text-center border border-yellow-600"
                    >
                        {{ day }}
                    </th>
                </tr>
            </thead>

            <!-- ================= BODY ================= -->
            <tbody>
                <!-- EACH WEEK -->
                <tr v-for="(week, wIdx) in dateGrid" :key="wIdx">

                    <!-- EACH DAY CELL -->
                    <td
                        v-for="day in week"
                        :key="new Date(day.date).toISOString()"
                        @click="handleDateClick(day.date)"
                        class="p-0 border border-yellow-400 align-top cursor-pointer relative"
                        :class="[
                            day.dayClass,
                            day.isToday
                                ? 'bg-yellow-400 ring-2 ring-inset ring-red-500/50'
                                : isSameMonth(day.date)
                                ? 'bg-white hover:bg-blue-100'
                                : 'bg-gray-50 hover:bg-gray-100'
                        ]"
                    >
                        <!-- FIXED HEIGHT CELL (NEVER STRETCHES) -->
                        <div class="h-36 flex flex-col p-1 overflow-hidden">

                            <!-- ===== DATE HEADER ===== -->
                            <div class="flex justify-between items-center text-xs font-bold flex-shrink-0 mb-1">
                                <!-- EVENT COUNT BADGE -->
                                <div
                                    v-if="(day.events || []).length > 0"
                                    class="text-xs font-semibold text-[#7A0C23] bg-yellow-100 px-2 py-1 rounded-full"
                                >
                                    {{ day.events.length }} {{ day.events.length === 1 ? 'event' : 'events' }}
                                </div>

                                <!-- DATE NUMBER -->
                                <span
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs"
                                    :class="day.isToday ? 'bg-red-600 text-white' : 'text-gray-700'"
                                >
                                    {{ new Date(day.date).getDate() }}
                                </span>
                            </div>

                            <!-- ===== EVENTS LIST (SCROLLABLE) ===== -->
                            <div class="flex-grow overflow-y-auto space-y-1 custom-scrollbar">

                                <!-- ALL-DAY EVENTS -->
                                <div
                                    v-for="event in (day.allDayEvents || [])"
                                    :key="'all-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="text-[10px] px-1.5 py-1 rounded truncate bg-green-100 text-green-800 border-l-2 border-green-500 cursor-pointer"
                                >
                                    ⚫ {{ truncateText(event.title || event.extendedProps?.subject, 20) }}
                                </div>

                                <!-- TIMED EVENTS -->
                                <div
                                    v-for="event in (day.events || []).slice(0, 3)"
                                    :key="'time-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="text-[10px] px-1.5 py-1 rounded truncate bg-blue-100 text-blue-800 border-l-2 border-blue-500 cursor-pointer"
                                >
                                    <b>{{ formatTime(event.start) }}</b>
                                    {{ truncateText(event.title || event.extendedProps?.subject, 15) }}
                                </div>

                                <!-- MORE EVENTS INDICATOR -->
                                <div
                                    v-if="(day.events || []).length > 3"
                                    class="text-[10px] text-center bg-yellow-100 text-yellow-800 rounded py-1"
                                >
                                    + {{ day.events.length - 3 }} more
                                </div>

                                <!-- NO EVENTS -->
                                <div
                                    v-if="(day.events || []).length === 0 && (day.allDayEvents || []).length === 0"
                                    class="text-[10px] text-gray-400 italic text-center pt-2"
                                >
                                    No events
                                </div>

                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
/* Ensures equal column width */
table {
    table-layout: fixed;
    border-spacing: 0;
}

/* Prevents accidental text selection */
td {
    user-select: none;
}

/* Custom scrollbar inside cells */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Small hover lift on events */
.cursor-pointer:hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}
</style>
