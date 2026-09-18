import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/published_catalog_model.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../catalog_repository.dart';
import 'publish_catalog_sheet.dart';

/// Online Catalog: published shareable links, publish/revoke. Mirrors
/// app/Livewire/Tenant/Catalog/Index.php.
class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  late final CatalogRepository _repository;
  late Future<({List<PublishedCatalogModel> catalogs, List<CatalogProductOption> products})> _future;

  @override
  void initState() {
    super.initState();
    _repository = CatalogRepository(context.read<ApiClient>());
    _future = _repository.fetchCatalogs();
  }

  void _reload() => setState(() => _future = _repository.fetchCatalogs());

  Future<void> _openPublish(List<CatalogProductOption> products) async {
    final published = await showAdaptiveSheet<bool>(
      context,
      builder: (_) => PublishCatalogSheet(repository: _repository, products: products),
    );
    if (published == true) _reload();
  }

  Future<void> _copyLink(String url) async {
    await Clipboard.setData(ClipboardData(text: url));
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Link copied to clipboard.')));
  }

  Future<void> _revoke(PublishedCatalogModel catalog) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Revoke "${catalog.title}"?'),
        content: const Text('The public link will stop working immediately.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Revoke')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.revoke(catalog.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Online Catalog')),
      body: FutureBuilder<({List<PublishedCatalogModel> catalogs, List<CatalogProductOption> products})>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load catalogs.', onRetry: _reload);

          final catalogs = snapshot.data!.catalogs;
          final products = snapshot.data!.products;

          return Scaffold(
            floatingActionButton: FloatingActionButton(
              onPressed: products.isEmpty ? null : () => _openPublish(products),
              child: const Icon(Icons.add),
            ),
            body: catalogs.isEmpty
                ? const Center(child: Text('No catalogs published yet.'))
                : RefreshIndicator(
                    onRefresh: () async => _reload(),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
                      itemCount: catalogs.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final catalog = catalogs[index];
                        return Card(
                          child: ListTile(
                            title: Text(catalog.title),
                            subtitle: Text([
                              '${catalog.productCount} products',
                              catalog.isExpired ? 'Expired' : (catalog.expiresAt == null ? 'No expiry' : 'Expires ${catalog.expiresAt!.toLocal().toString().split(' ').first}'),
                            ].join(' · ')),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                IconButton(icon: const Icon(Icons.copy_outlined), onPressed: () => _copyLink(catalog.url)),
                                IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _revoke(catalog)),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          );
        },
      ),
    );
  }
}
