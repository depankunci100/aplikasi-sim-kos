<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
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
        } elseif (!$user->isSuperAdmin()) {
            // Multi-tenant isolation: Owner dan Staf hanya melihat properti yang ditugaskan kepada mereka
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
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

        try {
            $booking = DB::transaction(function () use ($validated, $request) {
                // Kunci baris room secara pesimistik untuk serialisasi request konkuren
                $room = Room::with('roomType')
                    ->where('id', $validated['room_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$room->isAvailable()) {
                    throw new \DomainException('Kamar ini sedang tidak tersedia untuk dipesan.');
                }

                // Cek anti-double booking di dalam transaksi yang terkunci
                $hasOverlap = Booking::where('room_id', $room->id)
                    ->whereIn('status', [
                        BookingStatus::CONFIRMED->value,
                        BookingStatus::ACTIVE->value,
                    ])
                    ->where(function ($q) use ($validated) {
                        $q->where('start_date', '<', $validated['end_date'])
                          ->where('end_date', '>', $validated['start_date']);
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($hasOverlap) {
                    throw new \DomainException('Kamar sudah dipesan pada rentang tanggal tersebut.');
                }

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
                    'property_id' => $room->property_id,
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
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($booking->load(['room.roomType', 'invoices']), 'Pemesanan berhasil dibuat.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with(['property', 'room.roomType', 'invoices.payments']);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        $booking = $query->find($id);

        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan.', 404);
        }

        return $this->successResponse($booking, 'Detail pemesanan berhasil diambil.');
    }
}
