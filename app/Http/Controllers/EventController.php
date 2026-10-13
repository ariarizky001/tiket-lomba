<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('tanggal', 'desc')->get();
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'tanggal' => 'required|date',
            'lokasi' => 'nullable|string|max:150',
            'is_active' => 'boolean',
        ]);

        $event = Event::create($validated);
        return response()->json($event, 201);
    }

    public function show(Event $event)
    {
        return response()->json($event);
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'tanggal' => 'required|date',
            'lokasi' => 'nullable|string|max:150',
            'is_active' => 'boolean',
        ]);

        $event->update($validated);
        return response()->json($event);
    }

    public function destroy(Event $event)
    {
        if ($event->invoices()->count() > 0) {
            return response()->json(['message' => 'Event sudah memiliki invoice dan tidak dapat dihapus'], 422);
        }

        if ($event->expenses()->withTrashed()->count() > 0) {
            return response()->json(['message' => 'Event sudah memiliki data pembukuan dan tidak dapat dihapus'], 422);
        }
        
        $event->delete();
        return response()->json(null, 204);
    }
}
