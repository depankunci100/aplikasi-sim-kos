class PropertyModel {
  final int id;
  final String code;
  final String name;
  final String type;
  final String address;
  final String city;
  final String? phone;
  final String? rules;
  final List<String> facilities;
  final int totalRooms;
  final int availableRooms;

  PropertyModel({
    required this.id,
    required this.code,
    required this.name,
    required this.type,
    required this.address,
    required this.city,
    this.phone,
    this.rules,
    required this.facilities,
    this.totalRooms = 0,
    this.availableRooms = 0,
  });

  factory PropertyModel.fromJson(Map<String, dynamic> json) {
    return PropertyModel(
      id: json['id'] as int,
      code: json['code'] as String,
      name: json['name'] as String,
      type: json['type'] as String? ?? 'hybrid',
      address: json['address'] as String? ?? '',
      city: json['city'] as String? ?? '',
      phone: json['phone'] as String?,
      rules: json['rules'] as String?,
      facilities: (json['facilities'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      totalRooms: json['total_rooms'] as int? ?? 0,
      availableRooms: json['available_rooms'] as int? ?? 0,
    );
  }

  String get typeLabel {
    switch (type) {
      case 'kos':
        return 'Kos Eksklusif';
      case 'guesthouse':
        return 'Guesthouse';
      case 'hybrid':
      default:
        return 'Kos & Guesthouse';
    }
  }
}
