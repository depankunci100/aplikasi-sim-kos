<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Asset\AssetDepreciationService;
use App\Services\Asset\AssetService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AssetWebController extends Controller
{
    public function __construct(
        protected AssetService $assetService,
        protected AssetDepreciationService $depreciationService
    ) {}

    /**
     * Tampilkan katalog & ringkasan subledger aset tetap.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki hak akses ke manajemen aset tetap.');
        }

        $filters = $request->only([
            'property_id',
            'category',
            'classification',
            'location_type',
            'room_id',
            'lifecycle_status',
            'condition',
            'search',
        ]);

        $assets = $this->assetService->getAssetsQuery($user, $filters)->paginate(15)->withQueryString();

        $properties = $user->isSuperAdmin()
            ? Property::active()->get()
            : $user->properties()->active()->get();

        $allowedPropertyIds = $properties->pluck('id')->toArray();
        $rooms = Room::whereIn('property_id', $allowedPropertyIds)->orderBy('room_number')->get();

        // Rekapitulasi Finansial & Jumlah Aset
        $baseQuery = $this->assetService->getAssetsQuery($user, []);
        $summary = [
            'total_count' => (clone $baseQuery)->count(),
            'total_purchase_cost' => (float) (clone $baseQuery)->sum('purchase_cost'),
            'total_accumulated_depreciation' => (float) (clone $baseQuery)->sum('accumulated_depreciation'),
            'total_book_value' => (float) (clone $baseQuery)->sum('current_book_value'),
            'active_count' => (clone $baseQuery)->where('lifecycle_status', 'active')->count(),
            'maintenance_count' => (clone $baseQuery)->where('lifecycle_status', 'in_maintenance')->count(),
        ];

        return view('dashboard.assets.index', compact('assets', 'summary', 'properties', 'rooms', 'filters'));
    }

    /**
     * Daftarkan aset tetap baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'name' => ['required', 'string', 'max:150'],
            'asset_code' => ['nullable', 'string', 'max:60', 'unique:assets,asset_code'],
            'category' => ['required', 'string'],
            'classification' => ['required', 'string'],
            'location_type' => ['required', 'string'],
            'location_name' => ['nullable', 'string', 'max:100'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['nullable', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0'],
            'depreciation_method' => ['nullable', 'string'],
            'accumulated_depreciation' => ['nullable', 'numeric', 'min:0'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'purchase_reference' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'warranty_expiry_date' => ['nullable', 'date'],
            'condition' => ['nullable', 'string'],
            'lifecycle_status' => ['nullable', 'string'],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $asset = $this->assetService->createAsset($user, $validated);

            return redirect()->route('dashboard.assets.show', $asset->id)
                ->with('success', "Aset {$asset->name} ({$asset->asset_code}) berhasil didaftarkan ke sistem.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Tampilkan detail aset, riwayat perpindahan lokasi, skedul depresiasi, dan kartu pemeliharaan.
     */
    public function show(Request $request, int $id): View
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $asset = Asset::with([
            'property',
            'room',
            'pic',
            'expense',
            'disposalJournal',
            'locationHistories.transferredByUser',
            'locationHistories.fromRoom',
            'locationHistories.toRoom',
            'depreciationSchedules.journalEntry',
            'maintenanceRequests.reporter',
            'maintenanceRequests.assignee',
        ])->findOrFail($id);

        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            abort(403, 'Anda tidak memiliki hak akses ke cabang aset ini.');
        }

        $previewSchedules = $this->depreciationService->generateDepreciationPreview($asset);

        $rooms = Room::where('property_id', $asset->property_id)->orderBy('room_number')->get();
        $staffUsers = User::whereHas('properties', fn($q) => $q->where('properties.id', $asset->property_id))->get();

        return view('dashboard.assets.show', compact('asset', 'previewSchedules', 'rooms', 'staffUsers'));
    }

    /**
     * Perbarui data master aset.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category' => ['sometimes', 'required', 'string'],
            'classification' => ['sometimes', 'required', 'string'],
            'description' => ['nullable', 'string'],
            'in_service_date' => ['nullable', 'date'],
            'purchase_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:0'],
            'depreciation_method' => ['nullable', 'string'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'purchase_reference' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'warranty_expiry_date' => ['nullable', 'date'],
            'condition' => ['nullable', 'string'],
            'lifecycle_status' => ['nullable', 'string'],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->assetService->updateAsset($asset, $user, $validated);

            return redirect()->route('dashboard.assets.show', $asset->id)
                ->with('success', "Data aset {$asset->asset_code} berhasil diperbarui.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Pindahkan lokasi aset (Transfer Location).
     */
    public function transferLocation(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'to_location_type' => ['required', 'string'],
            'to_location_name' => ['nullable', 'string', 'max:100'],
            'to_room_id' => ['nullable', 'exists:rooms,id'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->assetService->transferLocation($asset, $user, $validated);

            return back()->with('success', "Aset {$asset->asset_code} berhasil dipindahkan ke lokasi baru.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pelepasan atau penjualan aset tetap dengan pembuatan jurnal berpasangan otomatis.
     */
    public function dispose(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_price' => ['nullable', 'numeric', 'min:0'],
            'disposal_cost' => ['nullable', 'numeric', 'min:0'],
            'disposal_notes' => ['required', 'string', 'max:500'],
            'post_journal' => ['nullable', 'boolean'],
        ]);

        try {
            $this->assetService->disposeAsset($asset, $user, $validated);

            return redirect()->route('dashboard.assets.show', $asset->id)
                ->with('success', "Aset {$asset->asset_code} berhasil dilepas dan status akuntansi telah dibukukan.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Posting penyusutan periodik individual untuk 1 aset.
     */
    public function postDepreciation(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'fiscal_month' => ['required', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $schedule = $this->depreciationService->postSingleDepreciation(
                $asset,
                (int) $validated['fiscal_year'],
                (int) $validated['fiscal_month'],
                $user,
                $validated['notes'] ?? null
            );

            return back()->with(
                'success',
                "Penyusutan aset periode {$schedule->fiscal_month}/{$schedule->fiscal_year} berhasil diposting. Jurnal: #{$schedule->journalEntry?->journal_number}"
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Batch Posting Penyusutan Aset massal per properti & periode.
     */
    public function batchDepreciation(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Aksi dilarang.');
        }

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'fiscal_month' => ['required', 'integer', 'min:1', 'max:12'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = !empty($validated['dry_run']);

        try {
            $result = $this->depreciationService->batchPostDepreciation(
                (int) $validated['property_id'],
                (int) $validated['fiscal_year'],
                (int) $validated['fiscal_month'],
                $user,
                $dryRun
            );

            $msg = $dryRun
                ? "Simulasi (Dry Run) depresiasi selesai: {$result['eligible']} aset dapat disusutkan dengan total nominal Rp " . number_format($result['total_amount'], 0, ',', '.') . "."
                : "Batch posting selesai: {$result['posted']} jurnal depresiasi berhasil dibukukan (Total: Rp " . number_format($result['total_amount'], 0, ',', '.') . ").";

            return back()->with('success', $msg);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
