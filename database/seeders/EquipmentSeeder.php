<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\UserAccount;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Canonical equipment catalog used for room autocomplete suggestions.
     */
    public const CATALOG = [
        ['name' => 'TV', 'brand' => 'Samsung', 'status' => 'available'],
        ['name' => 'Television', 'brand' => 'LG', 'status' => 'available'],
        ['name' => 'Chairs', 'brand' => 'Generic', 'status' => 'available'],
        ['name' => 'Remote Control', 'brand' => 'Universal', 'status' => 'available'],
        ['name' => 'Air Conditioner', 'brand' => 'Carrier', 'status' => 'available'],
        ['name' => 'Aircon', 'brand' => 'Daikin', 'status' => 'available'],
        ['name' => 'Projector', 'brand' => 'Epson', 'status' => 'available'],
        ['name' => 'Whiteboard', 'brand' => 'Office Pro', 'status' => 'available'],
        ['name' => 'Microphone', 'brand' => 'Shure', 'status' => 'available'],
        ['name' => 'Speaker System', 'brand' => 'JBL', 'status' => 'available'],
        ['name' => 'Laptop', 'brand' => 'Dell', 'status' => 'available'],
        ['name' => 'Desktop Computer', 'brand' => 'HP', 'status' => 'available'],
        ['name' => 'Printer', 'brand' => 'Canon', 'status' => 'available'],
        ['name' => 'Scanner', 'brand' => 'Fujitsu', 'status' => 'available'],
        ['name' => 'Camera', 'brand' => 'Sony', 'status' => 'available'],
        ['name' => 'Tablet', 'brand' => 'Apple', 'status' => 'available'],
        ['name' => 'Router', 'brand' => 'Cisco', 'status' => 'available'],
        ['name' => 'Switch', 'brand' => 'Netgear', 'status' => 'available'],
        ['name' => 'Server', 'brand' => 'Dell', 'status' => 'maintenance'],
        ['name' => 'Desk', 'brand' => 'IKEA', 'status' => 'available'],
        ['name' => 'Table', 'brand' => 'IKEA', 'status' => 'available'],
        ['name' => 'Clock', 'brand' => 'Seiko', 'status' => 'available'],
        ['name' => 'Fan', 'brand' => 'Panasonic', 'status' => 'available'],
        ['name' => 'Keyboard', 'brand' => 'Logitech', 'status' => 'available'],
        ['name' => 'Mouse', 'brand' => 'Logitech', 'status' => 'available'],
    ];

    public function run(): void
    {
        $room = Room::query()->first();
        $building = Building::query()->first();
        $college = College::query()->first();
        $department = Department::query()->first();
        $user = UserAccount::query()->first();

        $inventoryBase = Equipment::query()->max('id') ?? 0;

        foreach (self::CATALOG as $index => $item) {
            $inventoryNumber = $inventoryBase + $index + 1;

            Equipment::updateOrCreate(
                ['inventory_id' => 'INV-CAT-' . str_pad((string) $inventoryNumber, 5, '0', STR_PAD_LEFT)],
                [
                    'equipment_name' => $item['name'],
                    'property_id' => 'PROP-CAT-' . str_pad((string) $inventoryNumber, 5, '0', STR_PAD_LEFT),
                    'description' => 'Catalog item for room equipment picker.',
                    'quantity' => 1,
                    'room_id' => $room?->id,
                    'building_id' => $building?->id,
                    'college_id' => $college?->id,
                    'department_id' => $department?->id,
                    'status' => $item['status'],
                    'brand' => $item['brand'],
                    'model' => 'CAT-' . ($index + 1),
                    'serial_number' => 'SN-CAT-' . str_pad((string) $inventoryNumber, 6, '0', STR_PAD_LEFT),
                    'assigned_user_id' => $user?->id,
                ]
            );
        }

        $this->command?->info('Equipment catalog seeded: ' . count(self::CATALOG) . ' items.');
    }
}
