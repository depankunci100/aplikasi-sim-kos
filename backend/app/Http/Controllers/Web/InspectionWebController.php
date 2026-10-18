<?php

namespace App\Http\Controllers\Web;

use App\Enums\InspectionItemCondition;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionMedia;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Inspection\InspectionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InspectionWebController extends Controller
{
    public function __construct(
        protected InspectionService $inspectionService
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $query = Inspection::with(['property', 'room.roomType', 'inspector', 'tenant'])->latest('scheduled_date');

        if (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        $statusFilter = $request->query('status');
        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $typeFilter = $request->query('type');
        if (!empty($typeFilter) && $typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }

        $propertyFilter = $request->query('property_id');
        if (!empty($propertyFilter)) {
            $query->where('property_id', $propertyFilter);
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('inspection_code', 'like', "%{$q}%")
                    ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$q}%"))
                    ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', "%{$q}%"));
            });
        }

        $inspections = $query->paginate(15)->withQueryString();

        // Data filter properti
        $properties = $user->isSuperAdmin()
            ? Property::where('is_active', true)->get()
            : Property::whereIn('id', DB::table('property_users')->where('user_id', $user->id)->pluck('property_id'))->get();

        return view('dashboard.inspections.index', compact('inspections', 'properties', 'statusFilter', 'typeFilter', 'propertyFilter'));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();

        $properties = $user->isSuperAdmin()
            ? Property::with('rooms')->where('is_active', true)->get()
            : Property::with('rooms')->whereIn('id', DB::table('property_users')->where('user_id', $user->id)->pluck('property_id'))->get();

        $staffUsers = User::whereIn('role', ['manager', 'staff', 'receptionist'])
            ->where('is_active', true)
            ->get();

        $selectedRoomId = $request->query('room_id');
        $selectedBookingId = $request->query('booking_id');

        return view('dashboard.inspections.create', compact('properties', 'staffUsers', 'selectedRoomId', 'selectedBookingId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'inspector_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'in:check_in,periodic,check_out,maintenance'],
            'scheduled_date' => ['required', 'date'],
            'inspector_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$user->isSuperAdmin()) {
            $hasAccess = DB::table('property_users')
                ->where('property_id', $validated['property_id'])
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'manager'])
                ->exists();

            if (!$hasAccess) {
                abort(403, 'Anda tidak memiliki hak untuk menjadwalkan inspeksi di properti ini.');
            }
        }

        try {
            $inspection = $this->inspectionService->createInspection($validated, $user);

            return redirect()->route('dashboard.inspections.show', $inspection->id)
                ->with('success', "Jadwal inspeksi {$inspection->inspection_code} berhasil dibuat.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $user = Auth::user();
        $inspection = Inspection::with([
            'property',
            'room.roomType',
            'booking',
            'inspector',
            'tenant',
            'items.media',
            'media',
            'logs.user',
            'baselineInspection',
        ])->findOrFail($id);

        if (!$this->inspectionService->canAccessInspection($inspection, $user)) {
            abort(403, 'Akses inspeksi kamar ditolak.');
        }

        // Inspeksi sebelumnya untuk kamar yang sama (potensial baseline)
        $previousInspections = Inspection::where('room_id', $inspection->room_id)
            ->where('id', '!=', $inspection->id)
            ->whereIn('status', [InspectionStatus::COMPLETED, InspectionStatus::APPROVED_BY_TENANT])
            ->latest('scheduled_date')
            ->get();

        $propertyStaff = $inspection->property->users()
            ->whereIn('property_users.role', ['staff', 'manager', 'owner', 'receptionist'])
            ->get();
        if ($propertyStaff->isEmpty()) {
            $propertyStaff = User::whereIn('role', ['manager', 'staff', 'receptionist'])->where('is_active', true)->get();
        }

        return view('dashboard.inspections.show', compact('inspection', 'previousInspections', 'propertyStaff'));
    }

    public function start(int $id): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            abort(403, 'Anda tidak berwenang memulai inspeksi ini.');
        }

        try {
            $this->inspectionService->startInspection($inspection, $user);
            return back()->with('success', 'Inspeksi kamar telah dimulai. Silakan periksa checklist item.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            abort(403, 'Anda tidak berwenang mengubah checklist inspeksi ini.');
        }

        $item = InspectionItem::where('inspection_id', $inspection->id)->findOrFail($itemId);

        $validated = $request->validate([
            'condition' => ['required', 'in:good,fair,poor,damaged,missing'],
            'notes' => ['nullable', 'string', 'max:500'],
            'repair_required' => ['nullable', 'boolean'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['repair_required'] = $request->has('repair_required');

        try {
            $this->inspectionService->updateItem($item, $validated, $user);
            return back()->with('success', "Item {$item->item_name} berhasil diperbarui.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function uploadMedia(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            abort(403, 'Anda tidak berwenang mengunggah foto untuk inspeksi ini.');
        }

        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'inspection_item_id' => ['nullable', 'exists:inspection_items,id'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->inspectionService->addMedia(
                $inspection,
                $request->file('photo'),
                $validated['inspection_item_id'] ?? null,
                $validated['caption'] ?? null,
                $user
            );

            return back()->with('success', 'Foto bukti inspeksi berhasil dilampirkan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function streamMedia(int $id, int $mediaId): BinaryFileResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        if (!$this->inspectionService->canAccessInspection($inspection, $user)) {
            abort(403, 'Akses ke foto bukti ditolak.');
        }

        $media = InspectionMedia::where('inspection_id', $inspection->id)->findOrFail($mediaId);

        if (!Storage::disk('local')->exists($media->file_path)) {
            abort(404, 'File foto bukti tidak ditemukan.');
        }

        $fullPath = Storage::disk('local')->path($media->file_path);
        $mimeType = Storage::disk('local')->mimeType($media->file_path) ?? 'image/jpeg';

        $fileResponse = response()->file($fullPath, [
            'Content-Type' => $mimeType,
        ]);
        $fileResponse->setPrivate();
        $fileResponse->headers->addCacheControlDirective('no-cache', true);

        return $fileResponse;
    }

    public function complete(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            abort(403, 'Anda tidak berwenang menyelesaikan inspeksi ini.');
        }

        $validated = $request->validate([
            'overall_condition' => ['required', 'in:good,fair,poor'],
            'inspector_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->inspectionService->completeInspection($inspection, $validated, $user);
            return back()->with('success', 'Laporan inspeksi selesai dan diteruskan ke penyewa untuk verifikasi.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function compare(Request $request, int $id): View
    {
        $user = Auth::user();
        $current = Inspection::with(['items', 'room.roomType', 'property'])->findOrFail($id);

        if (!$this->inspectionService->canAccessInspection($current, $user)) {
            abort(403, 'Akses inspeksi kamar ditolak.');
        }

        $baselineId = $request->query('baseline_id', $current->baseline_inspection_id);

        $baseline = null;
        $comparison = null;

        if ($baselineId) {
            $baseline = Inspection::with(['items', 'inspector'])->find($baselineId);
            if ($baseline) {
                if (!$this->inspectionService->canAccessInspection($baseline, $user) || $baseline->room_id !== $current->room_id) {
                    abort(403, 'Akses ke data baseline inspeksi ditolak atau kamar tidak sesuai.');
                }
                $comparison = $this->inspectionService->compareInspections($current, $baseline);
            }
        }

        $availableBaselines = Inspection::where('room_id', $current->room_id)
            ->where('id', '!=', $current->id)
            ->whereIn('status', [InspectionStatus::COMPLETED, InspectionStatus::APPROVED_BY_TENANT])
            ->latest('scheduled_date')
            ->get();

        return view('dashboard.inspections.compare', compact('current', 'baseline', 'comparison', 'availableBaselines'));
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        $request->validate([
            'inspector_id' => 'required|exists:users,id',
        ]);

        try {
            $this->inspectionService->assignInspector($inspection, (int) $request->inspector_id, $user);
            return back()->with('success', 'Inspektor berhasil ditugaskan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reschedule(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $inspection = Inspection::findOrFail($id);

        $request->validate([
            'scheduled_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->inspectionService->rescheduleInspection($inspection, $request->scheduled_date, $request->reason, $user);
            return back()->with('success', 'Jadwal inspeksi kamar berhasil diperbarui.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
