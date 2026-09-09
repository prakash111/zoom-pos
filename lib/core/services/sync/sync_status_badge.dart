import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../l10n/app_localizations.dart';
import 'sync_engine.dart';

/// AppBar icon showing the offline-sync status — an unsynced-sale count
/// badge and a spinner while a sync is running — that opens a sheet with
/// the full status and a manual "Sync Now" button.
class SyncStatusBadge extends StatelessWidget {
  const SyncStatusBadge({super.key});

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncEngine>();
    final l10n = AppLocalizations.of(context);

    final icon = sync.isSyncing
        ? const SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : const Icon(Icons.sync);

    return IconButton(
      tooltip: l10n.syncStatusTitle,
      icon: sync.unsyncedCount > 0
          ? Badge(label: Text('${sync.unsyncedCount}'), child: icon)
          : icon,
      onPressed: () => showSyncStatusSheet(context),
    );
  }
}

/// Opens the shared sync panel (status, pending count, conflict log, Sync Now
/// / Retry failed). Used by the mobile AppBar [SyncStatusBadge] and the
/// Windows desktop status bar alike.
void showSyncStatusSheet(BuildContext context) {
  showModalBottomSheet(
    context: context,
    shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
    builder: (sheetContext) => ChangeNotifierProvider.value(
      value: context.read<SyncEngine>(),
      child: const _SyncSheetBody(),
    ),
  );
}

class _SyncSheetBody extends StatelessWidget {
  const _SyncSheetBody();

  String _relativeTime(DateTime when) {
    final diff = DateTime.now().difference(when);
    if (diff.inSeconds < 60) return 'just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncEngine>();
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final conflicts = sync.conflicts;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.7,
        ),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Icon(Icons.sync, size: 22),
                  const SizedBox(width: 8),
                  Text(l10n.syncStatusTitle,
                      style: theme.textTheme.titleMedium),
                ],
              ),
              const SizedBox(height: 16),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.pending_actions_outlined),
                title: Text(
                  sync.unsyncedCount > 0
                      ? l10n.unsyncedSalesCount(sync.unsyncedCount)
                      : l10n.syncCompleted,
                ),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.history),
                title: Text(
                  sync.lastSyncedAt != null
                      ? l10n.lastSyncedAt(_relativeTime(sync.lastSyncedAt!))
                      : l10n.syncNeverRun,
                ),
              ),
              if (sync.lastError != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    sync.lastError!,
                    style: TextStyle(color: Colors.red.shade700, fontSize: 12),
                  ),
                ),
              if (conflicts.isNotEmpty) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    Icon(Icons.merge_type,
                        size: 18, color: theme.colorScheme.error),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text('Conflicts (${conflicts.length})',
                          style: theme.textTheme.titleSmall),
                    ),
                    TextButton(
                      onPressed: () => sync.clearConflicts(),
                      child: const Text('Clear'),
                    ),
                  ],
                ),
                Text(
                  'The server had a newer copy, so these offline changes were '
                  "not applied. The local copy has been refreshed to the server's.",
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 8),
                Flexible(
                  child: ListView.builder(
                    shrinkWrap: true,
                    itemCount: conflicts.length,
                    itemBuilder: (context, i) {
                      final c = conflicts[i];
                      final entity = (c['entity'] ?? 'record').toString();
                      final label = (c['label'] ?? c['id'] ?? '').toString();
                      final deleted =
                          c['reason'] == 'deleted_offline_kept_on_server';
                      return ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          deleted
                              ? Icons.restore_from_trash
                              : Icons.sync_problem,
                          size: 20,
                        ),
                        title: Text(label.isEmpty ? entity : label),
                        subtitle: Text(deleted
                            ? '$entity · your delete was undone (edited on the server)'
                            : '$entity · your edit was overwritten'),
                      );
                    },
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: sync.isSyncing ? null : () => sync.syncNow(),
                      icon: sync.isSyncing
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.sync),
                      label: Text(sync.isSyncing ? l10n.syncing : l10n.syncNow),
                    ),
                  ),
                  if (sync.state == SyncState.failed) ...[
                    const SizedBox(width: 8),
                    OutlinedButton.icon(
                      onPressed:
                          sync.isSyncing ? null : () => sync.retryFailed(),
                      icon: const Icon(Icons.replay),
                      label: const Text('Retry failed'),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
