import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/booking/models/booking_model.dart';
import 'package:mobile/features/booking/providers/booking_provider.dart';
import 'package:mobile/features/booking/screens/booking_detail_screen.dart';
import 'package:mobile/features/booking/screens/booking_form_screen.dart';
import 'package:mobile/features/booking/screens/booking_list_screen.dart';
import 'package:mobile/features/booking/screens/booking_track_screen.dart';

class FakeBookingRepository extends BookingRepository {
  FakeBookingRepository() : super(null);

  @override
  Future<List<BookingModel>> fetchBookings({String? status, int? propertyId, String? search}) async {
    return [
      BookingModel(
        id: 1,
        bookingCode: 'BK-20261016-0001',
        propertyId: 1,
        roomId: 1,
        propertyName: 'Kos Permata Hijau',
        roomNumber: '101',
        roomTypeName: 'Deluxe Single',
        customerName: 'Ahmad Fauzi',
        rentalType: 'monthly',
        startDate: DateTime(2026, 11, 1),
        endDate: DateTime(2026, 12, 1),
        rateAmount: 1500000,
        depositAmount: 500000,
        downPaymentAmount: 300000,
        totalAmount: 2000000,
        status: 'pending',
      ),
      BookingModel(
        id: 2,
        bookingCode: 'BK-20261016-0002',
        propertyId: 1,
        roomId: 2,
        propertyName: 'Kos Permata Hijau',
        roomNumber: '102',
        roomTypeName: 'Standard Room',
        customerName: 'Siti Aminah',
        rentalType: 'daily',
        startDate: DateTime(2026, 11, 2),
        endDate: DateTime(2026, 11, 5),
        rateAmount: 450000,
        depositAmount: 0,
        downPaymentAmount: 0,
        totalAmount: 450000,
        status: 'confirmed',
      ),
    ];
  }

  @override
  Future<PricingEstimateModel> calculatePrice({
    required int roomId,
    required String rentalType,
    required String startDate,
    required String endDate,
  }) async {
    return PricingEstimateModel(
      days: 30,
      rentalType: rentalType,
      rateAmount: 1500000,
      depositAmount: 500000,
      downPaymentAmount: 300000,
      totalAmount: 2000000,
      dpRequired: true,
      paymentDeadlineHours: 24,
    );
  }

  @override
  Future<BookingModel?> trackBooking(String token) async {
    return BookingModel(
      id: 1,
      bookingCode: 'BK-TRACK-001',
      propertyId: 1,
      roomId: 1,
      propertyName: 'Kos Permata Hijau',
      roomNumber: '101',
      roomTypeName: 'Deluxe Single',
      customerName: 'Budi Santoso',
      rentalType: 'monthly',
      startDate: DateTime(2026, 11, 1),
      endDate: DateTime(2026, 12, 1),
      rateAmount: 1500000,
      depositAmount: 500000,
      downPaymentAmount: 300000,
      totalAmount: 2000000,
      status: 'pending',
      accessToken: token,
    );
  }
}

