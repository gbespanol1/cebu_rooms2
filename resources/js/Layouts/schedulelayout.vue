<script setup>
import { ref, computed, watchEffect } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

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
import QuickCreatePopover from '@/Components/ScheduleModal/QuickCreatePopover.vue';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    faculty: { type: Array, default: () => [] },
    requesters: { type: Array, default: () => [] },
    terms: { type: Array, default: () => [] },
});

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

const eventTypeFromDb = (type) => {
    const map = { class: 'Class', meeting: 'Meeting', event: 'Event', other: 'Other type of activity' };
    return map[type] || 'Other type of activity';
};

const fullName = (user) => {
    if (!user) return '';
    const parts = [user.first_name, user.middle_name, user.last_name].filter(Boolean);
    return parts.length ? parts.join(' ') : (user.username || '');
};

const dbScheduleToEvent = (s) => {
    const datePart = (s.date || '').slice(0, 10);
    const start = createDate(datePart, (s.start_time || '00:00').slice(0, 5));
    const end = createDate(datePart, (s.end_time || '00:00').slice(0, 5));
    return {
        id: s.id,
        dbId: s.id,
        title: s.event_title || 'Untitled',
        list: eventTypeFromDb(s.event_type),
        allDay: false,
        start,
        end,
        extendedProps: {
            room: s.room?.room_name || s.room?.room_code || 'N/A',
            roomCode: s.room?.room_code || '',
            building: s.room?.building?.building_name || 'N/A',
            college: s.room?.college?.college_name || s.organizer || 'N/A',
            subject: s.course_name || s.event_title || '',
            courseCode: s.course_code || '',
            type: eventTypeFromDb(s.event_type),
            requester: s.requester_name || fullName(s.requester) || 'N/A',
            description: s.description || '',
            agenda: s.agenda || '',
            organizer: s.organizer || '',
            numberOfStudents: s.number_of_participants,
            faculty: s.faculty_name || fullName(s.faculty) || '',
            section: s.section || '',
            equipment: Array.isArray(s.equipment_needed) ? s.equipment_needed : [],
            additional: Array.isArray(s.additional_requirements) ? s.additional_requirements : [],
            status: s.status || 'pending',
            isRecurring: !!s.is_recurring,
        },
    };
};

