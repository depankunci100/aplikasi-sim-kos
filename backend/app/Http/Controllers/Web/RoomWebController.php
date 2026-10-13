<?php

namespace App\Http\Controllers\Web;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomWebController extends Controller
{
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();

        $room = Room::findOrFail($id);

        if (!$user->isSuperAdmin()) {
            $isAssigned = DB::table('property_users')
                ->where('property_id', $room->property_id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isAssigned) {
                return back()->with('error', 'Anda tidak memiliki hak mengubah status kamar pada cabang ini.');
            }
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(RoomStatus::class)],
        ]);

        $oldStatus = $room->status->label();
        $room->status = RoomStatus::from($validated['status']);
        $room->save();

        return back()->with(
            'success',
            "Status kamar {$room->room_number} berhasil diubah dari '{$oldStatus}' menjadi '{$room->status->label()}'."
        );
    }
}
