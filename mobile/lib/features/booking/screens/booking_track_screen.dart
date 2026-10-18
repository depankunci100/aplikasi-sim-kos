import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/booking_model.dart';
import '../providers/booking_provider.dart';

class BookingTrackScreen extends ConsumerStatefulWidget {
  final String token;
  final String? initialBookingCode;

  const BookingTrackScreen({
    super.key,
    required this.token,
    this.initialBookingCode,
  });

  @override
  ConsumerState<BookingTrackScreen> createState() => _BookingTrackScreenState();
}

class _BookingTrackScreenState extends ConsumerState<BookingTrackScreen> {
  BookingModel? _booking;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadBooking();
  }

  Future<void> _loadBooking() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final repo = ref.read(bookingRepositoryProvider);
      final booking = await repo.trackBooking(widget.token);
      if (mounted) {
        setState(() {
          _booking = booking;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Gagal memuat status: $e';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Status Pemesanan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadBooking,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: Colors.red),
                        const SizedBox(height: 12),
                        Text(_errorMessage!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadBooking,
                          child: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  ),
                )
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Status Header Banner
                      Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        color: _booking?.badgeColor.withAlpha(25) ?? AppColors.primary.withAlpha(25),
                        child: Padding(
                          padding: const EdgeInsets.all(20.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Kode Referensi:', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: _booking?.badgeColor ?? AppColors.primary,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      _booking?.statusLabel ?? 'Pending',
                                      style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  Text(
                                    _booking?.bookingCode ?? widget.initialBookingCode ?? 'BK-NEW',
                                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                                  ),
                                  const SizedBox(width: 8),
                                  IconButton(
                                    icon: const Icon(Icons.copy, size: 18),
                                    onPressed: () {
                                      final code = _booking?.bookingCode ?? widget.initialBookingCode ?? '';
                                      Clipboard.setData(ClipboardData(text: code));
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(content: Text('Kode booking disalin!')),
                                      );
                                    },
                                    tooltip: 'Salin Kode',
                                  ),
                                ],
                              ),
                              const Divider(height: 24),
                              Text(
                                _booking?.isPending == true
                                    ? 'Pemesanan Anda sedang ditinjau. Mohon lakukan pembayaran uang muka jika berlaku sebelum batas waktu penahanan berakhir.'
                                    : _booking?.isConfirmed == true
                                        ? 'Pemesanan telah terkonfirmasi. Kamar siap ditempati saat tanggal check-in.'
                                        : 'Status pemesanan terkini telah diperbarui dalam sistem.',
                                style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Kamar & Properti
                      const Text('Detail Kamar & Lokasi', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _booking?.propertyName ?? 'Properti Kos',
                                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                              if (_booking?.propertyAddress != null) ...[
                                const SizedBox(height: 4),
                                Text(_booking!.propertyAddress!, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                              ],
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  const Icon(Icons.door_front_door_outlined, size: 18, color: AppColors.primary),
                                  const SizedBox(width: 6),
                                  Text(
                                    'Kamar ${_booking?.roomNumber ?? "-"} (${_booking?.roomTypeName ?? "-"})',
                                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  const Icon(Icons.date_range_outlined, size: 18, color: AppColors.primary),
                                  const SizedBox(width: 6),
                                  Text(
                                    '${_booking?.startDate != null ? Formatters.formatDate(_booking!.startDate!) : "-"} s/d ${_booking?.endDate != null ? Formatters.formatDate(_booking!.endDate!) : "-"}',
                                    style: const TextStyle(fontSize: 13),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Rincian Biaya
                      const Text('Rincian Biaya & Tagihan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Biaya Sewa Kamar', style: TextStyle(fontSize: 13)),
                                  Text(Formatters.toRupiah(_booking?.rateAmount ?? 0), style: const TextStyle(fontWeight: FontWeight.w600)),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Uang Jaminan (Deposit)', style: TextStyle(fontSize: 13)),
                                  Text(Formatters.toRupiah(_booking?.depositAmount ?? 0), style: const TextStyle(fontWeight: FontWeight.w600)),
                                ],
                              ),
                              const Divider(height: 20),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Total Biaya', style: TextStyle(fontWeight: FontWeight.bold)),
                                  Text(
                                    Formatters.toRupiah(_booking?.totalAmount ?? 0),
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.primary),
                                  ),
                                ],
                              ),
                              if ((_booking?.downPaymentAmount ?? 0) > 0) ...[
                                const SizedBox(height: 10),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Kewajiban Uang Muka (DP)', style: TextStyle(color: Colors.indigo, fontWeight: FontWeight.w600)),
                                    Text(
                                      Formatters.toRupiah(_booking!.downPaymentAmount),
                                      style: const TextStyle(color: Colors.indigo, fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Back to Catalog / Home Button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: OutlinedButton.icon(
                          onPressed: () => context.go('/home'),
                          icon: const Icon(Icons.home_outlined),
                          label: const Text('Kembali ke Halaman Utama'),
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],
                  ),
                ),
    );
  }
}
