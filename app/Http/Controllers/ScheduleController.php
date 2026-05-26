<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Room;
use App\Models\Term;
use Inertia\Inertia;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = Schedule::with(['room.building', 'room.college', 'faculty', 'requester', 'term'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $rooms = Room::where('status', 'available')->get();
        $faculty = UserAccount::where('user_type', 'faculty')->get();
        $requesters = UserAccount::whereIn('user_type', ['faculty', 'staff'])->get();
        $terms = Term::where('status', 'active')->get();

        return Inertia::render('Schedule', [
            'schedules' => $schedules,
            'rooms' => $rooms,
            'faculty' => $faculty,
            'requesters' => $requesters,
            'terms' => $terms,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->buildSchedulePayload($validated, $request);

        if ($conflict = $this->findConflict($payload)) {
            return response()->json([
                'success' => false,
                'message' => $this->formatConflictMessage($conflict),
                'conflict' => $conflict,
            ], 422);
        }

        $schedule = Schedule::create($payload);
        $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->buildSchedulePayload($validated, $request);

        if ($conflict = $this->findConflict($payload, $schedule->id)) {
            return response()->json([
                'success' => false,
                'message' => $this->formatConflictMessage($conflict),
                'conflict' => $conflict,
            ], 422);
        }

        $schedule->update($payload);
        $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }

    private function findConflict(array $payload, ?int $ignoreId = null)
    {
        $query = Schedule::query()
            ->where('room_id', $payload['room_id'])
            ->where('date', $payload['date'])
            ->where('start_time', '<', $payload['end_time'])
            ->where('end_time', '>', $payload['start_time']);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->with('room')->first();
    }

    private function formatConflictMessage(Schedule $conflict): string
    {
        $roomName = $conflict->room?->room_name ?? $conflict->room?->room_code ?? 'this room';
        $start = Carbon::parse($conflict->start_time)->format('g:i A');
        $end = Carbon::parse($conflict->end_time)->format('g:i A');
        $date = Carbon::parse($conflict->date)->format('l, F j, Y');
        $title = $conflict->event_title ?: 'an existing appointment';

        return "Booking conflict: {$roomName} is already reserved on {$date} from {$start} to {$end} for \"{$title}\". Please choose a different room or time.";
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'room' => 'required|string',
            'type' => 'required|string',
            'title' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'startTime' => 'required|date_format:H:i:s',
            'endTime' => 'required|date_format:H:i:s',
            'start' => 'nullable|string',
            'end' => 'nullable|string',
            'numberParticipants' => 'nullable|integer',
            'numberOfStudents' => 'nullable|integer',
            'deptOffice' => 'nullable|string',
            'organization' => 'nullable|string',
            'description' => 'nullable|string',
            'agenda' => 'nullable|string',
            'requester' => 'nullable|string',
            'subject' => 'nullable|string',
            'section' => 'nullable|string',
            'faculty' => 'nullable|string',
            'organizer' => 'nullable|string',
            'name' => 'nullable|string',
            'tablesChairs' => 'nullable|boolean',
            'airConditioner' => 'nullable|boolean',
            'whiteboard' => 'nullable|boolean',
            'additionalInstructions' => 'nullable|string',
            'recurring' => 'nullable|boolean',
        ]);
    }

    private function buildSchedulePayload(array $validated, Request $request): array
    {
        $room = Room::where('room_name', $validated['room'])->first()
            ?? Room::where('room_code', $validated['room'])->first()
            ?? Room::first();

        $eventTypeMap = [
            'Class' => 'class',
            'Meeting' => 'meeting',
            'Event' => 'event',
            'Other type of activity' => 'other',
        ];

        $equipment = [];
        if ($request->boolean('tablesChairs')) $equipment[] = 'Tables and chairs';
        if ($request->boolean('airConditioner')) $equipment[] = 'Air conditioner';
        if ($request->boolean('whiteboard')) $equipment[] = 'Whiteboard';

        $additional = [];
        if (!empty($validated['additionalInstructions'])) {
            $additional[] = $validated['additionalInstructions'];
        }

        return [
            'room_id' => $room?->id ?? 1,
            'event_title' => $validated['title'],
            'event_type' => $eventTypeMap[$validated['type']] ?? 'other',
            'course_code' => $validated['subject'] ?? null,
            'course_name' => $validated['subject'] ?? null,
            'section' => $validated['section'] ?? null,
            'faculty_name' => $validated['faculty'] ?? null,
            'date' => $validated['date'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'day_of_week' => Carbon::parse($validated['date'])->englishDayOfWeek,
            'number_of_participants' => $validated['numberParticipants']
                ?? $validated['numberOfStudents']
                ?? null,
            'requester_name' => $validated['requester'] ?? null,
            'description' => $validated['description'] ?? null,
            'agenda' => $validated['agenda'] ?? null,
            'organizer' => $validated['organizer']
                ?? $validated['deptOffice']
                ?? null,
            'equipment_needed' => $equipment ?: null,
            'additional_requirements' => $additional ?: null,
            'status' => 'pending',
            'is_recurring' => $request->boolean('recurring'),
        ];
    }
}
