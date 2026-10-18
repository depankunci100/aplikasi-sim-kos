<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use App\Models\Room;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends ApiController
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Booking::with(['property', 'room.roomType', 'invoices', 'user'])
            ->latest();

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            // Multi-tenant isolation: Staf dan Owner hanya melihat properti yang ditugaskan kepada mereka
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter property
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        // Search by booking code, guest name, or room number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhere('guest_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('room', fn($r) => $r->where('room_number', 'like', "%{$search}%"));
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $bookings = $query->paginate($perPage);

        return $this->successResponse($bookings, 'Daftar pemesanan berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'rental_type' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'user_id' => ['nullable', 'exists:users,id'],
            'guest_name' => ['nullable', 'string', 'max:150'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'guest_phone' => ['nullable', 'string', 'max:25'],
            'guest_id_card' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Cek multi-property isolation untuk staf/manager jika membuat booking internal
        if (!$user->isTenant() && !$user->isSuperAdmin()) {
            $room = Room::findOrFail($validated['room_id']);
            $hasAccess = DB::table('property_users')
                ->where('user_id', $user->id)
                ->where('property_id', $room->property_id)
                ->exists();

            if (!$hasAccess) {
                return $this->errorResponse('Anda tidak memiliki akses ke properti kamar ini.', 403);
            }
        }

        try {
            $booking = $this->bookingService->createBooking($validated, $user);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($booking->load(['room.roomType', 'invoices']), 'Pemesanan berhasil dibuat.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with([
            'property',
            'room.roomType',
            'invoices.payments',
            'user',
            'confirmedByUser',
            'cancelledByUser',
            'checkedInByUser',
        ]);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        $booking = $query->find($id);

        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau Anda tidak memiliki akses.', 404);
        }

        return $this->successResponse($booking, 'Detail pemesanan berhasil diambil.');
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $booking = $this->findAuthorizedBooking($id, $user);
        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau Anda tidak memiliki hak akses.', 404);
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist'])) {
            return $this->errorResponse('Hanya staf/manajemen berwenang yang dapat mengonfirmasi pemesanan.', 403);
        }

        try {
            $confirmedBooking = $this->bookingService->confirmBooking($booking, $user, $request->input('notes'));
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($confirmedBooking, 'Pemesanan berhasil dikonfirmasi.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $booking = $this->findAuthorizedBooking($id, $user);
        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau Anda tidak memiliki hak akses.', 404);
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist'])) {
            return $this->errorResponse('Hanya staf/manajemen berwenang yang dapat menolak pemesanan.', 403);
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rejectedBooking = $this->bookingService->rejectBooking($booking, $user, $request->input('rejection_reason'));
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($rejectedBooking, 'Pemesanan berhasil ditolak.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $booking = $this->findAuthorizedBooking($id, $user);
        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau Anda tidak memiliki hak akses.', 404);
        }

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $cancelledBooking = $this->bookingService->cancelBooking($booking, $user, $request->input('cancellation_reason'));
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($cancelledBooking, 'Pemesanan berhasil dibatalkan.');
    }

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $booking = $this->findAuthorizedBooking($id, $user);
        if (!$booking) {
            return $this->errorResponse('Pemesanan tidak ditemukan atau Anda tidak memiliki hak akses.', 404);
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist', 'staff'])) {
            return $this->errorResponse('Hanya staf/manajemen yang dapat memproses check-in.', 403);
        }

        try {
            $checkedInBooking = $this->bookingService->checkInBooking($booking, $user);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($checkedInBooking, 'Check-in berhasil diproses. Masa sewa telah aktif dan kamar terisi.');
    }

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

    protected function findAuthorizedBooking(int $id, $user): ?Booking
    {
        $query = Booking::where('id', $id);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        return $query->first();
    }
}
