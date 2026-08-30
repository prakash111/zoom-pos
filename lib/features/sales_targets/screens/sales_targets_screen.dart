import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/sales_target_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../sales_targets_repository.dart';

const _monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

/// Sales Targets & Goals: month/year selector, company-wide target with
/// per-salesperson breakdown and progress, matching
/// app/Livewire/Tenant/SalesTargets/Index.php.
class SalesTargetsScreen extends StatefulWidget {
  const SalesTargetsScreen({super.key});

  @override
  State<SalesTargetsScreen> createState() => _SalesTargetsScreenState();
}

class _SalesTargetsScreenState extends State<SalesTargetsScreen> {
  late final SalesTargetsRepository _repository;
  late int _year;
  late int _month;
  late Future<SalesTargetsBundle> _future;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _year = now.year;
    _month = now.month;
    _repository = SalesTargetsRepository(context.read<ApiClient>());
    _future = _repository.fetchTargets(year: _year, month: _month);
  }

  void _reload() => setState(() => _future = _repository.fetchTargets(year: _year, month: _month));

  void _changeMonth(int delta) {
    setState(() {
      var newMonth = _month + delta;
      var newYear = _year;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      } else if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      _month = newMonth;
      _year = newYear;
      _reload();
    });
  }

  Future<void> _openEdit(SalesTargetsBundle bundle, CurrencyFormatter formatter) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => _EditTargetsSheet(repository: _repository, bundle: bundle, formatter: formatter),
    );
    if (saved == true) _reload();
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Sales Targets'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => _changeMonth(-1)),
              Text('${_monthNames[_month - 1]} $_year', style: const TextStyle(fontWeight: FontWeight.w600)),
              IconButton(icon: const Icon(Icons.chevron_right), onPressed: () => _changeMonth(1)),
            ],
          ),
        ),
      ),
      body: FutureBuilder<SalesTargetsBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException ? (snapshot.error as ApiException).message : 'Could not load targets.';
            return ErrorView(message: message, onRetry: _reload);
          }

          final bundle = snapshot.data!;
          final progress = bundle.companyTargetAmount > 0 ? (bundle.overallAchieved / bundle.companyTargetAmount).clamp(0.0, 1.0) : 0.0;

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Company target', style: Theme.of(context).textTheme.titleMedium),
                            TextButton(onPressed: () => _openEdit(bundle, formatter), child: const Text('Edit')),
                          ],
                        ),
                        const SizedBox(height: 8),
                        LinearProgressIndicator(value: progress, minHeight: 8, borderRadius: BorderRadius.circular(4)),
                        const SizedBox(height: 8),
                        Text('${formatter.format(bundle.overallAchieved)} of ${formatter.format(bundle.companyTargetAmount)} (${bundle.overallPercentage.toStringAsFixed(1)}%)'),
                        Text('${formatter.format(bundle.overallRemaining)} remaining · ${formatter.format(bundle.dailyRunRateNeeded)}/day for ${bundle.remainingDays} days', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text('By salesperson', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final ut in bundle.userTargets)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(ut.name),
                      subtitle: Text('${formatter.format(ut.achievedAmount)} of ${formatter.format(ut.targetAmount)} · ${ut.salesCount} sales'),
                      trailing: Text('${ut.percentage.toStringAsFixed(0)}%', style: const TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _EditTargetsSheet extends StatefulWidget {
  const _EditTargetsSheet({required this.repository, required this.bundle, required this.formatter});

  final SalesTargetsRepository repository;
  final SalesTargetsBundle bundle;
  final CurrencyFormatter formatter;

  @override
  State<_EditTargetsSheet> createState() => _EditTargetsSheetState();
}

class _EditTargetsSheetState extends State<_EditTargetsSheet> {
  late final TextEditingController _companyTargetController;
  late final TextEditingController _notesController;
  late Map<String, TextEditingController> _userControllers;
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _companyTargetController = TextEditingController(text: widget.bundle.companyTargetAmount.toStringAsFixed(2));
    _notesController = TextEditingController(text: widget.bundle.companyNotes);
    _userControllers = {
      for (final ut in widget.bundle.userTargets) ut.userId: TextEditingController(text: ut.targetAmount.toStringAsFixed(2)),
    };
  }

  @override
  void dispose() {
    _companyTargetController.dispose();
    _notesController.dispose();
    for (final c in _userControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _splitEvenly() {
    final total = double.tryParse(_companyTargetController.text) ?? 0;
    if (total <= 0 || _userControllers.isEmpty) return;
    final split = (total / _userControllers.length);
    setState(() {
      for (final c in _userControllers.values) {
        c.text = split.toStringAsFixed(2);
      }
    });
  }

  Future<void> _submit() async {
    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.saveTargets(
        year: widget.bundle.year,
        month: widget.bundle.month,
        companyTargetAmount: double.tryParse(_companyTargetController.text) ?? 0,
        companyNotes: _notesController.text.trim(),
        userTargets: _userControllers.entries
            .map((e) => {'user_id': e.key, 'target_amount': double.tryParse(e.value.text) ?? 0})
            .toList(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isSaving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) {
          return SingleChildScrollView(
            controller: scrollController,
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Edit sales targets', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _companyTargetController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Company target'),
                ),
                const SizedBox(height: 12),
                TextFormField(controller: _notesController, decoration: const InputDecoration(labelText: 'Notes (optional)')),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(onPressed: _splitEvenly, child: const Text('Split evenly among staff')),
                ),
                const Divider(),
                for (final ut in widget.bundle.userTargets)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      children: [
                        Expanded(child: Text(ut.name)),
                        SizedBox(
                          width: 120,
                          child: TextFormField(
                            controller: _userControllers[ut.userId],
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            decoration: const InputDecoration(isDense: true),
                          ),
                        ),
                      ],
                    ),
                  ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(_error!, style: TextStyle(color: Colors.red.shade400)),
                ],
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _isSaving ? null : _submit,
                  child: _isSaving
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Save targets'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
