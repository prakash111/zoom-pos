import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/brand_model.dart';
import '../../../core/models/category_model.dart';
import '../../../core/models/supplier_model.dart';
import '../../../core/models/unit_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../brands_repository.dart';
import '../categories_repository.dart';
import '../suppliers_repository.dart';
import '../units_repository.dart';
import 'brand_form_dialog.dart';
import 'category_form_sheet.dart';
import 'supplier_form_sheet.dart';
import 'unit_form_dialog.dart';

/// Catalog admin hub: Categories / Brands / Units / Suppliers, mirroring
/// the web "Products & Inventory" nav section's lookup-table pages.
class CatalogAdminScreen extends StatefulWidget {
  const CatalogAdminScreen({super.key});

  @override
  State<CatalogAdminScreen> createState() => _CatalogAdminScreenState();
}

class _CatalogAdminScreenState extends State<CatalogAdminScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catalog Admin'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [Tab(text: 'Categories'), Tab(text: 'Brands'), Tab(text: 'Units'), Tab(text: 'Suppliers')],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _CategoriesTab(repository: CategoriesRepository(apiClient)),
          _BrandsTab(repository: BrandsRepository(apiClient)),
          _UnitsTab(repository: UnitsRepository(apiClient)),
          _SuppliersTab(repository: SuppliersRepository(apiClient)),
        ],
      ),
    );
  }
}

class _CategoriesTab extends StatefulWidget {
  const _CategoriesTab({required this.repository});

  final CategoriesRepository repository;

  @override
  State<_CategoriesTab> createState() => _CategoriesTabState();
}

class _CategoriesTabState extends State<_CategoriesTab> {
  late Future<List<CategoryModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchCategories();
  }

  void _reload() => setState(() => _future = widget.repository.fetchCategories());

  Future<void> _openForm({CategoryModel? category}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CategoryFormSheet(repository: widget.repository, category: category),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(CategoryModel category) async {
    final confirmed = await _confirmDelete(context, category.name);
    if (confirmed != true) return;
    try {
      await widget.repository.deleteCategory(category.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<CategoryModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) {
            return ErrorView(message: _errorMessage(snapshot.error), onRetry: _reload);
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) return const Center(child: Text('No categories yet.'));

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final category = items[index];
                return Card(
                  child: ListTile(
                    onTap: () => _openForm(category: category),
                    leading: CircleAvatar(backgroundColor: _parseColor(category.color) ?? Colors.grey),
                    title: Text(category.name),
                    subtitle: category.description.isEmpty ? null : Text(category.description),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => _delete(category),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _BrandsTab extends StatefulWidget {
  const _BrandsTab({required this.repository});

  final BrandsRepository repository;

  @override
  State<_BrandsTab> createState() => _BrandsTabState();
}

class _BrandsTabState extends State<_BrandsTab> {
  late Future<List<BrandModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchBrands();
  }

  void _reload() => setState(() => _future = widget.repository.fetchBrands());

  Future<void> _openForm({BrandModel? brand}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => BrandFormDialog(repository: widget.repository, brand: brand),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(BrandModel brand) async {
    final confirmed = await _confirmDelete(context, brand.name);
    if (confirmed != true) return;
    try {
      await widget.repository.deleteBrand(brand.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<BrandModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) {
            return ErrorView(message: _errorMessage(snapshot.error), onRetry: _reload);
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) return const Center(child: Text('No brands yet.'));

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final brand = items[index];
                return Card(
                  child: ListTile(
                    onTap: () => _openForm(brand: brand),
                    title: Text(brand.name),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => _delete(brand),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _UnitsTab extends StatefulWidget {
  const _UnitsTab({required this.repository});

  final UnitsRepository repository;

  @override
  State<_UnitsTab> createState() => _UnitsTabState();
}

class _UnitsTabState extends State<_UnitsTab> {
  late Future<List<UnitModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchUnits();
  }

  void _reload() => setState(() => _future = widget.repository.fetchUnits());

  Future<void> _openForm({UnitModel? unit}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => UnitFormDialog(repository: widget.repository, unit: unit),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(UnitModel unit) async {
    final confirmed = await _confirmDelete(context, unit.name);
    if (confirmed != true) return;
    try {
      await widget.repository.deleteUnit(unit.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<UnitModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) {
            return ErrorView(message: _errorMessage(snapshot.error), onRetry: _reload);
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) return const Center(child: Text('No units yet.'));

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final unit = items[index];
                return Card(
                  child: ListTile(
                    onTap: () => _openForm(unit: unit),
                    title: Text(unit.name),
                    subtitle: unit.abbreviation.isEmpty ? null : Text(unit.abbreviation),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => _delete(unit),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _SuppliersTab extends StatefulWidget {
  const _SuppliersTab({required this.repository});

  final SuppliersRepository repository;

  @override
  State<_SuppliersTab> createState() => _SuppliersTabState();
}

class _SuppliersTabState extends State<_SuppliersTab> {
  late Future<List<SupplierModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchSuppliers();
  }

  void _reload() => setState(() => _future = widget.repository.fetchSuppliers());

  Future<void> _openForm({SupplierModel? supplier}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => SupplierFormSheet(repository: widget.repository, supplier: supplier),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(SupplierModel supplier) async {
    final confirmed = await _confirmDelete(context, supplier.name);
    if (confirmed != true) return;
    try {
      await widget.repository.deleteSupplier(supplier.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<SupplierModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) {
            return ErrorView(message: _errorMessage(snapshot.error), onRetry: _reload);
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) return const Center(child: Text('No suppliers yet.'));

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final supplier = items[index];
                return Card(
                  child: ListTile(
                    onTap: () => _openForm(supplier: supplier),
                    title: Text(supplier.name),
                    subtitle: Text([
                      if (supplier.phone.isNotEmpty) supplier.phone,
                      if (!supplier.active) 'Inactive',
                    ].join(' · ')),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => _delete(supplier),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

Color? _parseColor(String? hex) {
  if (hex == null || hex.isEmpty) return null;
  final cleaned = hex.replaceAll('#', '');
  final value = int.tryParse(cleaned, radix: 16);
  if (value == null) return null;
  return Color(0xFF000000 | value);
}

String _errorMessage(Object? error) => error is ApiException ? error.message : 'Something went wrong.';

Future<bool?> _confirmDelete(BuildContext context, String name) {
  return showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text('Delete $name?'),
      content: const Text('This cannot be undone.'),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
        TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
      ],
    ),
  );
}
