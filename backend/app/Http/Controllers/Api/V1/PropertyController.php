<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::query()
            ->where('is_active', true)
            ->withCount(['rooms as total_rooms', 'rooms as available_rooms' => function ($query) {
                $query->where('status', 'available');
            }])
            ->get();

        return $this->successResponse($properties, 'Daftar properti berhasil diambil.');
    }

    public function show(int $id): JsonResponse
    {
        $property = Property::with(['roomTypes.rooms'])->find($id);

        if (!$property) {
            return $this->errorResponse('Properti tidak ditemukan.', 404);
        }

        return $this->successResponse($property, 'Detail properti berhasil diambil.');
    }
}
