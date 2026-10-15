import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/invoice_model.dart';
import '../models/payment_model.dart';
import '../providers/invoice_provider.dart';

class InvoiceDetailScreen extends ConsumerWidget {
  final int invoiceId;
  final InvoiceModel? initialInvoice;

  const InvoiceDetailScreen({
    super.key,
    required this.invoiceId,
    this.initialInvoice,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(invoiceDetailProvider(invoiceId));
    final invoice = detailAsync.value ?? initialInvoice;

    if (invoice == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Detail Tagihan')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(invoice.invoiceNumber),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(invoiceDetailProvider(invoiceId)),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status Banner Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: invoice.isPaid ? AppColors.success.withAlpha(25) : AppColors.danger.withAlpha(20),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: invoice.isPaid ? AppColors.success.withAlpha(50) : AppColors.danger.withAlpha(50),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    invoice.isPaid ? Icons.check_circle_rounded : Icons.pending_actions_rounded,
                    size: 36,
                    color: invoice.isPaid ? AppColors.success : AppColors.danger,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          invoice.statusLabel,
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: invoice.isPaid ? AppColors.success : AppColors.danger,
                          ),
                        ),
                        Text(
                          invoice.isPaid
                              ? 'Seluruh kewajiban pembayaran telah lunas.'
                              : 'Sisa saldo: ${Formatters.toRupiah(invoice.remainingBalance)}',
                          style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Rincian Biaya Tagihan
            Text('Rincian Biaya', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  children: [
                    if (invoice.items.isNotEmpty) ...[
                      for (final item in invoice.items) ...[
                        _DetailRow(
                          label: item.unit != null
                              ? '${item.name} (${item.quantity} ${item.unit})'
                              : item.name,
                          value: Formatters.toRupiah(item.amount),
                        ),
                        const Divider(height: 16),
                      ],
                    ] else ...[
                      _DetailRow(label: 'Sewa Pokok (${invoice.invoiceType.toUpperCase()})', value: Formatters.toRupiah(invoice.subtotal)),
                    ],
                    if (invoice.discount > 0) ...[
                      const Divider(height: 16),
                      _DetailRow(label: 'Potongan Harga (Diskon)', value: '- ${Formatters.toRupiah(invoice.discount)}', isDiscount: true),
                    ],
                    if (invoice.penalty > 0) ...[
                      const Divider(height: 16),
                      _DetailRow(label: 'Denda Keterlambatan', value: '+ ${Formatters.toRupiah(invoice.penalty)}', isPenalty: true),
                    ],
                    const Divider(height: 20),
                    _DetailRow(label: 'Total Tagihan', value: Formatters.toRupiah(invoice.totalAmount), isBold: true),
                    const Divider(height: 16),
                    _DetailRow(label: 'Sudah Diverifikasi Kasir', value: Formatters.toRupiah(invoice.paidAmount), isSuccess: true),
                    const Divider(height: 20),
                    _DetailRow(label: 'Sisa Saldo Wajib Bayar', value: Formatters.toRupiah(invoice.remainingBalance), isHighlight: true),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Riwayat Pembayaran Masuk
            Text('Riwayat Pembayaran Diajukan', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 10),
            if (invoice.payments.isEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surfaceMuted,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'Belum ada bukti pembayaran yang diajukan untuk tagihan ini.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
                ),
              )
            else
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: invoice.payments.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, idx) {
                  final p = invoice.payments[idx];
                  return _PaymentHistoryItem(payment: p);
                },
              ),
            const SizedBox(height: 32),
          ],
        ),
      ),
      bottomNavigationBar: invoice.remainingBalance > 0
          ? Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: AppColors.border)),
              ),
              child: SafeArea(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.payment_rounded),
                  label: Text('Bayar Sekarang (${Formatters.toRupiah(invoice.remainingBalance)})'),
                  onPressed: () {
                    context.push('/invoice/${invoice.id}/pay', extra: invoice);
                  },
                ),
              ),
            )
          : null,
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final bool isBold;
  final bool isDiscount;
  final bool isPenalty;
  final bool isSuccess;
  final bool isHighlight;

  const _DetailRow({
    required this.label,
    required this.value,
    this.isBold = false,
    this.isDiscount = false,
    this.isPenalty = false,
    this.isSuccess = false,
    this.isHighlight = false,
  });

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.textPrimary;
    if (isDiscount) color = AppColors.success;
    if (isPenalty) color = AppColors.danger;
    if (isSuccess) color = AppColors.success;
    if (isHighlight) color = AppColors.danger;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: isHighlight ? 14 : 13,
            fontWeight: isHighlight || isBold ? FontWeight.bold : FontWeight.normal,
            color: isHighlight ? AppColors.textPrimary : AppColors.textSecondary,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isHighlight ? 16 : 14,
            fontWeight: isHighlight || isBold ? FontWeight.bold : FontWeight.w600,
            color: color,
          ),
        ),
      ],
    );
  }
}

class _PaymentHistoryItem extends StatelessWidget {
  final PaymentModel payment;

  const _PaymentHistoryItem({required this.payment});

  @override
  Widget build(BuildContext context) {
    Color statusBg;
    Color statusText;
    if (payment.status == 'verified') {
      statusBg = AppColors.success.withAlpha(25);
      statusText = AppColors.success;
    } else if (payment.status == 'rejected') {
      statusBg = AppColors.danger.withAlpha(25);
      statusText = AppColors.danger;
    } else {
      statusBg = AppColors.warning.withAlpha(25);
      statusText = AppColors.warning;
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  payment.paymentCode,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusBg,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    payment.statusLabel,
                    style: TextStyle(color: statusText, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  Formatters.toRupiah(payment.amount),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppColors.primary),
                ),
                Text(
                  payment.createdAt != null ? Formatters.toShortDate(payment.createdAt) : '-',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
              ],
            ),
            if (payment.senderBank != null) ...[
              const SizedBox(height: 4),
              Text(
                'Pengirim: ${payment.senderBank} a.n ${payment.senderName ?? '-'}',
                style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
              ),
            ],
            if (payment.rejectionReason != null && payment.rejectionReason!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.danger.withAlpha(15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.danger.withAlpha(40)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Alasan Penolakan Kasir:',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.danger),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      payment.rejectionReason!,
                      style: const TextStyle(fontSize: 12, color: AppColors.danger),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
