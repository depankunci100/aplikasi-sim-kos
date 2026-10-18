<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'author_id',
        'title',
        'content',
        'attachment_path',
        'target_role',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isTenant()) {
            $activePropertyIds = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                ->pluck('property_id')
                ->filter()
                ->unique()
                ->toArray();

            return $query->published()
                ->where(function ($q) use ($activePropertyIds) {
                    $q->whereNull('property_id')
                        ->orWhereIn('property_id', $activePropertyIds);
                })
                ->whereIn('target_role', ['all', 'tenant']);
        }

        // Staf, Resepsionis, Manajer, Owner
        $accessiblePropertyIds = DB::table('property_users')
            ->where('user_id', $user->id)
            ->pluck('property_id')
            ->toArray();

        $allowedRoles = ['all'];
        if ($user->isStaff() || $user->isCashier()) {
            $allowedRoles[] = 'staff';
        }

        return $query->where(function ($q) use ($accessiblePropertyIds) {
            $q->whereNull('property_id')
                ->orWhereIn('property_id', $accessiblePropertyIds);
        })->whereIn('target_role', $allowedRoles);
    }

    public function isReadBy(User $user): bool
    {
        if ($this->relationLoaded('reads')) {
            return $this->reads->contains('user_id', $user->id);
        }

        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
