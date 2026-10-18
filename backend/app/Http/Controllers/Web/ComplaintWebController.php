<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Property;
use App\Models\User;
use App\Services\Complaint\ComplaintService;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComplaintWebController extends Controller
{
    public function __construct(
        protected ComplaintService $complaintService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'room_id', 'category', 'priority', 'status', 'date', 'search']);

        $complaints = $this->complaintService->getComplaintsQuery($user, $filters)->paginate(15);

        $counts = [
            'total' => (clone $this->complaintService->getComplaintsQuery($user, []))->count(),
            'submitted' => (clone $this->complaintService->getComplaintsQuery($user, ['status' => 'submitted']))->count(),
            'in_progress' => (clone $this->complaintService->getComplaintsQuery($user, ['status' => 'in_progress']))->count(),
            'resolved' => (clone $this->complaintService->getComplaintsQuery($user, ['status' => 'resolved']))->count(),
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

        return view('dashboard.complaints.index', compact('complaints', 'properties', 'filters', 'counts'));
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

        return view('dashboard.complaints.create', compact('properties'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'nullable|in:room,facility,service,billing,environment,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $complaint = $this->complaintService->createComplaint(
                $request->all(),
                $request->file('photos'),
                $user
            );

            return redirect()->route('dashboard.complaints.show', $complaint->id)
                ->with('success', "Keluhan #{$complaint->ticket_number} berhasil diajukan.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::with([
            'property.users',
            'room.roomType',
            'user',
            'assignee',
            'acknowledger',
            'closer',
            'maintenanceRequest',
            'logs.user',
            'comments.user',
        ])->findOrFail($id);

        $this->authorizeComplaintAccess($user, $complaint);

        $availableStaff = $complaint->property->users()
            ->whereIn('property_users.role', ['staff', 'manager', 'owner', 'receptionist'])
            ->get();
        if ($availableStaff->isEmpty()) {
            $availableStaff = $complaint->property->users;
        }

        return view('dashboard.complaints.show', compact('complaint', 'availableStaff'));
    }

    public function acknowledge(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        try {
            $this->complaintService->acknowledgeComplaint($complaint, $user);
            return back()->with('success', 'Keluhan telah diterima dan ditinjau petugas.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assign(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        try {
            $this->complaintService->assignComplaint($complaint, (int) $request->assigned_to, $user);
            return back()->with('success', 'Penanggung jawab keluhan berhasil ditetapkan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function addComment(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'comment' => 'required|string|max:1000',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $this->complaintService->addComment(
                $complaint,
                $request->comment,
                $request->file('attachments'),
                filter_var($request->is_internal ?? false, FILTER_VALIDATE_BOOLEAN),
                $user
            );

            return back()->with('success', 'Tanggapan berhasil dikirim.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function linkMaintenance(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $maintenance = $this->complaintService->linkToMaintenance($complaint, $request->all(), $user);
            return redirect()->route('dashboard.maintenance.show', $maintenance->id)
                ->with('success', "Keluhan berhasil ditautkan ke tiket perbaikan #{$maintenance->ticket_number}.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function resolve(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'resolution_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->complaintService->resolveComplaint($complaint, $request->resolution_notes, $user);
            return back()->with('success', 'Keluhan telah ditandai selesai.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function confirmOrReopen(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'action' => 'required|in:confirm,reopen',
            'reason' => 'required_if:action,reopen|nullable|string|min:3|max:500',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500',
        ]);

        try {
            $this->complaintService->confirmOrReopen(
                $complaint,
                $request->action,
                $request->reason,
                $request->rating ? (int) $request->rating : null,
                $request->feedback,
                $user
            );

            $msg = ($request->action === 'confirm')
                ? 'Keluhan berhasil dikonfirmasi selesai dan ditutup.'
                : 'Keluhan telah dibuka kembali.';

            return back()->with('success', $msg);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->complaintService->rejectComplaint($complaint, $request->rejection_reason, $user);
            return back()->with('success', 'Keluhan telah ditolak.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function photo(Request $request, int $id): BinaryFileResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);
        $this->authorizeComplaintAccess($user, $complaint);

        $path = $request->query('path');
        if (empty($path)) {
            abort(404, 'Path foto tidak valid.');
        }

        try {
            $filePath = $this->complaintService->getAuthorizedPhotoPath($complaint, $path, $user);
            return response()->file($filePath, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }

    protected function authorizeComplaintAccess(User $user, Complaint $complaint): void
    {
        if ($user->isTenant()) {
            if ($user->id !== $complaint->user_id) {
                abort(403, 'Anda tidak berhak mengakses keluhan ini.');
            }
        } else {
            try {
                $this->complaintService->authorizePropertyAccess($user, $complaint->property_id);
            } catch (DomainException $e) {
                abort(403, $e->getMessage());
            }
        }
    }
}
