import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/features/auth/models/user_model.dart';
import 'package:mobile/features/auth/providers/auth_provider.dart';
import 'package:mobile/features/properties/screens/home_screen.dart';

class MockAuthNotifier extends AuthNotifier {
  MockAuthNotifier(UserModel user) : super(ApiClient()) {
    state = AuthState(user: user, token: 'mock_token');
  }
}

void main() {
  testWidgets('HomeScreen renders Admin Operational View correctly matching reference UI', (WidgetTester tester) async {
    final adminUser = UserModel(
      id: 1,
      name: 'Admin Utama',
      email: 'admin@simkos.id',
      role: 'owner',
      phone: '081234567890',
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authProvider.overrideWith((ref) => MockAuthNotifier(adminUser)),
        ],
        child: const MaterialApp(
          home: HomeScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    // Verifikasi Header Admin
    expect(find.textContaining('Admin 👋'), findsOneWidget);
    expect(find.text('Kelola layanan, pelanggan, dan pembayaran kos dengan mudah.'), findsOneWidget);

    // Verifikasi 4 Stat Cards
    expect(find.text('Total Pelanggan'), findsOneWidget);
    expect(find.text('Total Layanan'), findsOneWidget);
    expect(find.text('Perlu Verifikasi'), findsOneWidget);
    expect(find.text('Total Pemasukan'), findsOneWidget);

    // Verifikasi Menu Cepat
    expect(find.text('Menu Cepat'), findsOneWidget);
    expect(find.text('Customer'), findsOneWidget);
    expect(find.text('Layanan'), findsWidgets);
    expect(find.text('Tagihan'), findsWidgets);
    expect(find.text('Verifikasi'), findsOneWidget);

    // Verifikasi Aktifitas Terbaru
    expect(find.text('Aktifitas Terbaru'), findsOneWidget);
    expect(find.text('Pelanggan baru: Aditya'), findsOneWidget);
    expect(find.text('Tagihan baru: Royan'), findsOneWidget);
    expect(find.text('Pembayaran pending: Rafael'), findsOneWidget);
    expect(find.text('Pembayaran terverifikasi: Zulkarnaen'), findsOneWidget);

    // Verifikasi Bottom Navigation Bar Admin (5 items)
    expect(find.text('Beranda'), findsOneWidget);
    expect(find.text('Penghuni'), findsOneWidget);
    expect(find.text('Profil'), findsOneWidget);
  });

  testWidgets('HomeScreen renders Customer / Tenant View correctly matching reference UI', (WidgetTester tester) async {
    final tenantUser = UserModel(
      id: 6,
      name: 'Rizky Customer',
      email: 'penyewa@simkos.id',
      role: 'tenant',
      phone: '08987654321',
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authProvider.overrideWith((ref) => MockAuthNotifier(tenantUser)),
        ],
        child: const MaterialApp(
          home: HomeScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    // Verifikasi Header Customer
    expect(find.textContaining('Halo'), findsOneWidget);
    expect(find.text('Pantau penggunaan air, cek tagihan bulanan, layanan kos secara praktis'), findsOneWidget);

    // Verifikasi Hero Card Total Tagihan
    expect(find.text('Total Tagihan'), findsOneWidget);
    expect(find.text('Rp 224.000'), findsOneWidget);
    expect(find.text('Bayar'), findsOneWidget);
    expect(find.text('24 Mei 2026'), findsOneWidget);
    expect(find.text('Penggunaan: 16 m³'), findsOneWidget);

    // Verifikasi 2 Status Cards
    expect(find.text('Belum Lunas'), findsWidgets);
    expect(find.text('Sudah Lunas'), findsOneWidget);

    // Verifikasi Tagihan per Bulan (Chart)
    expect(find.text('Tagihan per Bulan'), findsOneWidget);
    expect(find.text('5 Bulan Terakhir'), findsOneWidget);
    expect(find.text('Jan'), findsOneWidget);
    expect(find.text('Feb'), findsOneWidget);
    expect(find.text('Mar'), findsOneWidget);
    expect(find.text('Apr'), findsOneWidget);
    expect(find.text('Mei'), findsWidgets);

    // Verifikasi Tagihan Terbaru List
    expect(find.text('Tagihan Terbaru'), findsOneWidget);
    expect(find.text('Mei 2026'), findsOneWidget);
    expect(find.text('April 2026'), findsOneWidget);
    expect(find.text('Maret 2026'), findsOneWidget);
    expect(find.text('Lunas'), findsWidgets);
  });
}
