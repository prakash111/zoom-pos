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

  Future<void> _openTaxForm() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => TaxFormSheet(repository: _repository),
    );

    if (saved == true) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Taxes')),
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
              itemBuilder: (context, index) => _TaxTile(tax: taxes[index]),
            ),
          );
        },
      ),
    );
  }
}

class _TaxTile extends StatelessWidget {
  const _TaxTile({required this.tax});

  final TaxRuleModel tax;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: tax.active ? Theme.of(context).colorScheme.primaryContainer : Colors.grey.shade200,
          child: Text('${tax.rate.toStringAsFixed(0)}%', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
        ),
        title: Text(tax.name),
        subtitle: Text([
          if (tax.isDefault) 'Default',
          tax.active ? 'Active' : 'Inactive',
        ].join(' · ')),
      ),
    );
  }
}
