import 'package:intl/intl.dart';

class Formatters {
  static final NumberFormat _currencyFormat = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp ',
    decimalDigits: 0,
  );

  static String toRupiah(num? amount) {
    if (amount == null) return 'Rp 0';
    return _currencyFormat.format(amount);
  }

  static String toIndonesianDate(DateTime? date) {
    if (date == null) return '-';
    try {
      return DateFormat('d MMMM yyyy', 'id_ID').format(date);
    } catch (_) {
      return '${date.day}-${date.month}-${date.year}';
    }
  }

  static String toShortDate(DateTime? date) {
    if (date == null) return '-';
    try {
      return DateFormat('d MMM yyyy', 'id_ID').format(date);
    } catch (_) {
      return '${date.day}-${date.month}-${date.year}';
    }
  }

  static String formatDate(DateTime? date) => toShortDate(date);
  static String date(DateTime? date) => toShortDate(date);
  static String currency(num? amount) => toRupiah(amount);
}
