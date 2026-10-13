import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/invoice_model.dart';
import '../providers/invoice_provider.dart';

class InvoicesScreen extends ConsumerWidget {
  const InvoicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final invoicesAsync = ref.watch(invoicesProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tagihan & Pembayaran'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(invoicesProvider),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(invoicesProvider),
        child: invoicesAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (err, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline_rounded, size: 48, color: AppColors.danger),
                  const SizedBox(height: 12),
                  Text('Gagal memuat tagihan: $err', textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => ref.invalidate(invoicesProvider),
                    child: const Text('Coba Lagi'),
                  ),
                ],
              ),
            ),
          ),
          data: (invoices) {
            if (invoices.isEmpty) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.all(32.0),
                  child: Text('Tidak ada tagihan yang harus dibayar saat ini.'),
                ),
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: invoices.length,
              separatorBuilder: (_, __) => const SizedBox(height: 14),
              itemBuilder: (context, index) {
                final inv = invoices[index];
                return _InvoiceCard(invoice: inv);
              },
            );
          },
        ),
      ),
    );
  }
}

class _InvoiceCard extends StatelessWidget {
  final InvoiceModel invoice;

  const _InvoiceCard({required this.invoice});

  @override
  Widget build(BuildContext context) {
    Color statusColor;
    if (invoice.status == 'paid') {
      statusColor = AppColors.success;
    } else if (invoice.status == 'partially_paid') {
      statusColor = AppColors.warning;
    } else {
      statusColor = AppColors.danger;
    }

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () {
          context.push('/invoice/${invoice.id}', extra: invoice);
        },
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    invoice.invoiceNumber,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: statusColor.withAlpha(25),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      invoice.statusLabel,
                      style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Total Tagihan', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                      Text(
                        Formatters.toRupiah(invoice.totalAmount),
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                      ),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const Text('Sisa Wajib Bayar', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                      Text(
                        Formatters.toRupiah(invoice.remainingBalance),
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                          color: invoice.remainingBalance > 0 ? AppColors.danger : AppColors.success,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Divider(height: 1),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Jatuh tempo: ${Formatters.toShortDate(invoice.dueDate)}',
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                  const Row(
                    children: [
                      Text(
                        'Rincian Tagihan',
                        style: TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w600),
                      ),
                      Icon(Icons.chevron_right, size: 16, color: AppColors.primary),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
