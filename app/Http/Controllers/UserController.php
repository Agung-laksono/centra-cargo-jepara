<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->expedition_id === null;

        $usersQuery = User::with(['warehouse', 'expedition']);
        if (!$isSuperAdmin) {
            $usersQuery->where('expedition_id', $user->expedition_id);
        }

        $users = $usersQuery->latest()->get();
        $expeditions = \App\Models\Expedition::where('is_active', true)->get();
        
        $warehousesQuery = \App\Models\Warehouse::query();
        if (!$isSuperAdmin) {
            $warehousesQuery->where('expedition_id', $user->expedition_id);
        }
        $warehouses = $warehousesQuery->get();
        
        return Inertia::render('Settings/Users', [
            'users' => $users,
            'expeditions' => $expeditions,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser && $currentUser->expedition_id === null;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,DRIVER_PICKUP,DRIVER_LINTAS,GUDANG',
            'expedition_id' => 'nullable|exists:expeditions,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        // Tentukan expedition_id
        if ($isSuperAdmin) {
            if ($request->role === 'SUPER_ADMIN') {
                $expeditionId = null;
            } else {
                $expeditionId = $request->expedition_id ?: null;
            }
        } else {
            // Admin ekspedisi hanya bisa membuat staf untuk ekspedisinya sendiri
            abort_if($request->role === 'SUPER_ADMIN', 403, 'Tidak diizinkan membuat Super Admin.');
            $expeditionId = $currentUser->expedition_id;
        }

        // Mapping role enum yang disimpan di database
        $dbRole = $request->role === 'SUPER_ADMIN' ? 'ADMIN' : $request->role;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $dbRole,
            'expedition_id' => $expeditionId,
            'warehouse_id' => $request->role === 'GUDANG' ? $request->warehouse_id : null,
        ]);

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser && $currentUser->expedition_id === null;

        if (!$isSuperAdmin && $user->expedition_id !== $currentUser->expedition_id) {
            abort(403, 'Anda tidak berhak mengedit pengguna ekspedisi lain.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', Rules\Password::defaults()],
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,DRIVER_PICKUP,DRIVER_LINTAS,GUDANG',
            'expedition_id' => 'nullable|exists:expeditions,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        
        if ($isSuperAdmin) {
            if ($request->role === 'SUPER_ADMIN') {
                $user->expedition_id = null;
            } else {
                $user->expedition_id = $request->expedition_id;
            }
        }
        
        $user->role = $request->role === 'SUPER_ADMIN' ? 'ADMIN' : $request->role;
        $user->warehouse_id = $request->role === 'GUDANG' ? $request->warehouse_id : null;
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser && $currentUser->expedition_id === null;

        if (!$isSuperAdmin && $user->expedition_id !== $currentUser->expedition_id) {
            abort(403, 'Anda tidak berhak menghapus pengguna ekspedisi lain.');
        }

        if ($user->id === $currentUser->id) {
            return back()->withErrors(['message' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }
        
        $user->delete();
        return back()->with('success', 'Pengguna berhasil dihapus.');
    }
}
