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
      onPressed: () => _showSyncSheet(context),
    );
  }

  void _showSyncSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (sheetContext) => ChangeNotifierProvider.value(
        value: context.read<SyncEngine>(),
        child: const _SyncSheetBody(),
      ),
    );
  }
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

    return SafeArea(
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
                Text(l10n.syncStatusTitle, style: Theme.of(context).textTheme.titleMedium),
              ],
            ),
            const SizedBox(height: 16),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.pending_actions_outlined),
              title: Text(
                sync.unsyncedCount > 0 ? l10n.unsyncedSalesCount(sync.unsyncedCount) : l10n.syncCompleted,
              ),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.history),
              title: Text(
                sync.lastSyncedAt != null ? l10n.lastSyncedAt(_relativeTime(sync.lastSyncedAt!)) : l10n.syncNeverRun,
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
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: sync.isSyncing ? null : () => sync.syncNow(),
              icon: sync.isSyncing
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.sync),
              label: Text(sync.isSyncing ? l10n.syncing : l10n.syncNow),
            ),
          ],
        ),
      ),
    );
  }
}
