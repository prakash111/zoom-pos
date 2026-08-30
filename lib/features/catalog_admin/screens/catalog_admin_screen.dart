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

/// Categories management, reached from the "Inventory Management" hub
/// (see lib/features/inventory/screens/inventory_management_screen.dart).
/// This — along with [BrandsScreen], [UnitsScreen] and [SuppliersScreen]
/// below — used to be a tab under a single "Catalog Admin" screen; each is
/// now its own destination so it fits the Products/Categories/Brands
/// sub-module structure the hub presents.
class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key});

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  late final CategoriesRepository repository;
  late Future<List<CategoryModel>> _future;

  @override
  void initState() {
    super.initState();
    repository = CategoriesRepository(context.read<ApiClient>());
    _future = repository.fetchCategories();
  }

  void _reload() => setState(() => _future = repository.fetchCategories());

  Future<void> _openForm({CategoryModel? category}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CategoryFormSheet(repository: repository, category: category),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(CategoryModel category) async {
    final confirmed = await _confirmDelete(context, category.name);
    if (confirmed != true) return;
    try {
      await repository.deleteCategory(category.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Categories')),
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

class BrandsScreen extends StatefulWidget {
  const BrandsScreen({super.key});

  @override
  State<BrandsScreen> createState() => _BrandsScreenState();
}

class _BrandsScreenState extends State<BrandsScreen> {
  late final BrandsRepository repository;
  late Future<List<BrandModel>> _future;

  @override
  void initState() {
    super.initState();
    repository = BrandsRepository(context.read<ApiClient>());
    _future = repository.fetchBrands();
  }

  void _reload() => setState(() => _future = repository.fetchBrands());

  Future<void> _openForm({BrandModel? brand}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => BrandFormDialog(repository: repository, brand: brand),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(BrandModel brand) async {
    final confirmed = await _confirmDelete(context, brand.name);
    if (confirmed != true) return;
    try {
      await repository.deleteBrand(brand.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Brands')),
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

class UnitsScreen extends StatefulWidget {
  const UnitsScreen({super.key});

  @override
  State<UnitsScreen> createState() => _UnitsScreenState();
}

class _UnitsScreenState extends State<UnitsScreen> {
  late final UnitsRepository repository;
  late Future<List<UnitModel>> _future;

  @override
  void initState() {
    super.initState();
    repository = UnitsRepository(context.read<ApiClient>());
    _future = repository.fetchUnits();
  }

  void _reload() => setState(() => _future = repository.fetchUnits());

  Future<void> _openForm({UnitModel? unit}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => UnitFormDialog(repository: repository, unit: unit),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(UnitModel unit) async {
    final confirmed = await _confirmDelete(context, unit.name);
    if (confirmed != true) return;
    try {
      await repository.deleteUnit(unit.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Units')),
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

class SuppliersScreen extends StatefulWidget {
  const SuppliersScreen({super.key});

  @override
  State<SuppliersScreen> createState() => _SuppliersScreenState();
}

class _SuppliersScreenState extends State<SuppliersScreen> {
  late final SuppliersRepository repository;
  late Future<List<SupplierModel>> _future;

  @override
  void initState() {
    super.initState();
    repository = SuppliersRepository(context.read<ApiClient>());
    _future = repository.fetchSuppliers();
  }

  void _reload() => setState(() => _future = repository.fetchSuppliers());

  Future<void> _openForm({SupplierModel? supplier}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => SupplierFormSheet(repository: repository, supplier: supplier),
    );
    if (saved == true) _reload();
  }

  Future<void> _delete(SupplierModel supplier) async {
    final confirmed = await _confirmDelete(context, supplier.name);
    if (confirmed != true) return;
    try {
      await repository.deleteSupplier(supplier.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Suppliers')),
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
