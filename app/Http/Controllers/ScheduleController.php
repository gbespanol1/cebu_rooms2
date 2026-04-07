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
        $schedules = Schedule::with(['room', 'faculty', 'requester', 'term'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(20);

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
            // 'stats' => $this->getScheduleStats(),
        ]);
    }
}
