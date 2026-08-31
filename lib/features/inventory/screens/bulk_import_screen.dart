import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../inventory_repository.dart';

/// POST /inventory/import — bulk-creates products from a picked .csv/.txt
/// file. Pops `true` when at least one product was imported, so the caller
/// (InventoryScreen) knows to refresh its catalog.
class BulkImportScreen extends StatefulWidget {
  const BulkImportScreen({super.key});

  @override
  State<BulkImportScreen> createState() => _BulkImportScreenState();
}

class _BulkImportScreenState extends State<BulkImportScreen> {
  PlatformFile? _pickedFile;
  bool _importing = false;
  String? _error;

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['csv', 'txt'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    setState(() {
      _pickedFile = result.files.first;
      _error = null;
    });
  }

  Future<void> _import() async {
    final file = _pickedFile;
    final bytes = file?.bytes;
    if (file == null || bytes == null) return;

    setState(() {
      _importing = true;
      _error = null;
    });

    try {
      final repository = InventoryRepository(context.read<ApiClient>());
      final imported = await repository.bulkImport(bytes, file.name);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$imported product(s) imported.')));
      Navigator.of(context).pop(imported > 0);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _importing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Bulk import products')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text('Expected columns', style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.grey.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Text(
              'name, category, item_code, barcode, cost_price, sale_price, stock',
              style: TextStyle(fontFamily: 'monospace', fontSize: 12),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'One product per line. .csv rows are comma-separated; .txt rows use " | " between '
            'columns. The first row is skipped if it reads "name". item_code and barcode are '
            'optional — a code is auto-generated when left blank.',
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: _importing ? null : _pickFile,
            icon: const Icon(Icons.attach_file),
            label: Text(_pickedFile?.name ?? 'Choose .csv or .txt file'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: (_pickedFile == null || _importing) ? null : _import,
            child: _importing
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Import products'),
          ),
        ],
      ),
    );
  }
}
