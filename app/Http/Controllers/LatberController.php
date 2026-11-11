<?php

namespace App\Http\Controllers;

use App\Models\Latber;
use Illuminate\Http\Request;

class LatberController extends Controller
{
    public function index()
    {
        $latbers = Latber::with('sessions')
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($latber) {
                $latber->ringkasan = $latber->ringkasan;
                return $latber;
            });
        return response()->json($latbers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'tanggal' => 'required|date',
            'lokasi' => 'nullable|string|max:150',
        ]);

        $latber = Latber::create($validated);
        $latber->ringkasan = $latber->ringkasan;
        return response()->json($latber, 201);
    }

    public function show(Latber $latber)
    {
        $latber->load('sessions');
        $latber->ringkasan = $latber->ringkasan;
        return response()->json($latber);
    }

    public function update(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'tanggal' => 'required|date',
            'lokasi' => 'nullable|string|max:150',
        ]);

        $latber->update($validated);
        $latber->ringkasan = $latber->ringkasan;
        return response()->json($latber);
    }

    public function destroy(Latber $latber)
    {
        $hasSalesData = $latber->sessions()
            ->where(function ($query) {
                $query->where('terjual', '>', 0)
                    ->orWhere('terpesan', '>', 0);
            })
            ->exists();

        if ($hasSalesData) {
            return response()->json(['message' => 'Latber sudah memiliki data penjualan dan tidak dapat dihapus'], 422);
        }

        $latber->delete();
        return response()->json(null, 204);
    }
}
