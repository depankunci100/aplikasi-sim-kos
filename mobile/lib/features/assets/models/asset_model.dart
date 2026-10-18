class AssetModel {
  final int id;
  final String assetCode;
  final String name;
  final String category;
  final String classification;
  final String condition;
  final String lifecycleStatus;
  final String? locationLabel;
  final double? purchaseCost;
  final double? currentBookValue;
  final String? serialNumber;
  final String? vendorName;
  final String? notes;

  const AssetModel({
    required this.id,
    required this.assetCode,
    required this.name,
    required this.category,
    required this.classification,
    required this.condition,
    required this.lifecycleStatus,
    this.locationLabel,
    this.purchaseCost,
    this.currentBookValue,
    this.serialNumber,
    this.vendorName,
    this.notes,
  });

  factory AssetModel.fromJson(Map<String, dynamic> json) {
    return AssetModel(
      id: json['id'] as int,
      assetCode: json['asset_code'] as String? ?? '-',
      name: json['name'] as String? ?? '',
      category: json['category'] as String? ?? 'other',
      classification: json['classification'] as String? ?? 'fixed_asset',
      condition: json['condition'] as String? ?? 'good',
      lifecycleStatus: json['lifecycle_status'] as String? ?? 'active',
      locationLabel: json['location_label'] as String?,
      purchaseCost: json['purchase_cost'] != null ? (json['purchase_cost'] as num).toDouble() : null,
      currentBookValue: json['current_book_value'] != null ? (json['current_book_value'] as num).toDouble() : null,
      serialNumber: json['serial_number'] as String?,
      vendorName: json['vendor_name'] as String?,
      notes: json['notes'] as String?,
    );
  }

  String get conditionLabel {
    switch (condition) {
      case 'good':
        return 'Baik';
      case 'slightly_damaged':
        return 'Rusak Ringan';
      case 'broken':
        return 'Rusak Berat';
      case 'missing':
        return 'Hilang';
      default:
        return condition;
    }
  }

  String get statusLabel {
    switch (lifecycleStatus) {
      case 'active':
        return 'Aktif';
      case 'in_maintenance':
        return 'Perawatan';
      case 'inactive':
        return 'Nonaktif';
      case 'disposed':
        return 'Dilepas';
      case 'written_off':
        return 'Dihapus';
      default:
        return lifecycleStatus;
    }
  }
}