void main() {
  group('BookingModel & PricingEstimateModel Unit Tests', () {
    test('BookingModel parses JSON with guest details and down payment correctly', () {
      final json = {
        'id': 10,
        'booking_code': 'BK-20261016-ABCD',
        'property_id': 1,
        'room_id': 5,
        'user_id': null,
        'guest_name': 'Rian Pratama',
        'guest_email': 'rian@example.com',
        'guest_phone': '08123456789',
        'guest_id_card': '3201234567890001',
        'access_token': 'secret_token_123456',
        'rental_type': 'monthly',
        'start_date': '2026-11-01',
        'end_date': '2026-12-01',
        'rate_amount': '1800000.00',
        'deposit_amount': '500000.00',
        'down_payment_amount': '360000.00',
        'total_amount': '2300000.00',
        'status': 'pending',
        'notes': 'Mohon kamar di lantai bawah',
        'property': {
          'id': 1,
          'name': 'Kos Permata Hijau',
          'address': 'Jl. Kaliurang KM 5',
        },
        'room': {
          'id': 5,
          'room_number': '204',
          'room_type': {
            'id': 2,
            'name': 'Deluxe AC',
          },
        },
      };

      final model = BookingModel.fromJson(json);

      expect(model.id, 10);
      expect(model.bookingCode, 'BK-20261016-ABCD');
      expect(model.isGuest, isTrue);
      expect(model.customerName, 'Rian Pratama');
      expect(model.guestEmail, 'rian@example.com');
      expect(model.propertyName, 'Kos Permata Hijau');
      expect(model.roomNumber, '204');
      expect(model.roomTypeName, 'Deluxe AC');
      expect(model.rateAmount, 1800000);
      expect(model.depositAmount, 500000);
      expect(model.downPaymentAmount, 360000);
      expect(model.totalAmount, 2300000);
      expect(model.status, 'pending');
      expect(model.statusLabel, 'Menunggu Review / DP');
      expect(model.canConfirm, isTrue);
      expect(model.canCheckIn, isFalse);
    });

    test('PricingEstimateModel parses calculation JSON correctly', () {
      final json = {
        'days': 30,
        'rental_type': 'monthly',
        'rate_amount': '1500000.00',
        'deposit_amount': '500000.00',
        'down_payment_amount': '300000.00',
        'total_amount': '2000000.00',
        'dp_required': true,
        'dp_type': 'fixed',
        'payment_deadline_hours': 24,
      };

      final pricing = PricingEstimateModel.fromJson(json);

      expect(pricing.days, 30);
      expect(pricing.rentalType, 'monthly');
      expect(pricing.rateAmount, 1500000);
      expect(pricing.depositAmount, 500000);
      expect(pricing.downPaymentAmount, 300000);
      expect(pricing.totalAmount, 2000000);
      expect(pricing.dpRequired, isTrue);
      expect(pricing.paymentDeadlineHours, 24);
    });

    test('BookingModel resolves status badges and transitions properly', () {
      final pending = BookingModel(
        id: 1,
        bookingCode: 'BK-1',
        propertyId: 1,
        roomId: 1,
        rentalType: 'monthly',
        rateAmount: 1000000,
        depositAmount: 500000,
        downPaymentAmount: 200000,
        totalAmount: 1500000,
        status: 'pending',
      );
      expect(pending.canConfirm, isTrue);
      expect(pending.canCheckIn, isFalse);
      expect(pending.canCancel, isTrue);

      final confirmed = BookingModel(
        id: 2,
        bookingCode: 'BK-2',
        propertyId: 1,
        roomId: 1,
        rentalType: 'monthly',
        rateAmount: 1000000,
        depositAmount: 500000,
        downPaymentAmount: 200000,
        totalAmount: 1500000,
        status: 'confirmed',
      );
      expect(confirmed.canConfirm, isFalse);
      expect(confirmed.canCheckIn, isTrue);
      expect(confirmed.canCancel, isTrue);

      final active = BookingModel(
        id: 3,
        bookingCode: 'BK-3',
        propertyId: 1,
        roomId: 1,
        rentalType: 'monthly',
        rateAmount: 1000000,
        depositAmount: 500000,
        downPaymentAmount: 200000,
        totalAmount: 1500000,
        status: 'active',
      );
      expect(active.canConfirm, isFalse);
      expect(active.canCheckIn, isFalse);
      expect(active.canCancel, isFalse);
    });
  });

  group('Booking Screens Widget Tests', () {
    testWidgets('BookingListScreen renders tabs and booking cards', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            bookingRepositoryProvider.overrideWithValue(FakeBookingRepository()),
            bookingListProvider.overrideWith((ref) => FakeBookingRepository().fetchBookings()),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const BookingListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Daftar Pemesanan Kamar'), findsOneWidget);
      expect(find.text('Semua'), findsOneWidget);
      expect(find.text('Menunggu'), findsOneWidget);
      expect(find.text('Terkonfirmasi'), findsOneWidget);
      expect(find.text('Aktif'), findsOneWidget);

      expect(find.text('BK-20261016-0001'), findsOneWidget);
      expect(find.text('Ahmad Fauzi'), findsOneWidget);
      expect(find.text('Kos Permata Hijau • Kamar 101'), findsOneWidget);
    });

    testWidgets('BookingDetailScreen renders details and staff actions', (tester) async {
      final sample = BookingModel(
        id: 1,
        bookingCode: 'BK-DETAIL-001',
        propertyId: 1,
        roomId: 1,
        rentalType: 'monthly',
        rateAmount: 1500000,
        depositAmount: 500000,
        downPaymentAmount: 300000,
        totalAmount: 2000000,
        status: 'pending',
        propertyName: 'Kos Permata Hijau',
        roomNumber: '101',
        roomTypeName: 'Deluxe AC',
        customerName: 'Budi Santoso',
        guestPhone: '081234567890',
        guestEmail: 'budi@example.com',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            bookingRepositoryProvider.overrideWithValue(FakeBookingRepository()),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: BookingDetailScreen(
              bookingId: 1,
              initialBooking: sample,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('BK-DETAIL-001'), findsOneWidget);
      expect(find.text('Budi Santoso'), findsOneWidget);
      expect(find.text('Kos Permata Hijau'), findsOneWidget);
      expect(find.text('Kamar 101 (Deluxe AC)'), findsOneWidget);
      expect(find.text('Konfirmasi'), findsOneWidget);
      expect(find.text('Tolak'), findsOneWidget);
    });

    testWidgets('BookingFormScreen renders form fields and date pickers', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            bookingRepositoryProvider.overrideWithValue(FakeBookingRepository()),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const BookingFormScreen(
              roomId: 1,
              propertyName: 'Kos Permata Hijau',
              roomNumber: '101',
              roomTypeName: 'Deluxe AC',
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Pengajuan Sewa Kamar'), findsOneWidget);
      expect(find.text('Kamar 101'), findsOneWidget);
      expect(find.text('Kos Permata Hijau'), findsOneWidget);
      expect(find.text('Nama Lengkap Sesuai KTP *'), findsOneWidget);
      expect(find.text('Nomor WhatsApp / HP Aktif *'), findsOneWidget);
      expect(find.text('Alamat Email *'), findsOneWidget);
      expect(find.text('Ajukan Pemesanan Sekarang'), findsOneWidget);
    });

    testWidgets('BookingTrackScreen renders header and status info with fake repo', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            bookingRepositoryProvider.overrideWithValue(FakeBookingRepository()),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const BookingTrackScreen(
              token: 'demo_token_123456789',
              initialBookingCode: 'BK-TRACK-001',
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Status Pemesanan'), findsOneWidget);
      expect(find.text('BK-TRACK-001'), findsOneWidget);
      expect(find.text('Kos Permata Hijau'), findsOneWidget);
      expect(find.text('Kamar 101 (Deluxe Single)'), findsOneWidget);
    });
  });
}
