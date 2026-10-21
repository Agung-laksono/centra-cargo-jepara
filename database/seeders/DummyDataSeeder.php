<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sender;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Koli;
use App\Models\Manifest;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        // Bersihkan data lama jika diperlukan (opsional, hati-hati jika dipakai di production)
        // \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Manifest::truncate();
        // Koli::truncate();
        // Shipment::truncate();
        // Sender::truncate();
        // Driver::truncate();
        // \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Buat Supir
        $driver1 = Driver::firstOrCreate(
            ['phone_number' => '081234567890'],
            ['name' => 'Budi Supir', 'vehicle_name' => 'L300 - K 1234 AB']
        );

        $driver2 = Driver::firstOrCreate(
            ['phone_number' => '081234567891'],
            ['name' => 'Andi Pickup', 'vehicle_name' => 'Engkel - K 5678 CD']
        );

        // 2. Buat Sender (Pengrajin)
        $sender1 = Sender::firstOrCreate(
            ['phone' => '08987654321'],
            [
                'business_name' => 'UD Maju Mebel (Jepara)',
                'owner_name' => 'Pak Budi',
                'address' => 'Jl. Raya Tahunan No. 12',
                'village' => 'Tahunan',
                'district' => 'Tahunan',
                'city' => 'Jepara',
                'province' => 'Jawa Tengah',
                'maps_url' => 'https://maps.google.com/?q=Jl.+Raya+Tahunan+No.+12,+Jepara'
            ]
        );

        $sender2 = Sender::firstOrCreate(
            ['phone' => '08987654322'],
            [
                'business_name' => 'Karya Jati Abadi',
                'owner_name' => 'Pak Andi',
                'address' => 'Desa Mantingan RT 02/01',
                'village' => 'Mantingan',
                'district' => 'Tahunan',
                'city' => 'Jepara',
                'province' => 'Jawa Tengah'
            ]
        );

        // 3. Buat Shipment (Resi)
        $shipments = [];
        $tokoNames = [
            'Toko Furniture Jakarta', 'Mebel Nusantara Indah', 'Griya Jati Pesona', 'Jaya Karya Interior',
            'Sinar Jati Makmur', 'Bintang Mebel Abadi', 'Pesona Ukir Jepara', 'Aneka Kursi Kantor',
            'Galeri Kayu Solid', 'Sentra Lemari Custom'
        ];
        $alamat = [
            'Jl. Kemang Raya No 10, Jakarta Selatan',
            'Jl. Sudirman Kav 21, Jakarta Pusat',
            'Perum Pondok Indah Blok B/4, Jakarta Selatan',
            'Jl. Gatot Subroto No 88, Kuningan, Jakarta Selatan',
            'Jl. Kelapa Gading Boulevard Raya, Jakarta Utara',
            'Ruko Pantai Indah Kapuk No. 12, Jakarta Utara',
            'Jl. Fatmawati No 45, Cilandak, Jakarta Selatan',
            'Jl. TB Simatupang No 9, Pasar Minggu, Jakarta Selatan',
            'Jl. Pluit Karang Indah, Penjaringan, Jakarta Utara',
            'Jl. Letjen S. Parman, Slipi, Jakarta Barat'
        ];
        
        $trackingService = app(\App\Services\TrackingService::class);
        
        for ($i = 0; $i < 20; $i++) {
            $tokoName = $tokoNames[$i % count($tokoNames)] . ' - Cabang ' . ($i + 1);
            $alamatTujuan = $alamat[$i % count($alamat)] . ' (Blok ' . ($i + 1) . ')';
            
            $trackingNumber = $trackingService->generateTrackingNumber();
            
            $shipment = Shipment::firstOrCreate(
                ['tracking_number' => $trackingNumber],
                [
                    'sender_id' => $sender1->id,
                    'recipient_name' => $tokoName,
                    'recipient_phone' => '08111222' . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'recipient_address' => $alamatTujuan,
                    'destination_province' => 'DKI Jakarta',
                    'destination_city' => 'Jakarta Selatan',
                    'destination_district' => 'Kebayoran',
                    'destination_village' => 'Senayan',
                    'payment_type' => $i % 2 == 0 ? 'COD' : 'Lunas',
                    'total_cod' => $i % 2 == 0 ? (500000 * ($i % 5 + 1)) : 0,
                    'current_status' => 'Dalam Perjalanan',
                    'pickup_type' => 'Minta Dijemput Armada',
                ]
            );
            $shipments[] = $shipment;
        }

        // 4. Buat Koli (3 koli per shipment agar seru)
        $kolisDelivery = [];
        foreach ($shipments as $idx => $shp) {
            $totalKoli = ($idx % 3) + 1; // 1 to 3 kolis
            for ($k = 1; $k <= $totalKoli; $k++) {
                $koliSuffix = strtoupper(\Illuminate\Support\Str::random(3));
                $kolisDelivery[] = Koli::firstOrCreate(
                    ['barcode_id' => $shp->tracking_number . '-' . $koliSuffix],
                    [
                        'shipment_id' => $shp->id,
                        'koli_number' => $k,
                        'total_koli' => $totalKoli,
                        'description' => 'Paket Mebel ' . $k . ' dari ' . $totalKoli
                    ]
                );
            }
        }

        // 5. Buat Manifest DELIVERY (Supir Lintas)
        $manifestDelivery = Manifest::firstOrCreate(
            ['magic_link_token' => 'dummy-token'],
            [
                'manifest_type' => 'DELIVERY',
                'driver_id' => $driver1->id,
                'status' => 'PREPARING',
            ]
        );
        $manifestDelivery->kolis()->syncWithoutDetaching(collect($kolisDelivery)->pluck('id'));

        // 6. Buat Manifest PICKUP (Supir Jemput)
        $manifestPickup = Manifest::firstOrCreate(
            ['magic_link_token' => 'dummy-token-pickup'],
            [
                'manifest_type' => 'PICKUP',
                'driver_id' => $driver2->id,
                'status' => 'PREPARING',
            ]
        );
        $manifestPickup->kolis()->syncWithoutDetaching(collect($kolisDelivery)->pluck('id'));
    }
}
