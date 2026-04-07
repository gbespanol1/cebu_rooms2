<script setup>
import { ref, computed, watchEffect } from 'vue';

// Core Components
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import MessageFunction from '@/Components/MessageFunction.vue';

// ScheduleModal Components
import AppointmentModal from '@/Components/ScheduleModal/AppointmentModal.vue';
import CalendarView from '@/Components/ScheduleModal/CalendarView.vue';
import TableComponent from '@/Components/ScheduleModal/TableComponent.vue';
import MonthGridView from '@/Components/ScheduleModal/MonthGridView.vue';
import TimeGridView from '@/Components/ScheduleModal/TimeGridView.vue';
import EventViewerModal from '@/Components/ScheduleModal/EventViewerModal.vue';
import DayEventsViewerModal from '@/Components/ScheduleModal/DayEventsViewerModal.vue';
import OccupancyCheckModal from '@/Components/ScheduleModal/OccupancyCheckModal.vue';

// --- UTILITY FUNCTIONS ---
const createDate = (dateStr, timeStr) => {
    if (timeStr) {
        const [h, m] = timeStr.split(':').map(Number);
        const [y, M, d] = dateStr.split('-').map(Number);
        return new Date(y, M - 1, d, h, m);
    }
    const [y, M, d] = dateStr.split('-').map(Number);
    return new Date(y, M - 1, d);
};

const loadEventsFromStorage = () => {
    const saved = localStorage.getItem('scheduleEvents');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            return parsed.map(event => ({
                ...event,
                start: new Date(event.start),
                end: event.end ? new Date(event.end) : null
            }));
        } catch (e) {
            console.error('Failed to load events:', e);
        }
    }
    return [
        {
            id: 1,
            title: 'Class: CS 101',
            list: 'Class',
            allDay: false,
            start: createDate('2025-11-15', '10:00'),
            end: createDate('2025-11-15', '12:00'),
            extendedProps: {
                room: 'UG 114',
                building: 'Engineering',
                college: 'CompSci',
                subject: 'Class: CS 101',
                type: 'Class',
                requester: 'Prof. John Smith',
                description: 'Introduction to Computer Science'
            }
        }
    ];
};

const saveEventsToStorage = (events) => {
    const eventsToSave = events.map(event => ({
        ...event,
        start: event.start.toISOString(),
        end: event.end ? event.end.toISOString() : null
    }));
    localStorage.setItem('scheduleEvents', JSON.stringify(eventsToSave));
};

const dateToIsoDateString = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const formatTimeDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

const formatDateDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const extractExtendedProps = (data) => ({
    room: data.room || 'N/A',
    building: data.organization || 'N/A',
    college: data.deptOffice || 'N/A',
    subject: data.subject || data.title || data.agenda || data.name || 'Untitled',
    type: data.type || data.list || 'Event',
    requester: data.requester || 'N/A',
    description: data.description || '',
    agenda: data.agenda || '',
    numberOfStudents: data.numberOfStudents || null,
    faculty: data.faculty || '',
    section: data.section || ''
});

const checkTimeSlotOccupancy = (room, startTime, endTime, events, excludeEventId = null) => {
    const start = new Date(startTime);
    const end = new Date(endTime);

    return events.some(event => {
        if (excludeEventId && event.id === excludeEventId) return false;
        if (event.extendedProps?.room !== room) return false;

        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return (start < eventEnd && end > eventStart);
    });
};

const getAvailableSlotsForRoom = (room, date, events, durationHours = 1) => {
    const allSlots = [];
    const dayStart = new Date(date);
    dayStart.setHours(6, 0, 0, 0);

    const dayEnd = new Date(date);
    dayEnd.setHours(22, 0, 0, 0);

    const roomEvents = events.filter(event => {
        const eventDate = dateToIsoDateString(new Date(event.start));
        const targetDate = dateToIsoDateString(new Date(date));
        return event.extendedProps?.room === room && eventDate === targetDate;
    }).sort((a, b) => new Date(a.start) - new Date(b.start));

    let currentTime = new Date(dayStart);

    while (currentTime < dayEnd) {
        const slotEnd = new Date(currentTime);
        slotEnd.setHours(currentTime.getHours() + durationHours);

        const isOccupied = roomEvents.some(event => {
            const eventStart = new Date(event.start);
            const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
            return (currentTime < eventEnd && slotEnd > eventStart);
        });

        if (!isOccupied && slotEnd <= dayEnd) {
            allSlots.push({
                start: new Date(currentTime),
                end: new Date(slotEnd),
                display: `${formatTimeDisplay(currentTime)} - ${formatTimeDisplay(slotEnd)}`
            });
        }

        currentTime.setMinutes(currentTime.getMinutes() + 30);
    }

    return allSlots;
};

