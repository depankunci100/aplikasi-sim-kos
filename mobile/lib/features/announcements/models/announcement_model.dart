class AnnouncementModel {
  final int id;
  final int? propertyId;
  final String? propertyName;
  final String? authorName;
  final String title;
  final String content;
  final String? attachmentPath;
  final String targetRole;
  final bool isPublished;
  final DateTime? publishedAt;
  final bool isRead;

  AnnouncementModel({
    required this.id,
    this.propertyId,
    this.propertyName,
    this.authorName,
    required this.title,
    required this.content,
    this.attachmentPath,
    this.targetRole = 'all',
    this.isPublished = true,
    this.publishedAt,
    this.isRead = false,
  });

  factory AnnouncementModel.fromJson(Map<String, dynamic> json) {
    return AnnouncementModel(
      id: json['id'] as int,
      propertyId: json['property_id'] as int?,
      propertyName: json['property']?['name'] as String?,
      authorName: json['author']?['name'] as String?,
      title: json['title'] as String? ?? 'Pengumuman',
      content: json['content'] as String? ?? '',
      attachmentPath: json['attachment_path'] as String?,
      targetRole: json['target_role'] as String? ?? 'all',
      isPublished: json['is_published'] as bool? ?? true,
      publishedAt: json['published_at'] != null ? DateTime.tryParse(json['published_at']) : null,
      isRead: json['is_read'] as bool? ?? false,
    );
  }

  AnnouncementModel copyWith({
    bool? isRead,
  }) {
    return AnnouncementModel(
      id: id,
      propertyId: propertyId,
      propertyName: propertyName,
      authorName: authorName,
      title: title,
      content: content,
      attachmentPath: attachmentPath,
      targetRole: targetRole,
      isPublished: isPublished,
      publishedAt: publishedAt,
      isRead: isRead ?? this.isRead,
    );
  }

  String get targetRoleLabel {
    switch (targetRole) {
      case 'tenant':
        return 'Penghuni';
      case 'staff':
        return 'Staf';
      default:
        return 'Semua';
    }
  }
}