const loadEventsFromProps = () => {
    if (!Array.isArray(props.schedules)) return [];
    return props.schedules.map(dbScheduleToEvent);
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
const events = ref(loadEventsFromProps());
const availableRooms = computed(() => {
    const list = (props.rooms || [])
        .map((r) => r.room_name || r.room_code)
        .filter(Boolean);
    return list.length > 0 ? list : ['UG 114', 'AVR 201', 'Lab 305', 'Main Conference Room'];
});

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

// Quick-create popover state (Google Calendar style)
const quickCreate = ref({
    visible: false,
    position: null,
    date: null,
    room: '',
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
const pad2 = (n) => String(n).padStart(2, '0');
const fmtLocalDate = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const fmtLocalTime = (d) => `${pad2(d.getHours())}:${pad2(d.getMinutes())}:00`;
const fmtLocalDateTime = (d) => `${fmtLocalDate(d)} ${fmtLocalTime(d)}`;

const buildBackendPayload = (data) => {
    const s = new Date(data.start);
    const e = new Date(data.end);
    return {
        room: data.room,
        type: data.type,
        title: data.title,
        date: fmtLocalDate(s),
        startTime: fmtLocalTime(s),
        endTime: fmtLocalTime(e),
        start: fmtLocalDateTime(s),
        end: fmtLocalDateTime(e),
        numberParticipants: data.numberParticipants ?? null,
        numberOfStudents: data.numberOfStudents ?? null,
        deptOffice: data.deptOffice ?? '',
        organization: data.organization ?? '',
        description: data.description ?? '',
        agenda: data.agenda ?? '',
        requester: data.requester ?? '',
        subject: data.subject ?? '',
        section: data.section ?? '',
        faculty: data.faculty ?? '',
        organizer: data.organizer ?? '',
        name: data.name ?? '',
        tablesChairs: !!data.tablesChairs,
        airConditioner: !!data.airConditioner,
        whiteboard: !!data.whiteboard,
        additionalInstructions: data.additionalInstructions ?? '',
        recurring: !!data.recurring,
    };
};

const formatTimeRange = (start, end) => {
    const fmt = (d) => new Date(d).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    return `${fmt(start)} – ${fmt(end)}`;
};

const buildClientConflictMessage = (room, start, end, conflictEvent) => {
    const date = new Date(start).toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    const conflictTitle = conflictEvent?.title || conflictEvent?.extendedProps?.subject || 'an existing appointment';
    const conflictRange = formatTimeRange(conflictEvent.start, conflictEvent.end);
    return `Booking conflict: ${room} is already reserved on ${date} from ${conflictRange} for "${conflictTitle}". Please choose a different room or time.`;
};

const findRoomConflict = (room, startTime, endTime, eventsList, excludeEventId = null) => {
    const start = new Date(startTime);
    const end = new Date(endTime);
    return eventsList.find(event => {
        if (excludeEventId && event.id === excludeEventId) return false;
        if (event.extendedProps?.room !== room) return false;
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return start < eventEnd && end > eventStart;
    }) || null;
};

const handleAppointmentSuccess = async (data) => {
    const editingEvent = modalState.value.appointment.editing;
    const isEdit = !!editingEvent;
    const eventIdForConflictCheck = isEdit ? editingEvent.id : null;

    const conflictEvent = findRoomConflict(data.room, data.start, data.end, events.value, eventIdForConflictCheck);
    if (conflictEvent) {
        alert(buildClientConflictMessage(data.room, data.start, data.end, conflictEvent));
        openOccupancyModal(data.room, data.start, data.start.getHours(), data.start.getMinutes());
        return;
    }

    const payload = buildBackendPayload(data);

    try {
        let savedSchedule;
        if (isEdit && editingEvent.dbId) {
            const res = await axios.put(`/Schedule/${editingEvent.dbId}`, payload);
            savedSchedule = res.data.schedule;
        } else {
            const res = await axios.post('/Schedule', payload);
            savedSchedule = res.data.schedule;
        }

        const newEvent = dbScheduleToEvent(savedSchedule);

        if (isEdit) {
            const index = events.value.findIndex((e) => e.id === editingEvent.id);
            if (index !== -1) events.value[index] = newEvent;
            triggerToast('edit');
        } else {
            events.value.push(newEvent);
            triggerToast('create');
        }

        closeModal('appointment');

        if (currentView.value === 'calendar') {
            selectEventInCalendar(newEvent);
        }
    } catch (err) {
        console.error('Failed to save appointment:', err);
        const msg = err.response?.data?.message
            || (err.response?.data?.errors && Object.values(err.response.data.errors).flat().join('\n'))
            || 'Failed to save appointment. Please check the form and try again.';
        alert(msg);
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

const handleDeleteEvent = async (eventObject) => {
    if (!confirm(`Are you sure you want to delete: ${eventObject?.title}?`)) return;

    try {
        if (eventObject.dbId) {
            await axios.delete(`/Schedule/${eventObject.dbId}`);
        }
        events.value = events.value.filter((e) => e.id !== eventObject.id);
        triggerToast('delete', eventObject?.title || 'Appointment');

        if (
            modalState.value.eventViewer.visible
            && modalState.value.eventViewer.event?.id === eventObject.id
        ) {
            closeModal('eventViewer');
        }
    } catch (err) {
        console.error('Failed to delete appointment:', err);
        alert('Failed to delete appointment. Please try again.');
    }
};

// Open the floating quick-create popover when a calendar cell is clicked
const handleDateClick = (date, hour = null, minute = null, position = null) => {
    quickCreate.value = {
        visible: true,
        position: position || { x: window.innerWidth / 2 - 170, y: window.innerHeight / 2 - 180 },
        date: new Date(date),
        room: availableRooms.value[0] || '',
    };
};

const closeQuickCreate = () => {
    quickCreate.value = { visible: false, position: null, date: null, room: '' };
};

const handleQuickCreateSave = (data) => {
    const [sh, sm] = data.startTime.split(':').map(Number);
    const [eh, em] = data.endTime.split(':').map(Number);

    const startDt = new Date(data.date);
    startDt.setHours(sh, sm, 0, 0);

    const endDt = new Date(data.date);
    endDt.setHours(eh, em, 0, 0);
    if (endDt <= startDt) endDt.setDate(endDt.getDate() + 1);

    const payload = {
        title: data.title,
        type: data.eventType,
        room: data.room,
        start: startDt,
        end: endDt,
        allDay: false,
        deptOffice: '',
        organization: '',
        description: '',
        agenda: data.eventType === 'Meeting' ? data.title : '',
        subject: data.eventType === 'Class' ? data.title : '',
        section: '',
        faculty: '',
        organizer: '',
        name: '',
        requester: '',
        numberParticipants: null,
        numberOfStudents: null,
        tablesChairs: false,
        airConditioner: false,
        whiteboard: false,
        additionalInstructions: '',
        recurring: false,
    };

    closeQuickCreate();
    handleAppointmentSuccess(payload);
};

const handleQuickCreateMoreOptions = (data) => {
    const [sh, sm] = (data?.startTime || '09:00').split(':').map(Number);
    const baseDate = data?.date || quickCreate.value.date || new Date();
    closeQuickCreate();
    openAppointmentModal({
        selectedDate: dateToIsoDateString(baseDate),
        initialRoom: data?.room || availableRooms.value[0] || '',
        initialHour: sh,
        initialMinute: sm,
    });
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

// Reload events when fresh data arrives from the backend (e.g., after navigation)
watchEffect(() => {
    events.value = loadEventsFromProps();
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
                    @addAppointment="() => handleDirectAppointment(availableRooms[0])"
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
            @add-appointment="() => handleDirectAppointment(availableRooms[0])" />

        <QuickCreatePopover
            :is-visible="quickCreate.visible"
            :position="quickCreate.position"
            :initial-date="quickCreate.date"
            :initial-room="quickCreate.room"
            :rooms="availableRooms"
            @close="closeQuickCreate"
            @save="handleQuickCreateSave"
            @more-options="handleQuickCreateMoreOptions"
        />
    </div>
</template>
