<?php

namespace App\Http\Controllers;

use App\Models\Expedition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpeditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only Super Admin should ideally access this, but we'll assume middleware handles it
        $expeditions = Expedition::all();
        
        return Inertia::render('Expeditions/Index', [
            'expeditions' => $expeditions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain_slug' => 'nullable|string|max:255|unique:expeditions',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['domain_slug'])) {
            $baseSlug = \Illuminate\Support\Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Expedition::where('domain_slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $validated['domain_slug'] = $slug;
        }

        // Default receipt code dan theme color jika belum diset
        $receiptCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $validated['name']), 0, 3));
        if (strlen($receiptCode) < 3) $receiptCode = 'EXP';
        $validated['receipt_code'] = $receiptCode;
        $validated['theme_color'] = '#10b981'; // default emerald/green

        $expedition = Expedition::create($validated);

        // 1. Buatkan Gudang Cabang Utama default untuk ekspedisi baru
        $defaultWarehouse = \App\Models\Warehouse::create([
            'name' => 'Gudang Utama ' . $expedition->name,
            'city' => 'Jepara',
            'address' => $expedition->address ?: 'Jepara, Jawa Tengah',
            'phone' => $expedition->phone ?: '',
            'expedition_id' => $expedition->id,
        ]);

        // 2. Buat starter accounts lengkap: Admin, Supir Pickup, Supir Lintas, Staff Gudang
        $domain = $expedition->domain_slug . '.com';
        $starterAccounts = [
            [
                'name' => 'Admin ' . $expedition->name,
                'email' => 'admin@' . $domain,
                'role' => 'ADMIN',
                'warehouse_id' => null,
            ],
            [
                'name' => 'Supir Pickup ' . $expedition->name,
                'email' => 'pickup@' . $domain,
                'role' => 'DRIVER_PICKUP',
                'warehouse_id' => null,
            ],
            [
                'name' => 'Supir Lintas ' . $expedition->name,
                'email' => 'lintas@' . $domain,
                'role' => 'DRIVER_LINTAS',
                'warehouse_id' => null,
            ],
            [
                'name' => 'Staff Gudang ' . $expedition->name,
                'email' => 'gudang@' . $domain,
                'role' => 'GUDANG',
                'warehouse_id' => $defaultWarehouse->id,
            ],
        ];

        foreach ($starterAccounts as $acc) {
            // Hindari tabrakan jika email sudah ada
            $email = $acc['email'];
            $i = 1;
            while (\App\Models\User::where('email', $email)->exists()) {
                $email = str_replace('@', "{$i}@", $acc['email']);
                $i++;
            }

            \App\Models\User::create([
                'name' => $acc['name'],
                'email' => $email,
                'phone' => $expedition->phone,
                'password' => bcrypt('password'),
                'role' => $acc['role'],
                'expedition_id' => $expedition->id,
                'warehouse_id' => $acc['warehouse_id'],
            ]);
        }

        try {
            broadcast(new \App\Events\ExpeditionRegistered($expedition));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast ExpeditionRegistered error: '.$e->getMessage());
        }

        return redirect()->route('expeditions.index')->with(
            'success', 
            "Ekspedisi {$expedition->name} berhasil dibuat beserta 4 akun starter (admin@{$domain}, pickup@{$domain}, lintas@{$domain}, gudang@{$domain}) dengan password default 'password'."
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expedition $expedition)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain_slug' => 'nullable|string|max:255|unique:expeditions,domain_slug,' . $expedition->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $expedition->update($validated);

        return redirect()->route('expeditions.index')->with('success', 'Ekspedisi berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expedition $expedition)
    {
        $expedition->delete();

        return redirect()->route('expeditions.index')->with('success', 'Ekspedisi berhasil dihapus.');
    }
}
