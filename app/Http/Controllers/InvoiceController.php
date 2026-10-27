<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $query = Invoice::with(['items.session', 'event'])
            ->where('event_id', $event->id)
            ->orderBy('id', 'desc');

        if ($request->has('status') && $request->status !== 'semua') {
            $query->where('status', $request->status);
        }

        if ($request->has('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_peserta', 'like', '%' . $request->q . '%')
                  ->orWhere('kode', 'like', '%' . $request->q . '%');
            });
        }

        $invoices = $query->get();
        return response()->json($invoices);
    }

    public function store(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'nama_peserta' => 'required|string|max:150',
            'status' => 'in:lunas,belum_lunas',
            'items' => 'required|array|min:1',
            'items.*.event_session_id' => 'required|exists:event_sessions,id',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'items.*.event_session_id' => 'distinct',
        ]);

        $validator->after(function ($validator) use ($request, $event) {
            foreach ($request->items as $index => $item) {
                $session = EventSession::where('id', $item['event_session_id'])
                    ->where('event_id', $event->id)
                    ->first();
                
                if (!$session) {
                    $validator->errors()->add("items.{$index}.event_session_id", 'Sesi tidak ditemukan');
                    continue;
                }

                $existingQty = InvoiceItem::where('event_session_id', $session->id)
                    ->whereHas('invoice', function ($q) {
                        $q->whereNotIn('status', ['deleted']);
                    })
                    ->sum('qty');

                $totalQty = $existingQty + $item['qty'];
                if ($totalQty > $session->kuota) {
                    $validator->errors()->add(
                        "items.{$index}.event_session_id",
                        "Sesi \"{$session->nama}\" tersisa " . ($session->kuota - $existingQty) . " tiket"
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $maxNomor = Invoice::where('event_id', $event->id)->max('nomor');
            $nomor = $maxNomor ? $maxNomor + 1 : 1;
            
            $initials = substr(str_replace(' ', '', strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $event->nama))), 0, 4);
            $initials = preg_replace('/[^A-Z0-9]/', '', $initials) ?: 'INV';
            $kode = $initials . '-' . str_pad($nomor, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'event_id' => $event->id,
                'nomor' => $nomor,
                'kode' => $kode,
                'nama_peserta' => $request->nama_peserta,
                'status' => $request->status ?? 'belum_lunas',
                'paid_at' => $request->status === 'lunas' ? now() : null,
                'total' => 0,
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $session = EventSession::where('id', $item['event_session_id'])
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                $subtotal = $item['qty'] * $session->harga;
                $total += $subtotal;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'event_session_id' => $session->id,
                    'qty' => $item['qty'],
                    'harga' => $session->harga,
                    'subtotal' => $subtotal,
                ]);
            }

            $invoice->update(['total' => $total]);

            DB::commit();

            $invoice->load(['items.session']);
            return response()->json($invoice, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat invoice'], 500);
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items.session', 'event']);
        return response()->json($invoice);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validator = Validator::make($request->all(), [
            'nama_peserta' => 'required|string|max:150',
            'status' => 'in:lunas,belum_lunas',
            'items' => 'required|array|min:1',
            'items.*.event_session_id' => 'required|exists:event_sessions,id',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'items.*.event_session_id' => 'distinct',
        ]);

        $validator->after(function ($validator) use ($request, $invoice) {
            foreach ($request->items as $index => $item) {
                $session = EventSession::where('id', $item['event_session_id'])
                    ->where('event_id', $invoice->event_id)
                    ->first();
                
                if (!$session) {
                    $validator->errors()->add("items.{$index}.event_session_id", 'Sesi tidak ditemukan');
                    continue;
                }

                $existingQty = InvoiceItem::where('event_session_id', $session->id)
                    ->where('invoice_id', '!=', $invoice->id)
                    ->sum('qty');

                $qtyInThisInvoice = InvoiceItem::where('invoice_id', $invoice->id)
                    ->where('event_session_id', $item['event_session_id'])
                    ->sum('qty');

                $totalQty = $existingQty + $item['qty'];
                if ($totalQty > $session->kuota) {
                    $validator->errors()->add(
                        "items.{$index}.event_session_id",
                        "Sesi \"{$session->nama}\" tersisa " . ($session->kuota - $existingQty) . " tiket"
                    );
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $invoice->update([
                'nama_peserta' => $request->nama_peserta,
                'status' => $request->status ?? 'belum_lunas',
                'paid_at' => $request->status === 'lunas' ? now() : null,
            ]);

            $invoice->items()->delete();

            $total = 0;
            foreach ($request->items as $item) {
                $session = EventSession::where('id', $item['event_session_id'])
                    ->where('event_id', $invoice->event_id)
                    ->lockForUpdate()
                    ->first();

                $subtotal = $item['qty'] * $session->harga;
                $total += $subtotal;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'event_session_id' => $session->id,
                    'qty' => $item['qty'],
                    'harga' => $session->harga,
                    'subtotal' => $subtotal,
                ]);
            }

            $invoice->update(['total' => $total]);

            DB::commit();

            $invoice->load(['items.session']);
            return response()->json($invoice);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal update invoice'], 500);
        }
    }

    public function status(Request $request, Invoice $invoice)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:lunas,belum_lunas',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $invoice->update([
            'status' => $request->status,
            'paid_at' => $request->status === 'lunas' ? now() : null,
        ]);

        $invoice->load(['items.session']);
        return response()->json($invoice);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->json(null, 204);
    }
}
