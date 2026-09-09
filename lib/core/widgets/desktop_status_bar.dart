import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/sync/sync_engine.dart';
import '../services/sync/sync_status_badge.dart';

/// The bottom status strip of the Windows desktop shell:
/// `● Online · 3 pending           [ Sync now ]`
///
/// Split into a pure presentational [DesktopStatusBar] (easy to test / preview)
/// and a [SyncStatusBar] connector that feeds it from the live [SyncEngine].
class SyncStatusBar extends StatelessWidget {
  const SyncStatusBar({super.key});

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncEngine>();
    return DesktopStatusBar(
      state: sync.state,
      pendingCount: sync.pendingCount,
      conflictCount: sync.conflictCount,
      lastSyncedAt: sync.lastSyncedAt,
      lastError: sync.lastError,
      onSyncNow: sync.isSyncing ? null : () => sync.syncNow(),
      onOpenPanel: () => showSyncStatusSheet(context),
    );
  }
}

class DesktopStatusBar extends StatelessWidget {
  const DesktopStatusBar({
    super.key,
    required this.state,
    required this.pendingCount,
    this.conflictCount = 0,
    this.lastSyncedAt,
    this.lastError,
    this.onSyncNow,
    this.onOpenPanel,
  });

  final SyncState state;
  final int pendingCount;
  final int conflictCount;
  final DateTime? lastSyncedAt;
  final String? lastError;
  final VoidCallback? onSyncNow;
  final VoidCallback? onOpenPanel;

  static String _relative(DateTime when) {
    final d = DateTime.now().difference(when);
    if (d.inSeconds < 60) return 'just now';
    if (d.inMinutes < 60) return '${d.inMinutes}m ago';
    if (d.inHours < 24) return '${d.inHours}h ago';
    return '${d.inDays}d ago';
  }

  ({Color color, IconData icon, String label}) _describe(ColorScheme scheme) {
    switch (state) {
      case SyncState.syncing:
        return (color: scheme.primary, icon: Icons.sync, label: 'Syncing…');
      case SyncState.offline:
        return (
          color: scheme.onSurfaceVariant,
          icon: Icons.cloud_off,
          label: 'Offline'
        );
      case SyncState.failed:
        return (
          color: scheme.error,
          icon: Icons.error_outline,
          label: 'Sync failed'
        );
      case SyncState.online:
        return (
          color: scheme.primary,
          icon: Icons.cloud_queue,
          label: 'Online'
        );
      case SyncState.synced:
        return (
          color: Colors.green.shade600,
          icon: Icons.cloud_done,
          label: lastSyncedAt != null
              ? 'Synced ${_relative(lastSyncedAt!)}'
              : 'Synced',
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final d = _describe(scheme);
    final syncing = state == SyncState.syncing;

    return Material(
      color: scheme.surfaceContainerHighest,
      child: InkWell(
        onTap: onOpenPanel,
        child: SizedBox(
          height: 30,
          child: Row(
            children: [
              const SizedBox(width: 12),
              if (syncing)
                SizedBox(
                  width: 13,
                  height: 13,
                  child:
                      CircularProgressIndicator(strokeWidth: 2, color: d.color),
                )
              else
                Icon(d.icon, size: 15, color: d.color),
              const SizedBox(width: 7),
              Text(
                d.label,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                  fontWeight: FontWeight.w500,
                ),
              ),
              if (pendingCount > 0) ...[
                const SizedBox(width: 10),
                Text('·', style: TextStyle(color: scheme.onSurfaceVariant)),
                const SizedBox(width: 10),
                Icon(Icons.pending_actions_outlined,
                    size: 14, color: scheme.onSurfaceVariant),
                const SizedBox(width: 5),
                Text(
                  '$pendingCount pending',
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: scheme.onSurfaceVariant),
                ),
              ],
              if (conflictCount > 0) ...[
                const SizedBox(width: 10),
                Icon(Icons.merge_type, size: 14, color: scheme.error),
                const SizedBox(width: 5),
                Text(
                  '$conflictCount conflict${conflictCount == 1 ? '' : 's'}',
                  style:
                      theme.textTheme.bodySmall?.copyWith(color: scheme.error),
                ),
              ],
              if (state == SyncState.failed &&
                  (lastError?.isNotEmpty ?? false)) ...[
                const SizedBox(width: 10),
                Flexible(
                  child: Tooltip(
                    message: lastError!,
                    child: Text(
                      lastError!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall
                          ?.copyWith(color: scheme.error),
                    ),
                  ),
                ),
              ],
              const Spacer(),
              TextButton.icon(
                onPressed: onSyncNow,
                icon: const Icon(Icons.sync, size: 15),
                label: Text(syncing ? 'Syncing…' : 'Sync now'),
                style: TextButton.styleFrom(
                  visualDensity: VisualDensity.compact,
                  textStyle: theme.textTheme.bodySmall,
                ),
              ),
              const SizedBox(width: 6),
            ],
          ),
        ),
      ),
    );
  }
}
