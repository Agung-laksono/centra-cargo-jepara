<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::all();
        return Inertia::render('Settings/Warehouses', [
            'warehouses' => $warehouses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'maps_url' => 'nullable|url|max:1000',
        ]);

        $warehouse = Warehouse::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Gudang berhasil ditambahkan.',
                'warehouse' => $warehouse,
            ]);
        }

        return back()->with('success', 'Gudang berhasil ditambahkan.')->with('new_warehouse', $warehouse);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'maps_url' => 'nullable|url|max:1000',
        ]);

        $warehouse->update($validated);
        return back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();
        return back()->with('success', 'Gudang berhasil dihapus.');
    }
}
