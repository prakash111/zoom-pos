import 'package:flutter/material.dart';

import '../../../core/utils/responsive.dart';
import '../../catalog_admin/screens/catalog_admin_screen.dart';
import 'inventory_screen.dart';

class _InventoryTile {
  const _InventoryTile(this.title, this.icon, this.builder);

  final String title;
  final IconData icon;
  final WidgetBuilder builder;
}

const _tiles = [
  _InventoryTile('Products', Icons.inventory_2_outlined, _buildProducts),
  _InventoryTile('Categories', Icons.category_outlined, _buildCategories),
  _InventoryTile('Brands', Icons.sell_outlined, _buildBrands),
  _InventoryTile('Units', Icons.straighten_outlined, _buildUnits),
  _InventoryTile('Suppliers', Icons.local_shipping_outlined, _buildSuppliers),
];

Widget _buildProducts(BuildContext context) => const InventoryScreen();
Widget _buildCategories(BuildContext context) => const CategoriesScreen();
Widget _buildBrands(BuildContext context) => const BrandsScreen();
Widget _buildUnits(BuildContext context) => const UnitsScreen();
Widget _buildSuppliers(BuildContext context) => const SuppliersScreen();

/// Groups product catalog management under one entry point — previously
/// split across a standalone "Inventory" (products) tile and a separate
/// "Catalog Admin" tile (categories/brands/units/suppliers) on the
/// dashboard.
class InventoryManagementScreen extends StatelessWidget {
  const InventoryManagementScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Inventory Management')),
      body: LayoutBuilder(
        builder: (context, constraints) => GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: gridColumnsFor(constraints.maxWidth, mobile: 2, tablet: 3, desktop: 5),
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.3,
        children: [
          for (final tile in _tiles)
            Card(
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: tile.builder)),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(tile.icon, size: 32, color: Theme.of(context).colorScheme.primary),
                      const SizedBox(height: 10),
                      Text(tile.title, textAlign: TextAlign.center),
                    ],
                  ),
                ),
              ),
            ),
        ],
        ),
      ),
    );
  }
}
