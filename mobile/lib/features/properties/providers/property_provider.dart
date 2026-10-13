import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/property_model.dart';

final propertiesProvider = FutureProvider<List<PropertyModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.properties);
    if (response.data['success'] == true) {
      final list = response.data['data'] as List<dynamic>;
      return list.map((e) => PropertyModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (e) {
    // Return sample local fallback if backend is offline during early local development
    return [
      PropertyModel(
        id: 1,
        code: 'PROP-MLG-01',
        name: 'Wisma Melati Harmoni & Guesthouse',
        type: 'hybrid',
        address: 'Jl. Bendungan Sigura-gura No. 18, Lowokwaru',
        city: 'Malang',
        facilities: ['WiFi 100Mbps', 'Parkir Motor & Mobil', 'CCTV 24 Jam', 'Dapur Bersama'],
        totalRooms: 6,
        availableRooms: 6,
      ),
    ];
  }
  return [];
});
