<?php

namespace App\Services\Room;

use App\Models\Building;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BuildingService
{
    /**
     * Ambil daftar gedung dengan filter dan hak akses properti
     */
    public function getBuildingsQuery(User $user, array $filters = []): Builder
    {
        $query = Building::with(['property'])->withCount(['rooms']);

        if (!$user->isSuperAdmin()) {
            $propertyIds = $user->properties()->pluck('properties.id')->toArray();
            $query->whereIn('property_id', $propertyIds);
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('address_notes', 'like', $search);
            });
        }

        return $query->orderBy('property_id')->orderBy('name');
    }

    /**
     * Ambil daftar gedung dengan pagination
     */
    public function getBuildingsPaginated(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->getBuildingsQuery($user, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Ambil seluruh gedung aktif untuk dropdown form kamar
     */
    public function getActiveBuildingsForUser(User $user, ?int $propertyId = null): Collection
    {
        $query = Building::active()->orderBy('name');

        if (!$user->isSuperAdmin()) {
            $propertyIds = $user->properties()->pluck('properties.id')->toArray();
            $query->whereIn('property_id', $propertyIds);
        }

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        return $query->get();
    }

    /**
     * Tambah gedung baru
     */
    public function createBuilding(array $data): Building
    {
        return Building::create([
            'property_id' => $data['property_id'],
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'total_floors' => (int) ($data['total_floors'] ?? 1),
            'address_notes' => $data['address_notes'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * Update data gedung
     */
    public function updateBuilding(Building $building, array $data): Building
    {
        $building->update([
            'code' => strtoupper(trim($data['code'] ?? $building->code)),
            'name' => trim($data['name'] ?? $building->name),
            'total_floors' => isset($data['total_floors']) ? (int) $data['total_floors'] : $building->total_floors,
            'address_notes' => array_key_exists('address_notes', $data) ? $data['address_notes'] : $building->address_notes,
            'description' => array_key_exists('description', $data) ? $data['description'] : $building->description,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $building->is_active,
        ]);

        return $building;
    }

    /**
     * Toggle status aktif/non-aktif gedung
     */
    public function toggleActive(Building $building): Building
    {
        $building->is_active = !$building->is_active;
        $building->save();

        return $building;
    }

    /**
     * Hapus gedung dengan proteksi kamar terhubung
     */
    public function deleteBuilding(Building $building): bool
    {
        if ($building->rooms()->exists()) {
            throw new DomainException('Gedung "' . $building->name . '" tidak dapat dihapus karena masih memiliki unit kamar terhubung. Pindahkan atau hapus kamar terlebih dahulu.');
        }

        return $building->delete();
    }
}
