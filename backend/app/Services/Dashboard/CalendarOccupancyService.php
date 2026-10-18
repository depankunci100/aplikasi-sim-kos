<?php

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarOccupancyService
{
    /**
     * Nama bulan dalam Bahasa Indonesia
     */
    protected const INDONESIAN_MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Nama hari dalam Bahasa Indonesia (1 = Senin .. 7 = Minggu)
     */
    protected const INDONESIAN_DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * Hitung matriks okupansi harian dan status ketersediaan kamar untuk bulan tertentu
     */
    public function getMonthlyOccupancy(User $user, ?int $propertyId = null, ?string $monthString = null): array
    {
        // 1. Validasi & parsing bulan
        $carbonMonth = now();
        if ($monthString) {
            try {
                $carbonMonth = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
            } catch (\Exception $e) {
                $carbonMonth = now()->startOfMonth();
            }
        } else {
            $carbonMonth = $carbonMonth->startOfMonth();
        }

        $startDate = $carbonMonth->copy()->startOfMonth();
        $endDate = $carbonMonth->copy()->endOfMonth();
        $daysInMonth = $carbonMonth->daysInMonth;

        $monthYearStr = $carbonMonth->format('Y-m');
        $prevMonthStr = $carbonMonth->copy()->subMonth()->format('Y-m');
        $nextMonthStr = $carbonMonth->copy()->addMonth()->format('Y-m');
        $monthLabel = self::INDONESIAN_MONTHS[$carbonMonth->month] . ' ' . $carbonMonth->year;

        // 2. Tentukan batasan properti berdasarkan role user
        $accessiblePropertyIds = null;
        if (!$user->isSuperAdmin()) {
            $accessiblePropertyIds = DB::table('property_users')
                ->where('user_id', $user->id)
                ->pluck('property_id')
                ->toArray();
        }

        if ($propertyId && $accessiblePropertyIds && !in_array($propertyId, $accessiblePropertyIds)) {
            $propertyId = null;
        }

        // 3. Ambil seluruh kamar yang relevan
        $roomQuery = Room::where('is_active', true)
            ->with(['property:id,name,code', 'roomType:id,name,base_price_monthly']);

        if ($accessiblePropertyIds !== null) {
            $roomQuery->whereIn('property_id', $accessiblePropertyIds);
        }

        if ($propertyId) {
            $roomQuery->where('property_id', $propertyId);
        }

        $rooms = $roomQuery->orderBy('property_id')
            ->orderBy('floor')
            ->orderBy('room_number')
            ->get();

        $totalRooms = $rooms->count();

        // 4. Ambil seluruh booking aktif/terkonfirmasi yang bersinggungan dengan bulan ini
        $bookingQuery = Booking::with(['user:id,name,phone,email', 'room:id,room_number,room_type_id', 'property:id,name,code'])
            ->whereIn('status', [
                BookingStatus::CONFIRMED,
                BookingStatus::ACTIVE,
                BookingStatus::OVERSTAY,
            ])
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString());

        if ($accessiblePropertyIds !== null) {
            $bookingQuery->whereIn('property_id', $accessiblePropertyIds);
        }

        if ($propertyId) {
            $bookingQuery->where('property_id', $propertyId);
        }

        $bookings = $bookingQuery->get();

        // 5. Indexing booking ke per kamar untuk pencarian cepat O(1)
        // Grouped by room_id
        $bookingsByRoom = [];
        foreach ($bookings as $b) {
            if ($b->room_id) {
                $bookingsByRoom[$b->room_id][] = $b;
            }
        }

        // 6. Evaluasi okupansi untuk setiap hari dalam bulan
        $days = [];
        $todayDateStr = now()->toDateString();
        $sumOccupied = 0;
        $totalRoomDays = $totalRooms * $daysInMonth;
        $monthCheckoutsCount = 0;
        $monthCheckinsCount = 0;
        $daysFullCount = 0;
        $daysAvailableCount = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDayCarbon = $startDate->copy()->day($day);
            $dateStr = $currentDayCarbon->toDateString();
            $dayOfWeekIso = $currentDayCarbon->dayOfWeekIso; // 1 (Senin) .. 7 (Minggu)

            $occupiedRoomsCount = 0;
            $dayCheckoutsCount = 0;
            $dayCheckinsCount = 0;
            $dayRoomsList = [];

            foreach ($rooms as $room) {
                $roomBookings = $bookingsByRoom[$room->id] ?? [];
                $activeBookingForDay = null;

                foreach ($roomBookings as $bk) {
                    $bStart = $bk->start_date ? $bk->start_date->toDateString() : null;
                    $bEnd = $bk->end_date ? $bk->end_date->toDateString() : null;

                    if ($bStart && $bEnd && $bStart <= $dateStr && $bEnd >= $dateStr) {
                        $activeBookingForDay = $bk;
                        break;
                    }
                }

                if ($activeBookingForDay) {
                    $occupiedRoomsCount++;

                    $isCheckout = ($activeBookingForDay->end_date && $activeBookingForDay->end_date->toDateString() === $dateStr);
                    $isCheckin = ($activeBookingForDay->start_date && $activeBookingForDay->start_date->toDateString() === $dateStr);

                    if ($isCheckout) {
                        $dayCheckoutsCount++;
                        $monthCheckoutsCount++;
                    }
                    if ($isCheckin) {
                        $dayCheckinsCount++;
                        $monthCheckinsCount++;
                    }

                    $roomStatusKey = 'occupied';
                    $roomStatusLabel = 'Terisi (Dihuni)';
                    $statusBadge = 'bg-primary';

                    if ($isCheckout) {
                        $roomStatusKey = 'checkout';
                        $roomStatusLabel = 'Akan Checkout Hari Ini';
                        $statusBadge = 'bg-warning text-dark';
                    } elseif ($isCheckin) {
                        $roomStatusKey = 'checkin';
                        $roomStatusLabel = 'Jadwal Check-in Baru';
                        $statusBadge = 'bg-info text-dark';
                    }

                    $guestName = $activeBookingForDay->guest_name ?? $activeBookingForDay->user?->name ?? 'Tamu Terdaftar';
                    $guestPhone = $activeBookingForDay->guest_phone ?? $activeBookingForDay->user?->phone ?? '-';

                    $dayRoomsList[] = [
                        'room_id' => $room->id,
                        'room_number' => $room->room_number,
                        'room_type' => $room->roomType->name ?? 'Standard',
                        'floor' => $room->floor,
                        'property_name' => $room->property->name ?? '-',
                        'status' => $roomStatusKey,
                        'status_label' => $roomStatusLabel,
                        'status_badge' => $statusBadge,
                        'is_occupied' => true,
                        'is_checkout' => $isCheckout,
                        'is_checkin' => $isCheckin,
                        'guest_name' => $guestName,
                        'guest_phone' => $guestPhone,
                        'booking_code' => $activeBookingForDay->booking_code,
                        'booking_id' => $activeBookingForDay->id,
                        'start_date' => $activeBookingForDay->start_date?->format('d/m/Y'),
                        'end_date' => $activeBookingForDay->end_date?->format('d/m/Y'),
                    ];
                } else {
                    // Kamar tidak ada booking pada tanggal ini
                    $isDirty = ($room->status === RoomStatus::DIRTY);
                    $isMaintenance = ($room->status === RoomStatus::MAINTENANCE);

                    $roomStatusKey = 'available';
                    $roomStatusLabel = 'Slot Kosong (Tersedia)';
                    $statusBadge = 'bg-success';

                    if ($isDirty) {
                        $roomStatusKey = 'dirty';
                        $roomStatusLabel = 'Kotor (Perlu Housekeeping)';
                        $statusBadge = 'bg-secondary';
                    } elseif ($isMaintenance) {
                        $roomStatusKey = 'maintenance';
                        $roomStatusLabel = 'Perbaikan (Maintenance)';
                        $statusBadge = 'bg-danger';
                    }

                    $dayRoomsList[] = [
                        'room_id' => $room->id,
                        'room_number' => $room->room_number,
                        'room_type' => $room->roomType->name ?? 'Standard',
                        'floor' => $room->floor,
                        'property_name' => $room->property->name ?? '-',
                        'status' => $roomStatusKey,
                        'status_label' => $roomStatusLabel,
                        'status_badge' => $statusBadge,
                        'is_occupied' => false,
                        'is_checkout' => false,
                        'is_checkin' => false,
                        'guest_name' => null,
                        'guest_phone' => null,
                        'booking_code' => null,
                        'booking_id' => null,
                        'start_date' => null,
                        'end_date' => null,
                    ];
                }
            }

            $availableRoomsCount = max(0, $totalRooms - $occupiedRoomsCount);
            $occupancyRate = $totalRooms > 0 ? round(($occupiedRoomsCount / $totalRooms) * 100) : 0;

            // Tentukan status ketersediaan slot hari ini
            if ($totalRooms === 0) {
                $daySlotStatus = 'empty';
                $daySlotLabel = 'Belum Ada Kamar';
                $daySlotBadge = 'badge bg-light text-muted border';
            } elseif ($availableRoomsCount === 0) {
                $daySlotStatus = 'full';
                $daySlotLabel = 'Sudah Full (0 Slot)';
                $daySlotBadge = 'badge bg-danger-subtle text-danger border border-danger';
                $daysFullCount++;
            } else {
                $daySlotStatus = 'available';
                $daySlotLabel = "Masih Ada {$availableRoomsCount} Slot";
                $daySlotBadge = 'badge bg-success-subtle text-success border border-success';
                $daysAvailableCount++;
            }

            $sumOccupied += $occupiedRoomsCount;

            $days[$dateStr] = [
                'date' => $dateStr,
                'day_number' => $day,
                'day_name' => self::INDONESIAN_DAYS[$dayOfWeekIso],
                'day_of_week_iso' => $dayOfWeekIso,
                'is_today' => ($dateStr === $todayDateStr),
                'is_past' => ($dateStr < $todayDateStr),
                'total_rooms' => $totalRooms,
                'occupied_count' => $occupiedRoomsCount,
                'available_count' => $availableRoomsCount,
                'occupancy_rate' => $occupancyRate,
                'status' => $daySlotStatus,
                'status_label' => $daySlotLabel,
                'status_badge' => $daySlotBadge,
                'checkouts_count' => $dayCheckoutsCount,
                'checkins_count' => $dayCheckinsCount,
                'rooms' => $dayRoomsList,
            ];
        }

        // 7. Hitung leading & trailing blank cells untuk rendering kalender 7 kolom (Senin - Minggu)
        $firstDayOfWeekIso = $startDate->dayOfWeekIso; // 1 = Senin .. 7 = Minggu
        $leadingBlankDays = $firstDayOfWeekIso - 1;

        $lastDayOfWeekIso = $endDate->dayOfWeekIso;
        $trailingBlankDays = 7 - $lastDayOfWeekIso;

        $averageOccupancy = $totalRoomDays > 0 ? round(($sumOccupied / $totalRoomDays) * 100, 1) : 0;

        return [
            'current_month' => $monthYearStr,
            'month_label' => $monthLabel,
            'prev_month' => $prevMonthStr,
            'next_month' => $nextMonthStr,
            'year' => $carbonMonth->year,
            'month_number' => $carbonMonth->month,
            'total_rooms' => $totalRooms,
            'days_in_month' => $daysInMonth,
            'leading_blank_days' => $leadingBlankDays,
            'trailing_blank_days' => $trailingBlankDays,
            'kpi' => [
                'average_occupancy' => $averageOccupancy,
                'total_room_days' => $totalRoomDays,
                'occupied_room_days' => $sumOccupied,
                'available_room_days' => max(0, $totalRoomDays - $sumOccupied),
                'month_checkouts_count' => $monthCheckoutsCount,
                'month_checkins_count' => $monthCheckinsCount,
                'days_full_count' => $daysFullCount,
                'days_available_count' => $daysAvailableCount,
            ],
            'days' => $days,
        ];
    }
}