// --- CORE STATE ---
const events = ref(loadEventsFromStorage());
const availableRooms = ref(['UG 114', 'AVR 201', 'Lab 305', 'Main Conference Room']);

// Layout State
const sidebarOpen = ref(true);
const currentView = ref('table');
const toggleSidebar = () => (sidebarOpen.value = !sidebarOpen.value);

// Calendar State
const currentCalendarDate = ref(new Date());
const currentCalendarMode = ref('list');
const nextEventId = computed(() =>
    (events.value.length > 0 ? Math.max(...events.value.map(e => e.id)) : 0) + 1
);

// Modal States
const modalState = ref({
    appointment: { visible: false, data: null, editing: null },
    occupancy: { visible: false, data: null, callback: null },
    eventViewer: { visible: false, event: null },
    dayViewer: { visible: false, events: [], date: null }
});

// Toast State
const toastState = ref({
    create: false,
    edit: false,
    delete: { visible: false, name: '' }
});

// --- UTILITY FUNCTIONS ---
const triggerToast = (type, name = '') => {
    toastState.value = { create: false, edit: false, delete: { visible: false, name: '' } };

    if (type === 'create') toastState.value.create = true;
    if (type === 'edit') toastState.value.edit = true;
    if (type === 'delete') toastState.value.delete = { visible: true, name };

    setTimeout(() => {
        toastState.value = { create: false, edit: false, delete: { visible: false, name: '' } };
    }, 3000);
};

// --- MODAL CONTROLLERS ---
const openAppointmentModal = (data = null, editing = null) => {
    modalState.value.appointment = {
        visible: true,
        data: data || {
            selectedDate: new Date().toISOString().slice(0, 10),
            initialRoom: availableRooms.value[0],
            initialHour: 9,
            initialMinute: 0
        },
        editing
    };
};

const openOccupancyModal = (room, date, hour = null, minute = null, callback = null) => {
    const targetDate = new Date(date);
    if (hour !== null && minute !== null) {
        targetDate.setHours(hour, minute, 0, 0);
    }

    const existingEvents = events.value.filter(event => {
        const eventDate = dateToIsoDateString(new Date(event.start));
        const targetDateStr = dateToIsoDateString(targetDate);
        return event.extendedProps?.room === room && eventDate === targetDateStr;
    });

    modalState.value.occupancy = {
        visible: true,
        data: {
            room,
            date: targetDate,
            selectedHour: hour,
            selectedMinute: minute,
            existingEvents,
            availableSlots: getAvailableSlotsForRoom(room, targetDate, events.value)
        },
        callback: callback || (() => openAppointmentModal({
            selectedDate: dateToIsoDateString(targetDate),
            initialRoom: room,
            initialHour: hour,
            initialMinute: minute
        }))
    };
};

const openEventViewer = (event) => {
    modalState.value.eventViewer = { visible: true, event };
};

const openDayEventsViewer = (date) => {
    const dayStr = dateToIsoDateString(date);
    const dayEvents = events.value.filter(event => {
        const eventDate = dateToIsoDateString(new Date(event.start));
        return eventDate === dayStr;
    });
    modalState.value.dayViewer = { visible: true, events: dayEvents, date };
};

const closeModal = (modalName) => {
    modalState.value[modalName] = {
        visible: false,
        data: null,
        event: null,
        events: [],
        date: null,
        callback: null
    };
};

