<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ComplaintWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HousekeepingWebController;
use App\Http\Controllers\Web\InspectionWebController;
use App\Http\Controllers\Web\InvoiceWebController;
use App\Http\Controllers\Web\MaintenanceWebController;
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

    // Phase 5: Maintenance Management (All authenticated roles)
    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [MaintenanceWebController::class, 'index'])->name('index');
        Route::get('/create', [MaintenanceWebController::class, 'create'])->name('create');
        Route::post('/', [MaintenanceWebController::class, 'store'])->name('store');
        Route::get('/{id}', [MaintenanceWebController::class, 'show'])->name('show');
        Route::post('/{id}/triage', [MaintenanceWebController::class, 'triage'])->name('triage');
        Route::post('/{id}/assign', [MaintenanceWebController::class, 'assign'])->name('assign');
        Route::post('/{id}/start', [MaintenanceWebController::class, 'start'])->name('start');
        Route::post('/{id}/submit-verification', [MaintenanceWebController::class, 'submitVerification'])->name('submit-verification');
        Route::post('/{id}/verify', [MaintenanceWebController::class, 'verify'])->name('verify');
        Route::post('/{id}/reject-cancel', [MaintenanceWebController::class, 'rejectOrCancel'])->name('reject-cancel');
        Route::get('/{id}/photo', [MaintenanceWebController::class, 'photo'])->name('photo');
    });

    // Phase 5: Tenant Complaints
    Route::prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [ComplaintWebController::class, 'index'])->name('index');
        Route::get('/create', [ComplaintWebController::class, 'create'])->name('create');
        Route::post('/', [ComplaintWebController::class, 'store'])->name('store');
        Route::get('/{id}', [ComplaintWebController::class, 'show'])->name('show');
        Route::post('/{id}/acknowledge', [ComplaintWebController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{id}/assign', [ComplaintWebController::class, 'assign'])->name('assign');
        Route::post('/{id}/comments', [ComplaintWebController::class, 'addComment'])->name('comments');
        Route::post('/{id}/link-maintenance', [ComplaintWebController::class, 'linkMaintenance'])->name('link-maintenance');
        Route::post('/{id}/resolve', [ComplaintWebController::class, 'resolve'])->name('resolve');
        Route::post('/{id}/confirm-reopen', [ComplaintWebController::class, 'confirmOrReopen'])->name('confirm-reopen');
        Route::post('/{id}/reject', [ComplaintWebController::class, 'reject'])->name('reject');
        Route::get('/{id}/photo', [ComplaintWebController::class, 'photo'])->name('photo');
    });

    // Phase 5: Housekeeping Management
    Route::prefix('housekeeping')->name('housekeeping.')->group(function () {
        Route::get('/', [HousekeepingWebController::class, 'index'])->name('index');
        Route::get('/create', [HousekeepingWebController::class, 'create'])->name('create');
        Route::post('/', [HousekeepingWebController::class, 'store'])->name('store');
        Route::get('/{id}', [HousekeepingWebController::class, 'show'])->name('show');
        Route::post('/{id}/assign', [HousekeepingWebController::class, 'assign'])->name('assign');
        Route::post('/{id}/start', [HousekeepingWebController::class, 'start'])->name('start');
        Route::post('/{id}/submit-verification', [HousekeepingWebController::class, 'submitVerification'])->name('submit-verification');
        Route::post('/{id}/verify', [HousekeepingWebController::class, 'verify'])->name('verify');
        Route::post('/{id}/cancel', [HousekeepingWebController::class, 'cancel'])->name('cancel');
        Route::get('/{id}/photo', [HousekeepingWebController::class, 'photo'])->name('photo');
    });

    // Phase 6: Financial Management, Expenses & Approval Workflow
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\ExpenseWebController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Web\ExpenseWebController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Web\ExpenseWebController::class, 'store'])->name('store');
        Route::get('/categories', [\App\Http\Controllers\Web\ExpenseWebController::class, 'categories'])->name('categories');
        Route::post('/categories', [\App\Http\Controllers\Web\ExpenseWebController::class, 'storeCategory'])->name('categories.store');
        Route::post('/categories/{id}/toggle', [\App\Http\Controllers\Web\ExpenseWebController::class, 'toggleCategory'])->name('categories.toggle');
        Route::get('/{id}', [\App\Http\Controllers\Web\ExpenseWebController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Web\ExpenseWebController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Web\ExpenseWebController::class, 'update'])->name('update');
        Route::post('/{id}/submit', [\App\Http\Controllers\Web\ExpenseWebController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [\App\Http\Controllers\Web\ExpenseWebController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Web\ExpenseWebController::class, 'reject'])->name('reject');
        Route::post('/{id}/pay', [\App\Http\Controllers\Web\ExpenseWebController::class, 'recordPayment'])->name('pay');
        Route::get('/{id}/receipt', [\App\Http\Controllers\Web\ExpenseWebController::class, 'receipt'])->name('receipt');
        Route::get('/{id}/payments/{paymentId}/proof', [\App\Http\Controllers\Web\ExpenseWebController::class, 'paymentProof'])->name('payment-proof');
    });

    // Phase 6: Owner Dashboard, Business Analytics & Financial Reports
    Route::get('/finance', [\App\Http\Controllers\Web\FinanceWebController::class, 'dashboard'])->name('finance.index');
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/income', [\App\Http\Controllers\Web\FinanceWebController::class, 'incomeReport'])->name('income');
        Route::get('/expenses', [\App\Http\Controllers\Web\FinanceWebController::class, 'expenseReport'])->name('expenses');
        Route::get('/cash-flow', [\App\Http\Controllers\Web\FinanceWebController::class, 'cashFlowReport'])->name('cash-flow');
        Route::get('/receivables', [\App\Http\Controllers\Web\FinanceWebController::class, 'receivablesReport'])->name('receivables');
        Route::get('/operating-summary', [\App\Http\Controllers\Web\FinanceWebController::class, 'operatingSummaryReport'])->name('operating-summary');
        Route::get('/export/{type}', [\App\Http\Controllers\Web\FinanceWebController::class, 'export'])->name('export');
    });
});

