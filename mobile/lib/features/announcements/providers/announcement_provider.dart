import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/announcement_model.dart';

class AnnouncementState {
  final bool isLoading;
  final List<AnnouncementModel> announcements;
  final int unreadCount;
  final String? errorMessage;

  AnnouncementState({
    this.isLoading = false,
    this.announcements = const [],
    this.unreadCount = 0,
    this.errorMessage,
  });

  AnnouncementState copyWith({
    bool? isLoading,
    List<AnnouncementModel>? announcements,
    int? unreadCount,
    String? errorMessage,
  }) {
    return AnnouncementState(
      isLoading: isLoading ?? this.isLoading,
      announcements: announcements ?? this.announcements,
      unreadCount: unreadCount ?? this.unreadCount,
      errorMessage: errorMessage,
    );
  }
}

final announcementProvider = StateNotifierProvider<AnnouncementNotifier, AnnouncementState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AnnouncementNotifier(apiClient);
});

class AnnouncementNotifier extends StateNotifier<AnnouncementState> {
  final ApiClient _apiClient;

  AnnouncementNotifier(this._apiClient) : super(AnnouncementState()) {
    fetchAnnouncements();
  }

  Future<void> fetchAnnouncements({bool? isRead}) async {
    state = state.copyWith(isLoading: true, errorMessage: null);

    try {
      final queryParams = <String, dynamic>{};
      if (isRead != null) {
        queryParams['is_read'] = isRead;
      }

      final response = await _apiClient.dio.get(
        '/announcements',
        queryParameters: queryParams,
      );

      if (response.statusCode == 200 && response.data['success'] == true) {
        final data = response.data['data'];
        final unreadCount = data['unread_count'] as int? ?? 0;
        final list = (data['announcements']['data'] as List<dynamic>?) ?? [];

        final announcements = list.map((item) => AnnouncementModel.fromJson(item as Map<String, dynamic>)).toList();

        state = state.copyWith(
          isLoading: false,
          announcements: announcements,
          unreadCount: unreadCount,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message'] ?? 'Gagal memuat pengumuman.',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Terjadi kesalahan saat memuat pengumuman: $e',
      );
    }
  }

  Future<void> markAsRead(int announcementId) async {
    try {
      final response = await _apiClient.dio.post('/announcements/$announcementId/read');
      if (response.statusCode == 200) {
        final updatedList = state.announcements.map((a) {
          if (a.id == announcementId && !a.isRead) {
            return a.copyWith(isRead: true);
          }
          return a;
        }).toList();

        final newUnread = (state.unreadCount > 0) ? state.unreadCount - 1 : 0;
        state = state.copyWith(
          announcements: updatedList,
          unreadCount: newUnread,
        );
      }
    } catch (_) {}
  }
}
