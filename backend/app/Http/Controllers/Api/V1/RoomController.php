<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends ApiController
{
    public function index(Request $request, int $propertyId): JsonResponse
    {
        $rooms = Room::query()
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->with(['roomType'])
            ->get();

        return $this->successResponse($rooms, 'Daftar kamar berhasil diambil.');
    }

    public function show(int $id): JsonResponse
    {
        $room = Room::with(['property', 'roomType'])->find($id);

        if (!$room) {
            return $this->errorResponse('Kamar tidak ditemukan.', 404);
        }

        return $this->successResponse($room, 'Detail kamar berhasil diambil.');
    }
}
