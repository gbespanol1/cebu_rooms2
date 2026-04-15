<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
{
    return [
        'id' => $this->id,
        'room_name' => $this->room_name,
        'room_code' => $this->room_code,

        'room_type' => $this->whenLoaded('roomType', function () {
            return $this->roomType->room_type_name;
        }),

        // 'room_type_name' => $this->whenLoaded('roomType', function () {
        //     return [
        //         'id' => $this->roomType->id,
        //         'name' => $this->roomType->room_type_name, // adjust column name
        //     ];
        // }),
    ];
}
}
