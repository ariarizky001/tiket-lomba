<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\SheetRangeMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SheetRangeMappingController extends Controller
{
    public function index(Event $event)
    {
        $mappings = $event->sheetRangeMappings()
            ->with('session')
            ->orderBy('session_index')
            ->get()
            ->map(function ($mapping) {
                return [
                    'id' => $mapping->id,
                    'session_id' => $mapping->session_id,
                    'session_name' => $mapping->session?->nama,
                    'session_index' => $mapping->session_index,
                    'sheet_range' => $mapping->sheet_range,
                    'sheet_name' => $mapping->sheet_name,
                ];
            });

        return response()->json([
            'mappings' => $mappings,
        ]);
    }

    public function store(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|exists:event_sessions,id',
            'session_index' => 'required|integer|min:1',
            'sheet_range' => 'required|string|max:50',
            'sheet_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mapping = SheetRangeMapping::updateOrCreate(
            ['event_id' => $event->id, 'session_id' => $request->session_id],
            [
                'session_index' => $request->session_index,
                'sheet_range' => $request->sheet_range,
                'sheet_name' => $request->sheet_name ?? 'Daftar Peserta',
            ]
        );

        return response()->json([
            'message' => 'Mapping created successfully',
            'mapping' => $mapping,
        ]);
    }

    public function update(Request $request, Event $event, SheetRangeMapping $mapping)
    {
        if ($mapping->event_id !== $event->id) {
            return response()->json([
                'message' => 'Mapping not found for this event',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'session_index' => 'sometimes|integer|min:1',
            'sheet_range' => 'sometimes|string|max:50',
            'sheet_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mapping->update($request->only(['session_index', 'sheet_range', 'sheet_name']));

        return response()->json([
            'message' => 'Mapping updated successfully',
            'mapping' => $mapping,
        ]);
    }

    public function destroy(Event $event, SheetRangeMapping $mapping)
    {
        if ($mapping->event_id !== $event->id) {
            return response()->json([
                'message' => 'Mapping not found for this event',
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'message' => 'Mapping deleted successfully',
        ]);
    }

    public function bulkStore(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array',
            'mappings.*.session_id' => 'required|exists:event_sessions,id',
            'mappings.*.session_index' => 'required|integer|min:1',
            'mappings.*.sheet_range' => 'required|string|max:50',
            'mappings.*.sheet_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $created = [];
        foreach ($request->mappings as $mapData) {
            $mapping = SheetRangeMapping::updateOrCreate(
                ['event_id' => $event->id, 'session_id' => $mapData['session_id']],
                [
                    'session_index' => $mapData['session_index'],
                    'sheet_range' => $mapData['sheet_range'],
                    'sheet_name' => $mapData['sheet_name'] ?? 'Daftar Peserta',
                ]
            );
            $created[] = $mapping;
        }

        return response()->json([
            'message' => 'Mappings saved successfully',
            'mappings' => $created,
        ]);
    }
}
