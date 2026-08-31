import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/tax_rule_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../taxes_repository.dart';
import 'tax_form_sheet.dart';

/// Tax rule list and creation — GET/POST /taxes.
class TaxesScreen extends StatefulWidget {
  const TaxesScreen({super.key});

  @override
  State<TaxesScreen> createState() => _TaxesScreenState();
}

class _TaxesScreenState extends State<TaxesScreen> {
  late final TaxesRepository _repository;
  late Future<List<TaxRuleModel>> _future;

  @override
  void initState() {
    super.initState();
    _repository = TaxesRepository(context.read<ApiClient>());
    _future = _repository.fetchTaxes();
  }

  void _reload() {
    setState(() => _future = _repository.fetchTaxes());
  }

  Future<void> _loadCountryDefaults() async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      final presets = await _repository.fetchJurisdictionPresets();
      final existing = await _repository.fetchTaxes();
      final existingNames = existing.map((t) => t.name.trim().toLowerCase()).toSet();

      final missing = presets.rules.where((r) => !existingNames.contains(r.name.trim().toLowerCase())).toList();
      if (missing.isEmpty) {
        messenger.showSnackBar(SnackBar(content: Text('Already up to date with ${presets.country} defaults.')));
        return;
      }

      for (final preset in missing) {
        await _repository.createTax(name: preset.name, rate: preset.rate, isDefault: preset.isDefault, active: true);
      }

      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text('Added ${missing.length} tax rule(s) for ${presets.country}.')));
      _reload();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openTaxForm({TaxRuleModel? tax}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => TaxFormSheet(repository: _repository, tax: tax),
    );

    if (saved == true) _reload();
  }

  Future<void> _setDefault(TaxRuleModel tax) async {
    try {
      await _repository.setDefaultTax(tax.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(TaxRuleModel tax) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${tax.name}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.deleteTax(tax.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Taxes'),
        actions: [
          IconButton(
            tooltip: 'Load default tax rules for my country',
            icon: const Icon(Icons.auto_awesome_outlined),
            onPressed: _loadCountryDefaults,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _openTaxForm,
        child: const Icon(Icons.add),
      ),
      body: FutureBuilder<List<TaxRuleModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LoadingIndicator();
          }
          if (snapshot.hasError) {
            final message =
                snapshot.error is ApiException ? (snapshot.error as ApiException).message : 'Could not load tax rules.';
            return ErrorView(message: message, onRetry: _reload);
          }

          final taxes = snapshot.data ?? [];
          if (taxes.isEmpty) {
            return const Center(child: Text('No tax rules yet.'));
          }

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: taxes.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final tax = taxes[index];
                return _TaxTile(
                  tax: tax,
                  onTap: () => _openTaxForm(tax: tax),
                  onSetDefault: () => _setDefault(tax),
                  onDelete: () => _delete(tax),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _TaxTile extends StatelessWidget {
  const _TaxTile({required this.tax, required this.onTap, required this.onSetDefault, required this.onDelete});

  final TaxRuleModel tax;
  final VoidCallback onTap;
  final VoidCallback onSetDefault;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(
          backgroundColor: tax.active ? Theme.of(context).colorScheme.primaryContainer : Colors.grey.shade200,
          child: Text('${tax.rate.toStringAsFixed(0)}%', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
        ),
        title: Text(tax.name),
        subtitle: Text([
          if (tax.isDefault) 'Default',
          tax.active ? 'Active' : 'Inactive',
        ].join(' · ')),
        trailing: PopupMenuButton<String>(
          onSelected: (value) => value == 'default' ? onSetDefault() : onDelete(),
          itemBuilder: (context) => [
            if (!tax.isDefault) const PopupMenuItem(value: 'default', child: Text('Set as default')),
            const PopupMenuItem(value: 'delete', child: Text('Delete')),
          ],
        ),
      ),
    );
  }
}
