/// An active web-login session, as returned by DeviceApiController (table
/// `sessions` — NOT the TenantApiKey/POS terminal tokens mobile itself uses).
class DeviceSessionModel {
  DeviceSessionModel({
    required this.token,
    required this.userName,
    required this.ip,
    required this.userAgent,
    required this.isImpersonation,
    required this.isCurrentUser,
    required this.createdAt,
    required this.expiresAt,
  });

  factory DeviceSessionModel.fromJson(Map<String, dynamic> json) {
    return DeviceSessionModel(
      token: json['token'] as String? ?? '',
      userName: json['user_name'] as String? ?? 'Unknown',
      ip: json['ip'] as String?,
      userAgent: json['user_agent'] as String?,
      isImpersonation: json['is_impersonation'] as bool? ?? false,
      isCurrentUser: json['is_current_user'] as bool? ?? false,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
      expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
    );
  }

  final String token;
  final String userName;
  final String? ip;
  final String? userAgent;
  final bool isImpersonation;
  final bool isCurrentUser;
  final DateTime? createdAt;
  final DateTime? expiresAt;
}
