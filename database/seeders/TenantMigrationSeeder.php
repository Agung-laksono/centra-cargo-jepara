<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expedition;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Driver;
use App\Models\Sender;
use App\Models\Unit;
use App\Models\Manifest;
use App\Models\Shipment;
use App\Models\Setting;

class TenantMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable global scope temporarily just in case
        User::withoutGlobalScope('expedition');
        Warehouse::withoutGlobalScope('expedition');
        Driver::withoutGlobalScope('expedition');
        Sender::withoutGlobalScope('expedition');
        Unit::withoutGlobalScope('expedition');
        Manifest::withoutGlobalScope('expedition');
        Shipment::withoutGlobalScope('expedition');
        Setting::withoutGlobalScope('expedition');

        $expedition = Expedition::firstOrCreate(
            ['name' => 'Jepara Cargo (Default)'],
            [
                'domain_slug' => 'jepara-cargo',
                'address' => 'Jepara',
                'phone' => '08123456789',
                'is_active' => true,
            ]
        );

        // Assign existing data to this default expedition
        User::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Warehouse::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Driver::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Sender::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Unit::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Manifest::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Shipment::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);
        Setting::whereNull('expedition_id')->update(['expedition_id' => $expedition->id]);

        // Make the first user the Super Admin (Aggregator)
        $superAdmin = User::first();
        if ($superAdmin) {
            $superAdmin->expedition_id = null; // Aggregator has no expedition_id
            $superAdmin->save();
        }
    }
}
