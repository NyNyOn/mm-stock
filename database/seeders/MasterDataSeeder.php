<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Categories
        $categories = ['IT Equipment', 'Stationary', 'Furniture', 'Tools', 'Vehicles'];
        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['prefix' => strtoupper(substr($name, 0, 2))]);
        }

        // 2. Locations
        $locations = ['Server Room', 'Office A', 'Office B', 'Warehouse', 'Meeting Room'];
        foreach ($locations as $name) {
            Location::firstOrCreate(['name' => $name]);
        }

        // 3. Units
        $units = ['pcs', 'ea', 'set', 'box', 'roll'];
        foreach ($units as $name) {
            Unit::firstOrCreate(['name' => $name]);
        }
        
        // 4. Create some Equipments (Optional, to fix dashboard loop)
        // Check if there are any equipments, if not create one dummy
        if (\App\Models\Equipment::count() == 0) {
            $cat = Category::first();
            \App\Models\Equipment::create([
                'name' => 'Sample Laptop',
                'category_id' => $cat->id,
                'model' => 'XPS 15',
                'quantity' => 10,
                'min_stock' => 2,
                'status' => 'available',
                'withdrawal_type' => 'returnable'
            ]);
        }
    }
}
