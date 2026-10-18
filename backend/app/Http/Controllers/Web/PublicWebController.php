<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicWebController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Halaman Katalog Kamar Publik
     */
    public function catalog(Request $request): View
    {
        $query = Room::with(['property', 'roomType'])
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'dirty']);

        // Filter Properti
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        // Filter Kota
        if ($request->filled('city')) {
            $query->whereHas('property', fn($p) => $p->where('city', 'like', "%{$request->city}%"));
        }

        // Filter Tipe Kamar
        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Filter Rentang Harga Bulanan
        if ($request->filled('max_price')) {
            $query->whereHas('roomType', fn($rt) => $rt->where('base_price_monthly', '<=', $request->max_price));
        }

        // Filter Ketersediaan Tanggal
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $rentalType = $request->input('rental_type', 'monthly');

        $rooms = $query->paginate(12)->withQueryString();

        // Cek availability jika tanggal diisi
        if ($startDate && $endDate) {
            foreach ($rooms as $room) {
                $room->is_available_for_period = $this->bookingService->checkRoomAvailability(
                    $room->id,
                    $startDate,
                    $endDate
                );
                $room->calculated_pricing = $this->bookingService->calculatePricing(
                    $room,
                    $rentalType,
                    $startDate,
                    $endDate
                );
            }
        }

        $properties = Property::where('is_active', true)->orderBy('name')->get();
        $roomTypes = RoomType::orderBy('name')->get();
        $cities = Property::where('is_active', true)->whereNotNull('city')->pluck('city')->unique();

        return view('public.catalog', compact('rooms', 'properties', 'roomTypes', 'cities', 'startDate', 'endDate', 'rentalType'));
    }

    /**
     * Halaman Detail Kamar Publik
     */
    public function roomDetail(Request $request, int $id): View
    {
        $room = Room::with(['property', 'roomType'])->where('is_active', true)->findOrFail($id);

        $startDate = $request->input('start_date', now()->addDay()->toDateString());
        $rentalType = $request->input('rental_type', 'monthly');
        $endDate = $request->input('end_date', now()->addMonth()->toDateString());

        $isAvailable = $this->bookingService->checkRoomAvailability($room->id, $startDate, $endDate);
        $pricing = $this->bookingService->calculatePricing($room, $rentalType, $startDate, $endDate);

        return view('public.room_detail', compact('room', 'startDate', 'endDate', 'rentalType', 'isAvailable', 'pricing'));
    }

    /**
     * Form Pengajuan Booking Publik
     */
    public function bookingForm(Request $request): View
    {
        $roomId = $request->input('room_id');
        $room = Room::with(['property', 'roomType'])->findOrFail($roomId);

        $startDate = $request->input('start_date', now()->addDay()->toDateString());
        $rentalType = $request->input('rental_type', 'monthly');
        $endDate = $request->input('end_date', now()->addMonth()->toDateString());

        $pricing = $this->bookingService->calculatePricing($room, $rentalType, $startDate, $endDate);

        return view('public.booking_form', compact('room', 'startDate', 'endDate', 'rentalType', 'pricing'));
    }

    /**
     * Submit Pengajuan Booking Publik
     */
    public function submitBooking(Request $request): RedirectResponse
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
            return redirect()->route('public.booking.track', $booking->access_token)
                ->with('success', "Pemesanan berhasil dibuat! Nomor referensi Anda: {$booking->booking_code}.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Halaman Pelacakan Status Booking Publik (Via Secure Access Token)
     */
    public function trackBooking(string $token): View
    {
        $booking = Booking::with(['property', 'room.roomType', 'invoices.payments'])
            ->where('access_token', $token)
            ->firstOrFail();

        return view('public.booking_status', compact('booking'));
    }
}
