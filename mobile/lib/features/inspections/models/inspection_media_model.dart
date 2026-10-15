class InspectionMediaModel {
  final int id;
  final int inspectionId;
  final int? inspectionItemId;
  final String filePath;
  final String fileName;
  final String? caption;
  final String mediaType;
  final String? streamUrl;

  InspectionMediaModel({
    required this.id,
    required this.inspectionId,
    this.inspectionItemId,
    required this.filePath,
    required this.fileName,
    this.caption,
    required this.mediaType,
    this.streamUrl,
  });

  factory InspectionMediaModel.fromJson(Map<String, dynamic> json) {
    return InspectionMediaModel(
      id: json['id'] as int? ?? 0,
      inspectionId: json['inspection_id'] as int? ?? 0,
      inspectionItemId: json['inspection_item_id'] as int?,
      filePath: json['file_path'] as String? ?? '',
      fileName: json['file_name'] as String? ?? 'photo.jpg',
      caption: json['caption'] as String?,
      mediaType: json['media_type'] as String? ?? 'image/jpeg',
      streamUrl: json['stream_url'] as String?,
    );
  }
}
