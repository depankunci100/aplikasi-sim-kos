<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InspectionWebController;
use App\Http\Controllers\Web\InvoiceWebController;
use App\Http\Controllers\Web\PaymentVerificationController;
use App\Http\Controllers\Web\RoomWebController;
use App\Http\Controllers\Web\UtilityWebController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard or login
Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

// Authentication Web Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Web Dashboard Protected Routes
Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {
    // Main Unified Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Payments Verification Queue & Actions (Kasir, Manager, Owner, Super Admin)
    Route::middleware('role:super_admin,owner,manager,receptionist')->group(function () {
        Route::get('/payments', [PaymentVerificationController::class, 'index'])->name('payments.index');
        Route::get('/payments/{id}', [PaymentVerificationController::class, 'show'])->name('payments.show');
        Route::get('/payments/{id}/proof', [PaymentVerificationController::class, 'streamProof'])->name('payments.proof');
        Route::post('/payments/{id}/verify', [PaymentVerificationController::class, 'verify'])->name('payments.verify');
        Route::post('/payments/{id}/reject', [PaymentVerificationController::class, 'reject'])->name('payments.reject');
    });

    // Invoices Listing & Print-Ready Receipt View
    Route::get('/invoices', [InvoiceWebController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [InvoiceWebController::class, 'show'])->name('invoices.show');

    // Room Inspections & Checklist (Staff, Kasir, Manager, Owner, Super Admin)
    Route::middleware('role:super_admin,owner,manager,receptionist,staff')->group(function () {
        Route::get('/inspections', [InspectionWebController::class, 'index'])->name('inspections.index');
        Route::get('/inspections/create', [InspectionWebController::class, 'create'])->name('inspections.create');
        Route::post('/inspections', [InspectionWebController::class, 'store'])->name('inspections.store');
        Route::get('/inspections/{id}', [InspectionWebController::class, 'show'])->name('inspections.show');
        Route::post('/inspections/{id}/start', [InspectionWebController::class, 'start'])->name('inspections.start');
        Route::put('/inspections/{id}/items/{itemId}', [InspectionWebController::class, 'updateItem'])->name('inspections.items.update');
        Route::post('/inspections/{id}/media', [InspectionWebController::class, 'uploadMedia'])->name('inspections.media.upload');
        Route::get('/inspections/{id}/media/{mediaId}', [InspectionWebController::class, 'streamMedia'])->name('inspections.media.stream');
        Route::post('/inspections/{id}/complete', [InspectionWebController::class, 'complete'])->name('inspections.complete');
        Route::get('/inspections/{id}/compare', [InspectionWebController::class, 'compare'])->name('inspections.compare');
    });

    // Rooms Housekeeping & Maintenance (Staff, Kasir, Manager, Owner, Super Admin)
    Route::middleware('role:super_admin,owner,manager,receptionist,staff')->group(function () {
        Route::post('/rooms/{id}/status', [RoomWebController::class, 'updateStatus'])->name('rooms.status');
    });

    // Utilities: Meters, Tariffs, Readings, Reviews & Invoicing
    Route::middleware('role:super_admin,owner,manager,receptionist,staff')->prefix('utilities')->name('utilities.')->group(function () {
        // Meters
        Route::get('/meters', [UtilityWebController::class, 'indexMeters'])->name('meters.index');
        Route::get('/meters/create', [UtilityWebController::class, 'createMeter'])->name('meters.create');
        Route::post('/meters', [UtilityWebController::class, 'storeMeter'])->name('meters.store');
        Route::get('/meters/{id}', [UtilityWebController::class, 'showMeter'])->name('meters.show');
        Route::get('/meters/{id}/edit', [UtilityWebController::class, 'editMeter'])->name('meters.edit');
        Route::put('/meters/{id}', [UtilityWebController::class, 'updateMeter'])->name('meters.update');

        // Tariffs
        Route::get('/tariffs', [UtilityWebController::class, 'indexTariffs'])->name('tariffs.index');
        Route::post('/tariffs', [UtilityWebController::class, 'storeTariff'])->name('tariffs.store');

        // Readings & Reviews
        Route::get('/readings', [UtilityWebController::class, 'indexReadings'])->name('readings.index');
        Route::get('/readings/create', [UtilityWebController::class, 'createReading'])->name('readings.create');
        Route::post('/readings', [UtilityWebController::class, 'storeReading'])->name('readings.store');
        Route::get('/readings/{id}', [UtilityWebController::class, 'showReading'])->name('readings.show');
        Route::post('/readings/{id}/review', [UtilityWebController::class, 'reviewReading'])->name('readings.review');
        Route::post('/readings/{id}/correct', [UtilityWebController::class, 'correctReading'])->name('readings.correct');
        Route::post('/readings/{id}/generate-invoice', [UtilityWebController::class, 'generateInvoice'])->name('readings.generate-invoice');
        Route::get('/readings/{id}/proof', [UtilityWebController::class, 'streamProof'])->name('readings.proof');
    });
});
