<?php

namespace App\Http\Controllers;

use App\Models\Latber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LatberCountController extends Controller
{
    public function updateTerjual(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'sesi' => 'required|array|min:1',
            'sesi.*.id' => 'required|integer|distinct|exists:latber_sessions,id',
            'sesi.*.jumlah' => 'required|integer|between:0,99999',
        ]);

        $sessionIds = array_column($validated['sesi'], 'id');
        $latberSessionIds = $latber->sessions()->pluck('id')->toArray();

        $invalidIds = array_diff($sessionIds, $latberSessionIds);
        if (!empty($invalidIds)) {
            return response()->json(['message' => 'Terdapat ID sesi yang tidak milik latber ini'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($validated['sesi'] as $item) {
                $latber->sessions()->where('id', $item['id'])->update(['terjual' => $item['jumlah']]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data'], 500);
        }

        return $this->getSessions($latber);
    }

    public function updateTerpesan(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'sesi' => 'required|array|min:1',
            'sesi.*.id' => 'required|integer|distinct|exists:latber_sessions,id',
            'sesi.*.jumlah' => 'required|integer|between:0,99999',
        ]);

        $sessionIds = array_column($validated['sesi'], 'id');
        $latberSessionIds = $latber->sessions()->pluck('id')->toArray();

        $invalidIds = array_diff($sessionIds, $latberSessionIds);
        if (!empty($invalidIds)) {
            return response()->json(['message' => 'Terdapat ID sesi yang tidak milik latber ini'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($validated['sesi'] as $item) {
                $latber->sessions()->where('id', $item['id'])->update(['terpesan' => $item['jumlah']]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data'], 500);
        }

        return $this->getSessions($latber);
    }

    public function ambilTerpesan(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:latber_sessions,id',
            'jumlah' => 'required|integer|min:1|max:99999',
        ]);

        $session = $latber->sessions()->find($validated['session_id']);
        
        if (!$session) {
            return response()->json(['message' => 'Sesi tidak ditemukan dalam latber ini'], 422);
        }

        if ($validated['jumlah'] > $session->terpesan) {
            return response()->json(['message' => 'Jumlah melebihi tiket terpesan yang tersedia'], 422);
        }

        DB::beginTransaction();
        try {
            $pemasukanTambahan = $validated['jumlah'] * $session->harga;
            
            $session->decrement('terpesan', $validated['jumlah']);
            $session->increment('pemasukan_terpesan', $pemasukanTambahan);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data'], 500);
        }

        return $this->getSessions($latber);
    }

    private function getSessions(Latber $latber)
    {
        $sessions = $latber->sessions()
            ->orderBy('nomor_urut', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($session) {
                $session->pemasukan = $session->terjual * $session->harga;
                $session->pemasukan_total = ($session->terjual * $session->harga) + $session->pemasukan_terpesan;
                $session->nilai_terpesan = $session->terpesan * $session->harga;
                return $session;
            });
        return response()->json($sessions);
    }
}
