import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/asset_model.dart';

final assetsListProvider = FutureProvider.autoDispose<List<AssetModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  final response = await apiClient.dio.get(ApiEndpoints.assets);

  if (response.statusCode == 200 && response.data['success'] == true) {
    final rawData = response.data['data'];
    final List list = rawData is Map && rawData.containsKey('data') ? rawData['data'] as List : (rawData as List);
    return list.map((item) => AssetModel.fromJson(item as Map<String, dynamic>)).toList();
  }

  return [];
});

final assetDetailProvider = FutureProvider.autoDispose.family<AssetModel?, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  final response = await apiClient.dio.get(ApiEndpoints.assetDetail(id));

  if (response.statusCode == 200 && response.data['success'] == true) {
    return AssetModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  return null;
});
