import 'dart:io';

class ApiEndpoints {
  // Gunakan IP 10.0.2.2 jika di Android Emulator, atau localhost jika di iOS/Desktop
  static String get baseUrl {
    if (Platform.isAndroid) {
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
  static const String complaints = '/complaints';
}
