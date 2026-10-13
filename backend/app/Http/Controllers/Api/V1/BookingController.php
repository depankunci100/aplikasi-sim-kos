<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
use App\Enums\RoomStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Booking::with(['property', 'room.roomType', 'invoices'])
            ->latest();

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        }

        $bookings = $query->paginate(15);

        return $this->successResponse($bookings, 'Daftar pemesanan berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'rental_type' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $room = Room::with('roomType')->findOrFail($validated['room_id']);

        if (!$room->isAvailable()) {
            return $this->errorResponse('Kamar ini sedang tidak tersedia untuk dipesan.', 422);
        }

        // Anti-double-booking check
        $hasOverlap = Booking::where('room_id', $room->id)
            ->whereIn('status', [BookingStatus::CONFIRMED->value, BookingStatus::ACTIVE->value])
            ->where(function ($q) use ($validated) {
                $q->where('start_date', '<', $validated['end_date'])
                  ->where('end_date', '>', $validated['start_date']);
            })
            ->exists();

        if ($hasOverlap) {
            return $this->errorResponse('Kamar sudah dipesan pada rentang tanggal tersebut.', 422);
        }

        $booking = DB::transaction(function () use ($validated, $room, $request) {
            $rentalType = RentalType::from($validated['rental_type']);
            $rate = match ($rentalType) {
                RentalType::DAILY, RentalType::WEEKLY => $room->roomType->base_price_daily,
                default => $room->roomType->base_price_monthly,
            };

            $deposit = $rentalType->isShortTerm() ? 0 : 500000.00;
            $total = $rate + $deposit;

            $booking = Booking::create([
                'booking_code' => 'BK-' . strtoupper(Str::random(8)),
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'user_id' => $request->user()->id,
                'rental_type' => $rentalType,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'rate_amount' => $rate,
                'deposit_amount' => $deposit,
                'total_amount' => $total,
                'status' => BookingStatus::PENDING,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Terbitkan invoice pertama (biaya sewa + deposit)
            Invoice::create([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'booking_id' => $booking->id,
                'user_id' => $request->user()->id,
                'invoice_type' => 'rent',
                'period_start' => $validated['start_date'],
                'period_end' => $validated['end_date'],
                'subtotal' => $rate,
                'discount' => 0,
                'penalty' => 0,
                'total_amount' => $total,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'due_date' => now()->addDays(2)->toDateString(),
            ]);

            return $booking;
        });

        return $this->successResponse($booking->load(['room.roomType', 'invoices']), 'Pemesanan berhasil dibuat.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with(['property', 'room.roomType', 'invoices.payments']);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        }

        $booking = $query->find($id);

        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan.', 404);
        }

        return $this->successResponse($booking, 'Detail pemesanan berhasil diambil.');
    }
}