// --- EVENT HANDLERS ---
const handleAppointmentSuccess = (data) => {
    const isEdit = !!modalState.value.appointment.editing;
    const eventId = isEdit ? modalState.value.appointment.editing.id : nextEventId.value;

    // Final check for time conflicts
    if (checkTimeSlotOccupancy(data.room, data.start, data.end, events.value, isEdit ? eventId : null)) {
        alert('This time slot is now occupied. Please choose a different time.');
        openOccupancyModal(data.room, data.start, data.start.getHours(), data.start.getMinutes());
        return;
    }

    // Create FullCalendar-style event object
    const newEvent = {
        id: eventId,
        title: data.title,
        list: data.type,
        allDay: data.allDay,
        start: data.start,
        end: data.end,
        extendedProps: extractExtendedProps(data),
    };

    if (isEdit) {
        // Update existing event
        const index = events.value.findIndex(e => e.id === eventId);
        if (index !== -1) {
            events.value[index] = newEvent;
        }
        triggerToast('edit');
    } else {
        // Add new event
        events.value.push(newEvent);
        triggerToast('create');
    }

    // Persist to localStorage
    saveEventsToStorage(events.value);
    closeModal('appointment');

    // If in calendar view, navigate to the event
    if (currentView.value === 'calendar') {
        selectEventInCalendar(newEvent);
    }
};

const handleOccupancyProceed = (selectedSlot = null) => {
    const occupancyData = modalState.value.occupancy.data;
    const callback = modalState.value.occupancy.callback;
    closeModal('occupancy');

    if (callback) {
        if (selectedSlot) {
            // Use the selected slot
            callback({
                selectedDate: dateToIsoDateString(selectedSlot.start),
                initialRoom: occupancyData.room,
                initialHour: selectedSlot.start.getHours(),
                initialMinute: selectedSlot.start.getMinutes()
            });
        } else {
            // Use originally selected time
            callback({
                selectedDate: dateToIsoDateString(occupancyData.date),
                initialRoom: occupancyData.room,
                initialHour: occupancyData.selectedHour !== null ? occupancyData.selectedHour : 9,
                initialMinute: occupancyData.selectedMinute !== null ? occupancyData.selectedMinute : 0
            });
        }
    }
};

const handleEditEvent = (event) => {
    closeModal('eventViewer');
    openAppointmentModal(
        {
            selectedDate: dateToIsoDateString(event.start),
            initialRoom: event.extendedProps?.room || availableRooms.value[0],
            initialHour: event.allDay ? null : event.start.getHours(),
            initialMinute: event.allDay ? null : event.start.getMinutes()
        },
        event
    );
};

const handleDeleteEvent = (eventObject) => {
    if (confirm(`Are you sure you want to delete: ${eventObject?.title}?`)) {
        events.value = events.value.filter(e => e.id !== eventObject.id);
        saveEventsToStorage(events.value);
        triggerToast('delete', eventObject?.title || 'Appointment');

        if (modalState.value.eventViewer.visible && modalState.value.eventViewer.event?.id === eventObject.id) {
            closeModal('eventViewer');
        }
    }
};

// Handle calendar cell click - NEW IMPROVED FLOW
const handleDateClick = (date, hour = null, minute = null) => {
    // Show room selection prompt
    const selectedRoom = prompt(`Select a room for this appointment:\nAvailable rooms: ${availableRooms.value.join(', ')}`, availableRooms.value[0]);

    if (selectedRoom && availableRooms.value.includes(selectedRoom)) {
        // Check occupancy first
        openOccupancyModal(
            selectedRoom,
            date,
            hour,
            minute,
            (appointmentData) => {
                // This callback will be executed after occupancy check
                openAppointmentModal(appointmentData);
            }
        );
    } else if (selectedRoom !== null) {
        alert('Invalid room selected. Please choose from available rooms.');
    }
};

const handleDirectAppointment = (room) => {
    openOccupancyModal(room, new Date(), 9, 0, (appointmentData) => {
        openAppointmentModal(appointmentData);
    });
};

const selectEventInCalendar = (event) => {
    currentView.value = 'calendar';
    currentCalendarDate.value = event.start;
    currentCalendarMode.value = 'day';
    openEventViewer(event);
};

// Watch for changes and save
watchEffect(() => {
    saveEventsToStorage(events.value);
});
</script>

