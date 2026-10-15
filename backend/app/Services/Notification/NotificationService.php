<?php

namespace App\Services\Notification;

use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationService
{
    /**
     * Kirim notifikasi internal ke pengguna
     */
    public function createNotification(
        User|int $user,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?int $propertyId = null
    ): InternalNotification {
        $userId = $user instanceof User ? $user->id : $user;

        return InternalNotification::create([
            'user_id' => $userId,
            'property_id' => $propertyId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'read_at' => null,
        ]);
    }

    /**
     * Ambil query notifikasi pengguna
     */
    public function getNotificationsQuery(User $user, bool $unreadOnly = false): Builder
    {
        $query = InternalNotification::where('user_id', $user->id)
            ->latest('id');

        if ($unreadOnly) {
            $query->unread();
        }

        return $query;
    }

    /**
     * Tandai satu notifikasi sebagai telah dibaca
     */
    public function markAsRead(int $id, User $user): bool
    {
        $notification = InternalNotification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($notification && !$notification->read_at) {
            $notification->update(['read_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Tandai seluruh notifikasi pengguna sebagai telah dibaca
     */
    public function markAllAsRead(User $user): int
    {
        return InternalNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
