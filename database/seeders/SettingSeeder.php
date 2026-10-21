<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'company_name' => 'Galang Furniture Cargo',
            'company_contact' => '081234567890',
            'company_receipt_code' => 'GFC',
            'company_address' => 'Jl. Tahunan - Batealit, Jepara, Jawa Tengah',
            'company_tagline' => 'Sistem Operasional Terpadu & Terpercaya',
            'company_theme' => '#8B5A2B',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
