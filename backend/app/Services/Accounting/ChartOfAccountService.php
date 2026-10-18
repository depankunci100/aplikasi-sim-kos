<?php

namespace App\Services\Accounting;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Property;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChartOfAccountService
{
    /**
     * Definisi akun standar bawaan sistem SIM Kos & Guesthouse
     */
    public const DEFAULT_ACCOUNTS = [
        // ASET (1xxx)
        [
            'code' => '1101',
            'name' => 'Kas Tunai Properti',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'cash_bank',
            'description' => 'Kas fisik tunai di lokasi properti/kasir.',
            'is_system' => true,
        ],
        [
            'code' => '1102',
            'name' => 'Rekening Bank Operasional',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'cash_bank',
            'description' => 'Rekening bank operasional utama properti.',
            'is_system' => true,
        ],
        [
            'code' => '1103',
            'name' => 'Kas Titipan Uang Jaminan',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'cash_bank',
            'description' => 'Rekening / penampungan khusus uang jaminan penyewa.',
            'is_system' => true,
        ],
        [
            'code' => '1201',
            'name' => 'Piutang Sewa Kamar',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'receivable',
            'description' => 'Tagihan sewa kamar yang belum dilunasi penyewa.',
            'is_system' => true,
        ],
        [
            'code' => '1202',
            'name' => 'Piutang Utilitas Listrik & Air',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'receivable',
            'description' => 'Tagihan utilitas meteran belum dilunasi.',
            'is_system' => true,
        ],
        [
            'code' => '1203',
            'name' => 'Piutang Denda & Ganti Rugi',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'receivable',
            'description' => 'Tagihan kerusakan/denda check-out melebihi deposit.',
            'is_system' => true,
        ],
        [
            'code' => '1301',
            'name' => 'Aset Tetap - Bangunan & Prasarana',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'fixed_asset',
            'description' => 'Nilai perolehan bangunan, pagar, instalasi, dan prasarana fisik.',
            'is_system' => true,
        ],
        [
            'code' => '1302',
            'name' => 'Aset Tetap - Peralatan & Elektronik',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'fixed_asset',
            'description' => 'Nilai perolehan mesin cuci, AC, kulkas, CCTV, router, dll.',
            'is_system' => true,
        ],
        [
            'code' => '1303',
            'name' => 'Aset Tetap - Furnitur & Mebel',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'fixed_asset',
            'description' => 'Nilai perolehan tempat tidur, lemari, meja, dan kursi kamar/gedung.',
            'is_system' => true,
        ],
        [
            'code' => '1304',
            'name' => 'Aset Tetap - Kendaraan Operasional',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'fixed_asset',
            'description' => 'Nilai perolehan motor dan kendaraan operasional kos.',
            'is_system' => true,
        ],
        [
            'code' => '1391',
            'name' => 'Akumulasi Penyusutan Aset Tetap',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'accumulated_depreciation',
            'description' => 'Kontra akun aset: akumulasi penyusutan seluruh aset tetap operasional.',
            'is_system' => true,
        ],

        // KEWAJIBAN (2xxx)
        [
            'code' => '2101',
            'name' => 'Titipan Uang Jaminan (Deposit)',
            'type' => AccountType::LIABILITY,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'current_liability',
            'description' => 'Kewajiban uang jaminan penyewa yang ditahan sampai check-out.',
            'is_system' => true,
        ],
        [
            'code' => '2102',
            'name' => 'Pendapatan Sewa Diterima di Muka',
            'type' => AccountType::LIABILITY,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'current_liability',
            'description' => 'Pembayaran uang sewa atau DP untuk periode sewa mendatang.',
            'is_system' => true,
        ],
        [
            'code' => '2201',
            'name' => 'Utang Biaya Operasional (AP)',
            'type' => AccountType::LIABILITY,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'payable',
            'description' => 'Tagihan biaya operasional vendor yang belum dibayar.',
            'is_system' => true,
        ],

        // EKUITAS (3xxx)
        [
            'code' => '3101',
            'name' => 'Modal Pemilik Properti',
            'type' => AccountType::EQUITY,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'equity',
            'description' => 'Modal awal dan penambahan modal dari pemilik kos.',
            'is_system' => true,
        ],
        [
            'code' => '3201',
            'name' => 'Saldo Laba Ditahan',
            'type' => AccountType::EQUITY,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'equity',
            'description' => 'Akumulasi laba bersih periode-periode sebelumnya.',
            'is_system' => true,
        ],

        // PENDAPATAN (4xxx)
        [
            'code' => '4101',
            'name' => 'Pendapatan Sewa Kamar Kos',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'operating_revenue',
            'description' => 'Pendapatan operasional dari sewa kamar kos periode berjalan.',
            'is_system' => true,
        ],
        [
            'code' => '4102',
            'name' => 'Pendapatan Sewa Guesthouse',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'operating_revenue',
            'description' => 'Pendapatan sewa harian / kamar guesthouse.',
            'is_system' => true,
        ],
        [
            'code' => '4103',
            'name' => 'Pendapatan Utilitas',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'operating_revenue',
            'description' => 'Pendapatan dari tagihan listrik, air, dan utilitas penyewa.',
            'is_system' => true,
        ],
        [
            'code' => '4104',
            'name' => 'Pendapatan Denda & Ganti Rugi Kerusakan',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'operating_revenue',
            'description' => 'Pendapatan sah dari denda keterlambatan atau potongan kerusakan kamar.',
            'is_system' => true,
        ],
        [
            'code' => '4105',
            'name' => 'Pendapatan Operasional Lainnya',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'operating_revenue',
            'description' => 'Pendapatan dari fasilitas tambahan seperti parkir, laundry, dll.',
            'is_system' => true,
        ],
        [
            'code' => '4201',
            'name' => 'Keuntungan Pelepasan Aset Tetap',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'category' => 'other_revenue',
            'description' => 'Keuntungan modal dari penjualan atau pelepasan aset tetap di atas nilai buku.',
            'is_system' => true,
        ],

        // BEBAN (5xxx)
        [
            'code' => '5101',
            'name' => 'Beban Listrik & Air',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Beban tagihan listrik PLN dan air PDAM gedung.',
            'is_system' => true,
        ],
        [
            'code' => '5102',
            'name' => 'Beban Internet & Komunikasi',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Beban langganan WiFi, internet fiber, dan pulsa operasional.',
            'is_system' => true,
        ],
        [
            'code' => '5103',
            'name' => 'Beban Pemeliharaan & Perbaikan',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Beban reparasi AC, plumbing, cat, dan sarana gedung.',
            'is_system' => true,
        ],
        [
            'code' => '5104',
            'name' => 'Beban Kebersihan & Housekeeping',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Beban sabun, deterjen, iuran sampah, dan kebersihan.',
            'is_system' => true,
        ],
        [
            'code' => '5105',
            'name' => 'Beban Gaji & Upah Staf',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Gaji resepsionis, staf kebersihan, dan penjaga kos.',
            'is_system' => true,
        ],
        [
            'code' => '5106',
            'name' => 'Beban Perlengkapan & Operasional',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'operating_expense',
            'description' => 'Beban administrasi, ATK, perizinan, dan pengeluaran umum lainnya.',
            'is_system' => true,
        ],
        [
            'code' => '5201',
            'name' => 'Beban Penyusutan Aset Tetap',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'depreciation_expense',
            'description' => 'Beban penyusutan periodik aset tetap operasional gedung kos.',
            'is_system' => true,
        ],
        [
            'code' => '5301',
            'name' => 'Kerugian Pelepasan Aset Tetap',
            'type' => AccountType::EXPENSE,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'category' => 'other_expense',
            'description' => 'Kerugian modal dari penghapusan atau penjualan aset tetap di bawah nilai buku.',
            'is_system' => true,
        ],
    ];

