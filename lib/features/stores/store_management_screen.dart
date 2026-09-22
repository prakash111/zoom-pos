import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/stores/store_provider.dart';
import 'store_editor_dialog.dart';

class StoreManagementScreen extends StatefulWidget {
  const StoreManagementScreen({super.key});
  @override
  State<StoreManagementScreen> createState() => _StoreManagementScreenState();
}

class _StoreManagementScreenState extends State<StoreManagementScreen> {
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<StoreProvider>().load();
    });
  }

  Future<void> _switch(StoreBranch store) async {
    setState(() => _busy = true);
    try {
      await context.read<StoreProvider>().switchTo(store);
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _edit([StoreBranch? store]) async {
    await showDialog<bool>(
        context: context, builder: (_) => StoreEditorDialog(store: store));
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<StoreProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Stores & Branches')),
      body: RefreshIndicator(
        onRefresh: provider.load,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Text(
              '${provider.storeCount} stores · ${provider.storeLimit < 0 ? 'Unlimited plan' : '${provider.storeLimit} allowed by your plan'}',
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 16),
          if (provider.loading || _busy) const LinearProgressIndicator(),
          if (provider.error != null) ...[
            Text(provider.error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error)),
            TextButton.icon(
                onPressed: provider.load,
                icon: const Icon(Icons.refresh),
                label: const Text('Retry')),
          ],
          if (provider.fromCache)
            const Text(
                'Saved stores shown. Connect to the server to make changes.'),
          for (final store in provider.stores)
            Card(
                child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ListTile(
                              contentPadding: EdgeInsets.zero,
                              leading: Icon(provider.current?.id == store.id
                                  ? Icons.check_circle
                                  : Icons.store_outlined),
                              title: Text(store.name),
                              subtitle: Text([
                                store.code,
                                if (store.isPrimary) 'Primary',
                                if (provider.current?.id == store.id)
                                  'Selected',
                                if (!store.isActive) 'Inactive'
                              ].join(' · '))),
                          if (store.address.isNotEmpty) Text(store.address),
                          if (store.phone.isNotEmpty) Text(store.phone),
                          Wrap(spacing: 12, children: [
                            if (store.isActive &&
                                provider.current?.id != store.id)
                              TextButton(
                                  onPressed: _busy ||
                                          provider.loading ||
                                          provider.fromCache
                                      ? null
                                      : () => _switch(store),
                                  child: const Text('Switch to store')),
                            if (provider.canManage)
                              TextButton.icon(
                                  onPressed: _busy ||
                                          provider.loading ||
                                          provider.fromCache
                                      ? null
                                      : () => _edit(store),
                                  icon: const Icon(Icons.edit_outlined),
                                  label: const Text('Edit branch')),
                          ]),
                        ]))),
          if (provider.canCreateMore)
            FilledButton.icon(
                onPressed: _busy || provider.loading ? null : () => _edit(),
                icon: const Icon(Icons.add_business_outlined),
                label: const Text('Add New Store / Branch')),
          if (provider.canCreate && provider.limitReached)
            const Padding(
                padding: EdgeInsets.all(16),
                child: Text(
                    'Store limit reached. Upgrade your subscription to add another branch.')),
        ]),
      ),
    );
  }
}
