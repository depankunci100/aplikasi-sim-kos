<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\User;
use App\Services\Maintenance\MaintenanceService;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceWebController extends Controller
{
    public function __construct(
        protected MaintenanceService $maintenanceService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'room_id', 'category', 'priority', 'status', 'is_overdue', 'scheduled_date', 'search']);

        // Jalankan pengecekan overdue secara otomatis
        $this->maintenanceService->flagOverdueTickets();

        $requests = $this->maintenanceService->getMaintenanceQuery($user, $filters)->paginate(15);

        $counts = [
            'total' => (clone $this->maintenanceService->getMaintenanceQuery($user, []))->count(),
            'in_progress' => (clone $this->maintenanceService->getMaintenanceQuery($user, ['status' => 'in_progress']))->count(),
            'pending_verification' => (clone $this->maintenanceService->getMaintenanceQuery($user, ['status' => 'pending_verification']))->count(),
            'overdue' => (clone $this->maintenanceService->getMaintenanceQuery($user, ['is_overdue' => 1]))->count(),
        ];

        if ($user->isSuperAdmin()) {
            $properties = Property::with('rooms')->active()->get();
        } elseif ($user->isTenant()) {
            $propertyIds = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                ->pluck('property_id');
            $properties = Property::whereIn('id', $propertyIds)->with('rooms')->active()->get();
        } else {
            $properties = $user->properties()->with('rooms')->active()->get();
        }

        return view('dashboard.maintenance.index', compact('requests', 'properties', 'filters', 'counts'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $properties = Property::with('rooms')->active()->get();
        } elseif ($user->isTenant()) {
            $bookingRooms = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                ->with('room')
                ->get();
            $propertyIds = $bookingRooms->pluck('property_id')->unique();
            $roomIds = $bookingRooms->pluck('room_id')->filter();
            $properties = Property::whereIn('id', $propertyIds)
                ->with(['rooms' => fn ($q) => $q->whereIn('id', $roomIds)])
                ->active()
                ->get();
        } else {
            $properties = $user->properties()->with('rooms')->active()->get();
        }

        return view('dashboard.maintenance.create', compact('properties'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'photos_before' => 'nullable|array',
            'photos_before.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $maintenance = $this->maintenanceService->createMaintenance(
                $request->all(),
                $request->file('photos_before'),
                $user
            );

            return redirect()->route('dashboard.maintenance.show', $maintenance->id)
                ->with('success', "Tiket perbaikan #{$maintenance->ticket_number} berhasil diajukan.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::with([
            'property.users',
            'room.roomType',
            'roomAsset',
            'complaint',
            'reporter',
            'assignee',
            'verifier',
            'logs.user',
        ])->findOrFail($id);

        $this->authorizeMaintenanceAccess($user, $maintenance);

        $availableStaff = $maintenance->property->users()
            ->whereIn('property_users.role', ['staff', 'manager', 'owner'])
            ->get();
        if ($availableStaff->isEmpty()) {
            $availableStaff = $maintenance->property->users;
        }

        return view('dashboard.maintenance.show', compact('maintenance', 'availableStaff'));
    }

    public function triage(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $request->validate([
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->maintenanceService->triageMaintenance($maintenance, $request->all(), $user);
            return back()->with('success', 'Tiket perbaikan berhasil ditriase.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assign(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'scheduled_date' => 'nullable|date',
        ]);

        try {
            $this->maintenanceService->assignMaintenance(
                $maintenance,
                (int) $request->assigned_to,
                $request->scheduled_date,
                $user
            );

            return back()->with('success', 'Teknisi berhasil ditugaskan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function start(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        try {
            $this->maintenanceService->startMaintenance($maintenance, $request->diagnosis_notes, $user);
            return back()->with('success', 'Pekerjaan perbaikan telah dimulai.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submitVerification(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $request->validate([
            'action_taken' => 'required|string',
            'diagnosis_notes' => 'nullable|string',
            'spare_parts' => 'nullable|string',
            'labor_cost' => 'nullable|numeric|min:0',
            'material_cost' => 'nullable|numeric|min:0',
            'photos_after' => 'nullable|array',
            'photos_after.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $this->maintenanceService->submitForVerification(
                $maintenance,
                $request->all(),
                $request->file('photos_after'),
                $user
            );

            return back()->with('success', 'Hasil perbaikan berhasil diserahkan untuk verifikasi.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verify(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $request->validate([
            'verification_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->maintenanceService->verifyAndComplete(
                $maintenance,
                $request->verification_notes,
                $user
            );

            return back()->with('success', 'Perbaikan telah diverifikasi selesai.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectOrCancel(Request $request, int $id)
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $request->validate([
            'action' => 'required|in:reject,cancel',
            'reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->maintenanceService->rejectOrCancel(
                $maintenance,
                $request->action,
                $request->reason,
                $user
            );

            $msg = ($request->action === 'reject') ? 'Tiket perbaikan ditolak.' : 'Tiket perbaikan dibatalkan.';
            return back()->with('success', $msg);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function photo(Request $request, int $id): BinaryFileResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);
        $this->authorizeMaintenanceAccess($user, $maintenance);

        $path = $request->query('path');
        if (empty($path)) {
            abort(404, 'Path foto tidak valid.');
        }

        try {
            $filePath = $this->maintenanceService->getAuthorizedPhotoPath($maintenance, $path, $user);
            return response()->file($filePath, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }

    protected function authorizeMaintenanceAccess(User $user, MaintenanceRequest $maintenance): void
    {
        if ($user->isTenant()) {
            if ($user->id !== $maintenance->reporter_id) {
                $hasActiveRentalInProperty = Booking::where('user_id', $user->id)
                    ->where('property_id', $maintenance->property_id)
                    ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->exists();

                if (!$hasActiveRentalInProperty) {
                    abort(403, 'Anda tidak berhak mengakses tiket perbaikan ini.');
                }

                if ($maintenance->room_id) {
                    $isRoomRented = Booking::where('user_id', $user->id)
                        ->where('room_id', $maintenance->room_id)
                        ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                        ->exists();

                    if (!$isRoomRented) {
                        abort(403, 'Anda tidak berhak mengakses tiket perbaikan kamar milik orang lain.');
                    }
                }
            }
        } else {
            try {
                $this->maintenanceService->authorizePropertyAccess($user, $maintenance->property_id);
            } catch (DomainException $e) {
                abort(403, $e->getMessage());
            }
        }
    }
}
