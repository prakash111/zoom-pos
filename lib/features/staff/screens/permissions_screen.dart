import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/permission_grid_model.dart';
import '../staff_repository.dart';

/// Per-user permission matrix: one expandable section per module, a switch
/// per action, plus preset buttons. Mirrors
/// app/Livewire/Tenant/Users/Permissions.php's grid semantics exactly
/// (computed server-side — this screen never invents module/action names).
class PermissionsScreen extends StatefulWidget {
  const PermissionsScreen({super.key, required this.repository, required this.userId});

  final StaffRepository repository;
  final String userId;

  @override
  State<PermissionsScreen> createState() => _PermissionsScreenState();
}

class _PermissionsScreenState extends State<PermissionsScreen> {
  PermissionGridBundle? _bundle;
  Map<String, Map<String, bool>> _grid = {};
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final bundle = await widget.repository.fetchPermissions(widget.userId);
      setState(() {
        _bundle = bundle;
        _grid = {for (final entry in bundle.grid.entries) entry.key: Map<String, bool>.from(entry.value)};
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  void _applyPreset(String preset) {
    final bundle = _bundle;
    if (bundle == null) return;

    setState(() {
      for (final module in bundle.modules) {
        for (final action in module.actions) {
          switch (preset) {
            case 'full':
              _grid[module.slug]![action.slug] = true;
              break;
            case 'view_only':
              _grid[module.slug]![action.slug] = action.slug == 'view';
              break;
            case 'clear':
              _grid[module.slug]![action.slug] = false;
              break;
            default:
              final allowed = bundle.roleDefaults[preset]?[module.slug] ?? const [];
              _grid[module.slug]![action.slug] = allowed.contains(action.slug);
          }
        }
      }
    });
  }

  void _toggleRow(PermissionModule module) {
    setState(() {
      final allChecked = module.actions.every((a) => _grid[module.slug]?[a.slug] == true);
      for (final action in module.actions) {
        _grid[module.slug]![action.slug] = !allChecked;
      }
    });
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.repository.savePermissions(widget.userId, _grid);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Permissions saved.')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bundle = _bundle;

    return Scaffold(
      appBar: AppBar(title: Text(bundle != null ? 'Permissions · ${bundle.userName}' : 'Permissions')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : bundle == null
                  ? const SizedBox.shrink()
                  : bundle.isPrivileged
                      ? const Center(
                          child: Padding(
                            padding: EdgeInsets.all(24),
                            child: Text('This user has an administrator role and always has full access.', textAlign: TextAlign.center),
                          ),
                        )
                      : Column(
                          children: [
                            SizedBox(
                              height: 44,
                              child: ListView(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                children: [
                                  _PresetChip(label: 'Full access', onTap: () => _applyPreset('full')),
                                  _PresetChip(label: 'View only', onTap: () => _applyPreset('view_only')),
                                  _PresetChip(label: 'Clear', onTap: () => _applyPreset('clear')),
                                  _PresetChip(label: 'Role default', onTap: () => _applyPreset(bundle.userRole)),
                                ],
                              ),
                            ),
                            Expanded(
                              child: ListView(
                                padding: const EdgeInsets.fromLTRB(8, 0, 8, 80),
                                children: [
                                  for (final module in bundle.modules)
                                    Card(
                                      child: ExpansionTile(
                                        title: Text(module.label),
                                        subtitle: Text('${module.actions.where((a) => _grid[module.slug]?[a.slug] == true).length}/${module.actions.length} enabled'),
                                        trailing: IconButton(
                                          icon: const Icon(Icons.done_all),
                                          tooltip: 'Toggle all',
                                          onPressed: () => _toggleRow(module),
                                        ),
                                        children: [
                                          for (final action in module.actions)
                                            SwitchListTile(
                                              title: Text(action.label),
                                              value: _grid[module.slug]?[action.slug] ?? false,
                                              onChanged: (value) => setState(() => _grid[module.slug]![action.slug] = value),
                                            ),
                                        ],
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
      floatingActionButton: (bundle != null && !bundle.isPrivileged)
          ? FloatingActionButton.extended(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.save_outlined),
              label: const Text('Save'),
            )
          : null,
    );
  }
}

class _PresetChip extends StatelessWidget {
  const _PresetChip({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ActionChip(label: Text(label), onPressed: onTap),
    );
  }
}
