import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/permission_grid_model.dart';
import '../../core/models/staff_user_model.dart';

class InviteResult {
  InviteResult({
    required this.user,
    required this.invitationCode,
    required this.invitationLink,
    required this.whatsappUrl,
    required this.emailSent,
  });

  final StaffUserModel user;
  final String invitationCode;
  final String invitationLink;
  final String whatsappUrl;
  final bool emailSent;
}

/// Talks to UserApiController and PermissionApiController: staff
/// invite/role/status/commission/delete, and the permission grid.
/// Impersonation is intentionally not exposed here.
class StaffRepository {
  StaffRepository(this._client);

  final ApiClient _client;

  Future<({List<StaffUserModel> users, Map<String, String> roles})> fetchUsers({String? search}) async {
    final response = await _client.get(ApiEndpoints.users, query: {
      if (search != null && search.isNotEmpty) 'search': search,
    });
    return (
      users: (response['users'] as List? ?? []).map((e) => StaffUserModel.fromJson(e as Map<String, dynamic>)).toList(),
      roles: (response['roles'] as Map<String, dynamic>? ?? {}).map((k, v) => MapEntry(k, v as String)),
    );
  }

  Future<InviteResult> inviteUser({
    required String name,
    required String email,
    String? phone,
    required String role,
    double commissionRate = 0,
    String commissionType = 'percentage',
    bool sendViaEmail = true,
  }) async {
    final response = await _client.post(ApiEndpoints.usersInvite, data: {
      'name': name,
      'email': email,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      'role': role,
      'commission_rate': commissionRate,
      'commission_type': commissionType,
      'send_via_email': sendViaEmail,
    });
    return InviteResult(
      user: StaffUserModel.fromJson(response['user'] as Map<String, dynamic>),
      invitationCode: response['invitation_code'] as String? ?? '',
      invitationLink: response['invitation_link'] as String? ?? '',
      whatsappUrl: response['whatsapp_url'] as String? ?? '',
      emailSent: response['email_sent'] as bool? ?? false,
    );
  }

  Future<void> resendInvite(String id) => _client.post(ApiEndpoints.userResendInvite(id));

  Future<StaffUserModel> updateRole(String id, String role) async {
    final response = await _client.put(ApiEndpoints.userRole(id), data: {'role': role});
    return StaffUserModel.fromJson(response['user'] as Map<String, dynamic>);
  }

  Future<StaffUserModel> toggleStatus(String id) async {
    final response = await _client.post(ApiEndpoints.userToggleStatus(id));
    return StaffUserModel.fromJson(response['user'] as Map<String, dynamic>);
  }

  Future<StaffUserModel> updateCommission(String id, double rate, String type) async {
    final response = await _client.put(ApiEndpoints.userCommission(id), data: {'commission_rate': rate, 'commission_type': type});
    return StaffUserModel.fromJson(response['user'] as Map<String, dynamic>);
  }

  Future<void> deleteUser(String id) => _client.delete(ApiEndpoints.user(id));

  Future<PermissionGridBundle> fetchPermissions(String userId) async {
    final response = await _client.get(ApiEndpoints.userPermissions(userId));
    return PermissionGridBundle.fromJson(response);
  }

  Future<void> savePermissions(String userId, Map<String, Map<String, bool>> grid) {
    return _client.put(ApiEndpoints.userPermissions(userId), data: {'grid': grid});
  }
}
