<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth with Rate Limiting (Brute Force Protection)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // Public Properties & Rooms
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::get('/properties/{id}', [PropertyController::class, 'show']);
    Route::get('/properties/{property_id}/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // Authenticated Endpoints (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        // User Auth & Profile
        Route::get('/auth/profile', [AuthController::class, 'profile']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);

        // Invoices & Payments (Tenant & Staff)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{id}/payments', [InvoiceController::class, 'submitPayment']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::get('/payments/{id}/proof', [PaymentController::class, 'getProof']);

        // Verifikasi & Penolakan Pembayaran (Staf/Owner/Manager/Receptionist/Super Admin)
        Route::middleware('role:super_admin,owner,manager,receptionist')->group(function () {
            Route::post('/payments/{id}/verify', [PaymentController::class, 'verify']);
            Route::post('/payments/{id}/reject', [PaymentController::class, 'reject']);
        });
    });
});