    /**
     * Pastikan akun standar dan pemetaan bawaan telah dibuat untuk properti tertentu.
     */
    public function ensureDefaultAccountsForProperty(int $propertyId): void
    {
        DB::transaction(function () use ($propertyId) {
            foreach (self::DEFAULT_ACCOUNTS as $acc) {
                Account::firstOrCreate(
                    [
                        'property_id' => $propertyId,
                        'code' => $acc['code'],
                    ],
                    [
                        'name' => $acc['name'],
                        'type' => $acc['type']->value,
                        'normal_balance' => $acc['normal_balance']->value,
                        'category' => $acc['category'],
                        'description' => $acc['description'],
                        'is_active' => true,
                        'is_system' => $acc['is_system'],
                    ]
                );
            }

            // Inisialisasi Account Mappings bawaan
            $this->ensureDefaultMappingsForProperty($propertyId);
        });
    }

    /**
     * Inisialisasi pemetaan transaksi otomatis default untuk properti
     */
    public function ensureDefaultMappingsForProperty(int $propertyId): void
    {
        $accounts = Account::where('property_id', $propertyId)->get()->keyBy('code');

        $mappings = [
            'payment_rent_current' => [
                'debit' => '1102', // Bank Operasional
                'credit' => '4101', // Pendapatan Sewa Kamar Kos
                'description' => 'Pembayaran sewa kamar periode berjalan',
            ],
            'payment_rent_advance' => [
                'debit' => '1102',
                'credit' => '2102', // Pendapatan Diterima di Muka
                'description' => 'Pembayaran sewa kamar / DP periode mendatang',
            ],
            'payment_guesthouse' => [
                'debit' => '1102',
                'credit' => '4102', // Pendapatan Sewa Guesthouse
                'description' => 'Pembayaran sewa kamar guesthouse',
            ],
            'payment_deposit' => [
                'debit' => '1102',
                'credit' => '2101', // Titipan Uang Jaminan
                'description' => 'Penerimaan uang jaminan penyewa',
            ],
            'payment_utility' => [
                'debit' => '1102',
                'credit' => '4103', // Pendapatan Utilitas
                'description' => 'Pembayaran tagihan utilitas listrik & air',
            ],
            'checkout_damage_deduction' => [
                'debit' => '2101', // Titipan Uang Jaminan
                'credit' => '4104', // Pendapatan Denda & Ganti Rugi Kerusakan
                'description' => 'Pemotongan uang jaminan untuk denda kerusakan',
            ],
            'checkout_refund' => [
                'debit' => '2101', // Titipan Uang Jaminan
                'credit' => '1102', // Bank Operasional
                'description' => 'Pengembalian sisa uang jaminan ke penyewa',
            ],
        ];

        foreach ($mappings as $trxType => $cfg) {
            $debitAcc = $accounts->get($cfg['debit']);
            $creditAcc = $accounts->get($cfg['credit']);

            if ($debitAcc && $creditAcc) {
                AccountMapping::firstOrCreate(
                    [
                        'property_id' => $propertyId,
                        'transaction_type' => $trxType,
                    ],
                    [
                        'debit_account_id' => $debitAcc->id,
                        'credit_account_id' => $creditAcc->id,
                        'description' => $cfg['description'],
                    ]
                );
            }
        }
    }

