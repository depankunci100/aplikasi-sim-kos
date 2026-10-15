import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/expense_model.dart';

final expenseListProvider = FutureProvider.autoDispose<List<ExpenseModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.expenses);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => ExpenseModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    // Fallback data for offline test or preview
    return [
      ExpenseModel(
        id: 1,
        expenseNumber: 'EXP-20261016-0001',
        propertyId: 1,
        propertyName: 'Kos Permata Hijau',
        categoryName: 'Listrik & Air',
        title: 'Pembelian Token Listrik Induk',
        vendorName: 'PLN Mobile',
        amount: 500000,
        paidAmount: 500000,
        remainingBalance: 0,
        expenseDate: DateTime.now().subtract(const Duration(days: 2)),
        approvalStatus: 'approved',
        paymentStatus: 'paid',
        paymentMethod: 'bank_transfer',
        submitterName: 'Agus Staff',
        approverName: 'Haji Huda',
      ),
      ExpenseModel(
        id: 2,
        expenseNumber: 'EXP-20261016-0002',
        propertyId: 1,
        propertyName: 'Kos Permata Hijau',
        categoryName: 'Perbaikan & Maintenance',
        title: 'Perbaikan Pipa Air Bocor',
        vendorName: 'Toko Bangunan Berkah',
        amount: 250000,
        paidAmount: 0,
        remainingBalance: 250000,
        expenseDate: DateTime.now().subtract(const Duration(hours: 4)),
        approvalStatus: 'submitted',
        paymentStatus: 'unpaid',
        paymentMethod: 'cash',
        submitterName: 'Budi Teknisi',
      ),
    ];
  }
  return [];
});

final expenseCategoriesProvider = FutureProvider.autoDispose<List<ExpenseCategoryModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.expenseCategories);
    if (response.data['success'] == true) {
      final list = response.data['data'] as List<dynamic>? ?? [];
      return list.map((e) => ExpenseCategoryModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    return [
      ExpenseCategoryModel(id: 1, name: 'Listrik & Air', code: 'electricity_water'),
      ExpenseCategoryModel(id: 2, name: 'Internet & WiFi', code: 'internet'),
      ExpenseCategoryModel(id: 3, name: 'Perbaikan & Maintenance', code: 'maintenance'),
      ExpenseCategoryModel(id: 4, name: 'Kebersihan & Linen', code: 'housekeeping'),
      ExpenseCategoryModel(id: 5, name: 'Gaji & Tenaga Kerja', code: 'salaries'),
      ExpenseCategoryModel(id: 6, name: 'Perlengkapan & ATK', code: 'supplies'),
      ExpenseCategoryModel(id: 7, name: 'Pajak & Biaya Admin', code: 'taxes_admin'),
      ExpenseCategoryModel(id: 8, name: 'Pengeluaran Lainnya', code: 'other'),
    ];
  }
  return [];
});
