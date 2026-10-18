import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _notifications = [];
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.get('/notifications');

      if (response.statusCode == 200 && response.data['success'] == true) {
        final list = (response.data['data']['data'] as List<dynamic>?) ?? [];
        setState(() {
          _notifications = list.cast<Map<String, dynamic>>();
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = response.data['message'] ?? 'Gagal memuat notifikasi.';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Gagal memuat notifikasi: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllRead() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.post('/notifications/read-all');
      if (response.statusCode == 200) {
        _fetchNotifications();
      }
    } catch (_) {}
  }

  Future<void> _markSingleRead(int id) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/notifications/$id/read');
      setState(() {
        final index = _notifications.indexWhere((n) => n['id'] == id);
        if (index != -1) {
          _notifications[index]['read_at'] = DateTime.now().toIso8601String();
        }
      });
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi & Pembaruan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all_rounded),
            tooltip: 'Tandai Semua Dibaca',
            onPressed: _notifications.isEmpty ? null : _markAllRead,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchNotifications,
        child: _isLoading && _notifications.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : _errorMessage != null && _notifications.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline_rounded, size: 48, color: AppColors.danger),
                          const SizedBox(height: 12),
                          Text(_errorMessage!, textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          ElevatedButton(onPressed: _fetchNotifications, child: const Text('Coba Lagi')),
                        ],
                      ),
                    ),
                  )
                : _notifications.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 120),
                          Center(
                            child: Column(
                              children: [
                                Icon(Icons.notifications_none_rounded, size: 64, color: AppColors.textMuted),
                                SizedBox(height: 12),
                                Text('Belum ada notifikasi baru.', style: TextStyle(color: AppColors.textSecondary)),
                              ],
                            ),
                          ),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: _notifications.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final item = _notifications[index];
                          final isRead = item['read_at'] != null;
                          final dateStr = item['created_at'] != null
                              ? DateFormat('dd MMM yyyy, HH:mm').format(DateTime.parse(item['created_at']))
                              : '';

                          return Card(
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(color: isRead ? AppColors.border : AppColors.primary.withAlpha(100)),
                            ),
                            color: isRead ? Colors.white : AppColors.primary.withAlpha(10),
                            child: InkWell(
                              onTap: () {
                                if (!isRead) _markSingleRead(item['id']);
                              },
                              borderRadius: BorderRadius.circular(12),
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(8),
                                      decoration: BoxDecoration(
                                        color: isRead ? Colors.grey.withAlpha(20) : AppColors.primary.withAlpha(25),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Icon(
                                        Icons.notifications_active_rounded,
                                        color: isRead ? AppColors.textSecondary : AppColors.primary,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                            children: [
                                              Expanded(
                                                child: Text(
                                                  item['title'] ?? 'Pemberitahuan',
                                                  style: TextStyle(
                                                    fontWeight: isRead ? FontWeight.w600 : FontWeight.bold,
                                                    fontSize: 14,
                                                    color: AppColors.textPrimary,
                                                  ),
                                                ),
                                              ),
                                              Text(dateStr, style: const TextStyle(fontSize: 10, color: AppColors.textMuted)),
                                            ],
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            item['message'] ?? '',
                                            style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, height: 1.3),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
      ),
    );
  }
}
