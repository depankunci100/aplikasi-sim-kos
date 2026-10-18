import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/accounting_models.dart';
import '../providers/accounting_providers.dart';

class AccountingHubScreen extends ConsumerStatefulWidget {
  const AccountingHubScreen({super.key});

  @override
  ConsumerState<AccountingHubScreen> createState() => _AccountingHubScreenState();
}

class _AccountingHubScreenState extends ConsumerState<AccountingHubScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  int _selectedReportIndex = 0; // 0: Neraca Saldo, 1: Laba Rugi, 2: Neraca

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user;

    // Proteksi Hak Akses: Penyewa dilarang mengakses modul akuntansi
    if (user?.role == 'tenant') {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Akuntansi & Jurnal'),
          backgroundColor: Colors.white,
          elevation: 0,
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.lock_person_outlined, size: 72, color: AppColors.danger),
                const SizedBox(height: 16),
                const Text(
                  'Akses Dibatasi',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Modul pembukuan berpasangan dan laporan keuangan hanya dapat diakses oleh Pemilik atau Manajer properti.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.textSecondary),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
                  child: const Text('Kembali', style: TextStyle(color: Colors.white)),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Akuntansi & Jurnal',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 1,
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          indicatorWeight: 3,
          tabs: const [
            Tab(icon: Icon(Icons.menu_book), text: 'Jurnal Umum'),
            Tab(icon: Icon(Icons.assessment), text: 'Laporan'),
            Tab(icon: Icon(Icons.sync_alt), text: 'Rekonsiliasi'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildJournalsTab(),
          _buildReportsTab(),
          _buildReconciliationTab(),
        ],
      ),
    );
  }

  // 1. Tab Jurnal Umum
  Widget _buildJournalsTab() {
    final journalsAsync = ref.watch(accountingJournalsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(accountingJournalsProvider),
      child: journalsAsync.when(
        data: (journals) {
          if (journals.isEmpty) {
            return const Center(
              child: Text('Belum ada transaksi jurnal pembukuan.'),
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: journals.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final j = journals[index];
              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(color: Colors.grey.shade200),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _showJournalLinesSheet(j),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.grey.shade100,
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: Colors.grey.shade300),
                              ),
                              child: Text(
                                j.journalNumber,
                                style: const TextStyle(
                                  fontFamily: 'monospace',
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: j.status == 'posted'
                                    ? Colors.green.shade50
                                    : Colors.orange.shade50,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                j.status == 'posted' ? 'Diposting' : 'Dibalik',
                                style: TextStyle(
                                  color: j.status == 'posted' ? Colors.green.shade800 : Colors.orange.shade800,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Text(
                          j.description,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              j.entryDate,
                              style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                            ),
                            Text(
                              Formatters.toRupiah(j.totalAmount),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('Gagal memuat jurnal: $err'),
              ElevatedButton(
                onPressed: () => ref.refresh(accountingJournalsProvider),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Bottom Sheet Rincian Debit & Kredit Jurnal
  void _showJournalLinesSheet(JournalEntryItemModel j) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.65,
          minChildSize: 0.4,
          maxChildSize: 0.9,
          expand: false,
          builder: (context, scrollController) {
            return Padding(
              padding: const EdgeInsets.all(20),
              child: ListView(
                controller: scrollController,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    j.journalNumber,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                  Text(
                    j.description,
                    style: TextStyle(color: Colors.grey.shade700, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Baris Pembukuan (Debit / Kredit):',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                  const SizedBox(height: 8),
                  ...j.lines.map((l) {
                    final isDebit = l.debit > 0;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.grey.shade200),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${l.accountCode} - ${l.accountName}',
                                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                ),
                                if (l.memo != null)
                                  Text(
                                    l.memo!,
                                    style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                                  ),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                isDebit ? 'DEBIT' : 'KREDIT',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: isDebit ? Colors.blue.shade700 : Colors.purple.shade700,
                                ),
                              ),
                              Text(
                                Formatters.toRupiah(isDebit ? l.debit : l.credit),
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                            ],
                          ),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Keseimbangan (Balance):',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        Text(
                          Formatters.toRupiah(j.totalAmount),
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                            color: Colors.green.shade800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  // 2. Tab Laporan Keuangan
  Widget _buildReportsTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: SegmentedButton<int>(
            segments: const [
              ButtonSegment(value: 0, label: Text('Neraca Saldo', style: TextStyle(fontSize: 12))),
              ButtonSegment(value: 1, label: Text('Laba Rugi', style: TextStyle(fontSize: 12))),
              ButtonSegment(value: 2, label: Text('Neraca', style: TextStyle(fontSize: 12))),
            ],
            selected: {_selectedReportIndex},
            onSelectionChanged: (set) {
              setState(() {
                _selectedReportIndex = set.first;
              });
            },
          ),
        ),
        Expanded(
          child: IndexedStack(
            index: _selectedReportIndex,
            children: [
              _buildTrialBalanceView(),
              _buildIncomeStatementView(),
              _buildBalanceSheetView(),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildTrialBalanceView() {
    final tbAsync = ref.watch(accountingTrialBalanceProvider);

    return tbAsync.when(
      data: (tb) {
        return ListView(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: tb.isBalanced ? Colors.green.shade50 : Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(
                    tb.isBalanced ? Icons.check_circle : Icons.warning,
                    color: tb.isBalanced ? Colors.green : Colors.red,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      tb.isBalanced
                          ? 'Neraca Saldo Seimbang (Rp ${Formatters.toRupiah(tb.totalDebit)})'
                          : 'Neraca Saldo Tidak Seimbang!',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: tb.isBalanced ? Colors.green.shade800 : Colors.red.shade800,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            ...tb.rows.map((r) => ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text('${r.code} - ${r.name}', style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(r.typeLabel),
                  trailing: Text(
                    r.endingDebit > 0
                        ? 'D: ${Formatters.toRupiah(r.endingDebit)}'
                        : 'K: ${Formatters.toRupiah(r.endingCredit)}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                )),
          ],
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('Gagal: $e')),
    );
  }

  Widget _buildIncomeStatementView() {
    final isAsync = ref.watch(accountingIncomeStatementProvider);

    return isAsync.when(
      data: (isData) {
        return ListView(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          children: [
            Card(
              color: AppColors.primary,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const Text('Laba Bersih Operasional', style: TextStyle(color: Colors.white70)),
                    const SizedBox(height: 4),
                    Text(
                      Formatters.toRupiah(isData.netIncome),
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 22),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text('Pendapatan Operasional', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ...isData.revenues.map((rev) => ListTile(
                  dense: true,
                  title: Text(rev.name),
                  trailing: Text(
                    Formatters.toRupiah(rev.amount),
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green),
                  ),
                )),
            const Divider(),
            const Text('Beban Operasional', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ...isData.expenses.map((exp) => ListTile(
                  dense: true,
                  title: Text(exp.name),
                  trailing: Text(
                    Formatters.toRupiah(exp.amount),
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
                  ),
                )),
          ],
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('Gagal: $e')),
    );
  }

  Widget _buildBalanceSheetView() {
    final bsAsync = ref.watch(accountingBalanceSheetProvider);

    return bsAsync.when(
      data: (bs) {
        return ListView(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: bs.isBalanced ? Colors.green.shade50 : Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                bs.isBalanced
                    ? 'Total Aset = Kewajiban + Ekuitas (${Formatters.toRupiah(bs.totalAssets)})'
                    : 'Neraca Keuangan Belum Seimbang',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: bs.isBalanced ? Colors.green.shade800 : Colors.red.shade800,
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text('Aset (Assets)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ...bs.assetRows.map((a) => ListTile(
                  dense: true,
                  title: Text(a.name),
                  trailing: Text(Formatters.toRupiah(a.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                )),
            const Divider(),
            const Text('Kewajiban (Liabilities)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ...bs.liabilityRows.map((l) => ListTile(
                  dense: true,
                  title: Text(l.name),
                  trailing: Text(Formatters.toRupiah(l.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                )),
            const Divider(),
            const Text('Ekuitas (Equity)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ...bs.equityRows.map((eq) => ListTile(
                  dense: true,
                  title: Text(eq.name),
                  trailing: Text(Formatters.toRupiah(eq.amount), style: const TextStyle(fontWeight: FontWeight.bold)),
                )),
          ],
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('Gagal: $e')),
    );
  }

  // 3. Tab Rekonsiliasi Subledger
  Widget _buildReconciliationTab() {
    final reconAsync = ref.watch(accountingReconciliationProvider);

    return reconAsync.when(
      data: (recon) {
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildReconciliationCard(
              title: 'Penerimaan Kas & Bank',
              subledgerAmount: recon.cashInflowSubledger,
              journalAmount: recon.cashInflowJournal,
              variance: recon.cashInflowVariance,
              isReconciled: recon.isCashInflowReconciled,
              icon: Icons.south_west,
              color: Colors.green,
            ),
            const SizedBox(height: 12),
            _buildReconciliationCard(
              title: 'Pengeluaran Kas & Bank',
              subledgerAmount: recon.cashOutflowSubledger,
              journalAmount: recon.cashOutflowJournal,
              variance: recon.cashOutflowVariance,
              isReconciled: recon.isCashOutflowReconciled,
              icon: Icons.north_east,
              color: Colors.red,
            ),
            const SizedBox(height: 12),
            _buildReconciliationCard(
              title: 'Kewajiban Uang Jaminan (Deposit)',
              subledgerAmount: recon.depositSubledger,
              journalAmount: recon.depositJournal,
              variance: recon.depositVariance,
              isReconciled: recon.isDepositReconciled,
              icon: Icons.shield,
              color: Colors.orange,
            ),
          ],
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('Gagal memuat rekonsiliasi: $e')),
    );
  }

  Widget _buildReconciliationCard({
    required String title,
    required num subledgerAmount,
    required num journalAmount,
    required num variance,
    required bool isReconciled,
    required IconData icon,
    required Color color,
  }) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: isReconciled ? Colors.green.shade50 : Colors.red.shade50,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    isReconciled ? 'Cocok' : 'Selisih',
                    style: TextStyle(
                      color: isReconciled ? Colors.green.shade800 : Colors.red.shade800,
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Subledger Operasional:', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                Text(Formatters.toRupiah(subledgerAmount), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Jurnal Buku Besar:', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                Text(Formatters.toRupiah(journalAmount), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              ],
            ),
            const Divider(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Selisih (Variance):', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                Text(
                  Formatters.toRupiah(variance),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isReconciled ? Colors.green : Colors.red,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
