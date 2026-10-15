<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Enums\UtilityReadingStatus;
use App\Enums\UtilityType;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use App\Models\UtilityTariff;
use App\Services\Utility\UtilityService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UtilityWebController extends Controller
{
    public function __construct(
        protected UtilityService $utilityService
    ) {}

    // ==========================================
    // 1. METER MANAGEMENT
    // ==========================================

    public function indexMeters(Request $request): View
    {
        $user = Auth::user();
        $filters = $request->only(['property_id', 'room_id', 'utility_type', 'meter_type', 'is_active', 'search']);

        $meters = $this->utilityService->getMetersQuery($user, $filters)->paginate(15)->withQueryString();

        $propertiesQuery = Property::where('is_active', true);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $propertiesQuery->whereIn('id', $accessible ?? []);
        }
        $properties = $propertiesQuery->orderBy('name')->get();

        return view('dashboard.utilities.meters.index', compact('meters', 'properties', 'filters'));
    }

    public function createMeter(): View
    {
        $user = Auth::user();
        if ($user->isTenant()) {
            abort(403, 'Akses ditolak.');
        }

        $propertiesQuery = Property::where('is_active', true);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $propertiesQuery->whereIn('id', $accessible ?? []);
        }
        $properties = $propertiesQuery->with('rooms')->orderBy('name')->get();

        return view('dashboard.utilities.meters.create', compact('properties'));
    }

    public function storeMeter(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'utility_type' => 'required|in:electricity,water',
            'meter_number' => 'required|string|max:100|unique:utility_meters,meter_number',
            'meter_type' => 'nullable|in:individual,shared',
            'unit' => 'nullable|string|max:20',
            'initial_reading' => 'nullable|numeric|min:0',
            'max_reading' => 'nullable|numeric|min:0',
            'installed_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $meter = $this->utilityService->createMeter($validated, $user);
            return redirect()->route('dashboard.utilities.meters.show', $meter->id)
                ->with('success', "Meter {$meter->meter_number} berhasil didaftarkan.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showMeter(int $id): View
    {
        $user = Auth::user();
        $meter = UtilityMeter::with(['property', 'room.roomType', 'readings' => fn ($q) => $q->latest('reading_date')->with(['recorder', 'reviewer', 'invoice'])])
            ->findOrFail($id);

        $this->utilityService->authorizePropertyAccess($user, $meter->property_id);

        $effectiveTariff = $this->utilityService->getEffectiveTariff($meter->property_id, $meter->utility_type, now());

        return view('dashboard.utilities.meters.show', compact('meter', 'effectiveTariff'));
    }

    public function editMeter(int $id): View
    {
        $user = Auth::user();
        $meter = UtilityMeter::findOrFail($id);
        $this->utilityService->authorizePropertyAccess($user, $meter->property_id);

        $rooms = Room::where('property_id', $meter->property_id)->orderBy('room_number')->get();

        return view('dashboard.utilities.meters.edit', compact('meter', 'rooms'));
    }

    public function updateMeter(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $meter = UtilityMeter::findOrFail($id);

        $validated = $request->validate([
            'room_id' => 'nullable|exists:rooms,id',
            'meter_number' => "required|string|max:100|unique:utility_meters,meter_number,{$id}",
            'unit' => 'required|string|max:20',
            'max_reading' => 'nullable|numeric|min:0',
            'installed_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->utilityService->updateMeter($meter, $validated, $user);
            return redirect()->route('dashboard.utilities.meters.show', $meter->id)
                ->with('success', 'Konfigurasi meter berhasil diperbarui.');
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // 2. TARIFF MANAGEMENT
    // ==========================================

    public function indexTariffs(Request $request): View
    {
        $user = Auth::user();
        $filters = $request->only(['property_id', 'utility_type', 'is_active']);

        $tariffs = $this->utilityService->getTariffsQuery($user, $filters)->paginate(15)->withQueryString();

        $propertiesQuery = Property::where('is_active', true);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $propertiesQuery->whereIn('id', $accessible ?? []);
        }
        $properties = $propertiesQuery->orderBy('name')->get();

        return view('dashboard.utilities.tariffs.index', compact('tariffs', 'properties', 'filters'));
    }

    public function storeTariff(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'utility_type' => 'required|in:electricity,water',
            'rate_per_unit' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:20',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->utilityService->createTariff($validated, $user);
            return redirect()->route('dashboard.utilities.tariffs.index')
                ->with('success', 'Tarif utilitas baru berhasil dikonfigurasi dan disimpan.');
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // 3. READINGS, REVIEW & BILLING
    // ==========================================

    public function indexReadings(Request $request): View
    {
        $user = Auth::user();
        $filters = $request->only(['meter_id', 'property_id', 'room_id', 'utility_type', 'status', 'is_billed']);

        $readings = $this->utilityService->getReadingsQuery($user, $filters)->paginate(15)->withQueryString();

        $propertiesQuery = Property::where('is_active', true);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $propertiesQuery->whereIn('id', $accessible ?? []);
        }
        $properties = $propertiesQuery->orderBy('name')->get();

        // Hitung pembacaan yang menunggu review untuk badge notifikasi
        $pendingCountQuery = UtilityReading::where('status', UtilityReadingStatus::PENDING_REVIEW);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $pendingCountQuery->whereIn('property_id', $accessible ?? []);
        }
        $pendingCount = $pendingCountQuery->count();

        return view('dashboard.utilities.readings.index', compact('readings', 'properties', 'filters', 'pendingCount'));
    }

    public function createReading(Request $request): View
    {
        $user = Auth::user();
        if ($user->isTenant()) {
            abort(403, 'Akses ditolak.');
        }

        $meterId = $request->query('meter_id');
        $selectedMeter = null;
        if ($meterId) {
            $selectedMeter = UtilityMeter::with(['property', 'room', 'latestReading'])->find($meterId);
            if ($selectedMeter) {
                $this->utilityService->authorizePropertyAccess($user, $selectedMeter->property_id);
            }
        }

        $metersQuery = UtilityMeter::with(['property', 'room'])->where('is_active', true);
        if (!$user->isSuperAdmin()) {
            $accessible = $this->utilityService->getAccessiblePropertyIds($user);
            $metersQuery->whereIn('property_id', $accessible ?? []);
        }
        $meters = $metersQuery->orderBy('meter_number')->get();

        return view('dashboard.utilities.readings.create', compact('meters', 'selectedMeter'));
    }

    public function storeReading(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'meter_id' => 'required|exists:utility_meters,id',
            'current_meter' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'proof_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'status' => 'nullable|in:pending_review,approved',
            'notes' => 'nullable|string',
        ]);

        try {
            $reading = $this->utilityService->recordReading(
                $validated,
                $request->file('proof_photo'),
                $user
            );

            return redirect()->route('dashboard.utilities.readings.show', $reading->id)
                ->with('success', 'Pembacaan meter berhasil dicatat dengan pemakaian ' . $reading->usage . ' ' . ($reading->meter->unit ?? ''));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showReading(int $id): View
    {
        $user = Auth::user();
        $reading = UtilityReading::with(['meter', 'property', 'room.roomType', 'booking.user', 'invoice', 'recorder', 'reviewer', 'logs.user'])
            ->findOrFail($id);

        $this->utilityService->authorizePropertyAccess($user, $reading->property_id);

        $preview = $this->utilityService->previewBill($id, $user);

        // Ambil invoice aktif yang belum lunas jika kasir ingin melampirkan ke invoice sewa
        $eligibleInvoices = [];
        if ($reading->booking_id) {
            $eligibleInvoices = Invoice::where('booking_id', $reading->booking_id)
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->get();
        }

        return view('dashboard.utilities.readings.show', compact('reading', 'preview', 'eligibleInvoices'));
    }

    public function reviewReading(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $reading = UtilityReading::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->utilityService->reviewReading(
                $reading,
                $validated['status'],
                $validated['rejection_reason'] ?? null,
                $user
            );

            $msg = $validated['status'] === 'approved' ? 'Pembacaan meter berhasil disetujui.' : 'Pembacaan meter berhasil ditolak.';
            return back()->with('success', $msg);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function correctReading(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $reading = UtilityReading::findOrFail($id);

        $validated = $request->validate([
            'current_meter' => 'required|numeric|min:0',
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $this->utilityService->correctReading(
                $reading,
                (float) $validated['current_meter'],
                $validated['reason'],
                $user
            );

            return back()->with('success', 'Koreksi nilai pembacaan berhasil disimpan ke dalam audit trail.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function generateInvoice(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'target_invoice_id' => 'nullable|exists:invoices,id',
        ]);

        try {
            $invoice = $this->utilityService->generateUtilityInvoice(
                $id,
                $user,
                $validated['target_invoice_id'] ? (int) $validated['target_invoice_id'] : null
            );

            return redirect()->route('dashboard.invoices.show', $invoice->id)
                ->with('success', "Tagihan utilitas berhasil diterbitkan pada Invoice #{$invoice->invoice_number}.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function streamProof(int $id): BinaryFileResponse|RedirectResponse
    {
        $user = Auth::user();
        $reading = UtilityReading::findOrFail($id);

        try {
            $path = $this->utilityService->getAuthorizedProofPhotoPath($reading, $user);
            return response()->file($path, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }
}
