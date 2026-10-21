<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sender;

class SenderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:50',
        ]);

        Sender::create($validated);

        return back()->with('success', 'Data Pengirim berhasil ditambahkan!');
    }
}
