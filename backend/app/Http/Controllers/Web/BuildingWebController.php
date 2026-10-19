<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Property;
use App\Services\Room\BuildingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BuildingWebController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService
    ) {}

    /**
     * Tampilan daftar master gedung dengan ringkasan metrik dan filter
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $filters = $request->only(['property_id', 'is_active', 'search']);
        $buildings = $this->buildingService->getBuildingsPaginated($user, $filters, 15);

        $properties = $user->isSuperAdmin()
            ? Property::active()->orderBy('name')->get()
            : $user->properties()->active()->orderBy('name')->get();

        $baseQuery = $this->buildingService->getBuildingsQuery($user, []);
        $summary = [
            'total_buildings' => (clone $baseQuery)->count(),
            'active_buildings' => (clone $baseQuery)->where('is_active', true)->count(),
            'total_rooms' => \App\Models\Room::whereIn('property_id', $properties->pluck('id'))->whereNotNull('building_id')->count(),
        ];

        return view('dashboard.buildings.index', compact('buildings', 'properties', 'summary', 'filters'));
    }

    /**
     * Simpan master gedung baru
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'total_floors' => ['required', 'integer', 'min:1', 'max:100'],
            'address_notes' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $validated['property_id'])->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses pada cabang properti ini.')->withInput();
        }

        // Cek keunikan kode per properti
        $exists = Building::where('property_id', $validated['property_id'])
            ->where('code', strtoupper(trim($validated['code'])))
            ->exists();

        if ($exists) {
            return back()->with('error', "Kode gedung '{$validated['code']}' sudah terdaftar pada properti ini.")->withInput();
        }

        try {
            $building = $this->buildingService->createBuilding($validated);
            return redirect()->route('dashboard.buildings.index')
                ->with('success', "Gedung '{$building->name}' ({$building->code}) berhasil ditambahkan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menambahkan gedung: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Perbarui data master gedung
     */
    public function update(Request $request, Building $building): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $building->property_id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk mengubah gedung ini.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'total_floors' => ['required', 'integer', 'min:1', 'max:100'],
            'address_notes' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Cek keunikan kode jika berubah
        $newCode = strtoupper(trim($validated['code']));
        if ($newCode !== $building->code) {
            $exists = Building::where('property_id', $building->property_id)
                ->where('code', $newCode)
                ->where('id', '!=', $building->id)
                ->exists();

            if ($exists) {
                return back()->with('error', "Kode gedung '{$newCode}' sudah digunakan gedung lain pada properti ini.")->withInput();
            }
        }

        try {
            $this->buildingService->updateBuilding($building, $validated);
            return redirect()->route('dashboard.buildings.index')
                ->with('success', "Data gedung '{$building->name}' berhasil diperbarui.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui gedung: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Toggle status aktif gedung
     */
    public function toggleActive(Building $building): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $building->property_id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk mengubah gedung ini.');
        }

        $this->buildingService->toggleActive($building);
        $statusStr = $building->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Gedung '{$building->name}' berhasil {$statusStr}.");
    }

    /**
     * Hapus gedung
     */
    public function destroy(Building $building): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $building->property_id)->exists()) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk menghapus gedung ini.');
        }

        try {
            $name = $building->name;
            $this->buildingService->deleteBuilding($building);
            return redirect()->route('dashboard.buildings.index')
                ->with('success', "Gedung '{$name}' berhasil dihapus.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan sistem saat menghapus gedung.');
        }
    }
}
