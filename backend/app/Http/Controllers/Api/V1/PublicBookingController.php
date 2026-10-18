<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicBookingController extends ApiController
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Katalog properti publik yang aktif
     */
    public function properties(Request $request): JsonResponse
    {
        $query = Property::where('is_active', true)
            ->with(['roomTypes'])
            ->withCount(['rooms as available_rooms_count' => function ($q) {
                $q->where('is_active', true)->where('status', 'available');
            }]);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        $properties = $query->paginate(10);

        return $this->successResponse($properties, 'Katalog properti publik berhasil diambil.');
    }

    /**
     * Pencarian ketersediaan kamar berdasarkan rentang tanggal dan filter
     */
    public function searchAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'rental_type' => ['nullable', 'in:daily,weekly,monthly,quarterly,yearly'],
        ]);

        $query = Room::with(['property', 'roomType'])
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'dirty']);

        if (!empty($validated['property_id'])) {
            $query->where('property_id', $validated['property_id']);
        }

        if (!empty($validated['room_type_id'])) {
            $query->where('room_type_id', $validated['room_type_id']);
        }

        $allRooms = $query->get();

        $availableRooms = $allRooms->filter(function ($room) use ($validated) {
            return $this->bookingService->checkRoomAvailability(
                $room->id,
                $validated['start_date'],
                $validated['end_date']
            );
        })->values();

        // Sertakan kalkulasi harga estimasi untuk setiap kamar yang tersedia
        $rentalType = $validated['rental_type'] ?? 'monthly';
        $result = $availableRooms->map(function ($room) use ($rentalType, $validated) {
            $pricing = $this->bookingService->calculatePricing(
                $room,
                $rentalType,
                $validated['start_date'],
                $validated['end_date']
            );

            return [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'floor' => $room->floor,
                'property' => [
                    'id' => $room->property->id,
                    'name' => $room->property->name,
                    'type' => $room->property->type,
                    'city' => $room->property->city,
                    'address' => $room->property->address,
                    'dp_required' => (bool) $room->property->dp_required,
                ],
                'room_type' => [
                    'id' => $room->roomType->id,
                    'name' => $room->roomType->name,
                    'description' => $room->roomType->description,
                    'facilities' => $room->roomType->facilities,
                    'max_occupants' => $room->roomType->max_occupants,
                    'size_m2' => $room->roomType->size_m2,
                ],
                'pricing' => $pricing,
            ];
        });

        return $this->successResponse([
            'total_available' => $result->count(),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'rental_type' => $rentalType,
            'rooms' => $result,
        ], 'Ketersediaan kamar berhasil dicek.');
    }

    /**
     * Hitung estimasi biaya pemesanan (kalkulasi server-side)
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'rental_type' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $room = Room::with(['property', 'roomType'])->findOrFail($validated['room_id']);

        $pricing = $this->bookingService->calculatePricing(
            $room,
            $validated['rental_type'],
            $validated['start_date'],
            $validated['end_date']
        );

        $isAvailable = $this->bookingService->checkRoomAvailability(
            $room->id,
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->successResponse([
            'is_available' => $isAvailable,
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'property_name' => $room->property->name,
            'pricing' => $pricing,
        ], 'Kalkulasi harga berhasil diambil.');
    }

    /**
     * Submit permohonan booking oleh guest / publik (Rate Limited)
     */
    public function storeGuestBooking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'rental_type' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'guest_name' => ['required', 'string', 'max:150'],
            'guest_email' => ['required', 'email', 'max:150'],
            'guest_phone' => ['required', 'string', 'max:25'],
            'guest_id_card' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'agree_terms' => ['accepted'],
        ]);

        try {
            $booking = $this->bookingService->createBooking($validated);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse([
            'booking_code' => $booking->booking_code,
            'access_token' => $booking->access_token,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'rate_amount' => $booking->rate_amount,
            'deposit_amount' => $booking->deposit_amount,
            'down_payment_amount' => $booking->down_payment_amount,
            'total_amount' => $booking->total_amount,
            'property' => [
                'name' => $booking->property->name,
                'phone' => $booking->property->phone,
                'address' => $booking->property->address,
            ],
            'room' => [
                'room_number' => $booking->room->room_number,
                'type' => $booking->room->roomType->name,
            ],
            'invoice' => $booking->invoices->first() ? [
                'invoice_number' => $booking->invoices->first()->invoice_number,
                'total_amount' => $booking->invoices->first()->total_amount,
                'status' => $booking->invoices->first()->status->value,
                'due_date' => $booking->invoices->first()->due_date?->toDateString(),
            ] : null,
            'tracking_url' => url('/booking/track/' . $booking->access_token),
        ], 'Pemesanan Anda berhasil diajukan. Simpan nomor booking dan tautan pelacakan Anda.', 201);
    }

    /**
     * Pelacakan status pemesanan secara publik menggunakan access token yang aman
     * Mencegah enumerasi dan IDOR.
     */
    public function trackBooking(string $token): JsonResponse
    {
        $booking = Booking::with(['property', 'room.roomType', 'invoices.payments'])
            ->where('access_token', $token)
            ->first();

        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau token tidak valid.', 404);
        }

        // Sanitasi output publik agar tidak membocorkan data pribadi atau invoice internal lain
        $firstInvoice = $booking->invoices->first();

        return $this->successResponse([
            'booking_code' => $booking->booking_code,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'badge_class' => $booking->status->badgeClass(),
            'start_date' => $booking->start_date->toDateString(),
            'end_date' => $booking->end_date->toDateString(),
            'rental_type' => $booking->rental_type->value,
            'guest_name' => $booking->guest_name,
            'rate_amount' => $booking->rate_amount,
            'deposit_amount' => $booking->deposit_amount,
            'down_payment_amount' => $booking->down_payment_amount,
            'total_amount' => $booking->total_amount,
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'is_expired' => $booking->isExpired(),
            'rejection_reason' => $booking->rejection_reason,
            'cancellation_reason' => $booking->cancellation_reason,
            'property' => [
                'name' => $booking->property->name,
                'address' => $booking->property->address,
                'city' => $booking->property->city,
                'phone' => $booking->property->phone,
            ],
            'room' => [
                'room_number' => $booking->room->room_number,
                'room_type' => $booking->room->roomType->name,
            ],
            'invoice' => $firstInvoice ? [
                'invoice_number' => $firstInvoice->invoice_number,
                'total_amount' => $firstInvoice->total_amount,
                'paid_amount' => $firstInvoice->paid_amount,
                'status' => $firstInvoice->status->value,
                'status_label' => $firstInvoice->status->label(),
                'due_date' => $firstInvoice->due_date?->toDateString(),
                'payments' => $firstInvoice->payments->map(function ($p) {
                    return [
                        'amount' => $p->amount,
                        'payment_method' => $p->payment_method,
                        'status' => $p->status,
                        'paid_at' => $p->created_at->toIso8601String(),
                    ];
                }),
            ] : null,
        ], 'Status pemesanan berhasil diambil.');
    }
}
