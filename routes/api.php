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
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\LatberController;
use App\Http\Controllers\LatberSessionController;
use App\Http\Controllers\LatberCountController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Health check endpoint (no auth required)
Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    Route::apiResource('events', EventController::class)
        ->middleware([
            'index' => 'permission:view_events',
            'store' => 'permission:manage_events',
            'show' => 'permission:view_events',
            'update' => 'permission:manage_events',
            'destroy' => 'permission:manage_events',
        ]);
    Route::put('/events/{event}/sessions/reorder', [EventSessionController::class, 'reorder'])->middleware('permission:manage_sessions');
    Route::apiResource('events.sessions', EventSessionController::class)
        ->shallow()
        ->parameters(['sessions' => 'eventSession'])
        ->middleware([
            'index' => 'permission:view_sessions',
            'store' => 'permission:manage_sessions',
            'show' => 'permission:view_sessions',
            'update' => 'permission:manage_sessions',
            'destroy' => 'permission:manage_sessions',
        ]);
    
    Route::get('/events/{event}/invoices', [InvoiceController::class, 'index'])->middleware('permission:view_invoices');
    Route::post('/events/{event}/invoices', [InvoiceController::class, 'store'])->middleware('permission:create_invoices');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:view_invoices');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:edit_invoices');
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'status'])->middleware('permission:change_invoice_status');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:delete_invoices');
    
    Route::get('/events/{event}/invoice-template', [InvoiceTemplateController::class, 'show'])->middleware('permission:view_invoices');
    Route::put('/events/{event}/invoice-template', [InvoiceTemplateController::class, 'update'])->middleware('permission:manage_spreadsheet');
    Route::post('/events/{event}/invoice-template/preview', [InvoiceTemplateController::class, 'preview'])->middleware('permission:view_invoices');
    Route::get('/invoices/{invoice}/teks', [InvoiceTemplateController::class, 'renderInvoiceText'])->middleware('permission:view_invoices');
    
    Route::get('/events/{event}/report', [ReportController::class, 'show'])->middleware('permission:view_reports');
    Route::put('/events/{event}/prizes', [ReportController::class, 'updatePrizes'])->middleware('permission:view_reports');
    
    Route::get('/expense-categories', fn() => response()->json(config('expense.categories')))->middleware('permission:view_expenses');
    Route::get('/events/{event}/expenses', [ExpenseController::class, 'index'])->middleware('permission:view_expenses');
    Route::post('/events/{event}/expenses', [ExpenseController::class, 'store'])->middleware('permission:manage_expenses');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:manage_expenses');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:manage_expenses');
    Route::get('/events/{event}/bookkeeping', [ExpenseController::class, 'bookkeeping'])->middleware('permission:view_expenses');
    
    Route::get('/events/{event}/participant-recap', [ParticipantRecapController::class, 'show'])->middleware('permission:view_invoices');
    Route::get('/events/{event}/rekap-peserta', [RekapPesertaController::class, 'show'])->middleware('permission:view_rekap');
    
    Route::get('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'show'])->middleware('permission:manage_spreadsheet');
    Route::post('/events/{event}/spreadsheet/test', [EventSpreadsheetController::class, 'testConnection'])->middleware('permission:manage_spreadsheet');
    Route::post('/events/{event}/spreadsheet/send-rekap', [EventSpreadsheetController::class, 'sendRekapToSheet'])->middleware('permission:manage_spreadsheet');
    
    Route::get('/events/{event}/sheet-mappings', [SheetRangeMappingController::class, 'index'])->middleware('permission:manage_sheet_mapping');
    
    Route::apiResource('latbers', LatberController::class)
        ->middleware([
            'index' => 'permission:view_latbers',
            'store' => 'permission:manage_latbers',
            'show' => 'permission:view_latbers',
            'update' => 'permission:manage_latbers',
            'destroy' => 'permission:manage_latbers',
        ]);
    Route::put('/latbers/{latber}/sessions/reorder', [LatberSessionController::class, 'reorder'])->middleware('permission:manage_latber_sessions');
    Route::get('/latbers/{latber}/sessions', [LatberSessionController::class, 'index'])->middleware('permission:view_latber_sessions');
    Route::post('/latbers/{latber}/sessions', [LatberSessionController::class, 'store'])->middleware('permission:manage_latber_sessions');
    Route::put('/latber-sessions/{latberSession}', [LatberSessionController::class, 'update'])->middleware('permission:manage_latber_sessions');
    Route::delete('/latber-sessions/{latberSession}', [LatberSessionController::class, 'destroy'])->middleware('permission:manage_latber_sessions');
    Route::put('/latbers/{latber}/terjual', [LatberCountController::class, 'updateTerjual'])->middleware('permission:manage_latber_sessions');
    Route::put('/latbers/{latber}/terpesan', [LatberCountController::class, 'updateTerpesan'])->middleware('permission:manage_latber_sessions');
    Route::put('/latbers/{latber}/terpesan/ambil', [LatberCountController::class, 'ambilTerpesan'])->middleware('permission:manage_latber_sessions');
});

Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::post('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'store']);
    Route::put('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'update']);
    Route::delete('/events/{event}/spreadsheet', [EventSpreadsheetController::class, 'destroy']);
    Route::post('/events/{event}/sheet-mappings', [SheetRangeMappingController::class, 'store']);
    Route::put('/events/{event}/sheet-mappings/{mapping}', [SheetRangeMappingController::class, 'update']);
    Route::delete('/events/{event}/sheet-mappings/{mapping}', [SheetRangeMappingController::class, 'destroy']);
    Route::post('/events/{event}/sheet-mappings/bulk', [SheetRangeMappingController::class, 'bulkStore']);
    
    Route::apiResource('users', UserController::class);
    
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/permissions/role/{role}', [PermissionController::class, 'getRolePermissions']);
    Route::put('/permissions/role/{role}', [PermissionController::class, 'updateRolePermissions']);
    Route::get('/permissions/user/{user}', [PermissionController::class, 'getUserPermissions']);
    Route::put('/permissions/user/{user}', [PermissionController::class, 'updateUserPermissions']);
});
