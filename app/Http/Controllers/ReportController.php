<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Prize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function updatePrizes(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'sesi' => 'required|array',
            'sesi.*.event_session_id' => 'required|integer|exists:event_sessions,id',
            'sesi.*.juara' => 'required|array',
        ]);

        $validator->after(function ($validator) use ($request, $event) {
            foreach ($request->sesi as $index => $sesi) {
                if (!in_array($sesi['event_session_id'], $event->sessions()->pluck('id')->toArray())) {
                    $validator->errors()->add("sesi.{$index}.event_session_id", 'Sesi tidak ditemukan di event ini');
                }
            }
            
            foreach ($request->sesi as $index => $sesi) {
                foreach ($sesi['juara'] as $juara => $nominal) {
                    if ($nominal !== null && $nominal !== '' && $nominal !== 0) {
                        if (!is_int($juara) || $juara < 1 || $juara > 5) {
                            $validator->errors()->add("sesi.{$index}.juara.{$juara}", 'Juara harus antara 1-5');
                        }
                        if (!is_int($nominal) || $nominal < 0 || $nominal > 999999999) {
                            $validator->errors()->add("sesi.{$index}.juara.{$juara}", 'Nominal harus 0-999999999');
                        }
                    }
                }
            }
            
            $sessionIds = array_column($request->sesi, 'event_session_id');
            if (count($sessionIds) !== count(array_unique($sessionIds))) {
                $validator->errors()->add('sesi', 'Event session_id tidak boleh duplikat');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->sesi as $sesiData) {
                Prize::where('event_session_id', $sesiData['event_session_id'])->delete();
                
                foreach ($sesiData['juara'] as $juara => $nominal) {
                    if ($nominal !== null && $nominal !== '' && $nominal > 0) {
                        Prize::create([
                            'event_session_id' => $sesiData['event_session_id'],
                            'juara' => (int) $juara,
                            'nominal' => (int) $nominal,
                        ]);
                    }
                }
            }

            DB::commit();

            return $this->show($event);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan hadiah'], 500);
        }
    }

    public function show(Event $event)
    {
        $sessions = EventSession::select('event_sessions.*')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status = "lunas" THEN invoice_items.subtotal ELSE 0 END), 0) as pemasukan')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status = "belum_lunas" THEN invoice_items.subtotal ELSE 0 END), 0) as belum_lunas')
            ->selectRaw('COALESCE(SUM(invoice_items.qty), 0) as terjual')
            ->leftJoin('invoice_items', 'event_sessions.id', '=', 'invoice_items.event_session_id')
            ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('event_sessions.event_id', $event->id)
            ->groupBy('event_sessions.id', 'event_sessions.event_id', 'event_sessions.nama', 'event_sessions.harga', 'event_sessions.kuota', 'event_sessions.created_at', 'event_sessions.updated_at')
            ->with(['prizes' => function ($query) {
                $query->orderBy('juara')->select('event_session_id', 'juara', 'nominal');
            }])
            ->get();

        $reportSessions = $sessions->map(function ($session) {
            $terjual = (int) ($session->terjual ?? 0);
            $sisa = $session->kuota - $terjual;
            $pemasukan = (int) ($session->pemasukan ?? 0);
            $belumLunas = (int) ($session->belum_lunas ?? 0);
            
            $prizes = $session->prizes;

            $totalHadiah = $prizes->sum('nominal');
            $selisih = $pemasukan - $totalHadiah;

            return [
                'id' => $session->id,
                'nama' => $session->nama,
                'harga' => $session->harga,
                'kuota' => $session->kuota,
                'terjual' => $terjual,
                'sisa' => $sisa,
                'pemasukan' => $pemasukan,
                'belum_lunas' => $belumLunas,
                'prizes' => $prizes,
                'total_hadiah' => $totalHadiah,
                'selisih' => $selisih,
            ];
        });

        $totalKuota = $reportSessions->sum('kuota');
        $totalTerjual = $reportSessions->sum('terjual');
        $totalSisa = $reportSessions->sum('sisa');
        $totalPemasukan = $reportSessions->sum('pemasukan');
        $totalBelumLunas = $reportSessions->sum('belum_lunas');
        $totalHadiah = $reportSessions->sum('total_hadiah');
        $laba = $totalPemasukan - $totalHadiah;

        $jumlahInvoice = Invoice::where('event_id', $event->id)->count();
        $invoiceLunas = Invoice::where('event_id', $event->id)->where('status', 'lunas')->count();
        $invoiceBelumLunas = Invoice::where('event_id', $event->id)->where('status', 'belum_lunas')->count();

        return response()->json([
            'sesi' => $reportSessions->values(),
            'ringkasan' => [
                'total_kuota' => $totalKuota,
                'total_terjual' => $totalTerjual,
                'total_sisa' => $totalSisa,
                'pemasukan' => $totalPemasukan,
                'belum_lunas' => $totalBelumLunas,
                'total_hadiah' => $totalHadiah,
                'laba' => $laba,
                'jumlah_invoice' => $jumlahInvoice,
                'invoice_lunas' => $invoiceLunas,
                'invoice_belum_lunas' => $invoiceBelumLunas,
            ],
        ]);
    }
}