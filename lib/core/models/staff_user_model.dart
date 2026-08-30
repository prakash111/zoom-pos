/// A staff member, as returned by GET /users (UserApiController) — richer
/// than [UserModel] (auth session user), which is why it's a separate type.
class StaffUserModel {
  StaffUserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.roleLabel,
    required this.status,
    required this.commissionRate,
    required this.commissionType,
    required this.isPrivileged,
  });

  factory StaffUserModel.fromJson(Map<String, dynamic> json) {
    return StaffUserModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? '',
      roleLabel: json['role_label'] as String? ?? '',
      status: json['status'] as String? ?? 'approved',
      commissionRate: (json['commission_rate'] as num?)?.toDouble() ?? 0,
      commissionType: json['commission_type'] as String? ?? 'percentage',
      isPrivileged: json['is_privileged'] as bool? ?? false,
    );
  }

  final String id;
  final String name;
  final String email;
  final String role;
  final String roleLabel;
  final String status;
  final double commissionRate;
  final String commissionType;
  final bool isPrivileged;

  bool get isInvited => status == 'convidado';
  bool get isActive => status == 'approved';
}
