<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Invoice::with(['property', 'booking.room.roomType', 'user', 'payments'])
            ->latest();

        if (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->where(function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds)
                  ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('booking.room', fn ($r) => $r->where('room_number', 'like', "%{$q}%"));
            });
        }

        $invoices = $query->paginate(15)->withQueryString();

        return view('dashboard.invoices.index', compact('invoices'));
    }

    public function show(int $id): View
    {
        $user = Auth::user();

        $query = Invoice::with(['property', 'booking.room.roomType', 'user', 'payments.verifier']);

        if (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->where(function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds)
                  ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        $invoice = $query->findOrFail($id);

        return view('dashboard.invoices.show', compact('invoice'));
    }
}
