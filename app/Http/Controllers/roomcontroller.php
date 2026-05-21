<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomListResource;
use App\Http\Resources\RoomResource;
use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserAccount;
use App\Services\RoomService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function __construct(
        protected RoomService $roomService,
    ) {}
    public function index(Request $request)
    {
        $perPage = 10;
        $search = $request->input('search');
        $rooms = $this->roomService->getRooms($perPage, $search);
        $buildings = Building::all();
        $colleges = College::all();
        $roomTypes = RoomType::all();
        $departments = Department::all();
        $users = UserAccount::all();

        return Inertia::render('Rooms', [
            'rooms' => $rooms,
            'buildings' => $buildings,
            'colleges' => $colleges,
            'departments' => $departments,
            'roomTypes' => $roomTypes,
            'users' => $users,
        ]);
    }

    public function apiIndex()
    {
        $rooms = $this->roomService->getRoomsForApi();

        return RoomResource::collection($rooms);
    }

    public function apiShow($id)
    {
        $room = $this->roomService->getRoomById($id);

        return new RoomListResource($room);
    }

    public function store(StoreRoomRequest $request)
    {
        Room::create($request->validated());

        return redirect()->back()->with('success', 'Room created successfully.');
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        return redirect()->back()->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()
            ->back()
            ->with('success', 'Room deleted successfully.');
    }
}
