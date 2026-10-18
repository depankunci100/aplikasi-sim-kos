<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Memeriksa ketersediaan kamar pada rentang tanggal tertentu secara komprehensif.
     * Mengabaikan booking pending yang telah expired.
     */
    public function checkRoomAvailability(int $roomId, string $startDate, string $endDate, ?int $ignoreBookingId = null): bool
    {
        $room = Room::find($roomId);
        if (!$room || !$room->is_active || in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::DIRTY])) {
            return false;
        }

        $now = now();

        return !Booking::where('room_id', $roomId)
            ->when($ignoreBookingId, fn($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where(function ($q) use ($now) {
                // Booking yang aktif atau sudah terkonfirmasi memblokir tanggal
                $q->whereIn('status', [
                    BookingStatus::CONFIRMED->value,
                    BookingStatus::ACTIVE->value,
                ])
                // Booking pending hanya memblokir jika masa penahanan (hold) belum kedaluwarsa
                ->orWhere(function ($sub) use ($now) {
                    $sub->where('status', BookingStatus::PENDING->value)
                        ->where(function ($exp) use ($now) {
                            $exp->whereNull('expires_at')
                                ->orWhere('expires_at', '>', $now);
                        });
                });
            })
            ->where(function ($q) use ($startDate, $endDate) {
                // Formula overlap: start_date < requested_end_date AND end_date > requested_start_date
                $q->where('start_date', '<', $endDate)
                  ->where('end_date', '>', $startDate);
            })
            ->exists();
    }

    /**
     * Menghitung estimasi biaya sewa, uang jaminan (deposit), dan uang muka (down payment)
     * Menggunakan backend sebagai satu-satunya single source of truth.
     */
    public function calculatePricing(Room $room, string $rentalTypeStr, string $startDateStr, string $endDateStr): array
    {
        $room->loadMissing(['property', 'roomType']);
        $property = $room->property;
        $roomType = $room->roomType;

        $startDate = Carbon::parse($startDateStr)->startOfDay();
        $endDate = Carbon::parse($endDateStr)->startOfDay();

        $days = max(1, $startDate->diffInDays($endDate));
        $rentalType = RentalType::tryFrom($rentalTypeStr) ?? RentalType::MONTHLY;

        // Perhitungan biaya sewa berdasarkan durasi & tipe rental
        $rateAmount = 0.0;
        if ($rentalType === RentalType::DAILY) {
            $rateAmount = (float) $roomType->base_price_daily * $days;
        } elseif ($rentalType === RentalType::WEEKLY) {
            $weeks = max(1, ceil($days / 7));
            $weeklyRate = $roomType->base_price_daily > 0 ? ($roomType->base_price_daily * 7 * 0.95) : ($roomType->base_price_monthly / 4);
            $rateAmount = (float) $weeklyRate * $weeks;
        } else {
            // Bulanan / Tahunan / Quarterly
            $months = max(1, ceil($days / 30));
            if ($rentalType === RentalType::YEARLY && $roomType->base_price_yearly > 0) {
                $years = max(1, ceil($days / 365));
                $rateAmount = (float) $roomType->base_price_yearly * $years;
            } else {
                $rateAmount = (float) $roomType->base_price_monthly * $months;
            }
        }

        // Uang jaminan (deposit) terpisah dari sewa
        $depositAmount = 0.0;
        if (!$rentalType->isShortTerm()) {
            $depositAmount = (float) ($property->deposit_amount > 0 ? $property->deposit_amount : 500000.00);
        }

        $totalAmount = $rateAmount + $depositAmount;

        // Perhitungan kebijakan uang muka (DP) per properti
        $dpAmount = $property->calculateDownPayment($rateAmount, $depositAmount);

        return [
            'days' => $days,
            'rental_type' => $rentalType->value,
            'rate_amount' => round($rateAmount, 2),
            'deposit_amount' => round($depositAmount, 2),
            'down_payment_amount' => round($dpAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'dp_required' => (bool) $property->dp_required,
            'dp_type' => $property->dp_type ?? 'fixed',
            'payment_deadline_hours' => (int) ($property->payment_deadline_hours ?? 24),
        ];
    }

    /**
     * Membuat booking baru (mendukung guest publik maupun internal staff / penyewa).
     * Menerapkan pessimistic concurrency locking pada room dan overlapping checks.
     */
    public function createBooking(array $payload, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($payload, $actor) {
            // Lock room baris secara pesimistik untuk serialisasi request konkuren
            $room = Room::with(['property', 'roomType'])
                ->where('id', $payload['room_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!$room->is_active) {
                throw ValidationException::withMessages([
                    'room_id' => 'Kamar ini sedang non-aktif dan tidak dapat dipesan.',
                ]);
            }

            if (in_array($room->status, [RoomStatus::MAINTENANCE, RoomStatus::DIRTY])) {
                throw ValidationException::withMessages([
                    'room_id' => 'Kamar sedang dalam pemeliharaan atau pembersihan.',
                ]);
            }

            $startDate = $payload['start_date'];
            $endDate = $payload['end_date'];
            $now = now();

            // Cek konflik booking dengan pessimistic lock pada tabel bookings
            $hasConflict = Booking::where('room_id', $room->id)
                ->where(function ($q) use ($now) {
                    $q->whereIn('status', [
                        BookingStatus::CONFIRMED->value,
                        BookingStatus::ACTIVE->value,
                    ])
                    ->orWhere(function ($sub) use ($now) {
                        $sub->where('status', BookingStatus::PENDING->value)
                            ->where(function ($exp) use ($now) {
                                $exp->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', $now);
                            });
                    });
                })
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<', $endDate)
                      ->where('end_date', '>', $startDate);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'room_id' => 'Kamar sudah terisi atau memiliki pemesanan aktif pada rentang tanggal yang dipilih.',
                ]);
            }

            // Hitung harga dari backend (single source of truth)
            $pricing = $this->calculatePricing($room, $payload['rental_type'], $startDate, $endDate);

            $deadlineHours = $room->property->payment_deadline_hours ?: 24;
            $expiresAt = now()->addHours($deadlineHours);

            // Tentukan user_id atau guest attributes
            $userId = null;
            $guestName = null;
            $guestEmail = null;
            $guestPhone = null;
            $guestIdCard = null;

            if ($actor && ($actor->isTenant() || (isset($payload['is_tenant_self']) && $payload['is_tenant_self']))) {
                $userId = $actor->id;
            } elseif (!empty($payload['user_id'])) {
                $userId = $payload['user_id'];
            } else {
                $guestName = $payload['guest_name'] ?? ($actor ? $actor->name : 'Tamu');
                $guestEmail = $payload['guest_email'] ?? ($actor ? $actor->email : null);
                $guestPhone = $payload['guest_phone'] ?? ($actor ? $actor->phone : null);
                $guestIdCard = $payload['guest_id_card'] ?? null;
            }

            // Generate cryptographically secure token & booking code
            $accessToken = Str::random(60);
            $bookingCode = 'BK-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'user_id' => $userId,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'guest_phone' => $guestPhone,
                'guest_id_card' => $guestIdCard,
                'access_token' => $accessToken,
                'rental_type' => $payload['rental_type'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'rate_amount' => $pricing['rate_amount'],
                'deposit_amount' => $pricing['deposit_amount'],
                'down_payment_amount' => $pricing['down_payment_amount'],
                'total_amount' => $pricing['total_amount'],
                'status' => BookingStatus::PENDING,
                'notes' => $payload['notes'] ?? null,
                'expires_at' => $expiresAt,
            ]);

            // Terbitkan invoice awal untuk booking ini
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'property_id' => $room->property_id,
                'booking_id' => $booking->id,
                'user_id' => $userId,
                'invoice_type' => 'rent',
                'period_start' => $startDate,
                'period_end' => $endDate,
                'subtotal' => $pricing['rate_amount'],
                'discount' => 0,
                'penalty' => 0,
                'total_amount' => $pricing['total_amount'],
                'paid_amount' => 0,
                'status' => 'unpaid',
                'due_date' => $expiresAt->toDateString(),
            ]);

            return $booking->load(['property', 'room.roomType', 'invoices']);
        });
    }

    /**
     * Konfirmasi pemesanan oleh staf/admin atau setelah verifikasi pembayaran.
     */
    public function confirmBooking(Booking $booking, User $actor, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $notes) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                throw new \DomainException("Pemesanan tidak dapat dikonfirmasi karena status saat ini adalah {$lockedBooking->status->label()}.");
            }

            // Validasi ulang ketersediaan sebelum konfirmasi final
            $room = Room::where('id', $lockedBooking->room_id)->lockForUpdate()->firstOrFail();
            $hasOverlap = Booking::where('room_id', $room->id)
                ->where('id', '!=', $lockedBooking->id)
                ->whereIn('status', [BookingStatus::CONFIRMED->value, BookingStatus::ACTIVE->value])
                ->where(function ($q) use ($lockedBooking) {
                    $q->where('start_date', '<', $lockedBooking->end_date)
                      ->where('end_date', '>', $lockedBooking->start_date);
                })
                ->exists();

            if ($hasOverlap) {
                throw new \DomainException('Tidak dapat mengonfirmasi: Kamar sudah memiliki pemesanan terkonfirmasi pada tanggal yang sama.');
            }

            $lockedBooking->status = BookingStatus::CONFIRMED;
            $lockedBooking->confirmed_by = $actor->id;
            if ($notes) {
                $lockedBooking->notes = ($lockedBooking->notes ? $lockedBooking->notes . " | " : "") . $notes;
            }
            $lockedBooking->save();

            return $lockedBooking->fresh(['property', 'room.roomType', 'invoices.payments', 'confirmedByUser']);
        });
    }

    /**
     * Menolak permohonan booking dengan mencatat alasan penolakan.
     */
    public function rejectBooking(Booking $booking, User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::PENDING) {
                throw new \DomainException("Hanya pemesanan berstatus Pending yang dapat ditolak.");
            }

            if (empty(trim($reason))) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Alasan penolakan wajib diisi.',
                ]);
            }

            $lockedBooking->status = BookingStatus::REJECTED;
            $lockedBooking->rejection_reason = trim($reason);
            $lockedBooking->confirmed_by = $actor->id;
            $lockedBooking->save();

            // Batalkan invoice terkait yang belum dibayar
            $lockedBooking->invoices()->where('status', 'unpaid')->update(['status' => 'cancelled']);

            return $lockedBooking->fresh(['property', 'room.roomType', 'invoices']);
        });
    }

    /**
     * Membatalkan booking dengan mencatat alasan pembatalan.
     */
    public function cancelBooking(Booking $booking, ?User $actor, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedBooking->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED])) {
                throw new \DomainException("Pemesanan dengan status {$lockedBooking->status->label()} tidak dapat dibatalkan.");
            }

            if (empty(trim($reason))) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'Alasan pembatalan wajib diisi.',
                ]);
            }

            $lockedBooking->status = BookingStatus::CANCELLED;
            $lockedBooking->cancellation_reason = trim($reason);
            if ($actor) {
                $lockedBooking->cancelled_by = $actor->id;
            }
            $lockedBooking->save();

            // Batalkan invoice yang belum terbayar
            $lockedBooking->invoices()->where('status', 'unpaid')->update(['status' => 'cancelled']);

            return $lockedBooking->fresh(['property', 'room.roomType', 'invoices', 'cancelledByUser']);
        });
    }

    /**
     * Memproses Check-In penyewa untuk mengaktifkan masa sewa.
     * Mengubah status booking menjadi ACTIVE dan status kamar menjadi OCCUPIED.
     */
    public function checkInBooking(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::CONFIRMED) {
                throw new \DomainException("Check-in hanya dapat diproses untuk pemesanan yang telah Terkonfirmasi (CONFIRMED). Status saat ini: {$lockedBooking->status->label()}.");
            }

            $room = Room::where('id', $lockedBooking->room_id)->lockForUpdate()->firstOrFail();

            // Cek apakah kamar sedang aktif dihuni oleh booking lain
            $hasActiveResident = Booking::where('room_id', $room->id)
                ->where('id', '!=', $lockedBooking->id)
                ->where('status', BookingStatus::ACTIVE->value)
                ->exists();

            if ($hasActiveResident) {
                throw new \DomainException("Kamar saat ini masih dihuni oleh penyewa lain yang belum check-out.");
            }

            $lockedBooking->status = BookingStatus::ACTIVE;
            $lockedBooking->actual_check_in = now();
            $lockedBooking->checked_in_by = $actor->id;
            $lockedBooking->save();

            // Perbarui status kamar menjadi terisi (occupied)
            $room->status = RoomStatus::OCCUPIED;
            $room->save();

            return $lockedBooking->fresh(['property', 'room.roomType', 'invoices.payments', 'checkedInByUser']);
        });
    }

    /**
     * Mengubah status booking pending yang melewati batas masa penahanan (expired hold)
     */
    public function purgeExpiredBookings(?int $propertyId = null): int
    {
        $query = Booking::where('status', BookingStatus::PENDING->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $expiredBookings = $query->get();
        $count = 0;

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                $booking->status = BookingStatus::EXPIRED;
                $booking->cancellation_reason = 'Waktu pembayaran uang muka atau konfirmasi pemesanan telah kedaluwarsa.';
                $booking->save();

                $booking->invoices()->where('status', 'unpaid')->update(['status' => 'cancelled']);
            });
            $count++;
        }

        return $count;
    }
}
