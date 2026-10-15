<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\HousekeepingController;
use App\Http\Controllers\Api\V1\InspectionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MaintenanceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\UtilityController;
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

        // Room Inspections & Checklist (All Authenticated Users with proper policies)
        Route::get('/inspections', [InspectionController::class, 'index']);
        Route::post('/inspections', [InspectionController::class, 'store']);
        Route::get('/inspections/{id}', [InspectionController::class, 'show']);
        Route::post('/inspections/{id}/start', [InspectionController::class, 'start']);
        Route::put('/inspections/{id}/items/{itemId}', [InspectionController::class, 'updateItem']);
        Route::post('/inspections/{id}/media', [InspectionController::class, 'uploadMedia']);
        Route::get('/inspections/{id}/media/{mediaId}', [InspectionController::class, 'streamMedia']);
        Route::post('/inspections/{id}/complete', [InspectionController::class, 'complete']);
        Route::post('/inspections/{id}/approve', [InspectionController::class, 'approve']);
        Route::post('/inspections/{id}/object', [InspectionController::class, 'object']);
        Route::get('/inspections/{id}/compare/{baselineId}', [InspectionController::class, 'compare']);

        // Utility Meters, Tariffs, Readings & Invoicing
        Route::get('/utilities/meters', [UtilityController::class, 'indexMeters']);
        Route::post('/utilities/meters', [UtilityController::class, 'storeMeter']);
        Route::get('/utilities/meters/{id}', [UtilityController::class, 'showMeter']);
        Route::put('/utilities/meters/{id}', [UtilityController::class, 'updateMeter']);

        Route::get('/utilities/tariffs', [UtilityController::class, 'indexTariffs']);
        Route::post('/utilities/tariffs', [UtilityController::class, 'storeTariff']);
        Route::get('/utilities/tariffs/effective', [UtilityController::class, 'getEffectiveTariff']);

        Route::get('/utilities/readings', [UtilityController::class, 'indexReadings']);
        Route::post('/utilities/readings', [UtilityController::class, 'storeReading']);
        Route::get('/utilities/readings/{id}', [UtilityController::class, 'showReading']);
        Route::post('/utilities/readings/{id}/review', [UtilityController::class, 'reviewReading']);
        Route::post('/utilities/readings/{id}/correct', [UtilityController::class, 'correctReading']);
        Route::get('/utilities/readings/{id}/preview-bill', [UtilityController::class, 'previewBill']);
        Route::post('/utilities/readings/{id}/generate-invoice', [UtilityController::class, 'generateInvoice']);
        Route::get('/utilities/readings/{id}/proof-photo', [UtilityController::class, 'proofPhoto']);
        Route::get('/utilities/tenant/consumption', [UtilityController::class, 'tenantConsumption']);

        // Phase 5: Maintenance Management
        Route::get('/maintenance', [MaintenanceController::class, 'index']);
        Route::post('/maintenance', [MaintenanceController::class, 'store']);
        Route::get('/maintenance/{id}', [MaintenanceController::class, 'show']);
        Route::post('/maintenance/{id}/triage', [MaintenanceController::class, 'triage']);
        Route::post('/maintenance/{id}/assign', [MaintenanceController::class, 'assign']);
        Route::post('/maintenance/{id}/start', [MaintenanceController::class, 'start']);
        Route::post('/maintenance/{id}/submit-verification', [MaintenanceController::class, 'submitVerification']);
        Route::post('/maintenance/{id}/verify', [MaintenanceController::class, 'verify']);
        Route::post('/maintenance/{id}/reject-cancel', [MaintenanceController::class, 'rejectOrCancel']);
        Route::get('/maintenance/{id}/photo', [MaintenanceController::class, 'photo']);

        // Phase 5: Tenant Complaints
        Route::get('/complaints', [ComplaintController::class, 'index']);
        Route::post('/complaints', [ComplaintController::class, 'store']);
        Route::get('/complaints/{id}', [ComplaintController::class, 'show']);
        Route::post('/complaints/{id}/acknowledge', [ComplaintController::class, 'acknowledge']);
        Route::post('/complaints/{id}/assign', [ComplaintController::class, 'assign']);
        Route::post('/complaints/{id}/comments', [ComplaintController::class, 'addComment']);
        Route::post('/complaints/{id}/link-maintenance', [ComplaintController::class, 'linkMaintenance']);
        Route::post('/complaints/{id}/resolve', [ComplaintController::class, 'resolve']);
        Route::post('/complaints/{id}/confirm-reopen', [ComplaintController::class, 'confirmOrReopen']);
        Route::post('/complaints/{id}/reject', [ComplaintController::class, 'reject']);
        Route::get('/complaints/{id}/photo', [ComplaintController::class, 'photo']);

        // Phase 5: Housekeeping Management
        Route::get('/housekeeping', [HousekeepingController::class, 'index']);
        Route::post('/housekeeping', [HousekeepingController::class, 'store']);
        Route::get('/housekeeping/{id}', [HousekeepingController::class, 'show']);
        Route::post('/housekeeping/{id}/assign', [HousekeepingController::class, 'assign']);
        Route::post('/housekeeping/{id}/start', [HousekeepingController::class, 'start']);
        Route::post('/housekeeping/{id}/submit-verification', [HousekeepingController::class, 'submitVerification']);
        Route::post('/housekeeping/{id}/verify', [HousekeepingController::class, 'verify']);
        Route::post('/housekeeping/{id}/cancel', [HousekeepingController::class, 'cancel']);
        Route::get('/housekeeping/{id}/photo', [HousekeepingController::class, 'photo']);

        // Phase 5: Internal Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
