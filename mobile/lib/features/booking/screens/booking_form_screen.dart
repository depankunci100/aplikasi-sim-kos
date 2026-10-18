import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/booking_model.dart';
import '../providers/booking_provider.dart';

class BookingFormScreen extends ConsumerStatefulWidget {
  final int roomId;
  final String propertyName;
  final String roomNumber;
  final String roomTypeName;

  const BookingFormScreen({
    super.key,
    required this.roomId,
    required this.propertyName,
    required this.roomNumber,
    required this.roomTypeName,
  });

  @override
  ConsumerState<BookingFormScreen> createState() => _BookingFormScreenState();
}

class _BookingFormScreenState extends ConsumerState<BookingFormScreen> {
  final _formKey = GlobalKey<FormState>();

  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _idCardController = TextEditingController();
  final _notesController = TextEditingController();

  DateTime _startDate = DateTime.now().add(const Duration(days: 1));
  DateTime _endDate = DateTime.now().add(const Duration(days: 31));
  String _rentalType = 'monthly';
  bool _agreeTerms = true;

  bool _isSubmitting = false;
  bool _isLoadingPrice = false;
  PricingEstimateModel? _pricing;

  @override
  void initState() {
    super.initState();
    _fetchPriceEstimate();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _idCardController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _fetchPriceEstimate() async {
    setState(() => _isLoadingPrice = true);
    try {
      final repo = ref.read(bookingRepositoryProvider);
      final pricing = await repo.calculatePrice(
        roomId: widget.roomId,
        rentalType: _rentalType,
        startDate: _startDate.toIso8601String().split('T').first,
        endDate: _endDate.toIso8601String().split('T').first,
      );
      if (mounted) {
        setState(() {
          _pricing = pricing;
          _isLoadingPrice = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoadingPrice = false);
    }
  }

  Future<void> _selectStartDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _startDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null && picked != _startDate) {
      setState(() {
        _startDate = picked;
        if (_endDate.isBefore(_startDate)) {
          _endDate = _startDate.add(const Duration(days: 30));
        }
      });
      _fetchPriceEstimate();
    }
  }

  Future<void> _selectEndDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _endDate,
      firstDate: _startDate.add(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (picked != null && picked != _endDate) {
      setState(() {
        _endDate = picked;
      });
      _fetchPriceEstimate();
    }
  }

  Future<void> _submitBooking() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_agreeTerms) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Anda harus menyetujui aturan dan kebijakan sewa.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final repo = ref.read(bookingRepositoryProvider);
      final payload = {
        'room_id': widget.roomId,
        'rental_type': _rentalType,
        'start_date': _startDate.toIso8601String().split('T').first,
        'end_date': _endDate.toIso8601String().split('T').first,
        'guest_name': _nameController.text.trim(),
        'guest_phone': _phoneController.text.trim(),
        'guest_email': _emailController.text.trim(),
        'guest_id_card': _idCardController.text.trim().isNotEmpty ? _idCardController.text.trim() : null,
        'notes': _notesController.text.trim().isNotEmpty ? _notesController.text.trim() : null,
        'agree_terms': 1,
      };

      final result = await repo.submitGuestBooking(payload);
      final token = result['access_token'] as String? ?? '';
      final code = result['booking_code'] as String? ?? '';

