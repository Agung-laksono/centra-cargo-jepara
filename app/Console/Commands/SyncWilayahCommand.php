<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

#[Signature('wilayah:sync')]
#[Description('Sinkronisasi data wilayah lokal dari EMSIFA JSON API')]
class SyncWilayahCommand extends Command
{
    public function handle()
    {
        $this->info('Memulai sinkronisasi data wilayah dari EMSIFA API...');
        
        $baseUrl = 'https://www.emsifa.com/api-wilayah-indonesia/api';

        // 1. Download Provinces
        $this->info('Mengunduh data Provinsi...');
        $provinces = Http::withoutVerifying()->get("$baseUrl/provinces.json")->json();
        
        if (!is_array($provinces)) {
            $this->error('Gagal mengambil data Provinsi.');
            return;
        }

        $this->info('Mengosongkan tabel wilayah.sqlite...');
        DB::connection('wilayah')->table('regions')->delete();
        
        $this->info('Menyimpan data Provinsi...');
        $provData = array_map(function($p) {
            return ['id' => $p['id'], 'parent_id' => null, 'name' => $p['name'], 'type' => 'province'];
        }, $provinces);
        DB::connection('wilayah')->table('regions')->insert($provData);

        // 2. Fetch Regencies
        $this->info('Mengunduh data Kabupaten/Kota...');
        $regencies = [];
        $responses = Http::pool(function (Pool $pool) use ($provinces, $baseUrl) {
            foreach ($provinces as $p) {
                $pool->as($p['id'])->withoutVerifying()->get("$baseUrl/regencies/{$p['id']}.json");
            }
        });
        
        foreach ($responses as $provId => $response) {
            if ($response->ok() && is_array($response->json())) {
                foreach ($response->json() as $r) {
                    $regencies[] = [
                        'id' => $r['id'], 'parent_id' => $provId, 'name' => $r['name'], 'type' => 'regency'
                    ];
                }
            }
        }
        $this->bulkInsert('regions', $regencies);

        // 3. Fetch Districts
        $this->info('Mengunduh data Kecamatan...');
        $districts = [];
        $regencyChunks = array_chunk($regencies, 50); // Fetch 50 at a time
        
        $bar = $this->output->createProgressBar(count($regencyChunks));
        foreach ($regencyChunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl) {
                foreach ($chunk as $r) {
                    $pool->as($r['id'])->withoutVerifying()->get("$baseUrl/districts/{$r['id']}.json");
                }
            });
            foreach ($responses as $regId => $response) {
                if ($response->ok() && is_array($response->json())) {
                    foreach ($response->json() as $d) {
                        $districts[] = [
                            'id' => $d['id'], 'parent_id' => $regId, 'name' => $d['name'], 'type' => 'district'
                        ];
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->bulkInsert('regions', $districts);

        // 4. Fetch Villages
        $this->info('Mengunduh data Kelurahan/Desa (Peringatan: Ini akan memakan waktu karena ada >7000 request)...');
        $villages = [];
        $districtChunks = array_chunk($districts, 50);
        
        $bar = $this->output->createProgressBar(count($districtChunks));
        foreach ($districtChunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl) {
                foreach ($chunk as $d) {
                    $pool->as($d['id'])->withoutVerifying()->get("$baseUrl/villages/{$d['id']}.json");
                }
            });
            foreach ($responses as $distId => $response) {
                if ($response->ok() && is_array($response->json())) {
                    foreach ($response->json() as $v) {
                        $villages[] = [
                            'id' => $v['id'], 'parent_id' => $distId, 'name' => $v['name'], 'type' => 'village'
                        ];
                    }
                }
            }
            $bar->advance();
            // Insert incrementally to save RAM
            if (count($villages) > 5000) {
                $this->bulkInsert('regions', $villages);
                $villages = [];
            }
        }
        $bar->finish();
        $this->line('');
        
        if (count($villages) > 0) {
            $this->bulkInsert('regions', $villages);
        }

        $this->info('Sukses! Semua data wilayah berhasil disinkronisasi ke lokal.');
    }

    private function bulkInsert($table, $data, $chunkSize = 1000)
    {
        $chunks = array_chunk($data, $chunkSize);
        foreach ($chunks as $chunk) {
            DB::connection('wilayah')->table($table)->insertOrIgnore($chunk);
        }
    }
}
