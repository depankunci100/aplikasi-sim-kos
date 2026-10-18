<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_number',
        'property_id',
        'entry_date',
        'source_type',
        'source_id',
        'idempotency_key',
        'status',
        'description',
        'notes',
        'total_amount',
        'created_by',
        'posted_at',
        'reversed_journal_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'status' => JournalEntryStatus::class,
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (JournalEntry $journal) {
            if ($journal->status === JournalEntryStatus::POSTED || $journal->status === JournalEntryStatus::REVERSED) {
                throw new \DomainException("Jurnal pembukuan #{$journal->journal_number} dilarang dihapus demi menjaga integritas audit trail akuntansi.");
            }
        });

        static::updating(function (JournalEntry $journal) {
            $originalStatus = $journal->getOriginal('status');
            $originalStatusVal = $originalStatus instanceof JournalEntryStatus ? $originalStatus->value : $originalStatus;

            if ($originalStatusVal === JournalEntryStatus::POSTED->value) {
                $dirty = array_keys($journal->getDirty());
                $allowed = ['status', 'notes', 'reversed_journal_id', 'updated_at'];
                $disallowed = array_diff($dirty, $allowed);
                if (!empty($disallowed)) {
                    throw new \DomainException("Data jurnal #{$journal->journal_number} yang sudah diposting dilarang diubah (" . implode(', ', $disallowed) . "). Gunakan prosedur pembalikan jurnal (reversal).");
                }
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault([
            'name' => 'Sistem Otomatis',
        ]);
    }

    public function reversedJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_journal_id');
    }

    public function reversalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reversed_journal_id');
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === JournalEntryStatus::REVERSED;
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::DRAFT;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.001;
    }
}
