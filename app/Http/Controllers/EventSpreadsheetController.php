<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSpreadsheet;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventSpreadsheetController extends Controller
{
    public function show(Event $event)
    {
        $spreadsheet = $event->spreadsheet;
        
        return response()->json([
            'spreadsheet' => $spreadsheet,
        ]);
    }

    public function store(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'spreadsheet_url' => 'required|string|url',
            'sheet_name' => 'nullable|string|max:255',
            'credentials' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $spreadsheetId = $this->extractSpreadsheetId($request->spreadsheet_url);
        
        if (!$spreadsheetId) {
            return response()->json([
                'message' => 'Invalid Google Sheets URL format',
            ], 422);
        }

        $spreadsheet = EventSpreadsheet::updateOrCreate(
            ['event_id' => $event->id],
            [
                'spreadsheet_id' => $spreadsheetId,
                'spreadsheet_url' => $request->spreadsheet_url,
                'sheet_name' => $request->sheet_name ?? 'Daftar Peserta',
                'credentials' => $request->credentials,
            ]
        );

        return response()->json([
            'message' => 'Spreadsheet connected successfully',
            'spreadsheet' => $spreadsheet,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $spreadsheet = $event->spreadsheet;
        
        if (!$spreadsheet) {
            return response()->json([
                'message' => 'No spreadsheet connected to this event',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'spreadsheet_url' => 'sometimes|string|url',
            'sheet_name' => 'sometimes|string|max:255',
            'credentials' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('spreadsheet_url')) {
            $spreadsheetId = $this->extractSpreadsheetId($request->spreadsheet_url);
            
            if (!$spreadsheetId) {
                return response()->json([
                    'message' => 'Invalid Google Sheets URL format',
                ], 422);
            }
            
            $spreadsheet->spreadsheet_id = $spreadsheetId;
            $spreadsheet->spreadsheet_url = $request->spreadsheet_url;
        }

        if ($request->has('sheet_name')) {
            $spreadsheet->sheet_name = $request->sheet_name;
        }

        if ($request->has('credentials')) {
            $spreadsheet->credentials = $request->credentials;
        }

        $spreadsheet->save();

        return response()->json([
            'message' => 'Spreadsheet updated successfully',
            'spreadsheet' => $spreadsheet,
        ]);
    }

    public function destroy(Event $event)
    {
        $spreadsheet = $event->spreadsheet;
        
        if (!$spreadsheet) {
            return response()->json([
                'message' => 'No spreadsheet connected to this event',
            ], 404);
        }

        $spreadsheet->delete();

        return response()->json([
            'message' => 'Spreadsheet disconnected successfully',
        ]);
    }

    public function testConnection(Event $event, GoogleSheetsService $sheetsService)
    {
        $spreadsheet = $event->spreadsheet;
        
        if (!$spreadsheet) {
            return response()->json([
                'message' => 'No spreadsheet connected to this event',
            ], 404);
        }

        $result = $sheetsService->testConnection($spreadsheet->spreadsheet_id);

        if ($result['success']) {
            return response()->json([
                'message' => 'Connection successful',
                'title' => $result['title'],
                'sheets' => $result['sheets'],
            ]);
        }

        return response()->json([
            'message' => 'Connection failed',
            'error' => $result['error'],
        ], 400);
    }

    protected function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

