<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

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
    Route::put('/events/{event}/invoice-template', [InvoiceController::class, 'updateTemplate']);
});
