<?php

namespace App\Http\Controllers;

use App\Models\Latber;
use App\Models\LatberSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LatberSessionController extends Controller
{
    public function index(Latber $latber)
    {
        $sessions = $latber->sessions()
            ->urut()
            ->get()
            ->map(function ($session) {
                $session->pemasukan = $session->pemasukan;
                $session->pemasukan_total = $session->pemasukan_total;
                $session->nilai_terpesan = $session->nilai_terpesan;
                return $session;
            });
        return response()->json($sessions);
    }

    public function store(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150|unique:latber_sessions,nama,NULL,id,latber_id,' . $latber->id,
            'harga' => 'required|integer|min:0|max:999999999',
            'nomor_urut' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('latber_sessions', 'nomor_urut')->where('latber_id', $latber->id),
            ],
        ]);

        if (!isset($validated['nomor_urut'])) {
            $maxNomor = LatberSession::where('latber_id', $latber->id)->max('nomor_urut');
            $validated['nomor_urut'] = $maxNomor ? $maxNomor + 1 : 1;
        }

        $session = LatberSession::create([
            'latber_id' => $latber->id,
            'nomor_urut' => $validated['nomor_urut'],
            'nama' => $validated['nama'],
            'harga' => $validated['harga'],
        ]);

        $session->pemasukan = $session->pemasukan;
        return response()->json($session, 201);
    }

    public function show(LatberSession $latberSession)
    {
        $latberSession->pemasukan = $latberSession->pemasukan;
        return response()->json($latberSession);
    }

    public function update(Request $request, LatberSession $latberSession)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150|unique:latber_sessions,nama,' . $latberSession->id . ',id,latber_id,' . $latberSession->latber_id,
            'harga' => 'required|integer|min:0|max:999999999',
            'nomor_urut' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('latber_sessions', 'nomor_urut')
                    ->where('latber_id', $latberSession->latber_id)
                    ->ignore($latberSession->id),
            ],
        ]);

        $latberSession->update($validated);
        $latberSession->pemasukan = $latberSession->pemasukan;
        return response()->json($latberSession);
    }

    public function destroy(LatberSession $latberSession)
    {
        if ($latberSession->terjual > 0 || $latberSession->terpesan > 0) {
            return response()->json(['message' => 'Sesi sudah memiliki data penjualan dan tidak dapat dihapus'], 422);
        }

        $latberSession->delete();
        return response()->json(null, 204);
    }

    public function reorder(Request $request, Latber $latber)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:latber_sessions,id',
        ]);

        $ids = $validated['ids'];

        $latberSessionIds = $latber->sessions()->pluck('id')->toArray();

        if (count($ids) !== count($latberSessionIds)) {
            return response()->json(['message' => 'Daftar ID tidak lengkap atau melebihi sesi latber ini'], 422);
        }

        if (count($ids) !== count(array_unique($ids))) {
            return response()->json(['message' => 'ID tidak boleh duplikat'], 422);
        }

        if (array_diff($ids, $latberSessionIds)) {
            return response()->json(['message' => 'Terdapat ID sesi yang tidak milik latber ini'], 422);
        }

        DB::beginTransaction();
        try {
            LatberSession::where('latber_id', $latber->id)
                ->increment('nomor_urut', 1000);

            $nomor = 1;
            foreach ($ids as $id) {
                LatberSession::where('id', $id)->update(['nomor_urut' => $nomor++]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengatur ulang urutan'], 500);
        }

        return $this->index($latber);
    }
}
