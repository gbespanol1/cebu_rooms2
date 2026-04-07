<?php

namespace App\Services;

use App\Models\Room;

class RoomService
{
   public function getRooms(int $perPage = 10, ?string $search = null)
    {
        return Room::with('building', 'college', 'department', 'roomType', 'assignedUser')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('room_name', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->withQueryString();
    }
}
