import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/booking_model.dart';
import '../providers/booking_provider.dart';

class BookingDetailScreen extends ConsumerStatefulWidget {
  final int bookingId;
  final BookingModel? initialBooking;

  const BookingDetailScreen({
    super.key,
    required this.bookingId,
    this.initialBooking,
  });

  @override
  ConsumerState<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends ConsumerState<BookingDetailScreen> {
  late BookingModel _booking;
  bool _isLoadingAction = false;

  @override
  void initState() {
    super.initState();
    _booking = widget.initialBooking ??
        BookingModel(
          id: widget.bookingId,
          bookingCode: 'BK-${widget.bookingId}',
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
          roomTypeName: 'Deluxe',
          customerName: 'Penyewa',
        );
  }

  Future<void> _handleConfirm() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Pemesanan'),
        content: const Text('Apakah Anda yakin ingin mengonfirmasi pemesanan ini? Kamar akan dikunci untuk masa sewa yang dipilih.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Konfirmasi')),
        ],
      ),
    );

    if (confirm == true) {
      setState(() => _isLoadingAction = true);
      try {
        final repo = ref.read(bookingRepositoryProvider);
        await repo.confirmBooking(_booking.id);
        ref.invalidate(bookingListProvider);
        if (mounted) {
          setState(() {
            _isLoadingAction = false;
            _booking = BookingModel(
              id: _booking.id,
              bookingCode: _booking.bookingCode,
              propertyId: _booking.propertyId,
              roomId: _booking.roomId,
              rentalType: _booking.rentalType,
              startDate: _booking.startDate,
              endDate: _booking.endDate,
              rateAmount: _booking.rateAmount,
              depositAmount: _booking.depositAmount,
              downPaymentAmount: _booking.downPaymentAmount,
              totalAmount: _booking.totalAmount,
              status: 'confirmed',
              propertyName: _booking.propertyName,
              roomNumber: _booking.roomNumber,
              roomTypeName: _booking.roomTypeName,
              customerName: _booking.customerName,
            );
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Pemesanan berhasil dikonfirmasi!')),
          );
        }
      } catch (e) {
        if (mounted) {
          setState(() => _isLoadingAction = false);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Gagal konfirmasi: $e'), backgroundColor: Colors.red),
          );
        }
      }
    }
  }

  Future<void> _handleReject() async {
    final reasonController = TextEditingController();
    final reject = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Tolak Pemesanan', style: TextStyle(color: Colors.red)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Alasan penolakan wajib diisi:', style: TextStyle(fontSize: 13)),
            const SizedBox(height: 8),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(hintText: 'Contoh: Kuota kamar penuh...'),
              maxLines: 2,
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Tolak'),
          ),
        ],
      ),
    );

    if (reject == true && reasonController.text.trim().isNotEmpty) {
      setState(() => _isLoadingAction = true);
      try {
        final repo = ref.read(bookingRepositoryProvider);
        await repo.rejectBooking(_booking.id, reason: reasonController.text.trim());
        ref.invalidate(bookingListProvider);
        if (mounted) {
          setState(() {
            _isLoadingAction = false;
            _booking = BookingModel(
              id: _booking.id,
              bookingCode: _booking.bookingCode,
              propertyId: _booking.propertyId,
              roomId: _booking.roomId,
              rentalType: _booking.rentalType,
              startDate: _booking.startDate,
              endDate: _booking.endDate,
              rateAmount: _booking.rateAmount,
              depositAmount: _booking.depositAmount,
              downPaymentAmount: _booking.downPaymentAmount,
              totalAmount: _booking.totalAmount,
              status: 'rejected',
              rejectionReason: reasonController.text.trim(),
              propertyName: _booking.propertyName,
              roomNumber: _booking.roomNumber,
              roomTypeName: _booking.roomTypeName,
              customerName: _booking.customerName,
            );
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Pemesanan telah ditolak')),
          );
        }
      } catch (e) {
        if (mounted) {
          setState(() => _isLoadingAction = false);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Gagal menolak: $e'), backgroundColor: Colors.red),
          );
        }
      }
    }
  }

  Future<void> _handleCheckIn() async {
    final checkIn = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Proses Check-In'),
        content: Text('Konfirmasi check-in untuk ${_booking.customerName} di Kamar ${_booking.roomNumber}? Status kamar akan otomatis diperbarui menjadi Terisi (Occupied).'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Check-In Sekarang'),
          ),
        ],
      ),
    );

    if (checkIn == true) {
      setState(() => _isLoadingAction = true);
      try {
        final repo = ref.read(bookingRepositoryProvider);
        await repo.checkInBooking(_booking.id);
        ref.invalidate(bookingListProvider);
        if (mounted) {
          setState(() {
            _isLoadingAction = false;
            _booking = BookingModel(
              id: _booking.id,
              bookingCode: _booking.bookingCode,
              propertyId: _booking.propertyId,
              roomId: _booking.roomId,
              rentalType: _booking.rentalType,
              startDate: _booking.startDate,
              endDate: _booking.endDate,
              actualCheckIn: DateTime.now(),
              rateAmount: _booking.rateAmount,
              depositAmount: _booking.depositAmount,
              downPaymentAmount: _booking.downPaymentAmount,
              totalAmount: _booking.totalAmount,
              status: 'active',
              propertyName: _booking.propertyName,
              roomNumber: _booking.roomNumber,
              roomTypeName: _booking.roomTypeName,
              customerName: _booking.customerName,
            );
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Check-in berhasil diproses! Masa sewa telah aktif.')),
          );
        }
      } catch (e) {
        if (mounted) {
          setState(() => _isLoadingAction = false);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Gagal check-in: $e'), backgroundColor: Colors.red),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_booking.bookingCode),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status Header
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: _booking.badgeColor.withAlpha(50)),
              ),
              color: _booking.badgeColor.withAlpha(20),
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  children: [
                    Icon(Icons.info_outline, color: _booking.badgeColor, size: 28),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Status Pemesanan', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                          Text(
                            _booking.statusLabel,
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: _booking.badgeColor),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Data Calon Penghuni
            const Text('Calon Penghuni', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
              child: Padding(
                padding: const EdgeInsets.all(14.0),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Nama:', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                        Text(_booking.customerName, style: const TextStyle(fontWeight: FontWeight.bold)),
                      ],
                    ),
                    if (_booking.guestPhone != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Telepon:', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                          Text(_booking.guestPhone!, style: const TextStyle(fontWeight: FontWeight.w500)),
                        ],
                      ),
                    ],
                    if (_booking.guestEmail != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Email:', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                          Text(_booking.guestEmail!, style: const TextStyle(fontWeight: FontWeight.w500)),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Properti & Kamar
            const Text('Properti & Periode', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
              child: Padding(
                padding: const EdgeInsets.all(14.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_booking.propertyName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    const SizedBox(height: 4),
                    Text('Kamar ${_booking.roomNumber} (${_booking.roomTypeName})', style: const TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                    const Divider(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Jadwal Sewa:', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                        Text(
                          '${_booking.startDate != null ? Formatters.formatDate(_booking.startDate!) : "-"} s/d ${_booking.endDate != null ? Formatters.formatDate(_booking.endDate!) : "-"}',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Rincian Biaya
            const Text('Rincian Biaya', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade300)),
              child: Padding(
                padding: const EdgeInsets.all(14.0),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Sewa Kamar:', style: TextStyle(fontSize: 13)),
                        Text(Formatters.toRupiah(_booking.rateAmount), style: const TextStyle(fontWeight: FontWeight.w500)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Deposit Jaminan:', style: TextStyle(fontSize: 13)),
                        Text(Formatters.toRupiah(_booking.depositAmount), style: const TextStyle(fontWeight: FontWeight.w500)),
                      ],
                    ),
                    const Divider(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total:', style: TextStyle(fontWeight: FontWeight.bold)),
                        Text(Formatters.toRupiah(_booking.totalAmount), style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primary, fontSize: 15)),
                      ],
                    ),
                    if (_booking.downPaymentAmount > 0) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Uang Muka (DP):', style: TextStyle(color: Colors.indigo, fontSize: 13, fontWeight: FontWeight.w600)),
                          Text(Formatters.toRupiah(_booking.downPaymentAmount), style: const TextStyle(color: Colors.indigo, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Action Buttons Toolbar for Staff
            if (_isLoadingAction)
              const Center(child: CircularProgressIndicator())
            else ...[
              if (_booking.canConfirm) ...[
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _handleConfirm,
                        icon: const Icon(Icons.check_circle_outline),
                        label: const Text('Konfirmasi'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                        onPressed: _handleReject,
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('Tolak'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
              ],
              if (_booking.canCheckIn) ...[
                SizedBox(
                  width: double.infinity,
                  height: 46,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                    onPressed: _handleCheckIn,
                    icon: const Icon(Icons.login),
                    label: const Text('Proses Check-In'),
                  ),
                ),
                const SizedBox(height: 10),
              ],
            ],
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
