import 'package:flutter/foundation.dart';

class ApiEndpoints {
  static String? _customBaseUrl;

  /// Memungkinkan pengaturan Base URL kustom saat runtime (misal dari layar pengaturan IP)
  static void setCustomBaseUrl(String? url) {
    _customBaseUrl = url;
  }

  /// Menentukan Base URL secara adaptif:
  /// 1. Nilai kustom runtime jika disetel
  /// 2. Nilai compile-time via --dart-define=API_BASE_URL=http://<IP_KOMPUTER>:8000/api/v1
  /// 3. Default otomatis:
  ///    - Android Emulator: http://10.0.2.2:8000/api/v1
  ///    - iOS Simulator / macOS / Desktop: http://127.0.0.1:8000/api/v1
  ///    - Web: http://localhost:8000/api/v1
  static String get baseUrl {
    if (_customBaseUrl != null && _customBaseUrl!.isNotEmpty) {
      return _customBaseUrl!;
    }

    const envUrl = String.fromEnvironment('API_BASE_URL', defaultValue: '');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }

    if (kIsWeb) {
      return 'http://localhost:8000/api/v1';
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://192.168.1.12:8000/api/v1';
    }

    return 'http://127.0.0.1:8000/api/v1';
  }

  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String profile = '/auth/profile';
  static const String logout = '/auth/logout';

  static const String properties = '/properties';
  static const String bookings = '/bookings';
  static const String invoices = '/invoices';
  static const String inspections = '/inspections';
  static const String complaints = '/complaints';

  static const String utilityMeters = '/utilities/meters';
  static const String utilityReadings = '/utilities/readings';
  static const String utilityTariffs = '/utilities/tariffs';
  static const String tenantConsumption = '/utilities/tenant/consumption';

  static const String maintenance = '/maintenance';
  static const String housekeeping = '/housekeeping';
  static const String notifications = '/notifications';

  static const String expenses = '/expenses';
  static const String expenseCategories = '/expenses/categories';
  static const String financeDashboard = '/finance/dashboard';
  static const String reportsIncome = '/finance/reports/income';
  static const String reportsExpenses = '/finance/reports/expenses';
  static const String reportsCashFlow = '/finance/reports/cash-flow';
  static const String reportsReceivables = '/finance/reports/receivables';
  static const String reportsOperatingSummary = '/finance/reports/operating-summary';

  // Security Deposit Subledger & Check-Out Settlements (Phase 7)
  static const String deposits = '/deposits';
  static const String settlements = '/settlements';
  static String depositDetail(int id) => '/deposits/$id';
  static String settlementDetail(int id) => '/settlements/$id';
  static String settlementTenantDecision(int id) => '/settlements/$id/tenant-decision';
  static String settlementResolve(int id) => '/settlements/$id/resolve';
  static String settlementFinalize(int id) => '/settlements/$id/finalize';

  // Phase 8: Accounting, Double-Entry Journal & Reconciliation
  static const String accountingCoa = '/finance/accounting/coa';
  static const String accountingJournals = '/finance/accounting/journals';
  static String accountingJournalDetail(int id) => '/finance/accounting/journals/$id';
  static String accountingJournalReverse(int id) => '/finance/accounting/journals/$id/reverse';
  static const String accountingTrialBalance = '/finance/accounting/reports/trial-balance';
  static const String accountingIncomeStatement = '/finance/accounting/reports/income-statement';
  static const String accountingBalanceSheet = '/finance/accounting/reports/balance-sheet';
  static const String accountingReconciliation = '/finance/accounting/reports/reconciliation';

  // Public Booking & Availability
  static const String publicProperties = '/public/properties';
  static const String publicAvailability = '/public/rooms/availability';
  static const String publicCalculatePrice = '/public/bookings/calculate-price';
  static const String publicBookings = '/public/bookings';
  static String publicTrackBooking(String token) => '/public/bookings/track/$token';

  // Fixed Assets & Room Inventory
  static const String assets = '/assets';
  static String assetDetail(int id) => '/assets/$id';
  static String assetReportIssue(int id) => '/assets/$id/report-issue';
}
