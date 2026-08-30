import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/settings_models.dart';
import '../settings_repository.dart';

/// Payment Methods manager (GET/POST /settings/payment-methods,
/// PUT/DELETE/{id}, POST /{id}/toggle) — reached from the Financial tab.
class PaymentMethodsScreen extends StatefulWidget {
  const PaymentMethodsScreen({super.key, required this.repository});

  final SettingsRepository repository;

  @override
  State<PaymentMethodsScreen> createState() => _PaymentMethodsScreenState();
}

class _PaymentMethodsScreenState extends State<PaymentMethodsScreen> {
  late Future<List<PaymentMethodModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchPaymentMethods();
  }

  void _reload() => setState(() => _future = widget.repository.fetchPaymentMethods());

  Future<void> _openForm({PaymentMethodModel? method}) async {
    final nameController = TextEditingController(text: method?.name ?? '');
    final descriptionController = TextEditingController(text: method?.description ?? '');

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(method == null ? 'New payment method' : 'Edit payment method'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Name'), autofocus: true),
            const SizedBox(height: 12),
            TextField(controller: descriptionController, decoration: const InputDecoration(labelText: 'Description (optional)')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () async {
              if (nameController.text.trim().isEmpty) return;
              try {
                await widget.repository.savePaymentMethod(
                  id: method?.id,
                  name: nameController.text.trim(),
                  description: descriptionController.text.trim(),
                );
                if (context.mounted) Navigator.of(context).pop(true);
              } on ApiException catch (e) {
                if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );

    if (saved == true) _reload();
  }

  Future<void> _toggle(PaymentMethodModel method) async {
    try {
      await widget.repository.togglePaymentMethod(method.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(PaymentMethodModel method) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${method.name}?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await widget.repository.deletePaymentMethod(method.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Payment Methods')),
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<PaymentMethodModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          final methods = snapshot.data ?? [];
          if (methods.isEmpty) return const Center(child: Text('No payment methods yet.'));

          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
            itemCount: methods.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final method = methods[index];
              return Card(
                child: ListTile(
                  onTap: () => _openForm(method: method),
                  title: Text(method.name),
                  subtitle: Text(method.description.isEmpty ? method.code : method.description),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Switch(value: method.isActive, onChanged: (_) => _toggle(method)),
                      IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(method)),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
