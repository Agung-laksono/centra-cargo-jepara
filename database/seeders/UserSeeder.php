<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultExpedition = \App\Models\Expedition::firstOrCreate(
            ['domain_slug' => 'jepara-cargo'],
            [
                'name' => 'Jepara Cargo',
                'address' => 'Jepara, Jawa Tengah',
                'phone' => '08123456789',
                'is_active' => true,
            ]
        );

        $nusantaraExpedition = \App\Models\Expedition::firstOrCreate(
            ['domain_slug' => 'nusantara-express'],
            [
                'name' => 'Nusantara Express',
                'address' => 'Semarang / Jepara',
                'phone' => '081299887766',
                'is_active' => true,
            ]
        );

        $users = [
            [
                'name' => 'Super Admin (Agregator Pusat)',
                'email' => 'admin@admin.com',
                'password' => bcrypt('password'),
                'phone' => '081111111111',
                'role' => 'ADMIN',
                'expedition_id' => null,
            ],
            [
                'name' => 'Admin Jepara Cargo',
                'email' => 'admin.jepara@cargo.com',
                'password' => bcrypt('password'),
                'phone' => '081222333444',
                'role' => 'ADMIN',
                'expedition_id' => $defaultExpedition->id,
            ],
            [
                'name' => 'Admin Nusantara Express',
                'email' => 'admin.nusantara@cargo.com',
                'password' => bcrypt('password'),
                'phone' => '081255566677',
                'role' => 'ADMIN',
                'expedition_id' => $nusantaraExpedition->id,
            ],
            [
                'name' => 'Supir Pickup (Dalam Kota)',
                'email' => 'pickup@driver.com',
                'password' => bcrypt('password'),
                'phone' => '082222222222',
                'role' => 'DRIVER_PICKUP',
                'expedition_id' => $defaultExpedition->id,
            ],
            [
                'name' => 'Supir Lintas Kota',
                'email' => 'lintas@driver.com',
                'password' => bcrypt('password'),
                'phone' => '083333333333',
                'role' => 'DRIVER_LINTAS',
                'expedition_id' => $defaultExpedition->id,
            ],
            [
                'name' => 'Staff Gudang',
                'email' => 'gudang@admin.com',
                'password' => bcrypt('password'),
                'phone' => '084444444444',
                'role' => 'GUDANG',
                'expedition_id' => $defaultExpedition->id,
            ]
        ];

        foreach ($users as $user) {
            \App\Models\User::firstOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
