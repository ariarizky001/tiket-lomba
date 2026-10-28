<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapPesertaController extends Controller
{
    public function show(Event $event)
    {
        $sessions = $event->sessions()
            ->urut()
            ->with([
                'invoiceItems' => function ($query) {
                    $query->select('id', 'invoice_id', 'event_session_id', 'qty', 'harga', 'subtotal')
                        ->with(['invoice' => function ($q) {
                            $q->select('id', 'event_id', 'nama_peserta', 'status', 'paid_at', 'created_at')
                                ->whereIn('status', ['lunas', 'belum_lunas']);
                        }]);
                }
            ])
            ->get(['id', 'nomor_urut', 'nama', 'harga', 'kuota']);

        $sessionsData = $sessions->map(function ($session) {
            // Collect all invoice items with their invoice data
            $items = $session->invoiceItems->filter(fn($item) => $item->invoice && in_array($item->invoice->status, ['lunas', 'belum_lunas']));

            // Group by invoice
            $invoices = $items->groupBy('invoice_id')->map(function ($items, $invoiceId) {
                $invoice = $items->first()->invoice;
                $totalQty = $items->sum('qty');
                
                return [
                    'invoice_id' => $invoice->id,
                    'nama_peserta' => $invoice->nama_peserta,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at,
                    'created_at' => $invoice->created_at,
                    'total_qty' => $totalQty,
                ];
            })->values();

            // Split into lunas and belum_lunas
            $lunas = $invoices->filter(fn($inv) => $inv['status'] === 'lunas')
                ->sortBy(['paid_at', 'invoice_id'])
                ->values();
            
            $belumLunas = $invoices->filter(fn($inv) => $inv['status'] === 'belum_lunas')
                ->sortBy(['created_at', 'invoice_id'])
                ->values();

            // Build peserta array with qty expansion
            $peserta = [];
            $no = 1;

            foreach ($lunas as $inv) {
                for ($i = 0; $i < $inv['total_qty']; $i++) {
                    $peserta[] = [
                        'no' => $no++,
                        'invoice_id' => $inv['invoice_id'],
                        'nama' => $inv['nama_peserta'],
                        'status' => 'lunas',
                    ];
                }
            }

            foreach ($belumLunas as $inv) {
                for ($i = 0; $i < $inv['total_qty']; $i++) {
                    $peserta[] = [
                        'no' => $no++,
                        'invoice_id' => $inv['invoice_id'],
                        'nama' => $inv['nama_peserta'],
                        'status' => 'belum_lunas',
                    ];
                }
            }

            return [
                'id' => $session->id,
                'nomor_urut' => $session->nomor_urut,
                'urutan' => $session->nomor_urut,
                'nama' => $session->nama,
                'harga' => (int) $session->harga,
                'kuota' => (int) $session->kuota,
                'terisi' => count($peserta),
                'peserta' => $peserta,
            ];
        });

        return response()->json([
            'sesi' => $sessionsData->values(),
        ]);
    }
}