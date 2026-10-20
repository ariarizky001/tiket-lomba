<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventSpreadsheet;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SheetRangeMapping;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function sendRekapToSheet(Request $request, Event $event, GoogleSheetsService $sheetsService)
    {
        $spreadsheet = $event->spreadsheet;
        
        if (!$spreadsheet) {
            return response()->json([
                'message' => 'No spreadsheet connected to this event',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|exists:event_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionId = $request->session_id;
        $session = EventSession::find($sessionId);
        
        if (!$session || $session->event_id !== $event->id) {
            return response()->json([
                'message' => 'Session not found for this event',
            ], 404);
        }

        $mapping = SheetRangeMapping::where('event_id', $event->id)
            ->where('session_id', $sessionId)
            ->first();

        if (!$mapping) {
            return response()->json([
                'message' => 'No sheet range mapping found for this session. Please configure mapping first.',
            ], 404);
        }

        $sessionIndex = $mapping->session_index;
        $sheetRange = $mapping->sheet_range;
        $sheetName = $mapping->sheet_name ?? $spreadsheet->sheet_name ?? 'Daftar Peserta';

        $pesertaData = $this->getPesertaData($event, $session);

        if (empty($pesertaData)) {
            return response()->json([
                'message' => 'No participant data found for this session',
            ], 400);
        }

        $values = array_map(function ($p) {
            return [$p['nama']];
        }, $pesertaData);

        try {
            $fullRange = "{$sheetName}!{$sheetRange}";
            
            $cleared = $sheetsService->clearValues($spreadsheet->spreadsheet_id, $fullRange);
            if (!$cleared) {
                Log::warning('Failed to clear range, continuing with update', [
                    'range' => $fullRange,
                ]);
            }

            $updated = $sheetsService->updateValues($spreadsheet->spreadsheet_id, $fullRange, $values);

            if (!$updated) {
                return response()->json([
                    'message' => 'Failed to update Google Sheets',
                ], 500);
            }

            $spreadsheet->last_synced_at = now();
            $spreadsheet->save();

            return response()->json([
                'message' => 'Data sent to spreadsheet successfully',
                'session' => $session->nama,
                'range' => $fullRange,
                'rows_sent' => count($values),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Send rekap to sheet error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error sending data to spreadsheet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getPesertaData(Event $event, EventSession $session)
    {
        $items = InvoiceItem::where('event_session_id', $session->id)
            ->whereHas('invoice', function ($q) {
                $q->whereIn('status', ['lunas', 'belum_lunas']);
            })
            ->with(['invoice' => function ($q) {
                $q->select('id', 'event_id', 'nama_peserta', 'status', 'paid_at', 'created_at')
                    ->whereIn('status', ['lunas', 'belum_lunas']);
            }])
            ->get();

        $invoices = $items->groupBy('invoice_id')->map(function ($items) {
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

        $lunas = $invoices->filter(fn($inv) => $inv['status'] === 'lunas')
            ->sortBy(['paid_at', 'invoice_id'])
            ->values();
        
        $belumLunas = $invoices->filter(fn($inv) => $inv['status'] === 'belum_lunas')
            ->sortBy(['created_at', 'invoice_id'])
            ->values();

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

        return $peserta;
    }

    protected function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