      if (mounted) {
        setState(() => _isSubmitting = false);
        context.pushReplacement('/guest/track/$token', extra: {
          'booking_code': code,
          'token': token,
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSubmitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengajukan booking: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengajuan Sewa Kamar'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Info Kamar Card
              Card(
                elevation: 0,
                color: AppColors.primary.withAlpha(15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.hotel_rounded, color: Colors.white, size: 28),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.propertyName,
                              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w500),
                            ),
                            Text(
                              'Kamar ${widget.roomNumber}',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                            ),
                            Text(
                              widget.roomTypeName,
                              style: const TextStyle(fontSize: 13, color: AppColors.primary, fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Pemilihan Periode & Model Sewa
              const Text('Periode & Model Sewa', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              DropdownButtonFormField<String>(
                value: _rentalType,
                decoration: const InputDecoration(
                  labelText: 'Model Sewa',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                items: const [
                  DropdownMenuItem(value: 'monthly', child: Text('Bulanan (Kos)')),
                  DropdownMenuItem(value: 'daily', child: Text('Harian (Guesthouse)')),
                  DropdownMenuItem(value: 'weekly', child: Text('Mingguan')),
                  DropdownMenuItem(value: 'yearly', child: Text('Tahunan')),
                ],
                onChanged: (val) {
                  if (val != null) {
                    setState(() => _rentalType = val);
                    _fetchPriceEstimate();
                  }
                },
              ),
              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: _selectStartDate,
                      borderRadius: BorderRadius.circular(10),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Tanggal Masuk',
                          prefixIcon: Icon(Icons.calendar_today_outlined, size: 20),
                        ),
                        child: Text(
                          Formatters.formatDate(_startDate),
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: _selectEndDate,
                      borderRadius: BorderRadius.circular(10),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'Tanggal Keluar',
                          prefixIcon: Icon(Icons.event_outlined, size: 20),
                        ),
                        child: Text(
                          Formatters.formatDate(_endDate),
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Rincian Biaya Box
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.grey.shade300),
                ),
                child: _isLoadingPrice
                    ? const Center(child: Padding(
                        padding: EdgeInsets.all(8.0),
                        child: CircularProgressIndicator(),
                      ))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Durasi Sewa: ${_pricing?.days ?? 0} hari', style: const TextStyle(fontSize: 13)),
                              Text('Sewa: ${Formatters.toRupiah(_pricing?.rateAmount ?? 0)}', style: const TextStyle(fontWeight: FontWeight.w600)),
                            ],
                          ),
                          if ((_pricing?.depositAmount ?? 0) > 0) ...[
                            const SizedBox(height: 4),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Deposit Jaminan:', style: TextStyle(fontSize: 13)),
                                Text(Formatters.toRupiah(_pricing?.depositAmount ?? 0), style: const TextStyle(fontWeight: FontWeight.w600)),
                              ],
                            ),
                          ],
                          const Divider(height: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Total Biaya:', style: TextStyle(fontWeight: FontWeight.bold)),
                              Text(
                                Formatters.toRupiah(_pricing?.totalAmount ?? 0),
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.primary),
                              ),
                            ],
                          ),
                          if (_pricing?.dpRequired == true && (_pricing?.downPaymentAmount ?? 0) > 0) ...[
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              decoration: BoxDecoration(
                                color: Colors.indigo.shade50,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Wajib Uang Muka (DP):', style: TextStyle(fontSize: 12, color: Colors.indigo, fontWeight: FontWeight.w600)),
                                  Text(Formatters.toRupiah(_pricing!.downPaymentAmount), style: const TextStyle(fontSize: 13, color: Colors.indigo, fontWeight: FontWeight.bold)),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
              ),
              const SizedBox(height: 24),

              // Data Calon Tamu
              const Text('Data Calon Tamu / Penghuni', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(
                  labelText: 'Nama Lengkap Sesuai KTP *',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Nama lengkap wajib diisi' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Nomor WhatsApp / HP Aktif *',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Nomor telepon wajib diisi' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Alamat Email *',
                  prefixIcon: Icon(Icons.email_outlined),
                ),
                validator: (val) => (val == null || !val.contains('@')) ? 'Alamat email tidak valid' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _idCardController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nomor NIK / KTP (Opsional)',
                  prefixIcon: Icon(Icons.badge_outlined),
                ),
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _notesController,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Catatan Khusus (Opsional)',
                  prefixIcon: Icon(Icons.notes_outlined),
                ),
              ),
              const SizedBox(height: 16),

              // Agree terms checkbox
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: _agreeTerms,
                onChanged: (val) => setState(() => _agreeTerms = val ?? false),
                title: const Text(
                  'Saya menyetujui aturan kos dan kebijakan pembayaran sewa.',
                  style: TextStyle(fontSize: 13),
                ),
                controlAffinity: ListTileControlAffinity.leading,
              ),
              const SizedBox(height: 20),

              // Submit Button
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submitBooking,
                  child: _isSubmitting
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Text('Ajukan Pemesanan Sekarang'),
                ),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}