    /**
     * Dapatkan akun berdasarkan kode untuk properti tertentu
     */
    public function getAccountByCode(int $propertyId, string $code): Account
    {
        $this->ensureDefaultAccountsForProperty($propertyId);

        return Account::where('property_id', $propertyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Dapatkan akun Kas atau Bank berdasarkan metode pembayaran
     */
    public function getCashOrBankAccount(int $propertyId, string $paymentMethod): Account
    {
        $this->ensureDefaultAccountsForProperty($propertyId);

        $code = ($paymentMethod === 'cash') ? '1101' : '1102';

        return Account::where('property_id', $propertyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Dapatkan akun beban berdasarkan kode kategori pengeluaran
     */
    public function getExpenseAccountByCategory(int $propertyId, ?string $categoryCode): Account
    {
        $this->ensureDefaultAccountsForProperty($propertyId);

        $code = match ($categoryCode) {
            'electricity_water', 'electricity', 'water' => '5101',
            'internet' => '5102',
            'maintenance' => '5103',
            'housekeeping' => '5104',
            'salaries' => '5105',
            default => '5106',
        };

        return Account::where('property_id', $propertyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Dapatkan akun harga perolehan aset tetap (13xx) berdasarkan kategori aset
     */
    public function getAssetCostAccountByCategory(int $propertyId, ?string $category): Account
    {
        $this->ensureDefaultAccountsForProperty($propertyId);

        $code = match ($category) {
            'building_infrastructure' => '1301', // Bangunan & Prasarana
            'electronics' => '1302', // Peralatan & Elektronik
            'furniture' => '1303', // Furnitur & Mebel
            'vehicles' => '1304', // Kendaraan Operasional
            default => '1302',
        };

        return Account::where('property_id', $propertyId)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Buat akun baru custom
     */
    public function createAccount(int $propertyId, array $data): Account
    {
        $existing = Account::where('property_id', $propertyId)
            ->where('code', $data['code'])
            ->exists();

        if ($existing) {
            throw new DomainException("Kode akun '{$data['code']}' sudah digunakan pada properti ini.");
        }

        $type = AccountType::from($data['type']);
        $normalBalance = isset($data['normal_balance'])
            ? AccountNormalBalance::from($data['normal_balance'])
            : $type->defaultNormalBalance();

        return Account::create([
            'property_id' => $propertyId,
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'type' => $type,
            'normal_balance' => $normalBalance,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ]);
    }

    /**
     * Perbarui akun
     */
    public function updateAccount(Account $account, array $data): Account
    {
        if ($account->is_system && isset($data['code']) && $data['code'] !== $account->code) {
            throw new DomainException("Kode akun bawaan sistem ({$account->code}) tidak boleh diubah.");
        }

        if (isset($data['code']) && $data['code'] !== $account->code) {
            $existing = Account::where('property_id', $account->property_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $account->id)
                ->exists();

            if ($existing) {
                throw new DomainException("Kode akun '{$data['code']}' sudah digunakan.");
            }
        }

        $account->update(array_filter([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : null,
        ], fn($v) => $v !== null));

        return $account->fresh();
    }

    /**
     * Hapus akun custom (akun sistem dilarang dihapus)
     */
    public function deleteAccount(Account $account): bool
    {
        if ($account->is_system) {
            throw new DomainException("Akun bawaan sistem dilarang dihapus.");
        }

        if ($account->journalLines()->exists()) {
            throw new DomainException("Akun '{$account->code} - {$account->name}' memiliki riwayat transaksi jurnal dan tidak dapat dihapus. Anda dapat menonaktifkannya.");
        }

        return $account->delete();
    }
}
