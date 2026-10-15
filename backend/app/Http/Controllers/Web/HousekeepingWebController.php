<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Property;
use App\Services\Housekeeping\HousekeepingService;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HousekeepingWebController extends Controller
{
    public function __construct(
        protected HousekeepingService $housekeepingService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'room_id', 'status', 'type', 'scheduled_date', 'assigned_to']);

        $tasks = $this->housekeepingService->getTasksQuery($user, $filters)->paginate(15);
        $properties = $user->isSuperAdmin()
            ? Property::active()->get()
            : $user->properties()->active()->get();

        return view('dashboard.housekeeping.index', compact('tasks', 'properties', 'filters'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $properties = $user->isSuperAdmin()
            ? Property::with('rooms', 'users')->active()->get()
            : $user->properties()->with('rooms', 'users')->active()->get();

        return view('dashboard.housekeeping.create', compact('properties'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'area_name' => 'nullable|string|max:100',
            'type' => 'nullable|in:room_cleaning,turnover_cleaning,deep_cleaning,common_area_cleaning,on_demand',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'scheduled_slot' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $task = $this->housekeepingService->createTask($request->all(), $user);
            return redirect()->route('dashboard.housekeeping.show', $task->id)
                ->with('success', "Tugas kebersihan #{$task->task_number} berhasil dijadwalkan.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::with([
            'property.users',
            'room.roomType',
            'assignee',
            'assigner',
            'verifier',
            'logs.user',
        ])->findOrFail($id);

        try {
            if ($user->isTenant()) {
                $isRoomRented = false;
                if ($task->room_id) {
                    $isRoomRented = \App\Models\Booking::where('user_id', $user->id)
                        ->where('room_id', $task->room_id)
                        ->whereIn('status', ['confirmed', 'checked_in'])
                        ->exists();
                }
                if (!$isRoomRented) {
                    abort(403, 'Anda tidak berhak melihat tugas kebersihan ini.');
                }
            } else {
                $this->housekeepingService->authorizePropertyAccess($user, $task->property_id);
            }
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        $availableStaff = $task->property->users()
            ->whereIn('property_users.role', ['staff', 'manager', 'owner'])
            ->get();

        return view('dashboard.housekeeping.show', compact('task', 'availableStaff'));
    }

    public function assign(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        try {
            $this->housekeepingService->assignTask($task, (int) $request->assigned_to, $user);
            return back()->with('success', 'Staf kebersihan berhasil ditugaskan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function start(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'photos_before' => 'nullable|array',
            'photos_before.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $this->housekeepingService->startTask($task, $request->file('photos_before'), $user);
            return back()->with('success', 'Tugas kebersihan telah dimulai.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submitVerification(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'checklist_done' => 'nullable|array',
            'damaged_or_missing_items' => 'nullable|string',
            'notes' => 'nullable|string',
            'photos_after' => 'nullable|array',
            'photos_after.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        // Buat struktur checklist yang diperbarui
        $updatedChecklist = [];
        $doneIds = $request->input('checklist_done', []);
        foreach ($task->checklist ?? [] as $item) {
            $itemId = (string) ($item['id'] ?? '');
            $item['is_done'] = in_array($itemId, $doneIds);
            $updatedChecklist[] = $item;
        }

        try {
            $this->housekeepingService->submitTaskVerification(
                $task,
                $updatedChecklist,
                $request->file('photos_after'),
                $request->damaged_or_missing_items,
                $request->notes,
                $user
            );

            return back()->with('success', 'Tugas kebersihan berhasil diserahkan untuk verifikasi.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verify(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'verification_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->housekeepingService->verifyTask($task, $request->verification_notes, $user);
            return back()->with('success', 'Tugas kebersihan telah diverifikasi selesai.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id)
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->housekeepingService->cancelTask($task, $request->reason, $user);
            return back()->with('success', 'Tugas kebersihan telah dibatalkan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function photo(Request $request, int $id): BinaryFileResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $path = $request->query('path');
        if (empty($path)) {
            abort(404, 'Path foto tidak valid.');
        }

        try {
            $filePath = $this->housekeepingService->getAuthorizedPhotoPath($task, $path, $user);
            return response()->file($filePath, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }
}
