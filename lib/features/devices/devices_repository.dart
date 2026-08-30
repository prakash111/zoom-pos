import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/device_session_model.dart';

/// Talks to DeviceApiController: GET /devices, POST /devices/{token}/revoke.
class DevicesRepository {
  DevicesRepository(this._client);

  final ApiClient _client;

  Future<List<DeviceSessionModel>> fetchDevices() async {
    final response = await _client.get(ApiEndpoints.devices);
    return (response['sessions'] as List? ?? [])
        .map((e) => DeviceSessionModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> revoke(String token) => _client.post(ApiEndpoints.deviceRevoke(token));
}
