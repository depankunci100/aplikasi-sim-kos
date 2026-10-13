<?php

namespace App\Http\Controllers\Web;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Tentukan properti yang dapat diakses oleh user ini
        $accessiblePropertyIds = null;
        if (!$user->isSuperAdmin()) {
            $accessiblePropertyIds = DB::table('property_users')
                ->where('user_id', $user->id)
                ->pluck('property_id')
                ->toArray();
        }

        $propertyFilter = $request->query('property_id');
        if ($propertyFilter && $accessiblePropertyIds && !in_array($propertyFilter, $accessiblePropertyIds)) {
            $propertyFilter = null; // Abaikan jika tidak punya akses ke properti tersebut
        }

        // Query Builder dasar terisolasi
        $propertyQuery = Property::where('is_active', true);
        $roomQuery = Room::where('is_active', true);
        $bookingQuery = Booking::query();
        $invoiceQuery = Invoice::query();
        $paymentQuery = Payment::query();

        if ($accessiblePropertyIds !== null) {
            $propertyQuery->whereIn('id', $accessiblePropertyIds);
            $roomQuery->whereIn('property_id', $accessiblePropertyIds);
            $bookingQuery->whereIn('property_id', $accessiblePropertyIds);
            $invoiceQuery->whereIn('property_id', $accessiblePropertyIds);
            $paymentQuery->whereHas('invoice', fn ($inv) => $inv->whereIn('property_id', $accessiblePropertyIds));
        }

        if ($propertyFilter) {
            $propertyQuery->where('id', $propertyFilter);
            $roomQuery->where('property_id', $propertyFilter);
            $bookingQuery->where('property_id', $propertyFilter);
            $invoiceQuery->where('property_id', $propertyFilter);
            $paymentQuery->whereHas('invoice', fn ($inv) => $inv->where('property_id', $propertyFilter));
        }

        // Statistik Kamar
        $roomStats = [
            'total' => (clone $roomQuery)->count(),
            'available' => (clone $roomQuery)->where('status', RoomStatus::AVAILABLE)->count(),
            'occupied' => (clone $roomQuery)->where('status', RoomStatus::OCCUPIED)->count(),
            'dirty' => (clone $roomQuery)->where('status', RoomStatus::DIRTY)->count(),
            'maintenance' => (clone $roomQuery)->where('status', RoomStatus::MAINTENANCE)->count(),
        ];

        // Statistik Finansial & Operasional
        $kpi = [
            'total_properties' => (clone $propertyQuery)->count(),
            'active_bookings' => (clone $bookingQuery)->where('status', BookingStatus::ACTIVE)->count(),
            'pending_bookings' => (clone $bookingQuery)->where('status', BookingStatus::PENDING)->count(),
            'unpaid_invoices_count' => (clone $invoiceQuery)->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])->count(),
            'pending_payments_count' => (clone $paymentQuery)->where('status', 'pending')->count(),
            'total_revenue_verified' => (clone $paymentQuery)->where('status', 'verified')->sum('amount'),
            'total_unpaid_amount' => (clone $invoiceQuery)->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])->sum(DB::raw('total_amount - paid_amount')),
        ];

        // Antrean pembayaran terbaru yang butuh verifikasi
        $pendingPayments = (clone $paymentQuery)
            ->where('status', 'pending')
            ->with(['invoice.property', 'user'])
            ->latest()
            ->take(8)
            ->get();

        // Booking terbaru
        $recentBookings = (clone $bookingQuery)
            ->with(['property', 'room.roomType', 'user'])
            ->latest()
            ->take(8)
            ->get();

        // Kamar untuk status staf
        $rooms = (clone $roomQuery)
            ->with(['property', 'roomType'])
            ->orderBy('property_id')
            ->orderBy('floor')
            ->orderBy('room_number')
            ->paginate(20);

        $propertiesList = (clone $propertyQuery)->get(['id', 'name', 'code']);

        return view('dashboard.index', compact(
            'user',
            'roomStats',
            'kpi',
            'pendingPayments',
            'recentBookings',
            'rooms',
            'propertiesList',
            'propertyFilter'
        ));
    }
}
