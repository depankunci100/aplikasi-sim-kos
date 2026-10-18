<?php

namespace App\Services\Asset;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetDepreciationService
{
    public function __construct(
        protected ChartOfAccountService $coaService,
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Hitung preview jadwal penyusutan garis lurus lengkap selama sisa masa manfaat aset.
     */
    public function generateDepreciationPreview(Asset $asset): array
    {
        if (!$asset->isDepreciable()) {
            return [];
        }

        $purchaseCost = (float) $asset->purchase_cost;
        $salvageValue = (float) $asset->salvage_value;
        $usefulLifeMonths = (int) $asset->useful_life_months;
        $monthlyAmount = $asset->calculateMonthlyDepreciation();

        $startDate = $asset->in_service_date ? Carbon::parse($asset->in_service_date) : Carbon::parse($asset->acquisition_date);
        $schedules = [];

        $runningAccumulated = (float) $asset->accumulated_depreciation;
        $runningBookValue = (float) $asset->current_book_value;

        // Dapatkan jadwal yang sudah tersimpan / diposting
        $existingSchedules = AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->get()
            ->keyBy(fn($item) => "{$item->fiscal_year}-{$item->fiscal_month}");

        $totalPeriods = $usefulLifeMonths;

        for ($i = 0; $i < $totalPeriods; $i++) {
            $periodDate = (clone $startDate)->addMonthsNoOverflow($i)->endOfMonth();
            $year = (int) $periodDate->format('Y');
            $month = (int) $periodDate->format('n');
            $key = "{$year}-{$month}";

            if ($existingSchedules->has($key)) {
                $saved = $existingSchedules->get($key);
                $schedules[] = [
                    'schedule_id' => $saved->id,
                    'fiscal_year' => $saved->fiscal_year,
                    'fiscal_month' => $saved->fiscal_month,
                    'period_date' => $saved->period_date->toDateString(),
                    'depreciation_amount' => (float) $saved->depreciation_amount,
                    'accumulated_before' => (float) $saved->accumulated_depreciation_before,
                    'accumulated_after' => (float) $saved->accumulated_depreciation_after,
                    'book_value_beginning' => (float) $saved->book_value_beginning,
                    'book_value_ending' => (float) $saved->book_value_ending,
                    'status' => $saved->status,
                    'journal_number' => $saved->journalEntry?->journal_number,
                    'is_persisted' => true,
                ];
                continue;
            }

            // Jika nilai buku sudah mencapai atau kurang dari nilai residu
            if ($runningBookValue <= $salvageValue) {
                break;
            }

            $currentDepr = $monthlyAmount;
            // Batasi agar nilai buku akhir tidak di bawah nilai residu
            if (($runningBookValue - $currentDepr) < $salvageValue) {
                $currentDepr = round($runningBookValue - $salvageValue, 2);
            }

            if ($currentDepr <= 0) {
                break;
            }

            $accumBefore = $runningAccumulated;
            $accumAfter = round($runningAccumulated + $currentDepr, 2);
            $bookBefore = $runningBookValue;
            $bookAfter = round($runningBookValue - $currentDepr, 2);

            $schedules[] = [
                'schedule_id' => null,
                'fiscal_year' => $year,
                'fiscal_month' => $month,
                'period_date' => $periodDate->toDateString(),
                'depreciation_amount' => $currentDepr,
                'accumulated_before' => $accumBefore,
                'accumulated_after' => $accumAfter,
                'book_value_beginning' => $bookBefore,
                'book_value_ending' => $bookAfter,
                'status' => 'preview',
                'journal_number' => null,
                'is_persisted' => false,
            ];

            $runningAccumulated = $accumAfter;
            $runningBookValue = $bookAfter;
        }

        return $schedules;
    }

    /**
     * Posting jurnal penyusutan periodik untuk 1 aset pada periode (tahun & bulan) tertentu.
     * Menggunakan Idempotency Key dan Atomic Database Transaction.
     */
    public function postSingleDepreciation(
        Asset $asset,
        int $year,
        int $month,
        User $actor,
        ?string $notes = null
    ): AssetDepreciationSchedule {
        if (!$asset->isDepreciable()) {
            throw new DomainException("Aset #{$asset->asset_code} bukan aset tetap yang dapat disusutkan.");
        }

        if ($asset->isFullyDepreciated()) {
            throw new DomainException("Aset #{$asset->asset_code} sudah habis masa manfaatnya (nilai buku telah mencapai nilai residu).");
        }

        $propertyId = $asset->property_id;
        $idempotencyKey = "asset_depreciation_{$asset->id}_{$year}_{$month}";

        // Cek jika schedule ini sudah pernah diposting sebelumnya
        $existing = AssetDepreciationSchedule::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->status === 'posted') {
            return $existing->load('journalEntry');
        }

        return DB::transaction(function () use ($asset, $propertyId, $year, $month, $actor, $notes, $idempotencyKey) {
            // Lock asset row untuk mencegah race condition concurrency
            $lockedAsset = Asset::where('id', $asset->id)->lockForUpdate()->firstOrFail();

            $salvageValue = (float) $lockedAsset->salvage_value;
            $currentBookValue = (float) $lockedAsset->current_book_value;

            if ($currentBookValue <= $salvageValue) {
                throw new DomainException("Aset #{$lockedAsset->asset_code} sudah mencapai nilai residu.");
            }

            $monthlyAmount = $lockedAsset->calculateMonthlyDepreciation();
            if (($currentBookValue - $monthlyAmount) < $salvageValue) {
                $monthlyAmount = round($currentBookValue - $salvageValue, 2);
            }

            if ($monthlyAmount <= 0) {
                throw new DomainException("Nilai penyusutan periodik untuk aset #{$lockedAsset->asset_code} adalah Rp 0.");
            }

            $accumBefore = (float) $lockedAsset->accumulated_depreciation;
            $accumAfter = round($accumBefore + $monthlyAmount, 2);
            $bookBefore = $currentBookValue;
            $bookAfter = round($currentBookValue - $monthlyAmount, 2);

            $periodDate = Carbon::create($year, $month, 1)->endOfMonth();

            // Siapkan Akun Jurnal:
            // Debit: Beban Penyusutan (5201)
            // Kredit: Akumulasi Penyusutan (1391)
            $this->coaService->ensureDefaultAccountsForProperty($propertyId);
            $deprExpenseAcc = $this->coaService->getAccountByCode($propertyId, '5201');
            $accumDeprAcc = $this->coaService->getAccountByCode($propertyId, '1391');

            $lines = [
                [
                    'account_id' => $deprExpenseAcc->id,
                    'debit' => $monthlyAmount,
                    'credit' => 0.00,
                    'memo' => "Beban penyusutan aset {$lockedAsset->name} ({$lockedAsset->asset_code}) periode {$month}/{$year}",
                ],
                [
                    'account_id' => $accumDeprAcc->id,
                    'debit' => 0.00,
                    'credit' => $monthlyAmount,
                    'memo' => "Akumulasi penyusutan aset {$lockedAsset->name} ({$lockedAsset->asset_code})",
                ],
            ];

            $desc = "Penyusutan Aset Tetap: {$lockedAsset->name} ({$lockedAsset->asset_code}) Periode {$month}/{$year}";

            $journal = $this->journalPostingService->postJournal(
                $propertyId,
                'asset_depreciation',
                $lockedAsset->id,
                $idempotencyKey,
                $desc,
                $lines,
                $periodDate,
                $actor,
                $notes
            );

            // Simpan / update schedule
            $schedule = AssetDepreciationSchedule::updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'asset_id' => $lockedAsset->id,
                    'property_id' => $propertyId,
                    'fiscal_year' => $year,
                    'fiscal_month' => $month,
                    'period_date' => $periodDate->toDateString(),
                    'depreciation_amount' => $monthlyAmount,
                    'accumulated_depreciation_before' => $accumBefore,
                    'accumulated_depreciation_after' => $accumAfter,
                    'book_value_beginning' => $bookBefore,
                    'book_value_ending' => $bookAfter,
                    'status' => 'posted',
                    'journal_entry_id' => $journal->id,
                    'posted_at' => now(),
                ]
            );

            // Update saldo aset
            $lockedAsset->accumulated_depreciation = $accumAfter;
            $lockedAsset->current_book_value = $bookAfter;
            $lockedAsset->save();

            return $schedule->load('journalEntry');
        });
    }

    /**
     * Batch Posting Penyusutan untuk seluruh aset yang memenuhi syarat dalam properti tertentu.
     */
    public function batchPostDepreciation(
        int $propertyId,
        int $year,
        int $month,
        User $actor,
        bool $dryRun = false
    ): array {
        $assets = Asset::where('property_id', $propertyId)
            ->where('classification', 'fixed_asset')
            ->where('depreciation_method', 'straight_line')
            ->where('lifecycle_status', 'active')
            ->get();

        $results = [
            'total_assets' => $assets->count(),
            'eligible' => 0,
            'posted' => 0,
            'skipped' => 0,
            'total_amount' => 0.00,
            'items' => [],
            'dry_run' => $dryRun,
        ];

        foreach ($assets as $asset) {
            if (!$asset->isDepreciable() || $asset->isFullyDepreciated()) {
                $results['skipped']++;
                continue;
            }

            $idempotencyKey = "asset_depreciation_{$asset->id}_{$year}_{$month}";
            $alreadyPosted = AssetDepreciationSchedule::where('idempotency_key', $idempotencyKey)
                ->where('status', 'posted')
                ->exists();

            if ($alreadyPosted) {
                $results['skipped']++;
                continue;
            }

            $amount = $asset->calculateMonthlyDepreciation();
            if (($asset->current_book_value - $amount) < $asset->salvage_value) {
                $amount = round($asset->current_book_value - $asset->salvage_value, 2);
            }

            if ($amount <= 0) {
                $results['skipped']++;
                continue;
            }

            $results['eligible']++;
            $results['total_amount'] += $amount;

            if (!$dryRun) {
                try {
                    $schedule = $this->postSingleDepreciation($asset, $year, $month, $actor, "Batch posting depresiasi {$month}/{$year}");
                    $results['posted']++;
                    $results['items'][] = [
                        'asset_code' => $asset->asset_code,
                        'name' => $asset->name,
                        'amount' => $amount,
                        'status' => 'success',
                        'journal_number' => $schedule->journalEntry?->journal_number,
                    ];
                } catch (\Exception $e) {
                    $results['items'][] = [
                        'asset_code' => $asset->asset_code,
                        'name' => $asset->name,
                        'amount' => $amount,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $results['items'][] = [
                    'asset_code' => $asset->asset_code,
                    'name' => $asset->name,
                    'amount' => $amount,
                    'status' => 'preview',
                ];
            }
        }

        $results['total_amount'] = round($results['total_amount'], 2);
        return $results;
    }
}
