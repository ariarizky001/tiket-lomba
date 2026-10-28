<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParticipantRecapController extends Controller
{
    public function show(Event $event)
    {
        $sessions = $event->sessions()
            ->urut()
            ->with(['invoiceItems.invoice' => function ($query) {
                $query->select('id', 'event_id', 'nama_peserta', 'kode', 'status', 'paid_at', 'total');
            }])
            ->get(['id', 'nomor_urut', 'nama', 'event_id', 'harga', 'kuota']);

        $sessionsData = $sessions->map(function ($session) {
            $pesertaList = [];
            $totalLunasAmount = 0;
            $totalBelumLunasAmount = 0;

            foreach ($session->invoiceItems as $item) {
                $invoice = $item->invoice;
                $pricePerTicket = (int) $item->subtotal / $item->qty;
                
                for ($i = 1; $i <= $item->qty; $i++) {
                    $pesertaList[] = [
                        'nama_peserta' => $invoice->nama_peserta,
                        'urutan' => $i,
                        'kode' => $invoice->kode,
                        'status' => $invoice->status,
                        'total' => (int) $invoice->total,
                        'paid_at' => $invoice->paid_at ? $invoice->paid_at->format('Y-m-d') : null,
                    ];
                }
                
                if ($invoice->status === 'lunas') {
                    $totalLunasAmount += (int) $item->subtotal;
                } else {
                    $totalBelumLunasAmount += (int) $item->subtotal;
                }
            }

            return [
                'id' => $session->id,
                'nomor_urut' => $session->nomor_urut,
                'nama' => $session->nama,
                'harga' => (int) $session->harga,
                'kuota' => (int) $session->kuota,
                'peserta' => $pesertaList,
                'summary' => [
                    'total_peserta' => count($pesertaList),
                    'total_qty' => count($pesertaList),
                    'total_revenue_lunas' => (int) $totalLunasAmount,
                    'total_revenue_belum_lunas' => (int) $totalBelumLunasAmount,
                ],
            ];
        });

        return response()->json([
            'event' => [
                'id' => $event->id,
                'nama' => $event->nama,
                'tanggal' => $event->tanggal->format('Y-m-d'),
                'lokasi' => $event->lokasi,
            ],
            'sesi' => $sessionsData->values(),
        ]);
    }
}

