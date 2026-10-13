<?php

namespace App\Http\Controllers;

use App\Models\EventSession;
use App\Models\Prize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrizeController extends Controller
{
    public function update(Request $request, EventSession $eventSession)
    {
        $validator = Validator::make($request->all(), [
            'prizes' => 'required|array|max:5',
            'prizes.*.juara' => 'required|integer|between:1,5',
            'prizes.*.nominal' => 'required|integer|min:1|max:999999999',
            'prizes.*.juara' => 'distinct',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            Prize::where('event_session_id', $eventSession->id)->delete();

            $prizes = [];
            foreach ($request->prizes as $prize) {
                Prize::create([
                    'event_session_id' => $eventSession->id,
                    'juara' => $prize['juara'],
                    'nominal' => $prize['nominal'],
                ]);
            }

            DB::commit();

            $session = $eventSession->load('prizes');

            $terjual = (int) $eventSession->invoiceItems()->sum('qty');
            $sisa = $eventSession->kuota - $terjual;
            $pemasukan = (int) $eventSession->invoiceItems()
                ->whereHas('invoice', fn($q) => $q->where('status', 'lunas'))
                ->sum('subtotal');
            $belumLunas = (int) $eventSession->invoiceItems()
                ->whereHas('invoice', fn($q) => $q->where('status', 'belum_lunas'))
                ->sum('subtotal');
            $totalHadiah = $session->prizes->sum('nominal');
            $selisih = $pemasukan - $totalHadiah;

            return response()->json([
                'id' => $session->id,
                'nama' => $session->nama,
                'harga' => $session->harga,
                'kuota' => $session->kuota,
                'terjual' => $terjual,
                'sisa' => $sisa,
                'pemasukan' => $pemasukan,
                'belum_lunas' => $belumLunas,
                'prizes' => $session->prizes,
                'total_hadiah' => $totalHadiah,
                'selisih' => $selisih,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan hadiah'], 500);
        }
    }
}
