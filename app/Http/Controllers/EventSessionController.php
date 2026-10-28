<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventSessionController extends Controller
{
    public function index(Event $event)
    {
        $sessions = $event->sessions()
            ->urut()
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
            'nomor_urut' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('event_sessions', 'nomor_urut')->where('event_id', $event->id),
            ],
        ]);

        if (!isset($validated['nomor_urut'])) {
            $maxNomor = EventSession::where('event_id', $event->id)->max('nomor_urut');
            $validated['nomor_urut'] = $maxNomor ? $maxNomor + 1 : 1;
        }

        $session = EventSession::create([
            'event_id' => $event->id,
            'nomor_urut' => $validated['nomor_urut'],
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
            'nomor_urut' => [
                'nullable',
                'integer',
                'min:1',
                'max:999',
                Rule::unique('event_sessions', 'nomor_urut')
                    ->where('event_id', $eventSession->event_id)
                    ->ignore($eventSession->id),
            ],
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

    public function reorder(Request $request, Event $event)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:event_sessions,id',
        ]);

        $ids = $validated['ids'];

        // Validasi: ids harus tepat semua id sesi event ini, tanpa duplikat, tanpa id event lain
        $eventSessionIds = $event->sessions()->pluck('id')->toArray();

        if (count($ids) !== count($eventSessionIds)) {
            return response()->json(['message' => 'Daftar ID tidak lengkap atau melebihi sesi event ini'], 422);
        }

        if (count($ids) !== count(array_unique($ids))) {
            return response()->json(['message' => 'ID tidak boleh duplikat'], 422);
        }

        if (array_diff($ids, $eventSessionIds)) {
            return response()->json(['message' => 'Terdapat ID sesi yang tidak milik event ini'], 422);
        }

        DB::beginTransaction();
        try {
            // Shift all nomor_urut by +1000 to avoid unique constraint conflict during swap
            EventSession::where('event_id', $event->id)
                ->increment('nomor_urut', 1000);

            // Assign new nomor_urut 1, 2, 3... based on order in ids
            $nomor = 1;
            foreach ($ids as $id) {
                EventSession::where('id', $id)->update(['nomor_urut' => $nomor++]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengatur ulang urutan'], 500);
        }

        // Return updated sessions list (same as index)
        return $this->index($event);
    }
}