<template>
    <div class="bg-gray-200 font-sans min-h-screen">
        <MessageFunction :show-create-success="toastState.create" :show-edit-success="toastState.edit"
            :show-delete-success="toastState.delete.visible" :deleted-room-name="toastState.delete.name" />

        <Navbar @toggleSidebar="toggleSidebar" class="fixed top-0 left-0 right-0 z-30" />

        <div class="pt-14 min-h-screen transition-all duration-300">
            <Sidebar :sidebarOpen="sidebarOpen" :class="['fixed top-14 left-0 h-[calc(100vh-3.5rem)] z-20 transition-all duration-300 w-64',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full']" />

            <main :class="['p-6 overflow-y-auto transition-all duration-300',
                sidebarOpen ? 'ml-64' : 'ml-0']">
                <!-- Page Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="text-2xl font-semibold text-[#7A0C23]">Schedule</div>
                    <div class="text-sm text-gray-500">UPCEBU > SCHEDULES</div>
                </div>

                <!-- View Toggles -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex justify-start space-x-2">
                        <button @click="currentView = 'table'" :class="['px-4 py-2 rounded-lg font-medium transition flex items-center border-2',
                            currentView === 'table'
                                ? 'bg-[#7A0C23] text-white shadow-lg border-[#7A0C23]'
                                : 'bg-white text-[#7A0C23] border-[#7A0C23] hover:bg-red-50']">
                            Appointment List
                        </button>
                        <button @click="currentView = 'calendar'" :class="['px-4 py-2 rounded-lg font-medium transition flex items-center border-2',
                            currentView === 'calendar'
                                ? 'bg-[#7A0C23] text-white shadow-lg border-[#7A0C23]'
                                : 'bg-white text-[#7A0C23] border-[#7A0C23] hover:bg-red-50']">
                            Calendar View
                        </button>
                    </div>

                    <!-- Quick Add Buttons -->

                </div>

                <!-- Main Content -->
                <TableComponent v-if="currentView === 'table'" :events="events" @view-details="selectEventInCalendar"
                    @edit-event="handleEditEvent" @delete-event="handleDeleteEvent" @row-clicked="openEventViewer" />

                <CalendarView v-else :data="events" :initial-date="currentCalendarDate"
                    :initial-mode="currentCalendarMode" :ListViewComponent="TableComponent"
                    :MonthGridViewComponent="MonthGridView" :TimeGridViewComponent="TimeGridView"
                    @update:date="(date) => currentCalendarDate = date"
                    @update:mode="(mode) => currentCalendarMode = mode" @dateClicked="handleDateClick"
                    @selectEvent="openEventViewer" @editEvent="handleEditEvent" @deleteEvent="handleDeleteEvent"
                    @addAppointment="() => handleDirectAppointment(availableRooms.value[0])"
                    @dayClick="openDayEventsViewer" />
            </main>
        </div>

        <!-- Modals -->
        <AppointmentModal :is-visible="modalState.appointment.visible"
            :selected-date="modalState.appointment.data?.selectedDate"
            :initial-room="modalState.appointment.data?.initialRoom"
            :initial-hour="modalState.appointment.data?.initialHour"
            :initial-minute="modalState.appointment.data?.initialMinute" :editing-event="modalState.appointment.editing"
            @close="() => closeModal('appointment')" @success="handleAppointmentSuccess" />

        <OccupancyCheckModal :is-visible="modalState.occupancy.visible" :data="modalState.occupancy.data"
            @close="() => closeModal('occupancy')" @proceed="handleOccupancyProceed" />

        <EventViewerModal :is-visible="modalState.eventViewer.visible" :event="modalState.eventViewer.event"
            @close="() => closeModal('eventViewer')" @edit="handleEditEvent" @delete="handleDeleteEvent" />

        <DayEventsViewerModal :is-visible="modalState.dayViewer.visible" :events="modalState.dayViewer.events"
            :date="modalState.dayViewer.date" @close="() => closeModal('dayViewer')" @view-event="openEventViewer"
            @edit-event="handleEditEvent" @delete-event="handleDeleteEvent"
            @add-appointment="() => handleDirectAppointment(availableRooms.value[0])" />
    </div>
</template>
