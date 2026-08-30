import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/device_session_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../devices_repository.dart';

final _dateFormat = DateFormat('MMM d, y · h:mm a');

/// Active web-login sessions — sign out a stray dashboard login from the
/// phone. Mirrors app/Livewire/Tenant/Devices/Index.php.
class DevicesScreen extends StatefulWidget {
  const DevicesScreen({super.key});

  @override
  State<DevicesScreen> createState() => _DevicesScreenState();
}

class _DevicesScreenState extends State<DevicesScreen> {
  late final DevicesRepository _repository;
  late Future<List<DeviceSessionModel>> _future;

  @override
  void initState() {
    super.initState();
    _repository = DevicesRepository(context.read<ApiClient>());
    _future = _repository.fetchDevices();
  }

  void _reload() => setState(() => _future = _repository.fetchDevices());

  Future<void> _revoke(DeviceSessionModel session) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out this device?'),
        content: Text('${session.userName} will be signed out of the web dashboard on this device.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Sign out')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.revoke(session.token);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Devices')),
      body: FutureBuilder<List<DeviceSessionModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load devices.', onRetry: _reload);

          final sessions = snapshot.data ?? [];
          if (sessions.isEmpty) return const Center(child: Text('No active web sessions.'));

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: sessions.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final session = sessions[index];
                return Card(
                  child: ListTile(
                    leading: Icon(session.isImpersonation ? Icons.supervisor_account_outlined : Icons.devices_outlined),
                    title: Text(session.userName),
                    subtitle: Text([
                      if (session.ip != null && session.ip!.isNotEmpty) session.ip!,
                      if (session.createdAt != null) _dateFormat.format(session.createdAt!),
                    ].join(' · ')),
                    trailing: TextButton(onPressed: () => _revoke(session), child: const Text('Sign out')),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
