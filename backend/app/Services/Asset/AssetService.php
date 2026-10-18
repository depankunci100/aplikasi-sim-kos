<?php

namespace App\Services\Asset;

use App\Models\Asset;
use App\Models\AssetLocationHistory;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetService
{
    public function __construct(
        protected ChartOfAccountService $coaService,
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Dapatkan query aset dengan isolasi cabang dan multi-kriteria filtering.
     */
    public function getAssetsQuery(User $user, array $filters = []): Builder
    {
        $query = Asset::query()->with(['property', 'room', 'pic']);

        // 1. Property Isolation
        if (!$user->isSuperAdmin()) {
            $allowedPropertyIds = $user->properties()->pluck('properties.id')->toArray();
            $query->whereIn('assets.property_id', $allowedPropertyIds);
        }

        // 2. Filter Cabang Properti
        if (!empty($filters['property_id'])) {
            $query->where('assets.property_id', (int) $filters['property_id']);
        }

        // 3. Filter Kategori Aset
        if (!empty($filters['category'])) {
            $query->where('assets.category', $filters['category']);
        }

        // 4. Filter Klasifikasi Akuntansi
        if (!empty($filters['classification'])) {
            $query->where('assets.classification', $filters['classification']);
        }

        // 5. Filter Tipe Lokasi & Kamar
        if (!empty($filters['location_type'])) {
            $query->where('assets.location_type', $filters['location_type']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('assets.room_id', (int) $filters['room_id']);
        }

        // 6. Filter Lifecycle Status & Kondisi
        if (!empty($filters['lifecycle_status'])) {
            $query->where('assets.lifecycle_status', $filters['lifecycle_status']);
        }

        if (!empty($filters['condition'])) {
            $query->where('assets.condition', $filters['condition']);
        }

        // 7. Pencarian Text
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('assets.asset_code', 'like', "%{$search}%")
                    ->orWhere('assets.name', 'like', "%{$search}%")
                    ->orWhere('assets.serial_number', 'like', "%{$search}%")
                    ->orWhere('assets.vendor_name', 'like', "%{$search}%")
                    ->orWhere('assets.location_name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('assets.property_id')->orderBy('assets.asset_code');
    }

    /**
     * Generate kode aset unik terstruktur. Format: AST-PRP{ID}-{YYYYMM}-{RANDOM4}
     */
    public function generateAssetCode(int $propertyId): string
    {
        $prefix = "AST-P{$propertyId}-" . date('Ym') . "-";
        do {
            $candidate = $prefix . strtoupper(Str::random(4));
        } while (Asset::where('asset_code', $candidate)->exists());

        return $candidate;
    }

    /**
     * Daftarkan master aset baru dengan pencatatan lokasi awal dan opsi integrasi jurnal akuisisi.
     */
    public function createAsset(User $user, array $data): Asset
    {
        $propertyId = (int) $data['property_id'];

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $propertyId)->exists()) {
            throw new DomainException('Anda tidak memiliki hak akses mendaftarkan aset pada properti ini.');
        }

        // Tentukan kode aset
        $assetCode = !empty($data['asset_code']) ? trim($data['asset_code']) : $this->generateAssetCode($propertyId);

        if (Asset::where('asset_code', $assetCode)->exists()) {
            throw new DomainException("Kode aset '{$assetCode}' sudah digunakan di sistem.");
        }

        // Validasi kamar jika lokasi di kamar
        $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId) {
            $room = Room::findOrFail($roomId);
            if ($room->property_id !== $propertyId) {
                throw new DomainException('Kamar yang dipilih tidak berada di properti aset.');
            }
        }

        $purchaseCost = (float) ($data['purchase_cost'] ?? 0);
        $salvageValue = (float) ($data['salvage_value'] ?? 0);
        $accumulatedDepreciation = (float) ($data['accumulated_depreciation'] ?? 0);
        $usefulLifeMonths = (int) ($data['useful_life_months'] ?? 0);

        if ($salvageValue > $purchaseCost) {
            throw new DomainException('Nilai residu tidak boleh lebih besar dari harga perolehan aset.');
        }

        $currentBookValue = max(0, round($purchaseCost - $accumulatedDepreciation, 2));

        return DB::transaction(function () use (
            $user,
            $data,
            $propertyId,
            $roomId,
            $assetCode,
            $purchaseCost,
            $salvageValue,
            $accumulatedDepreciation,
            $currentBookValue,
            $usefulLifeMonths
        ) {
            $asset = Asset::create([
                'asset_code' => $assetCode,
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'name' => trim($data['name']),
                'category' => $data['category'],
                'classification' => $data['classification'] ?? 'fixed_asset',
                'location_type' => $data['location_type'] ?? ($roomId ? 'room' : 'common_area'),
                'location_name' => $data['location_name'] ?? null,
                'description' => $data['description'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? now()->toDateString(),
                'in_service_date' => $data['in_service_date'] ?? $data['acquisition_date'] ?? now()->toDateString(),
                'purchase_cost' => $purchaseCost,
                'salvage_value' => $salvageValue,
                'useful_life_months' => $usefulLifeMonths,
                'depreciation_method' => $data['depreciation_method'] ?? ($usefulLifeMonths > 0 ? 'straight_line' : 'none'),
                'accumulated_depreciation' => $accumulatedDepreciation,
                'current_book_value' => $currentBookValue,
                'vendor_name' => $data['vendor_name'] ?? null,
                'purchase_reference' => $data['purchase_reference'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'warranty_expiry_date' => $data['warranty_expiry_date'] ?? null,
                'condition' => $data['condition'] ?? 'good',
                'lifecycle_status' => $data['lifecycle_status'] ?? 'active',
                'pic_user_id' => !empty($data['pic_user_id']) ? (int) $data['pic_user_id'] : null,
                'expense_id' => !empty($data['expense_id']) ? (int) $data['expense_id'] : null,
                'photos' => isset($data['photos']) ? (is_array($data['photos']) ? $data['photos'] : json_decode($data['photos'], true)) : null,
                'documents' => isset($data['documents']) ? (is_array($data['documents']) ? $data['documents'] : json_decode($data['documents'], true)) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Catat riwayat penempatan lokasi perdana
            AssetLocationHistory::create([
                'asset_id' => $asset->id,
                'from_location_type' => null,
                'from_location_name' => null,
                'from_room_id' => null,
                'to_location_type' => $asset->location_type,
                'to_location_name' => $asset->location_name,
                'to_room_id' => $asset->room_id,
                'transferred_by' => $user->id,
                'transferred_at' => now(),
                'reason' => 'Pendaftaran perolehan aset perdana.',
            ]);

            return $asset->load(['property', 'room', 'pic']);
        });
    }

    /**
     * Perbarui data aset operasional.
     */
    public function updateAsset(Asset $asset, User $user, array $data): Asset
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak akses mengubah aset ini.');
        }

        if (in_array($asset->lifecycle_status, ['disposed', 'written_off'])) {
            throw new DomainException('Aset yang sudah dilepas/dihapuskan tidak dapat diedit kembali.');
        }

        // Cek jika harga perolehan diubah padahal penyusutan sudah dibukukan
        if (isset($data['purchase_cost']) && (float) $data['purchase_cost'] !== (float) $asset->purchase_cost) {
            if ($asset->accumulated_depreciation > 0) {
                throw new DomainException('Harga perolehan tidak dapat diubah langsung karena penyusutan telah berjalan. Lakukan penyesuaian/revaluasi akuntansi.');
            }
        }

        return DB::transaction(function () use ($asset, $data) {
            $updatePayload = [];

            if (isset($data['name'])) $updatePayload['name'] = trim($data['name']);
            if (isset($data['category'])) $updatePayload['category'] = $data['category'];
            if (isset($data['classification'])) $updatePayload['classification'] = $data['classification'];
            if (array_key_exists('description', $data)) $updatePayload['description'] = $data['description'];
            if (isset($data['in_service_date'])) $updatePayload['in_service_date'] = $data['in_service_date'];
            if (isset($data['salvage_value'])) $updatePayload['salvage_value'] = (float) $data['salvage_value'];
            if (isset($data['useful_life_months'])) $updatePayload['useful_life_months'] = (int) $data['useful_life_months'];
            if (isset($data['depreciation_method'])) $updatePayload['depreciation_method'] = $data['depreciation_method'];
            if (array_key_exists('vendor_name', $data)) $updatePayload['vendor_name'] = $data['vendor_name'];
            if (array_key_exists('purchase_reference', $data)) $updatePayload['purchase_reference'] = $data['purchase_reference'];
            if (array_key_exists('serial_number', $data)) $updatePayload['serial_number'] = $data['serial_number'];
            if (array_key_exists('warranty_expiry_date', $data)) $updatePayload['warranty_expiry_date'] = $data['warranty_expiry_date'];
            if (isset($data['condition'])) $updatePayload['condition'] = $data['condition'];
            if (isset($data['lifecycle_status'])) $updatePayload['lifecycle_status'] = $data['lifecycle_status'];
            if (array_key_exists('pic_user_id', $data)) $updatePayload['pic_user_id'] = $data['pic_user_id'] ? (int) $data['pic_user_id'] : null;
            if (array_key_exists('notes', $data)) $updatePayload['notes'] = $data['notes'];

            if (isset($data['purchase_cost']) && $asset->accumulated_depreciation == 0) {
                $updatePayload['purchase_cost'] = (float) $data['purchase_cost'];
                $updatePayload['current_book_value'] = (float) $data['purchase_cost'];
            }

            $asset->update($updatePayload);

            return $asset->fresh(['property', 'room', 'pic']);
        });
    }

    /**
     * Mutasi / Pindah Lokasi Aset dengan audit trail lengkap.
     */
    public function transferLocation(Asset $asset, User $user, array $data): Asset
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak melakukan mutasi lokasi pada cabang ini.');
        }

        $toRoomId = !empty($data['to_room_id']) ? (int) $data['to_room_id'] : null;
        if ($toRoomId) {
            $room = Room::findOrFail($toRoomId);
            if ($room->property_id !== $asset->property_id) {
                throw new DomainException('Aset hanya dapat dipindahkan ke kamar dalam properti/cabang yang sama.');
            }
        }

        return DB::transaction(function () use ($asset, $user, $data, $toRoomId) {
            $fromLocationType = $asset->location_type;
            $fromLocationName = $asset->location_name;
            $fromRoomId = $asset->room_id;

            $toLocationType = $data['to_location_type'] ?? ($toRoomId ? 'room' : 'common_area');
            $toLocationName = $data['to_location_name'] ?? null;

            $asset->location_type = $toLocationType;
            $asset->location_name = $toLocationName;
            $asset->room_id = $toRoomId;
            $asset->save();

            AssetLocationHistory::create([
                'asset_id' => $asset->id,
                'from_location_type' => $fromLocationType,
                'from_location_name' => $fromLocationName,
                'from_room_id' => $fromRoomId,
                'to_location_type' => $toLocationType,
                'to_location_name' => $toLocationName,
                'to_room_id' => $toRoomId,
                'transferred_by' => $user->id,
                'transferred_at' => now(),
                'reason' => $data['reason'] ?? 'Mutasi lokasi aset rutin.',
                'notes' => $data['notes'] ?? null,
            ]);

            return $asset->fresh(['property', 'room']);
        });
    }

    /**
     * Pelepasan / Penjualan Aset Tetap terintegrasi dengan Double-Entry Accounting.
     */
    public function disposeAsset(Asset $asset, User $user, array $data): Asset
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak melakukan pelepasan aset ini.');
        }

        if (in_array($asset->lifecycle_status, ['disposed', 'written_off'])) {
            throw new DomainException("Aset #{$asset->asset_code} sudah pernah dilepas atau dihapuskan sebelumnya.");
        }

        $disposalDate = $data['disposal_date'] ?? now()->toDateString();
        $disposalPrice = round((float) ($data['disposal_price'] ?? 0), 2);
        $disposalCost = round((float) ($data['disposal_cost'] ?? 0), 2);
        $disposalNotes = $data['disposal_notes'] ?? 'Pelepasan aset tetap operasional.';
        $postJournal = !empty($data['post_journal']);

        return DB::transaction(function () use ($asset, $user, $disposalDate, $disposalPrice, $disposalCost, $disposalNotes, $postJournal) {
            $propertyId = $asset->property_id;
            $this->coaService->ensureDefaultAccountsForProperty($propertyId);

            $purchaseCost = (float) $asset->purchase_cost;
            $accumulatedDepr = (float) $asset->accumulated_depreciation;
            $currentBookValue = (float) $asset->current_book_value;

            $netProceeds = round($disposalPrice - $disposalCost, 2);
            $gainOrLoss = round($netProceeds - $currentBookValue, 2);

            $journalEntry = null;

            if ($postJournal && $purchaseCost > 0) {
                // Siapkan baris-baris jurnal pelepasan aset:
                // 1. Kredit Akun Aset Tetap Asli (13xx) sebesar Harga Perolehan
                $assetCostAcc = $this->coaService->getAssetCostAccountByCategory($propertyId, $asset->category);
                // 2. Debit Akumulasi Penyusutan (1391) sebesar akumulasi yang sudah disusutkan
                $accumAcc = $this->coaService->getAccountByCode($propertyId, '1391');
                // 3. Debit Kas/Bank (1102) sebesar penerimaan kas bersih
                $cashAcc = $this->coaService->getAccountByCode($propertyId, '1102');

                $lines = [];

                // Debit Kas/Bank jika ada penerimaan uang penjualan
                if ($netProceeds > 0) {
                    $lines[] = [
                        'account_id' => $cashAcc->id,
                        'debit' => $netProceeds,
                        'credit' => 0.00,
                        'memo' => "Penerimaan hasil penjualan aset {$asset->asset_code}",
                    ];
                }

                // Debit Akumulasi Penyusutan untuk menghapus saldo kontra aset
                if ($accumAcc && $accumulatedDepr > 0) {
                    $lines[] = [
                        'account_id' => $accumAcc->id,
                        'debit' => $accumulatedDepr,
                        'credit' => 0.00,
                        'memo' => "Penghapusan akumulasi penyusutan aset {$asset->asset_code}",
                    ];
                }

                // Jika Rugi Pelepasan Aset (Debit 5301)
                if ($gainOrLoss < 0) {
                    $lossAcc = $this->coaService->getAccountByCode($propertyId, '5301');
                    $lines[] = [
                        'account_id' => $lossAcc->id,
                        'debit' => abs($gainOrLoss),
                        'credit' => 0.00,
                        'memo' => "Rugi pelepasan aset tetap {$asset->asset_code}",
                    ];
                }

                // Kredit Akun Aset Tetap Asli
                $lines[] = [
                    'account_id' => $assetCostAcc->id,
                    'debit' => 0.00,
                    'credit' => $purchaseCost,
                    'memo' => "Pelepasan harga perolehan aset {$asset->asset_code}",
                ];

                // Jika Untung Pelepasan Aset (Kredit 4201)
                if ($gainOrLoss > 0) {
                    $gainAcc = $this->coaService->getAccountByCode($propertyId, '4201');
                    $lines[] = [
                        'account_id' => $gainAcc->id,
                        'debit' => 0.00,
                        'credit' => $gainOrLoss,
                        'memo' => "Laba penjualan aset tetap {$asset->asset_code}",
                    ];
                }

                $idempotencyKey = "asset_disposal_{$asset->id}_" . date('Ymd');
                $desc = "Pelepasan/Penjualan Aset Tetap: {$asset->name} ({$asset->asset_code})";

                $journalEntry = $this->journalPostingService->postJournal(
                    $propertyId,
                    'asset_disposal',
                    $asset->id,
                    $idempotencyKey,
                    $desc,
                    $lines,
                    $disposalDate,
                    $user,
                    $disposalNotes
                );
            }

            $asset->lifecycle_status = ($disposalPrice > 0) ? 'disposed' : 'written_off';
            $asset->disposal_date = $disposalDate;
            $asset->disposal_price = $disposalPrice;
            $asset->disposal_cost = $disposalCost;
            $asset->disposal_notes = $disposalNotes;
            $asset->disposal_journal_id = $journalEntry?->id;
            $asset->current_book_value = 0.00;
            $asset->save();

            return $asset->fresh(['property', 'disposalJournal']);
        });
    }
}
