<?php

namespace App\Http\Controllers\Web;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingWebController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = Booking::with(['property', 'room.roomType', 'user', 'invoices'])
            ->latest();

        // Multi-tenant isolation: Staf & Owner hanya melihat properti yang ditugaskan kepada mereka
        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        // Summary KPI Counts
        $kpiQuery = clone $query;
        $kpis = [
            'total' => (clone $kpiQuery)->count(),
            'pending' => (clone $kpiQuery)->where('status', BookingStatus::PENDING->value)->count(),
            'confirmed' => (clone $kpiQuery)->where('status', BookingStatus::CONFIRMED->value)->count(),
            'active' => (clone $kpiQuery)->where('status', BookingStatus::ACTIVE->value)->count(),
            'completed' => (clone $kpiQuery)->where('status', BookingStatus::COMPLETED->value)->count(),
        ];

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('rental_type')) {
            $query->where('rental_type', $request->rental_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhere('guest_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('room', fn($r) => $r->where('room_number', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->paginate(15)->withQueryString();

        // Data Properti yang diizinkan untuk modal pembuatan booking
        if ($user->isSuperAdmin()) {
            $properties = Property::where('is_active', true)->with(['rooms.roomType'])->get();
            $tenants = User::where('role', 'tenant')->orderBy('name')->get();
        } elseif ($user->isTenant()) {
            $properties = collect();
            $tenants = collect();
        } else {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $properties = Property::whereIn('id', $propertyIds)->where('is_active', true)->with(['rooms.roomType'])->get();
            $tenants = User::where('role', 'tenant')->orderBy('name')->get();
        }

        return view('dashboard.bookings.index', compact('bookings', 'kpis', 'properties', 'tenants'));
    }

    public function show(int $id): View
    {
        $user = auth()->user();
        $booking = $this->findAuthorizedBooking($id, $user);

        if (!$booking) {
            abort(404, 'Pemesanan tidak ditemukan atau Anda tidak memiliki akses ke properti ini.');
        }

        return view('dashboard.bookings.show', compact('booking'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'rental_type' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'customer_type' => ['required', 'in:tenant,guest'],
            'user_id' => ['nullable', 'required_if:customer_type,tenant', 'exists:users,id'],
            'guest_name' => ['nullable', 'required_if:customer_type,guest', 'string', 'max:150'],
            'guest_email' => ['nullable', 'required_if:customer_type,guest', 'email', 'max:150'],
            'guest_phone' => ['nullable', 'required_if:customer_type,guest', 'string', 'max:25'],
            'guest_id_card' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Cek kepemilikan properti kamar untuk staf
        if (!$user->isSuperAdmin() && !$user->isTenant()) {
            $room = Room::findOrFail($validated['room_id']);
            $hasAccess = DB::table('property_users')
                ->where('user_id', $user->id)
                ->where('property_id', $room->property_id)
                ->exists();

            if (!$hasAccess) {
                return back()->with('error', 'Anda tidak memiliki hak akses pada properti kamar yang dipilih.')->withInput();
            }
        }

        try {
            $booking = $this->bookingService->createBooking($validated, $user);
            return redirect()->route('dashboard.bookings.show', $booking->id)
                ->with('success', "Pemesanan berhasil dibuat dengan kode {$booking->booking_code}.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function confirm(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $booking = $this->findAuthorizedBooking($id, $user);

        if (!$booking) {
            abort(404, 'Pemesanan tidak ditemukan.');
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist'])) {
            return back()->with('error', 'Hanya staf berwenang yang dapat mengonfirmasi pemesanan.');
        }

        try {
            $this->bookingService->confirmBooking($booking, $user, $request->input('notes'));
            return back()->with('success', 'Pemesanan berhasil dikonfirmasi. Kamar siap untuk check-in.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $booking = $this->findAuthorizedBooking($id, $user);

        if (!$booking) {
            abort(404, 'Pemesanan tidak ditemukan.');
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist'])) {
            return back()->with('error', 'Hanya staf berwenang yang dapat menolak pemesanan.');
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->bookingService->rejectBooking($booking, $user, $request->input('rejection_reason'));
            return back()->with('success', 'Pemesanan telah ditolak dan tagihan terkait dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $booking = $this->findAuthorizedBooking($id, $user);

        if (!$booking) {
            abort(404, 'Pemesanan tidak ditemukan.');
        }

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->bookingService->cancelBooking($booking, $user, $request->input('cancellation_reason'));
            return back()->with('success', 'Pemesanan berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function checkIn(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $booking = $this->findAuthorizedBooking($id, $user);

        if (!$booking) {
            abort(404, 'Pemesanan tidak ditemukan.');
        }

        if (!$user->canManageFinances() && !$user->isSuperAdmin() && !in_array($user->role, ['manager', 'owner', 'receptionist', 'staff'])) {
            return back()->with('error', 'Hanya staf/petugas yang dapat memproses check-in.');
        }

        try {
            $this->bookingService->checkInBooking($booking, $user);
            return back()->with('success', 'Check-in berhasil diproses! Masa sewa telah aktif dan status kamar diubah menjadi Terisi.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function findAuthorizedBooking(int $id, $user): ?Booking
    {
        $query = Booking::with([
            'property',
            'room.roomType',
            'invoices.payments',
            'user',
            'confirmedByUser',
            'cancelledByUser',
            'checkedInByUser',
        ])->where('id', $id);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        return $query->first();
    }
}
