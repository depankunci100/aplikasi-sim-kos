class UserModel {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final String? roleLabel;
  final String? avatarUrl;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    this.roleLabel,
    this.avatarUrl,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String? ?? 'tenant',
      roleLabel: json['role_label'] as String?,
      avatarUrl: json['avatar_url'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'role_label': roleLabel,
      'avatar_url': avatarUrl,
    };
  }

  bool get isTenant => role == 'tenant';
  bool get isOwner => role == 'owner';
  bool get isManager => role == 'manager';
  bool get isStaff => role == 'staff' || role == 'receptionist';
  bool get isSuperAdmin => role == 'super_admin';
}
