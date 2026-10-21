<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WilayahController extends Controller
{
    public function search(Request $request)
    {
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
        $provinces = DB::connection('wilayah')
            ->table('regions')
            ->where('type', 'province')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
            
        return response()->json($provinces);
    }

    public function regencies($provinceId)
    {
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
                                                            ->timeout(10)
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
                        DB::connection('wilayah')->table('regions')->insert($insertData);
                        
                        // Regenerate wilayah.json in background (optional, but good for offline sync)
                        // This might slow down response slightly, but it's rare
                        $allRegions = DB::connection('wilayah')->table('regions')->get();
                        file_put_contents(public_path('wilayah.json'), json_encode($allRegions));
                        
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
