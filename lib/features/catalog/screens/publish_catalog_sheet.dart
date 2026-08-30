import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/published_catalog_model.dart';
import '../catalog_repository.dart';

const _ttlOptions = [1, 7, 30, 0];

/// Bottom sheet for POST /catalog. Pops with `true` when published.
class PublishCatalogSheet extends StatefulWidget {
  const PublishCatalogSheet({super.key, required this.repository, required this.products});

  final CatalogRepository repository;
  final List<CatalogProductOption> products;

  @override
  State<PublishCatalogSheet> createState() => _PublishCatalogSheetState();
}

class _PublishCatalogSheetState extends State<PublishCatalogSheet> {
  final _titleController = TextEditingController();
  final Set<String> _selectedIds = {};
  int _ttlDays = 7;
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_titleController.text.trim().isEmpty) {
      setState(() => _error = 'Enter a title.');
      return;
    }
    if (_selectedIds.isEmpty) {
      setState(() => _error = 'Select at least one product.');
      return;
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.publish(title: _titleController.text.trim(), productIds: _selectedIds.toList(), ttlDays: _ttlDays);
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
          return Column(
            children: [
              const SizedBox(height: 12),
              Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Publish catalog', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 12),
                    TextField(controller: _titleController, decoration: const InputDecoration(labelText: 'Catalog title')),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      value: _ttlDays,
                      decoration: const InputDecoration(labelText: 'Link expires in'),
                      items: [
                        for (final days in _ttlOptions)
                          DropdownMenuItem(value: days, child: Text(days == 0 ? 'Never' : '$days days')),
                      ],
                      onChanged: (value) => setState(() => _ttlDays = value ?? _ttlDays),
                    ),
                    const SizedBox(height: 8),
                    Text('${_selectedIds.length} of ${widget.products.length} products selected', style: TextStyle(color: Colors.grey.shade600)),
                  ],
                ),
              ),
              Expanded(
                child: ListView.builder(
                  controller: scrollController,
                  itemCount: widget.products.length,
                  itemBuilder: (context, index) {
                    final product = widget.products[index];
                    final selected = _selectedIds.contains(product.id);
                    return CheckboxListTile(
                      value: selected,
                      title: Text(product.name),
                      subtitle: Text(product.salePrice.toStringAsFixed(2)),
                      onChanged: (checked) => setState(() {
                        if (checked == true) {
                          _selectedIds.add(product.id);
                        } else {
                          _selectedIds.remove(product.id);
                        }
                      }),
                    );
                  },
                ),
              ),
              if (_error != null)
                Padding(padding: const EdgeInsets.all(8), child: Text(_error!, style: TextStyle(color: Colors.red.shade400))),
              Padding(
                padding: const EdgeInsets.all(16),
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _submit,
                  child: _isSaving
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Publish'),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
