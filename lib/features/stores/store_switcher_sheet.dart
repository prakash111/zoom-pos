import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/stores/store_provider.dart';
import 'store_editor_dialog.dart';
import 'store_management_screen.dart';

class StoreSwitcherSheet extends StatefulWidget {
  const StoreSwitcherSheet({super.key});

  @override
  State<StoreSwitcherSheet> createState() => _StoreSwitcherSheetState();
}

class _StoreSwitcherSheetState extends State<StoreSwitcherSheet> {
  bool _switching = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<StoreProvider>().load();
    });
  }

  Future<void> _switch(StoreBranch store) async {
    setState(() => _switching = true);
    try {
      await context.read<StoreProvider>().switchTo(store);
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Could not switch store: $error')));
      }
    } finally {
      if (mounted) setState(() => _switching = false);
    }
  }

  Future<void> _addStore() async {
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => const StoreEditorDialog(),
    );
    if (created == true && mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<StoreProvider>();
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Switch store', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            if (provider.loading)
              const Center(child: CircularProgressIndicator())
            else if (provider.error != null)
              TextButton.icon(
                onPressed: provider.load,
                icon: const Icon(Icons.refresh),
                label: Text('${provider.error}\nRetry loading stores'),
              )
            else
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 360),
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: provider.activeStores.length,
                  itemBuilder: (context, index) {
                    final store = provider.activeStores[index];
                    final selected = provider.current?.id == store.id;
                    return ListTile(
                      leading:
                          Icon(selected ? Icons.store : Icons.store_outlined),
                      title: Text(store.name),
                      subtitle: Text([
                        store.code,
                        if (store.isPrimary) 'Primary store',
                        if (store.address.isNotEmpty) store.address
                      ].join(' · ')),
                      trailing:
                          selected ? const Icon(Icons.check_circle) : null,
                      selected: selected,
                      enabled: !_switching && !provider.fromCache,
                      onTap: selected
                          ? () => Navigator.of(context).pop(false)
                          : () => _switch(store),
                    );
                  },
                ),
              ),
            if (provider.fromCache)
              const Text(
                  'Saved stores shown. Connect to the server to switch stores.'),
            if (provider.canCreateMore) ...[
              const Divider(height: 24),
              OutlinedButton.icon(
                onPressed: _switching || provider.loading ? null : _addStore,
                icon: const Icon(Icons.add_business_outlined),
                label: const Text('Add New Store / Branch'),
                style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48)),
              ),
            ],
            if (provider.canCreate && provider.limitReached)
              Text(
                  'Your plan allows ${provider.storeLimit} stores. Upgrade your subscription to add another branch.'),
            if (provider.stores.isNotEmpty)
              TextButton.icon(
                onPressed: _switching
                    ? null
                    : () async {
                        await Navigator.of(context).push(
                            MaterialPageRoute<void>(
                                builder: (_) => const StoreManagementScreen()));
                        if (mounted) Navigator.of(this.context).pop(true);
                      },
                icon: const Icon(Icons.settings_outlined),
                label: const Text('Manage stores & branches'),
              ),
            if (_switching) const LinearProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
