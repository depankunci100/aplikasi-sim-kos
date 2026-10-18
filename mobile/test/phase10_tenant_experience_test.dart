import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/features/announcements/models/announcement_model.dart';
import 'package:mobile/features/announcements/providers/announcement_provider.dart';
import 'package:mobile/features/announcements/screens/announcement_detail_screen.dart';
import 'package:mobile/features/profile/services/ktp_ocr_service.dart';

class FakeAnnouncementNotifier extends StateNotifier<AnnouncementState> implements AnnouncementNotifier {
  FakeAnnouncementNotifier(super.initialState);

  @override
  Future<void> fetchAnnouncements({bool? isRead}) async {}

  @override
  Future<void> markAsRead(int announcementId) async {}
}

void main() {
  group('Phase 10 - AnnouncementModel Unit Tests', () {
    test('parses JSON correctly with property scope and read status', () {
      final json = {
        'id': 101,
        'title': 'Pemberitahuan Maintenance Air Bersih',
        'content': 'Akan dilakukan pengurasan tandon air pada hari Sabtu pukul 09:00 - 12:00 WIB.',
        'property_id': 2,
        'property': {'name': 'Kos Andalan Soehat'},
        'author': {'name': 'Mas Huda Admin'},
        'target_role': 'tenant',
        'is_published': true,
        'attachment_path': 'announcements/water_maintenance.pdf',
        'published_at': '2026-10-18T10:00:00Z',
        'is_read': false,
      };

      final announcement = AnnouncementModel.fromJson(json);

      expect(announcement.id, 101);
      expect(announcement.title, 'Pemberitahuan Maintenance Air Bersih');
      expect(announcement.propertyName, 'Kos Andalan Soehat');
      expect(announcement.authorName, 'Mas Huda Admin');
      expect(announcement.isRead, false);
      expect(announcement.isPublished, true);
      expect(announcement.attachmentPath, 'announcements/water_maintenance.pdf');
    });

    test('handles global scope announcement (null property_id)', () {
      final json = {
        'id': 102,
        'title': 'Kebijakan Parkir Bersama',
        'content': 'Harap memarkir kendaraan secara tertib dan mengunci ganda.',
        'property_id': null,
        'target_role': 'all',
        'is_published': true,
        'published_at': '2026-10-15T08:00:00Z',
        'is_read': true,
      };

      final announcement = AnnouncementModel.fromJson(json);

      expect(announcement.id, 102);
      expect(announcement.propertyId, null);
      expect(announcement.propertyName, null);
      expect(announcement.isRead, true);
      expect(announcement.attachmentPath, null);
    });
  });

  group('Phase 10 - KTP OCR Service Unit Tests', () {
    test('extracts clean 16-digit NIK and Indonesian name correctly', () {
      const rawSample = '''
      REPUBLIK INDONESIA
      PROVINSI DAERAH KHUSUS IBUKOTA JAKARTA
      JAKARTA SELATAN
      NIK : 3174051809950002
      Nama : BUDI SANTOSO
      Tempat/Tgl Lahir : JAKARTA, 18-09-1995
      Jenis Kelamin : LAKI-LAKI
      Alamat : JL. TEBET BARAT DALAM NO. 45
      ''';

      final result = KtpOcrService.parseKtpText(rawSample);

      expect(result.nik, '3174051809950002');
      expect(result.isValidNik, true);
      expect(result.name, 'BUDI SANTOSO');
      expect(result.gender, 'LAKI-LAKI');
      expect(result.isConfident, true);
    });

    test('recovers NIK with OCR character ambiguity (O instead of 0)', () {
      const corruptedSample = '''
      NIK: 3174O518O995OOO2
      Nama: SITI AMINAH
      Jenis Kelamin: PEREMPUAN
      ''';

      final result = KtpOcrService.parseKtpText(corruptedSample);

      expect(result.nik, '3174051809950002');
      expect(result.isValidNik, true);
      expect(result.gender, 'PEREMPUAN');
    });

    test('flags invalid NIK if length is not 16 digits', () {
      const invalidSample = '''
      NIK: 12345
      Nama: TES PENDEK
      ''';

      final result = KtpOcrService.parseKtpText(invalidSample);
      expect(result.nik, null);
      expect(result.isValidNik, false);
    });
  });

  group('Phase 10 - Announcement Detail Widget Test', () {
    testWidgets('renders announcement title, author, and content', (tester) async {
      final announcement = AnnouncementModel(
        id: 99,
        title: 'Pengumuman Uji Coba Phase 10',
        content: 'Ini adalah isi detail pengumuman pengujian untuk penyewa.',
        propertyName: 'Kos Melati',
        authorName: 'Staff Pengelola',
        publishedAt: DateTime(2026, 10, 18, 12, 0),
        isRead: false,
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            announcementProvider.overrideWith(
              (ref) => FakeAnnouncementNotifier(
                AnnouncementState(announcements: [announcement]),
              ),
            ),
          ],
          child: MaterialApp(
            home: AnnouncementDetailScreen(
              announcementId: 99,
              initialAnnouncement: announcement,
            ),
          ),
        ),
      );

      expect(find.text('Detail Pengumuman'), findsOneWidget);
      expect(find.text('Pengumuman Uji Coba Phase 10'), findsOneWidget);
      expect(find.text('Ini adalah isi detail pengumuman pengujian untuk penyewa.'), findsOneWidget);
      expect(find.text('Staff Pengelola'), findsOneWidget);
      expect(find.text('Kos Melati'), findsOneWidget);
      expect(find.text('Target: Semua'), findsOneWidget);
    });
  });
}
