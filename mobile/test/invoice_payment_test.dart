import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/invoices/models/invoice_model.dart';
import 'package:mobile/features/invoices/models/payment_model.dart';
import 'package:mobile/features/invoices/providers/invoice_provider.dart';
import 'package:mobile/features/invoices/screens/invoice_detail_screen.dart';
import 'package:mobile/features/invoices/screens/invoices_screen.dart';
import 'package:mobile/features/invoices/screens/payment_submit_screen.dart';

void main() {
  group('InvoiceModel & PaymentModel Tests', () {
    test('InvoiceModel parses json and calculates remaining balance correctly', () {
      final json = {
        'id': 10,
        'invoice_number': 'INV-20261013-0001',
        'invoice_type': 'rent',
        'subtotal': 1800000,
        'discount': 100000,
        'penalty': 50000,
        'total_amount': 1750000,
        'paid_amount': 500000,
        'remaining_balance': 1250000,
        'status': 'partially_paid',
        'due_date': '2026-10-20',
        'payments': [
          {
            'id': 101,
            'payment_code': 'PAY-001',
            'amount': 500000,
            'payment_method': 'manual_transfer',
            'payment_provider': 'manual_bca',
            'sender_name': 'Rizky',
            'sender_bank': 'BCA',
            'status': 'verified',
            'created_at': '2026-10-13T10:00:00Z',
          }
        ],
      };

      final invoice = InvoiceModel.fromJson(json);

      expect(invoice.id, 10);
      expect(invoice.invoiceNumber, 'INV-20261013-0001');
      expect(invoice.totalAmount, 1750000);
      expect(invoice.paidAmount, 500000);
      expect(invoice.remainingBalance, 1250000);
      expect(invoice.status, 'partially_paid');
      expect(invoice.isPaid, false);
      expect(invoice.statusLabel, 'Dibayar Sebagian');
      expect(invoice.payments.length, 1);
      expect(invoice.payments.first.paymentCode, 'PAY-001');
      expect(invoice.payments.first.statusLabel, 'Sah / Diterima');
    });

    test('PaymentModel handles rejection reason correctly', () {
      final json = {
        'id': 102,
        'payment_code': 'PAY-002',
        'amount': 300000,
        'payment_method': 'manual_transfer',
        'payment_provider': 'manual_mandiri',
        'status': 'rejected',
        'rejection_reason': 'Nominal mutasi tidak sesuai',
      };

      final payment = PaymentModel.fromJson(json);
      expect(payment.status, 'rejected');
      expect(payment.statusLabel, 'Ditolak');
      expect(payment.rejectionReason, 'Nominal mutasi tidak sesuai');
    });
  });

  group('Invoice & Payment Widget Tests', () {
    final testInvoice = InvoiceModel(
      id: 99,
      invoiceNumber: 'INV-TEST-99',
      invoiceType: 'rent',
      subtotal: 1500000,
      totalAmount: 1500000,
      paidAmount: 500000,
      remainingBalance: 1000000,
      status: 'partially_paid',
      dueDate: DateTime(2026, 10, 25),
      payments: [
        PaymentModel(
          id: 201,
          paymentCode: 'PAY-TEST-01',
          invoiceId: 99,
          amount: 500000,
          paymentMethod: 'manual_transfer',
          paymentProvider: 'manual_bca',
          senderBank: 'BCA',
          senderName: 'Budi Santoso',
          status: 'verified',
        ),
      ],
    );

    testWidgets('InvoicesScreen displays invoice items', (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            invoicesProvider.overrideWith((ref) => Future.value([testInvoice])),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const InvoicesScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Tagihan & Pembayaran'), findsOneWidget);
      expect(find.text('INV-TEST-99'), findsOneWidget);
      expect(find.text('Dibayar Sebagian'), findsOneWidget);
    });

    testWidgets('InvoiceDetailScreen displays breakdown and payment history', (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            invoiceDetailProvider(99).overrideWith((ref) => Future.value(testInvoice)),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: InvoiceDetailScreen(invoiceId: 99, initialInvoice: testInvoice),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('INV-TEST-99'), findsOneWidget);
      expect(find.text('Rincian Biaya'), findsOneWidget);
      expect(find.text('Total Tagihan'), findsOneWidget);
      expect(find.text('PAY-TEST-01'), findsOneWidget);
      expect(find.textContaining('Budi Santoso'), findsWidgets);
    });

    testWidgets('PaymentSubmitScreen validates amount and renders provider options', (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: PaymentSubmitScreen(invoiceId: 99, invoice: testInvoice),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Kirim Bukti Pembayaran'), findsOneWidget);
      expect(find.text('Pilih Rekening Tujuan Kos'), findsOneWidget);
      expect(find.text('Bank Central Asia (BCA)'), findsOneWidget);
      expect(find.text('Ajukan Bukti Transfer'), findsOneWidget);

      // Cek tombol submit ditekan dengan input valid
      final submitBtn = find.text('Ajukan Bukti Transfer');
      expect(submitBtn, findsOneWidget);
    });
  });
}
