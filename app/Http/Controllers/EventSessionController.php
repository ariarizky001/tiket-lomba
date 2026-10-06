<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventSessionController extends Controller
{
    public function index(Event $event)
    {
        $sessions = $event->sessions()
            ->withSum(['invoiceItems' => function ($query) {
                $query->select(DB::raw('SUM(qty)'));
            }], 'qty')
            ->get()
            ->map(function ($session) {
                $session->terjual = $session->invoice_items_sum_qty ?? 0;
                $session->sisa = $session->kuota - $session->terjual;
                return $session;
            });
        return response()->json($sessions);
    }

    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150|unique:event_sessions,nama,NULL,id,event_id,' . $event->id,
            'harga' => 'required|integer|min:0',
            'kuota' => 'required|integer|min:1|max:1000',
        ]);

        $session = EventSession::create([
            'event_id' => $event->id,
            'nama' => $validated['nama'],
            'harga' => $validated['harga'],
            'kuota' => $validated['kuota'],
        ]);

        return response()->json($session, 201);
    }

    public function show(EventSession $eventSession)
    {
        $eventSession->terjual = $eventSession->invoiceItems()->sum('qty');
        $eventSession->sisa = $eventSession->kuota - $eventSession->terjual;
        return response()->json($eventSession);
    }

    public function update(Request $request, EventSession $eventSession)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150|unique:event_sessions,nama,' . $eventSession->id . ',id,event_id,' . $eventSession->event_id,
            'harga' => 'required|integer|min:0',
            'kuota' => 'required|integer|min:1|max:1000',
        ]);

        $existingQty = $eventSession->invoiceItems()->sum('qty');
        if ($validated['kuota'] < $existingQty) {
            return response()->json(['message' => 'Kuota tidak boleh kurang dari tiket terjual (' . $existingQty . ')'], 422);
        }

        $eventSession->update($validated);
        return response()->json($eventSession);
    }

    public function destroy(EventSession $eventSession)
    {
        if ($eventSession->invoiceItems()->count() > 0) {
            return response()->json(['message' => 'Sesi sudah memiliki invoice dan tidak dapat dihapus'], 422);
        }
        
        $eventSession->delete();
        return response()->json(null, 204);
    }
}
