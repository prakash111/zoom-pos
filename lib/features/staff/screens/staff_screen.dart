import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/staff_user_model.dart';
import '../../auth/auth_provider.dart';
import '../staff_repository.dart';
import 'invite_user_sheet.dart';
import 'permissions_screen.dart';

/// Staff & access control: invite, role/status/commission management,
/// delete, and a link into the per-user permission matrix. Impersonation is
/// intentionally not exposed on mobile. Mirrors
/// app/Livewire/Tenant/Users/Index.php.
class StaffScreen extends StatefulWidget {
  const StaffScreen({super.key});

  @override
  State<StaffScreen> createState() => _StaffScreenState();
}

class _StaffScreenState extends State<StaffScreen> {
  late final StaffRepository _repository;
  late Future<({List<StaffUserModel> users, Map<String, String> roles})> _future;

  @override
  void initState() {
    super.initState();
    _repository = StaffRepository(context.read<ApiClient>());
    _future = _repository.fetchUsers();
  }

  void _reload() => setState(() => _future = _repository.fetchUsers());

  Future<void> _invite(Map<String, String> roles) async {
    final invited = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => InviteUserSheet(repository: _repository, roles: roles),
    );
    if (invited == true) _reload();
  }

  Future<void> _changeRole(StaffUserModel user, Map<String, String> roles) async {
    final role = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: Text('Change role for ${user.name}'),
        children: [
          for (final entry in roles.entries)
            SimpleDialogOption(onPressed: () => Navigator.of(context).pop(entry.key), child: Text(entry.value)),
        ],
      ),
    );
    if (role == null || role == user.role) return;

    try {
      await _repository.updateRole(user.id, role);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _toggleStatus(StaffUserModel user) async {
    try {
      await _repository.toggleStatus(user.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _editCommission(StaffUserModel user) async {
    final rateController = TextEditingController(text: user.commissionRate.toStringAsFixed(2));
    String type = user.commissionType;

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('Commission for ${user.name}'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: rateController, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Rate')),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: type,
                decoration: const InputDecoration(labelText: 'Type'),
                items: const [
                  DropdownMenuItem(value: 'percentage', child: Text('Percentage')),
                  DropdownMenuItem(value: 'fixed', child: Text('Fixed')),
                  DropdownMenuItem(value: 'profit_percentage', child: Text('Profit %')),
                  DropdownMenuItem(value: 'profit', child: Text('Profit')),
                ],
                onChanged: (value) => setDialogState(() => type = value ?? type),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Save')),
          ],
        ),
      ),
    );
    if (saved != true) return;

    try {
      await _repository.updateCommission(user.id, double.tryParse(rateController.text) ?? 0, type);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(StaffUserModel user) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Remove ${user.name}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Remove')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.deleteUser(user.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _resendInvite(StaffUserModel user) async {
    try {
      await _repository.resendInvite(user.id);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('New invitation code generated.')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final myId = context.watch<AuthProvider>().user?.id;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Staff & Access'),
        actions: [
          TextButton.icon(
            onPressed: () async {
              await Navigator.of(context).pushNamed('/api/tenant/views/roles');
              _reload();
            },
            icon: const Icon(Icons.admin_panel_settings_outlined, size: 18),
            label: const Text('Manage Roles'),
          ),
        ],
      ),
      body: FutureBuilder<({List<StaffUserModel> users, Map<String, String> roles})>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException ? (snapshot.error as ApiException).message : 'Could not load staff.';
            return Center(child: Text(message));
          }

          final users = snapshot.data!.users;
          final roles = snapshot.data!.roles;

          return Scaffold(
            floatingActionButton: FloatingActionButton(onPressed: () => _invite(roles), child: const Icon(Icons.person_add_alt)),
            body: RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
                itemCount: users.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final user = users[index];
                  final isSelf = user.id == myId;

                  return Card(
                    child: ListTile(
                      title: Text(user.name),
                      subtitle: Text([
                        user.email,
                        user.roleLabel,
                        if (user.isInvited) 'Invited' else if (!user.isActive) 'Suspended',
                      ].join(' · ')),
                      trailing: PopupMenuButton<String>(
                        onSelected: (value) {
                          switch (value) {
                            case 'role':
                              _changeRole(user, roles);
                              break;
                            case 'status':
                              _toggleStatus(user);
                              break;
                            case 'commission':
                              _editCommission(user);
                              break;
                            case 'permissions':
                              Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => PermissionsScreen(repository: _repository, userId: user.id)),
                              );
                              break;
                            case 'resend':
                              _resendInvite(user);
                              break;
                            case 'delete':
                              _delete(user);
                              break;
                          }
                        },
                        itemBuilder: (context) => [
                          if (user.isInvited) const PopupMenuItem(value: 'resend', child: Text('Resend invite')),
                          const PopupMenuItem(value: 'permissions', child: Text('Permissions')),
                          if (!isSelf) ...[
                            const PopupMenuItem(value: 'role', child: Text('Change role')),
                            PopupMenuItem(value: 'status', child: Text(user.isActive ? 'Suspend' : 'Reactivate')),
                          ],
                          const PopupMenuItem(value: 'commission', child: Text('Edit commission')),
                          if (!isSelf) const PopupMenuItem(value: 'delete', child: Text('Remove')),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }
}
