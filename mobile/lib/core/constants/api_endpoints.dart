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
      return 'http://10.0.2.2:8000/api/v1';
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
}
