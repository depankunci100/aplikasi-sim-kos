import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/invoice_model.dart';
import '../providers/invoice_provider.dart';

class PaymentSubmitScreen extends ConsumerStatefulWidget {
  final int invoiceId;
  final InvoiceModel? invoice;

  const PaymentSubmitScreen({
    super.key,
    required this.invoiceId,
    this.invoice,
  });

  @override
  ConsumerState<PaymentSubmitScreen> createState() => _PaymentSubmitScreenState();
}

class _PaymentSubmitScreenState extends ConsumerState<PaymentSubmitScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _senderNameController = TextEditingController();
  final _senderBankController = TextEditingController();

  String _selectedProvider = 'manual_bca';
  bool _hasProofAttached = true;
  final String _proofFilename = 'bukti_transfer.png';

  // Sample valid PNG bytes (1x1 pixel) for mobile demonstration and test isolation
  static const List<int> _samplePngBytes = [
    0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0x00, 0x00, 0x00, 0x0D,
    0x49, 0x48, 0x44, 0x52, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
    0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4, 0x89, 0x00, 0x00, 0x00,
    0x0A, 0x49, 0x44, 0x41, 0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
    0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00, 0x00, 0x00, 0x00, 0x49,
    0x45, 0x4E, 0x44, 0xAE, 0x42, 0x60, 0x82,
  ];

  final Map<String, Map<String, String>> _providers = {
    'manual_bca': {
      'title': 'Bank Central Asia (BCA)',
      'rekening': '8830-192-881 a.n SIM Kos Graha',
      'bank': 'BCA',
    },
    'manual_mandiri': {
      'title': 'Bank Mandiri',
      'rekening': '137-00-9921-002 a.n SIM Kos Graha',
      'bank': 'Mandiri',
    },
    'manual_bri': {
      'title': 'Bank BRI',
      'rekening': '0129-01-00821-50 a.n SIM Kos Graha',
      'bank': 'BRI',
    },
    'manual_gopay': {
      'title': 'GoPay / QRIS Graha',
      'rekening': '0812-3456-7890 (QRIS SIM Kos)',
      'bank': 'GoPay',
    },
  };

  @override
  void initState() {
    super.initState();
    final remaining = widget.invoice?.remainingBalance ?? 0;
    _amountController.text = remaining > 0 ? remaining.toStringAsFixed(0) : '';
    _senderBankController.text = 'BCA';
  }

  @override
  void dispose() {
    _amountController.dispose();
    _senderNameController.dispose();
    _senderBankController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (!_hasProofAttached) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Silakan lampirkan foto/file bukti transfer!'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    final senderName = _senderNameController.text.trim();
    final senderBank = _senderBankController.text.trim();

    final success = await ref.read(paymentSubmissionProvider.notifier).submitPayment(
          invoiceId: widget.invoiceId,
          amount: amount,
          paymentMethod: 'manual_transfer',
          paymentProvider: _selectedProvider,
          senderName: senderName,
          senderBank: senderBank,
          proofBytes: _samplePngBytes,
          proofFilename: _proofFilename,
        );

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Bukti transfer berhasil diajukan! Menunggu verifikasi Kasir.'),
          backgroundColor: AppColors.success,
        ),
      );
      // Kembali ke detail tagihan
      context.pop();
    } else {
      final error = ref.read(paymentSubmissionProvider).errorMessage;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error ?? 'Gagal mengajukan pembayaran.'),
          backgroundColor: AppColors.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final submissionState = ref.watch(paymentSubmissionProvider);
    final invoice = widget.invoice;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kirim Bukti Pembayaran'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Ringkasan Tagihan
              if (invoice != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withAlpha(20),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.primary.withAlpha(50)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        invoice.invoiceNumber,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Total Wajib Bayar: ${Formatters.toRupiah(invoice.remainingBalance)}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
              ],

              // Pilihan Tujuan Rekening Transfer
              Text('Pilih Rekening Tujuan Kos', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 10),
              ..._providers.entries.map((entry) {
                final isSelected = _selectedProvider == entry.key;
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.primary.withAlpha(15) : Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isSelected ? AppColors.primary : AppColors.border,
                      width: isSelected ? 1.5 : 1.0,
                    ),
                  ),
                  child: RadioListTile<String>(
                    value: entry.key,
                    groupValue: _selectedProvider,
                    activeColor: AppColors.primary,
                    title: Text(entry.value['title']!, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    subtitle: Text(entry.value['rekening']!, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    onChanged: (val) {
                      if (val != null) {
                        setState(() {
                          _selectedProvider = val;
                          _senderBankController.text = _providers[val]?['bank'] ?? 'Bank';
                        });
                      }
                    },
                  ),
                );
              }),
              const SizedBox(height: 20),

              // Form Input
              Text('Data Pengirim & Nominal', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 10),

              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nominal Transfer (Rp)',
                  prefixIcon: Icon(Icons.money_rounded),
                  hintText: 'Contoh: 1500000',
                ),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) return 'Nominal wajib diisi';
                  final numVal = double.tryParse(val.trim());
                  if (numVal == null || numVal <= 0) return 'Nominal harus lebih dari 0';
                  if (invoice != null && numVal > invoice.remainingBalance) {
                    return 'Nominal melebihi sisa saldo (${Formatters.toRupiah(invoice.remainingBalance)})';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 14),

              TextFormField(
                controller: _senderNameController,
                decoration: const InputDecoration(
                  labelText: 'Nama Pemilik Rekening Pengirim',
                  prefixIcon: Icon(Icons.person_outline_rounded),
                  hintText: 'Contoh: Rizky Pratama',
                ),
                validator: (val) =>
                    val == null || val.trim().isEmpty ? 'Nama pengirim wajib diisi' : null,
              ),
              const SizedBox(height: 14),

              TextFormField(
                controller: _senderBankController,
                decoration: const InputDecoration(
                  labelText: 'Bank / E-Wallet Pengirim',
                  prefixIcon: Icon(Icons.account_balance_rounded),
                  hintText: 'Contoh: BCA / Mandiri / GoPay',
                ),
                validator: (val) =>
                    val == null || val.trim().isEmpty ? 'Bank pengirim wajib diisi' : null,
              ),
              const SizedBox(height: 20),

              // Lampiran Bukti Transfer
              Text('Lampiran Bukti Transfer', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.surfaceMuted,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.receipt_long_rounded, color: AppColors.primary, size: 28),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _hasProofAttached ? _proofFilename : 'Belum ada bukti dipilih',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              Text(
                                _hasProofAttached ? 'Format PNG/JPG terlampir (Siap diunggah)' : 'Wajib melampirkan bukti',
                                style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                        Switch(
                          value: _hasProofAttached,
                          activeColor: AppColors.primary,
                          onChanged: (val) {
                            setState(() {
                              _hasProofAttached = val;
                            });
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Penyewa wajib melampirkan screenshot mutasi atau resi transfer fisik yang sah. Kasir akan memverifikasi ke rekening koran operasional.',
                      style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 32),

              // Tombol Kirim
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  icon: submissionState.isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Icon(Icons.send_rounded),
                  label: Text(
                    submissionState.isLoading ? 'Mengirim Bukti...' : 'Ajukan Bukti Transfer',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                  onPressed: submissionState.isLoading ? null : _submit,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
