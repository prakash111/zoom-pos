import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/stores/store_provider.dart';

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
    final provider = context.read<StoreProvider>();
    if (provider.limitReached) {
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Store limit reached'),
          content: Text('Your plan allows ${provider.storeLimit} '
              '${provider.storeLimit == 1 ? 'store' : 'stores'}. '
              'Upgrade your subscription to add another store.'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Close'))
          ],
        ),
      );
      return;
    }
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => const _CreateStoreDialog(),
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
                label: const Text('Could not load stores. Retry'),
              )
            else
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 360),
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: provider.stores.length,
                  itemBuilder: (context, index) {
                    final store = provider.stores[index];
                    final selected = provider.current?.id == store.id;
                    return ListTile(
                      leading:
                          Icon(selected ? Icons.store : Icons.store_outlined),
                      title: Text(store.name),
                      subtitle: Text(store.isPrimary
                          ? '${store.code} · Primary store'
                          : store.code),
                      trailing:
                          selected ? const Icon(Icons.check_circle) : null,
                      selected: selected,
                      enabled: !_switching,
                      onTap: selected
                          ? () => Navigator.of(context).pop(false)
                          : () => _switch(store),
                    );
                  },
                ),
              ),
            if (provider.canCreate) ...[
              const Divider(height: 24),
              ListTile(
                leading: const Icon(Icons.add_business_outlined),
                title: const Text('Add New Store'),
                subtitle: provider.limitReached
                    ? const Text('Upgrade your plan to add more stores')
                    : null,
                onTap: _addStore,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _CreateStoreDialog extends StatefulWidget {
  const _CreateStoreDialog();

  @override
  State<_CreateStoreDialog> createState() => _CreateStoreDialogState();
}

class _CreateStoreDialogState extends State<_CreateStoreDialog> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _code = TextEditingController();
  final _phone = TextEditingController();
  final _address = TextEditingController();
  final _taxId = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    for (final controller in [_name, _code, _phone, _address, _taxId]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await context.read<StoreProvider>().create(
            name: _name.text.trim(),
            code: _code.text.trim().toLowerCase(),
            phone: _phone.text.trim(),
            address: _address.text.trim(),
            taxId: _taxId.text.trim(),
          );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Add New Store'),
        content: SizedBox(
          width: 420,
          child: SingleChildScrollView(
            child: Form(
              key: _formKey,
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'Store name'),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? 'Store name is required'
                      : null,
                ),
                TextFormField(
                  controller: _code,
                  decoration: const InputDecoration(labelText: 'Branch code'),
                  validator: (value) => value == null ||
                          !RegExp(r'^[a-zA-Z0-9_-]+$').hasMatch(value.trim())
                      ? 'Use letters, numbers, dashes or underscores'
                      : null,
                ),
                TextFormField(
                    controller: _phone,
                    decoration: const InputDecoration(labelText: 'Phone')),
                TextFormField(
                    controller: _address,
                    decoration: const InputDecoration(labelText: 'Address'),
                    maxLines: 2),
                TextFormField(
                    controller: _taxId,
                    decoration: const InputDecoration(labelText: 'Tax number')),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                  ),
              ]),
            ),
          ),
        ),
        actions: [
          TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: _saving ? null : _save,
              child: const Text('Create and switch')),
        ],
      );
}
