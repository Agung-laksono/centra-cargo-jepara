<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WilayahController extends Controller
{
    public function __construct()
    {
        $this->ensureDatabaseExists();
    }

    /**
     * Pastikan database wilayah.sqlite ada dan terisi dari public/wilayah.json jika belum tersedia.
     */
    protected function ensureDatabaseExists()
    {
        $dbPath = config('database.connections.wilayah.database', database_path('wilayah.sqlite'));
        if (!file_exists($dbPath) || filesize($dbPath) < 1000) {
            $jsonPath = public_path('wilayah.json');
            if (!file_exists($jsonPath)) {
                return;
            }

            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            try {
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->exec("PRAGMA synchronous = OFF");
                $pdo->exec("PRAGMA journal_mode = MEMORY");

                $pdo->exec("CREATE TABLE IF NOT EXISTS regions (id VARCHAR(20) PRIMARY KEY, parent_id VARCHAR(20), name VARCHAR(255), type VARCHAR(50))");

                $data = json_decode(file_get_contents($jsonPath), true);
                if (is_array($data) && count($data) > 0) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO regions (id, parent_id, name, type) VALUES (?, ?, ?, ?)");
                    foreach ($data as $item) {
                        $stmt->execute([
                            $item['id'] ?? null,
                            $item['parent_id'] ?? null,
                            $item['name'] ?? null,
                            $item['type'] ?? null
                        ]);
                    }
                    $pdo->commit();
                }

                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_parent ON regions(parent_id)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_name ON regions(name COLLATE NOCASE)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_type ON regions(type)");

                $stmt = null;
                $pdo = null;

                @chmod($dbPath, 0666);
                DB::purge('wilayah');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Gagal inisialisasi wilayah.sqlite: " . $e->getMessage());
            }
        }
    }

    public function search(Request $request)
    {
        $this->ensureDatabaseExists();

        $query = $request->input('q');
        if (!$query || strlen($query) < 3) {
            return response()->json([]);
        }

        // We want to find a district (kecamatan) or a village (kelurahan)
        // and return the full hierarchy (province, regency, district, village)
        $terms = explode(' ', trim($query));
        
        // Build base query
        $dbQuery = DB::connection('wilayah')
            ->table('regions as v')
            ->join('regions as d', 'v.parent_id', '=', 'd.id')
            ->join('regions as c', 'd.parent_id', '=', 'c.id')
            ->join('regions as p', 'c.parent_id', '=', 'p.id')
            ->where('v.type', 'village');

        // Apply smart search: EVERY word must match SOMEWHERE in the hierarchy
        foreach ($terms as $term) {
            $searchTerm = "%{$term}%";
            $dbQuery->where(function($q) use ($searchTerm) {
                $q->where('v.name', 'LIKE', $searchTerm)
                  ->orWhere('d.name', 'LIKE', $searchTerm)
                  ->orWhere('c.name', 'LIKE', $searchTerm)
                  ->orWhere('p.name', 'LIKE', $searchTerm);
            });
        }
        
        $villages = $dbQuery->select(
                'v.id as village_id', 'v.name as village_name',
                'd.id as district_id', 'd.name as district_name',
                'c.id as city_id', 'c.name as city_name',
                'p.id as province_id', 'p.name as province_name'
            )
            ->limit(20)
            ->get();

        return response()->json($villages);
    }

    public function provinces()
    {
        $this->ensureDatabaseExists();

        $provinces = DB::connection('wilayah')
            ->table('regions')
            ->where('type', 'province')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
            
        return response()->json($provinces);
    }

    public function regencies($provinceId)
    {
        $this->ensureDatabaseExists();

        $regencies = DB::connection('wilayah')
            ->table('regions')
            ->where('type', 'regency')
            ->where('parent_id', $provinceId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'parent_id as province_id']);
            
        return response()->json($regencies);
    }

    public function districts($regencyId)
    {
        $this->ensureDatabaseExists();

        $districts = DB::connection('wilayah')
            ->table('regions')
            ->where('type', 'district')
            ->where('parent_id', $regencyId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'parent_id as regency_id']);
            
        return response()->json($districts);
    }

    public function villages($districtId)
    {
        $this->ensureDatabaseExists();

        $villages = DB::connection('wilayah')
            ->table('regions')
            ->where('type', 'village')
            ->where('parent_id', $districtId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'parent_id as district_id']);
            
        // Jika kosong di DB lokal, fetch on-the-fly dari EMSIFA! (Self-healing DB)
        if ($villages->isEmpty()) {
            try {
                $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
                                                            ->timeout(3)
                                                            ->get("https://www.emsifa.com/api-wilayah-indonesia/api/villages/{$districtId}.json");
                
                if ($response->successful()) {
                    $apiVillages = $response->json();
                    $insertData = [];
                    foreach($apiVillages as $vil) {
                        $insertData[] = [
                            'id' => $vil['id'],
                            'name' => $vil['name'],
                            'type' => 'village',
                            'parent_id' => $districtId
                        ];
                    }
                    
                    if (count($insertData) > 0) {
                        DB::connection('wilayah')->table('regions')->insertOrIgnore($insertData);
                        
                        // Re-query dari DB
                        $villages = DB::connection('wilayah')
                            ->table('regions')
                            ->where('type', 'village')
                            ->where('parent_id', $districtId)
                            ->orderBy('name', 'asc')
                            ->get(['id', 'name', 'parent_id as district_id']);
                    }
                }
            } catch (\Exception $e) {
                // Abaikan jika gagal, return array kosong
            }
        }
            
        return response()->json($villages);
    }
}

