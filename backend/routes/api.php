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
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\DepositController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth with Rate Limiting (Brute Force Protection)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/password-reset/complete', [\App\Http\Controllers\Api\V1\PasswordRecoveryController::class, 'complete']);
    });

    // Public Properties & Rooms
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::get('/properties/{id}', [PropertyController::class, 'show']);
    Route::get('/properties/{property_id}/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // Public Catalog, Availability & Guest Booking
    Route::prefix('public')->group(function () {
        Route::get('/properties', [PublicBookingController::class, 'properties']);
        Route::get('/rooms/availability', [PublicBookingController::class, 'searchAvailability']);
        Route::post('/bookings/calculate-price', [PublicBookingController::class, 'calculatePrice']);
        Route::middleware('throttle:15,1')->group(function () {
            Route::post('/bookings', [PublicBookingController::class, 'storeGuestBooking']);
            Route::get('/bookings/track/{token}', [PublicBookingController::class, 'trackBooking']);
        });
    });

    // Authenticated Endpoints (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        // User Auth & Profile
        Route::get('/auth/profile', [AuthController::class, 'profile']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/calculate-price', [BookingController::class, 'calculatePrice']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
        Route::post('/bookings/{id}/reject', [BookingController::class, 'reject']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{id}/check-in', [BookingController::class, 'checkIn']);

        // Security Deposit Subledger & Check-Out Settlements
        Route::get('/deposits', [DepositController::class, 'index']);
        Route::get('/deposits/{id}', [DepositController::class, 'show']);
        Route::post('/deposits/{id}/adjustment', [DepositController::class, 'recordAdjustment']);

        Route::get('/settlements', [DepositController::class, 'settlementsIndex']);
        Route::post('/settlements', [DepositController::class, 'initiateSettlement']);
        Route::get('/settlements/{id}', [DepositController::class, 'settlementShow']);
        Route::post('/settlements/{id}/tenant-decision', [DepositController::class, 'submitTenantDecision']);
        Route::post('/settlements/{id}/resolve', [DepositController::class, 'resolveDispute']);
        Route::post('/settlements/{id}/finalize', [DepositController::class, 'finalizeRefund']);
        Route::post('/settlements/{id}/finalize-refund', [DepositController::class, 'finalizeRefund']);

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
        Route::post('/inspections/request', [InspectionController::class, 'tenantRequest']);
        Route::get('/inspections/{id}', [InspectionController::class, 'show'])->whereNumber('id');
        Route::post('/inspections/{id}/start', [InspectionController::class, 'start'])->whereNumber('id');
        Route::put('/inspections/{id}/items/{itemId}', [InspectionController::class, 'updateItem'])->whereNumber('id');
        Route::post('/inspections/{id}/media', [InspectionController::class, 'uploadMedia'])->whereNumber('id');
        Route::get('/inspections/{id}/media/{mediaId}', [InspectionController::class, 'streamMedia'])->whereNumber('id');
        Route::post('/inspections/{id}/complete', [InspectionController::class, 'complete'])->whereNumber('id');
        Route::post('/inspections/{id}/approve', [InspectionController::class, 'approve'])->whereNumber('id');
        Route::post('/inspections/{id}/object', [InspectionController::class, 'object'])->whereNumber('id');
        Route::post('/inspections/{id}/assign', [InspectionController::class, 'assign'])->whereNumber('id');
        Route::post('/inspections/{id}/reschedule', [InspectionController::class, 'reschedule'])->whereNumber('id');
        Route::get('/inspections/{id}/compare/{baselineId}', [InspectionController::class, 'compare'])->whereNumber('id');

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
        Route::post('/housekeeping/request', [HousekeepingController::class, 'tenantRequest']);
        Route::get('/housekeeping/{id}', [HousekeepingController::class, 'show'])->whereNumber('id');
        Route::post('/housekeeping/{id}/assign', [HousekeepingController::class, 'assign'])->whereNumber('id');
        Route::post('/housekeeping/{id}/start', [HousekeepingController::class, 'start'])->whereNumber('id');
        Route::post('/housekeeping/{id}/submit-verification', [HousekeepingController::class, 'submitVerification'])->whereNumber('id');
        Route::post('/housekeeping/{id}/verify', [HousekeepingController::class, 'verify'])->whereNumber('id');
        Route::post('/housekeeping/{id}/approve', [HousekeepingController::class, 'approve'])->whereNumber('id');
        Route::post('/housekeeping/{id}/request-revision', [HousekeepingController::class, 'requestRevision'])->whereNumber('id');
        Route::post('/housekeeping/{id}/reschedule', [HousekeepingController::class, 'reschedule'])->whereNumber('id');
        Route::post('/housekeeping/{id}/cancel', [HousekeepingController::class, 'cancel'])->whereNumber('id');
        Route::post('/housekeeping/{id}/tenant-cancel', [HousekeepingController::class, 'tenantCancel'])->whereNumber('id');
        Route::get('/housekeeping/{id}/photo', [HousekeepingController::class, 'photo'])->whereNumber('id');

        // Phase 5: Internal Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Phase 10: Announcements (Pengumuman)
        Route::get('/announcements', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'index']);
        Route::get('/announcements/unread-count', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'unreadCount']);
        Route::get('/announcements/{id}', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'show'])->whereNumber('id');
        Route::post('/announcements/{id}/read', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'markAsRead'])->whereNumber('id');
        Route::post('/announcements', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'store']);
        Route::put('/announcements/{id}', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'update'])->whereNumber('id');
        Route::delete('/announcements/{id}', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'destroy'])->whereNumber('id');

        // Phase 10: Tenant Experience (Active Room, Identity Verification & NIK, Password Recovery Initiation)
        Route::get('/tenant/active-room', [HousekeepingController::class, 'activeRoom']);
        Route::get('/tenant/identity', [\App\Http\Controllers\Api\V1\IdentityVerificationController::class, 'show']);
        Route::post('/tenant/identity/verify', [\App\Http\Controllers\Api\V1\IdentityVerificationController::class, 'verify']);
        Route::put('/tenant/identity', [\App\Http\Controllers\Api\V1\IdentityVerificationController::class, 'update']);
        Route::post('/auth/tenant/password-reset/initiate', [\App\Http\Controllers\Api\V1\PasswordRecoveryController::class, 'initiate']);

        // Phase 6: Operational Expense Management & Approval Workflow
        Route::get('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'index']);
        Route::post('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'store']);
        Route::get('/expenses/categories', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'categories']);
        Route::post('/expenses/categories', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'storeCategory']);
        Route::get('/expenses/{id}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'show']);
        Route::put('/expenses/{id}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'update']);
        Route::post('/expenses/{id}/submit', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'submit']);
        Route::post('/expenses/{id}/approve', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'approve']);
        Route::post('/expenses/{id}/reject', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'reject']);
        Route::post('/expenses/{id}/pay', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'recordPayment']);
        Route::get('/expenses/{id}/receipt', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'receipt']);

        // Phase 6: Business Analytics & Financial Reports
        Route::get('/finance/dashboard', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'dashboard']);
        Route::get('/finance/reports/income', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'income']);
        Route::get('/finance/reports/expenses', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'expenses']);
        Route::get('/finance/reports/cash-flow', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'cashFlow']);
        Route::get('/finance/reports/receivables', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'receivables']);
        Route::get('/finance/reports/operating-summary', [\App\Http\Controllers\Api\V1\FinancialReportApiController::class, 'operatingSummary']);

        // Phase 8: Accounting, Double-Entry General Journal & Reconciliation
        Route::prefix('finance/accounting')->group(function () {
            Route::get('/coa', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'coa']);
            Route::post('/coa', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'storeAccount']);
            Route::get('/journals', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'journals']);
            Route::get('/journals/{id}', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'journalDetail']);
            Route::post('/journals/{id}/reverse', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'reverseJournal']);
            Route::get('/reports/trial-balance', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'trialBalance']);
            Route::get('/reports/income-statement', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'incomeStatement']);
            Route::get('/reports/balance-sheet', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'balanceSheet']);
            Route::get('/reports/reconciliation', [\App\Http\Controllers\Api\V1\AccountingApiController::class, 'reconciliation']);
        });

        // Fixed Assets & Operational Inventory API
        Route::get('/assets', [\App\Http\Controllers\Api\V1\AssetApiController::class, 'index']);
        Route::get('/assets/{id}', [\App\Http\Controllers\Api\V1\AssetApiController::class, 'show'])->whereNumber('id');
        Route::post('/assets/{id}/report-issue', [\App\Http\Controllers\Api\V1\AssetApiController::class, 'reportIssue'])->whereNumber('id');
    });
});

