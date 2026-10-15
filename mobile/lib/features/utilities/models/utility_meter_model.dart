class UtilityMeterModel {
  final int id;
  final int propertyId;
  final int? roomId;
  final String utilityType; // electricity, water
  final String meterNumber;
  final String meterType; // individual, shared
  final String unit;
  final num initialReading;
  final num currentReading;
  final num? maxReading;
  final DateTime? installedAt;
  final bool isActive;
  final String? propertyName;
  final String? roomNumber;

  UtilityMeterModel({
    required this.id,
    required this.propertyId,
    this.roomId,
    required this.utilityType,
    required this.meterNumber,
    required this.meterType,
    required this.unit,
    required this.initialReading,
    required this.currentReading,
    this.maxReading,
    this.installedAt,
    this.isActive = true,
    this.propertyName,
    this.roomNumber,
  });

  factory UtilityMeterModel.fromJson(Map<String, dynamic> json) {
    return UtilityMeterModel(
      id: json['id'] as int? ?? 0,
      propertyId: json['property_id'] as int? ?? 0,
      roomId: json['room_id'] as int?,
      utilityType: json['utility_type'] as String? ?? 'electricity',
      meterNumber: json['meter_number'] as String? ?? '-',
      meterType: json['meter_type'] as String? ?? 'individual',
      unit: json['unit'] as String? ?? (json['utility_type'] == 'water' ? 'm3' : 'kWh'),
      initialReading: num.tryParse(json['initial_reading']?.toString() ?? '0') ?? 0,
      currentReading: num.tryParse(json['current_reading']?.toString() ?? '0') ?? 0,
      maxReading: json['max_reading'] != null ? num.tryParse(json['max_reading'].toString()) : null,
      installedAt: json['installed_at'] != null ? DateTime.tryParse(json['installed_at'].toString()) : null,
      isActive: json['is_active'] == true || json['is_active'] == 1,
      propertyName: json['property']?['name'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
    );
  }

  bool get isElectricity => utilityType == 'electricity';
  bool get isWater => utilityType == 'water';

  String get utilityTypeLabel => isElectricity ? 'Listrik (kWh)' : 'Air (m³)';
  String get meterTypeLabel => meterType == 'individual' ? 'Kamar Individual' : 'Komunal / Bersama';
}
