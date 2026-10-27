<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ParticipantRecapController;
use App\Http\Controllers\EventSpreadsheetController;
use App\Http\Controllers\SheetRangeMappingController;
use App\Http\Controllers\RekapPesertaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::apiResource('events', EventController::class);
    Route::apiResource('events.sessions', EventSessionController::class)
        ->shallow()
        ->parameters(['sessions' => 'eventSession']);
    Route::get('/events/{event}/invoices', [InvoiceController::class, 'index']);
    Route::post('/events/{event}/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'status']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::get('/events/{event}/invoice-template', [InvoiceTemplateController::class, 'show']);
    Route::put('/events/{event}/invoice-template', [InvoiceTemplateController::class, 'update']);
    Route::post('/events/{event}/invoice-template/preview', [InvoiceTemplateController::class, 'preview']);
    Route::get('/invoices/{invoice}/teks', [InvoiceTemplateController::class, 'renderInvoiceText']);
    Route::get('/events/{event}/report', [ReportController::class, 'show']);
    Route::put('/events/{event}/prizes', [ReportController::class, 'updatePrizes']);
    Route::get('/expense-categories', fn() => response()->json(config('expense.categories')));
    Route::get('/events/{event}/expenses', [ExpenseController::class, 'index']);
    Route::post('/events/{event}/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::get('/events/{event}/bookkeeping', [ExpenseController::class, 'bookkeeping']);
    // Route::get('/events/{event}/participant-recap', [ParticipantRecapController::class, 'show']);
    Route::get('/events/{event}/rekap-peserta', [RekapPesertaController::class, 'show']);
    Route::get('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'show']);
    Route::post('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'store']);
    Route::put('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'update']);
    Route::delete('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'destroy']);
    Route::post('/events/{event}/spreadsheet/test', [EventSpreadsheetController::class, 'testConnection']);
    Route::post('/events/{event}/spreadsheet/send-rekap', [EventSpreadsheetController::class, 'sendRekapToSheet']);
    
    Route::get('/events/{event}/sheet-mappings', [SheetRangeMappingController::class, 'index']);
    Route::post('/events/{event}/sheet-mappings', [SheetRangeMappingController::class, 'store']);
    Route::put('/events/{event}/sheet-mappings/{mapping}', [SheetRangeMappingController::class, 'update']);
    Route::delete('/events/{event}/sheet-mappings/{mapping}', [SheetRangeMappingController::class, 'destroy']);
    Route::post('/events/{event}/sheet-mappings/bulk', [SheetRangeMappingController::class, 'bulkStore']);
});
