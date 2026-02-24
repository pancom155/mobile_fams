<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Site;
use App\Models\Location;
use App\Models\Asset;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Check if sites exist to avoid duplication if seeder runs multiple times
        if (Site::count() == 0) {
             $site1 = Site::create(['name' => 'Headquarters']);
             $site2 = Site::create(['name' => 'Branch Office']);
     
             Location::create(['site_id' => $site1->id, 'name' => 'Main Warehouse']);
             Location::create(['site_id' => $site1->id, 'name' => 'Audit Room']);
             Location::create(['site_id' => $site2->id, 'name' => 'Storage A']);
             Location::create(['site_id' => $site2->id, 'name' => 'Front Desk']);
        }

        // Seed Asset
        if (Asset::where('asset_id', 'CP123-00000000001')->doesntExist()) {
            Asset::create([
                'asset_id' => 'CP123-00000000001',
                'short_description' => 'Dell Latitude 5420',
                'brand' => 'Dell',
                'serial_number' => 'ABC123XYZ'
            ]);
        }
    }
}
