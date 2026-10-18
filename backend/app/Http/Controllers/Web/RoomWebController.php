<?php

namespace App\Http\Controllers\Web;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Room\RoomService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomWebController extends Controller
{
    public function __construct(
        protected RoomService $roomService
    ) {}

    /**
     * Tampilkan katalog & tabel master kamar kos & guesthouse.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki hak akses ke manajemen master kamar.');
        }

        $filters = $request->only([
            'property_id',
            'room_type_id',
            'status',
            'floor',
            'building',
            'is_active',
            'search',
        ]);

        $rooms = $this->roomService->getRoomsQuery($user, $filters)->paginate(15)->withQueryString();

        $properties = $user->isSuperAdmin()
            ? Property::active()->get()
            : $user->properties()->active()->get();

        $allowedPropertyIds = $properties->pluck('id')->toArray();
        $roomTypes = RoomType::whereIn('property_id', $allowedPropertyIds)->get();

        // Rekapitulasi KPI Kamar
        $counts = [
            'total' => (clone $this->roomService->getRoomsQuery($user, []))->count(),
            'available' => (clone $this->roomService->getRoomsQuery($user, ['status' => RoomStatus::AVAILABLE->value]))->count(),
            'occupied' => (clone $this->roomService->getRoomsQuery($user, ['status' => RoomStatus::OCCUPIED->value]))->count(),
            'maintenance' => (clone $this->roomService->getRoomsQuery($user, ['status' => RoomStatus::MAINTENANCE->value]))->count(),
            'dirty' => (clone $this->roomService->getRoomsQuery($user, ['status' => RoomStatus::DIRTY->value]))->count(),
            'inactive' => (clone $this->roomService->getRoomsQuery($user, ['is_active' => 0]))->count(),
        ];

        return view('dashboard.rooms.index', compact('rooms', 'counts', 'properties', 'roomTypes', 'filters'));
    }

    /**
     * Simpan kamar baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:50'],
            'floor' => ['required', 'integer', 'min:1', 'max:100'],
            'building' => ['nullable', 'string', 'max:50'],
            'size_m2' => ['nullable', 'numeric', 'min:1'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'facilities' => ['nullable'],
            'price_daily' => ['nullable', 'numeric', 'min:0'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
            'is_active' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        // Parsing fasilitas jika dikirim sebagai string comma-separated
        if (!empty($validated['facilities']) && is_string($validated['facilities'])) {
            $validated['facilities'] = array_map('trim', explode(',', $validated['facilities']));
        }

        try {
            $room = $this->roomService->createRoom($user, $validated);

            return redirect()->route('dashboard.rooms.index', ['property_id' => $room->property_id])
                ->with('success', "Kamar {$room->room_number} berhasil didaftarkan ke sistem.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Tampilkan detail komprehensif master kamar.
     */
    public function show(Request $request, int $id): View
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $room = Room::with([
            'property',
            'roomType',
            'assets',
            'fixedAssets',
            'bookings' => fn($q) => $q->latest()->take(10),
            'bookings.user',
            'histories.user',
            'inspections' => fn($q) => $q->latest()->take(5),
            'maintenanceRequests' => fn($q) => $q->latest()->take(5),
        ])->findOrFail($id);

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $room->property_id)->exists()) {
            abort(403, 'Anda tidak memiliki hak akses ke cabang kamar ini.');
        }

        $properties = $user->isSuperAdmin()
            ? Property::active()->get()
            : $user->properties()->active()->get();
        $roomTypes = RoomType::where('property_id', $room->property_id)->get();

        return view('dashboard.rooms.show', compact('room', 'properties', 'roomTypes'));
    }

    /**
     * Perbarui data master kamar.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'room_type_id' => ['sometimes', 'required', 'exists:room_types,id'],
            'room_number' => ['sometimes', 'required', 'string', 'max:50'],
            'floor' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'building' => ['nullable', 'string', 'max:50'],
            'size_m2' => ['nullable', 'numeric', 'min:1'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'facilities' => ['nullable'],
            'price_daily' => ['nullable', 'numeric', 'min:0'],
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'down_payment_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
            'is_active' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string'],
            'change_reason' => ['nullable', 'string'],
        ]);

        if (isset($validated['facilities']) && is_string($validated['facilities'])) {
            $validated['facilities'] = array_map('trim', explode(',', $validated['facilities']));
        }

        try {
            $this->roomService->updateRoom($room, $user, $validated);

            return redirect()->route('dashboard.rooms.show', $room->id)
                ->with('success', "Data kamar {$room->room_number} berhasil diperbarui.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Aktifkan atau nonaktifkan kamar operasional.
     */
    public function toggleActive(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $room = Room::findOrFail($id);

        try {
            $updated = $this->roomService->toggleActive($room, $user, $request->input('reason'));
            $statusLabel = $updated->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

            return back()->with('success', "Kamar {$room->room_number} berhasil {$statusLabel}.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Ubah status operasional kamar secara langsung.
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $oldStatus = $room->status->label();
            $updated = $this->roomService->changeStatus(
                $room,
                $user,
                RoomStatus::from($validated['status']),
                $validated['notes'] ?? null
            );

            return back()->with(
                'success',
                "Status kamar {$room->room_number} berhasil diubah dari '{$oldStatus}' menjadi '{$updated->status->label()}'."
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Hapus kamar jika memenuhi syarat keamanan.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $room = Room::findOrFail($id);

        try {
            $roomNumber = $room->room_number;
            $this->roomService->deleteRoom($room, $user);

            return redirect()->route('dashboard.rooms.index')
                ->with('success', "Kamar {$roomNumber} berhasil dihapus.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
