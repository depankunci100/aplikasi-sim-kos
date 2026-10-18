<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Services\Asset\AssetService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssetApiController extends ApiController
{
    public function __construct(
        protected AssetService $assetService
    ) {}

    /**
     * Dapatkan daftar aset operasional (terisolasi per properti dan peran pengguna).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Penyewa hanya diizinkan melihat aset yang terpasang di kamar mereka sendiri
        if ($user->isTenant()) {
            $activeBooking = $user->bookings()
                ->whereIn('status', ['confirmed', 'active'])
                ->first();

            if (!$activeBooking || !$activeBooking->room_id) {
                return $this->successResponse([], 'Penyewa tidak memiliki kamar aktif.');
            }

            $assets = Asset::where('room_id', $activeBooking->room_id)
                ->where('lifecycle_status', 'active')
                ->select(['id', 'asset_code', 'name', 'category', 'condition', 'room_id'])
                ->get();

            return $this->successResponse($assets, 'Daftar inventaris kamar Anda berhasil diambil.');
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

        $assets = $this->assetService->getAssetsQuery($user, $filters)->paginate(20);

        return $this->successResponse($assets, 'Daftar aset berhasil diambil.');
    }

    /**
     * Detail spesifikasi dan histori pemeliharaan aset.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $asset = Asset::with(['property', 'room', 'pic'])->find($id);

        if (!$asset) {
            return $this->errorResponse('Aset tidak ditemukan.', 404);
        }

        // Cek otorisasi tenant
        if ($user->isTenant()) {
            $hasAccess = $user->bookings()
                ->whereIn('status', ['confirmed', 'active'])
                ->where('room_id', $asset->room_id)
                ->exists();

            if (!$hasAccess) {
                return $this->errorResponse('Anda tidak memiliki akses melihat informasi aset ini.', 403);
            }

            // Sembunyikan data finansial internal dari penyewa
            $sanitized = $asset->only(['id', 'asset_code', 'name', 'category', 'condition', 'location_type', 'notes']);
            return $this->successResponse($sanitized, 'Informasi inventaris kamar berhasil diambil.');
        }

        // Cek otorisasi staf/manajer
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            return $this->errorResponse('Anda tidak memiliki hak akses pada cabang aset ini.', 403);
        }

        return $this->successResponse($asset->load(['locationHistories', 'maintenanceRequests']), 'Detail aset berhasil diambil.');
    }

    /**
     * Pelaporan Kerusakan / Tiket Perbaikan langsung dari Aset (Scan QR / Mobile).
     */
    public function reportIssue(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $asset = Asset::findOrFail($id);

        // Jika tenant, pastikan aset berada di kamar penyewa
        if ($user->isTenant()) {
            $hasAccess = $user->bookings()
                ->whereIn('status', ['confirmed', 'active'])
                ->where('room_id', $asset->room_id)
                ->exists();

            if (!$hasAccess) {
                return $this->errorResponse('Anda hanya dapat melaporkan kerusakan pada fasilitas kamar Anda.', 403);
            }
        } elseif (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $asset->property_id)->exists()) {
            return $this->errorResponse('Akses ditolak pada cabang properti ini.', 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'photos' => ['nullable', 'array'],
        ]);

        $ticketNumber = 'MNT-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        $maintenance = MaintenanceRequest::create([
            'ticket_number' => $ticketNumber,
            'property_id' => $asset->property_id,
            'room_id' => $asset->room_id,
            'asset_id' => $asset->id,
            'reporter_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => match ($asset->category) {
                'electronics' => 'ac',
                'furniture' => 'furniture',
                'building_infrastructure' => 'plumbing',
                default => 'other'
            },
            'priority' => $validated['priority'] ?? 'normal',
            'status' => 'reported',
            'photos_before' => $validated['photos'] ?? null,
        ]);

        // Update status kondisi aset menjadi perlu perbaikan
        $asset->condition = 'slightly_damaged';
        $asset->lifecycle_status = 'in_maintenance';
        $asset->save();

        return $this->successResponse(
            $maintenance,
            "Laporan perbaikan untuk aset {$asset->name} ({$asset->asset_code}) berhasil diajukan dengan tiket #{$ticketNumber}."
        );
    }
}
