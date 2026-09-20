import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/widgets/app_network_image.dart';

class StorefrontItem {
  final ProductModel product;
  int quantity;

  StorefrontItem({required this.product, this.quantity = 1});
}

class StorefrontScreen extends StatefulWidget {
  final String? storeSlug;
  final String? catalogId;

  const StorefrontScreen({super.key, this.storeSlug, this.catalogId});

  @override
  State<StorefrontScreen> createState() => _StorefrontScreenState();
}

class _StorefrontScreenState extends State<StorefrontScreen> {
  bool _isLoading = true;

  Map<String, dynamic> _company = {
    'name': 'PK Digital Store',
    'phone': '+1 (555) 019-2834',
    'city': 'Bengaluru',
    'currency': 'USD',
  };

  List<String> _categories = [];
  List<ProductModel> _products = [];
  final Set<String> _wishlist = {};

  String _selectedCategory = 'all';
  String _searchQuery = '';
  String _sortBy = 'featured';

  final List<StorefrontItem> _cart = [];

  // Checkout form controllers
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  String _paymentMethod = 'cod';
  bool _isSubmittingOrder = false;

  @override
  void initState() {
    super.initState();
    _loadStorefrontData();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    super.dispose();
  }

  Future<void> _loadStorefrontData() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final client = context.read<ApiClient>();
      final uri = widget.storeSlug != null
          ? '/api/storefront/catalog?store=${widget.storeSlug}'
          : '/api/storefront/catalog';

      final response = await client.get(uri);
      if (response['products'] is List) {
        final rawCompany = response['company'] as Map<String, dynamic>?;
        if (rawCompany != null) {
          _company = rawCompany;
        }

        final rawProducts = response['products'] as List;
        _products = rawProducts
            .map((p) => ProductModel.fromJson(p as Map<String, dynamic>))
            .toList();

        final rawCategories = response['categories'] as List?;
        if (rawCategories != null) {
          _categories = rawCategories
              .map((c) => (c['name'] ?? '').toString())
              .where((name) => name.isNotEmpty)
              .toList();
        } else {
          _categories = _products
              .map((p) => p.categoryName)
              .toSet()
              .toList();
        }
      }
    } catch (e) {
      if (kDebugMode) print('Error loading storefront catalog: $e');
      // If API call fails (e.g. offline/mock demo), keep fallback products
      if (_products.isEmpty) {
        _products = _fallbackProducts();
        _categories = ['Beverages', 'Packaged Snacks', 'Electronics & Accessories', 'Household Goods'];
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  List<ProductModel> _fallbackProducts() {
    return [
      ProductModel(
        id: '832',
        name: 'Braided USB-C Fast Charging Cable 2m',
        barcode: 'USBC-2M',
        sku: 'ELEC-001',
        salePrice: 12.99,
        costPrice: 5.0,
        currentStock: 30,
        minimumStock: 5,
        unit: 'pcs',
        categoryId: '1',
        categoryName: 'Electronics & Accessories',
        brandName: 'Originals',
        imageUrl: null,
        taxRate: 0,
        active: true,
        isLowStock: false,
        description: 'Heavy-duty 100W power delivery fast charging cable. Anti-tangle nylon braided with reinforced zinc alloy connectors.',
      ),
      ProductModel(
        id: '833',
        name: 'Compact Dual-Port USB-A/C Wall Adapter',
        barcode: 'ADPT-45W',
        sku: 'ELEC-002',
        salePrice: 18.50,
        costPrice: 9.0,
        currentStock: 25,
        minimumStock: 4,
        unit: 'pcs',
        categoryId: '1',
        categoryName: 'Electronics & Accessories',
        brandName: 'Originals',
        imageUrl: null,
        taxRate: 0,
        active: true,
        isLowStock: false,
        description: 'GaN technology 45W dual fast charger with smart chip surge protection for phone and laptops.',
      ),
      ProductModel(
        id: '828',
        name: 'Organic Cold Brew Coffee (330ml)',
        barcode: 'COFFEE-330',
        sku: 'BEV-001',
        salePrice: 3.50,
        costPrice: 1.5,
        currentStock: 44,
        minimumStock: 10,
        unit: 'can',
        categoryId: '2',
        categoryName: 'Beverages',
        brandName: 'ColdCraft',
        imageUrl: null,
        taxRate: 0,
        active: true,
        isLowStock: false,
        description: 'Steeped for 18 hours using 100% organic single-origin Arabica beans. Rich, smooth, and naturally sweet.',
      ),
      ProductModel(
        id: '830',
        name: 'Artisan Potato Crisps - Sea Salt',
        barcode: 'CHIP-SALT',
        sku: 'SNACK-001',
        salePrice: 2.75,
        costPrice: 1.0,
        currentStock: 35,
        minimumStock: 8,
        unit: 'bag',
        categoryId: '3',
        categoryName: 'Packaged Snacks',
        brandName: 'CrunchWorks',
        imageUrl: null,
        taxRate: 0,
        active: true,
        isLowStock: false,
        description: 'Kettle-cooked golden russet potatoes sprinkled with natural Celtic sea salt.',
      ),
    ];
  }

  int get _cartTotalCount => _cart.fold(0, (sum, i) => sum + i.quantity);
  double get _cartTotalPrice => _cart.fold(0.0, (sum, i) => sum + (i.product.salePrice * i.quantity));

  void _addToCart(ProductModel product, [int qty = 1]) {
    setState(() {
      final index = _cart.indexWhere((item) => item.product.id == product.id);
      if (index >= 0) {
        _cart[index].quantity += qty;
      } else {
        _cart.add(StorefrontItem(product: product, quantity: qty));
      }
    });
  }

  void _decreaseQty(String productId) {
    setState(() {
      final index = _cart.indexWhere((item) => item.product.id == productId);
      if (index >= 0) {
        if (_cart[index].quantity > 1) {
          _cart[index].quantity--;
        } else {
          _cart.removeAt(index);
        }
      }
    });
  }

  int _getItemQty(String productId) {
    final index = _cart.indexWhere((item) => item.product.id == productId);
    return index >= 0 ? _cart[index].quantity : 0;
  }

  List<ProductModel> get _filteredProducts {
    var list = _products.where((p) {
      if (_selectedCategory != 'all' && p.categoryName != _selectedCategory) {
        return false;
      }
      if (_searchQuery.trim().isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        final matchesName = p.name.toLowerCase().contains(q);
        final matchesSku = p.sku.toLowerCase().contains(q);
        final matchesCat = p.categoryName.toLowerCase().contains(q);
        if (!matchesName && !matchesSku && !matchesCat) return false;
      }
      return true;
    }).toList();

    if (_sortBy == 'price_low') {
      list.sort((a, b) => a.salePrice.compareTo(b.salePrice));
    } else if (_sortBy == 'price_high') {
      list.sort((a, b) => b.salePrice.compareTo(a.salePrice));
    } else if (_sortBy == 'name') {
      list.sort((a, b) => a.name.compareTo(b.name));
    }
    return list;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : CustomScrollView(
              slivers: [
                // 1. Top Announcement Bar (matching store-idea.mp4)
                SliverToBoxAdapter(
                  child: Container(
                    color: const Color(0xFF064E3B),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.phone, size: 14, color: Color(0xFF34D399)),
                            const SizedBox(width: 6),
                            Text(
                              _company['phone'] ?? '+1 (555) 019-2834',
                              style: const TextStyle(fontSize: 12, color: Colors.white, fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                        const Expanded(
                          child: Text(
                            'Get 50% Off on Selected Items | Shop Now',
                            textAlign: TextAlign.center,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(fontSize: 12, color: Color(0xFFA7F3D0), fontWeight: FontWeight.bold),
                          ),
                        ),
                        Row(
                          children: [
                            const Icon(Icons.location_on_outlined, size: 14, color: Color(0xFF34D399)),
                            const SizedBox(width: 4),
                            Text(
                              _company['city'] ?? 'Location',
                              style: const TextStyle(fontSize: 12, color: Colors.white70, fontWeight: FontWeight.w500),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

                // 2. Main Storefront Header (matching store-idea.mp4)
                SliverToBoxAdapter(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 4, offset: const Offset(0, 2)),
                      ],
                    ),
                    child: Row(
                      children: [
                        // Store Branding
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(colors: [Color(0xFF059669), Color(0xFF10B981)]),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Text(
                              (_company['name'] ?? 'S').substring(0, 1).toUpperCase(),
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 18),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(
                                  _company['name'] ?? 'Shopcart',
                                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                                ),
                                const SizedBox(width: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFD1FAE5),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: const Text('Verified', style: TextStyle(fontSize: 10, color: Color(0xFF065F46), fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ),
                            const Text('Online Storefront', style: TextStyle(fontSize: 11, color: Colors.grey)),
                          ],
                        ),
                        const Spacer(),

                        // Cart Pill Trigger Button
                        InkWell(
                          onTap: _showCartDrawer,
                          borderRadius: BorderRadius.circular(16),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                            decoration: BoxDecoration(
                              color: const Color(0xFF059669),
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: [
                                BoxShadow(color: const Color(0xFF059669).withValues(alpha: 0.25), blurRadius: 8, offset: const Offset(0, 3)),
                              ],
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.shopping_bag_outlined, color: Colors.white, size: 18),
                                const SizedBox(width: 6),
                                const Text('Cart', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                                if (_cartTotalCount > 0) ...[
                                  const SizedBox(width: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                                    child: Text('$_cartTotalCount', style: const TextStyle(color: Color(0xFF059669), fontWeight: FontWeight.w900, fontSize: 11)),
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    '\$${_cartTotalPrice.toStringAsFixed(2)}',
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 12),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // 3. Hero Showcase Banner (matching store-idea.mp4)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                    child: Container(
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF064E3B), Color(0xFF0F172A)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: [
                          BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, 6)),
                        ],
                      ),
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Text('✨ SPECIAL STORE DEALS', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
                          ),
                          const SizedBox(height: 12),
                          const Text(
                            'Grab Up To 50% Off On Selected Products',
                            style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900, height: 1.2),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Explore our verified store selection. Instant live sync with POS inventory.',
                            style: TextStyle(color: Color(0xFFD1FAE5), fontSize: 13),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            children: [
                              ElevatedButton(
                                onPressed: () {},
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF10B981),
                                  foregroundColor: Colors.black,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                                ),
                                child: const Text('Shop Now', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13)),
                              ),
                              const SizedBox(width: 12),
                              const Text('⭐ 4.9/5 Rating  •  🚚 Fast Dispatch', style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 11, fontWeight: FontWeight.bold)),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                // 4. Search & Filter Bar
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    child: Column(
                      children: [
                        // Search Bar
                        TextField(
                          onChanged: (v) => setState(() => _searchQuery = v),
                          decoration: InputDecoration(
                            hintText: 'Search products in store…',
                            prefixIcon: const Icon(Icons.search, size: 20, color: Colors.grey),
                            filled: true,
                            fillColor: Colors.white,
                            contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(16),
                              borderSide: BorderSide(color: Colors.grey.shade200),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(16),
                              borderSide: BorderSide(color: Colors.grey.shade200),
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),

                        // Categories Horizontal Pills
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              ChoiceChip(
                                label: Text('All (${_products.length})'),
                                selected: _selectedCategory == 'all',
                                onSelected: (_) => setState(() => _selectedCategory = 'all'),
                                selectedColor: const Color(0xFF059669),
                                labelStyle: TextStyle(
                                  color: _selectedCategory == 'all' ? Colors.white : Colors.black87,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                              const SizedBox(width: 8),
                              ..._categories.map((cat) {
                                final isSelected = _selectedCategory == cat;
                                return Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ChoiceChip(
                                    label: Text(cat),
                                    selected: isSelected,
                                    onSelected: (_) => setState(() => _selectedCategory = cat),
                                    selectedColor: const Color(0xFF059669),
                                    labelStyle: TextStyle(
                                      color: isSelected ? Colors.white : Colors.black87,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 12,
                                    ),
                                  ),
                                );
                              }),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // 5. Products Grid (matching store-idea.mp4)
                SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  sliver: _filteredProducts.isEmpty
                      ? const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.all(40),
                            child: Center(child: Text('No products match your search or filter.')),
                          ),
                        )
                      : SliverGrid(
                          gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                            maxCrossAxisExtent: 220,
                            mainAxisSpacing: 14,
                            crossAxisSpacing: 14,
                            childAspectRatio: 0.62,
                          ),
                          delegate: SliverChildBuilderDelegate(
                            (context, index) {
                              final product = _filteredProducts[index];
                              final isFav = _wishlist.contains(product.id);
                              final inCartQty = _getItemQty(product.id);

                              return _buildProductCard(product, isFav, inCartQty);
                            },
                            childCount: _filteredProducts.length,
                          ),
                        ),
                ),

                // 6. Services To Help You Shop (matching store-idea.mp4)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 16),
                        const Text(
                          'Services To Help You Shop',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(child: _buildServiceCard('❓', 'FAQ Updates', 'Safe shopping & guarantees')),
                            const SizedBox(width: 10),
                            Expanded(child: _buildServiceCard('💳', 'Online Payment', 'COD & secure cards')),
                            const SizedBox(width: 10),
                            Expanded(child: _buildServiceCard('🚚', 'Home Delivery', 'Same-day POS dispatch')),
                          ],
                        ),
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildProductCard(ProductModel product, bool isFav, int inCartQty) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 8, offset: const Offset(0, 2)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Image container with Wishlist Heart
          Stack(
            children: [
              GestureDetector(
                onTap: () => _showProductDetailModal(product),
                child: Container(
                  height: 140,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  child: Center(
                    child: product.imageUrl != null && product.imageUrl!.isNotEmpty
                        ? AppNetworkImage(imageUrl: product.imageUrl!, fit: BoxFit.cover)
                        : const Icon(Icons.shopping_bag_outlined, size: 48, color: Colors.black26),
                  ),
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: InkWell(
                  onTap: () {
                    setState(() {
                      if (isFav) {
                        _wishlist.remove(product.id);
                      } else {
                        _wishlist.add(product.id);
                      }
                    });
                  },
                  child: CircleAvatar(
                    radius: 14,
                    backgroundColor: Colors.white.withValues(alpha: 0.9),
                    child: Icon(
                      isFav ? Icons.favorite : Icons.favorite_border,
                      size: 16,
                      color: isFav ? Colors.red : Colors.grey,
                    ),
                  ),
                ),
              ),
            ],
          ),

          // Details
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Price
                  Text(
                    '\$${product.salePrice.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 4),

                  // Name
                  GestureDetector(
                    onTap: () => _showProductDetailModal(product),
                    child: Text(
                      product.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                    ),
                  ),
                  const SizedBox(height: 2),

                  // Rich Description (Only when populated)
                  if ((product.description ?? '').trim().isNotEmpty) ...[
                    Text(
                      product.description!.trim(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 11, color: Colors.grey, height: 1.2),
                    ),
                    const SizedBox(height: 2),
                  ],
                  const Spacer(),

                  // 5 Green Stars (★★★★★) with (121) from store-idea.mp4
                  Row(
                    children: [
                      const Text('★★★★★', style: TextStyle(color: Color(0xFF10B981), fontSize: 11)),
                      const SizedBox(width: 4),
                      Text('(${80 + (int.tryParse(product.id) ?? 0) % 50})', style: const TextStyle(fontSize: 10, color: Colors.grey)),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Add To Cart / Stepper Pill Button
                  if (inCartQty == 0)
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () => _addToCart(product),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: Color(0xFF10B981)),
                          backgroundColor: const Color(0xFFECFDF5),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          padding: const EdgeInsets.symmetric(vertical: 8),
                        ),
                        icon: const Icon(Icons.add, size: 16, color: Color(0xFF059669)),
                        label: const Text('Add to Cart', style: TextStyle(color: Color(0xFF059669), fontWeight: FontWeight.bold, fontSize: 12)),
                      ),
                    )
                  else
                    Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFF059669),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          IconButton(
                            onPressed: () => _decreaseQty(product.id),
                            icon: const Icon(Icons.remove, size: 14, color: Colors.white),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                          Text('$inCartQty', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 12)),
                          IconButton(
                            onPressed: () => _addToCart(product),
                            icon: const Icon(Icons.add, size: 14, color: Colors.white),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildServiceCard(String emoji, String title, String subtitle) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(emoji, style: const TextStyle(fontSize: 22)),
          const SizedBox(height: 6),
          Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
          const SizedBox(height: 2),
          Text(subtitle, style: const TextStyle(fontSize: 10, color: Colors.grey)),
        ],
      ),
    );
  }

  // 7. Product Detail Modal Sheet (matching store-idea.mp4)
  void _showProductDetailModal(ProductModel product) {
    int modalQty = 1;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              padding: const EdgeInsets.all(20),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(4)),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Big Image
                    Container(
                      height: 220,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade50,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Center(
                        child: product.imageUrl != null && product.imageUrl!.isNotEmpty
                            ? AppNetworkImage(imageUrl: product.imageUrl!, fit: BoxFit.contain)
                            : const Icon(Icons.shopping_bag_outlined, size: 64, color: Colors.grey),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Title & Category
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(color: const Color(0xFFD1FAE5), borderRadius: BorderRadius.circular(8)),
                          child: Text(product.categoryName, style: const TextStyle(fontSize: 11, color: Color(0xFF065F46), fontWeight: FontWeight.bold)),
                        ),
                        const Spacer(),
                        const Text('★★★★★ (121 reviews)', style: TextStyle(color: Color(0xFF10B981), fontSize: 12, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(product.name, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),

                    // Price & Discount
                    Row(
                      children: [
                        Text('\$${product.salePrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF059669))),
                        const SizedBox(width: 8),
                        Text('\$${(product.salePrice * 1.25).toStringAsFixed(2)}', style: const TextStyle(fontSize: 13, decoration: TextDecoration.lineThrough, color: Colors.grey)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(6)),
                          child: const Text('20% OFF', style: TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Full Rich Description (Only when populated)
                    if ((product.description ?? '').trim().isNotEmpty) ...[
                      const Text('Description', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      const SizedBox(height: 4),
                      Text(product.description!.trim(), style: const TextStyle(fontSize: 12, color: Colors.black87, height: 1.4)),
                      const SizedBox(height: 16),
                    ],

                    // Specs Table
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
                      child: Column(
                        children: [
                          _specRow('SKU / Code', product.sku.isNotEmpty ? product.sku : product.barcode),
                          _specRow('Unit', product.unit),
                          _specRow('Availability', 'In Stock (${product.currentStock.toInt()} available)'),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Stepper & Buy Now
                    Row(
                      children: [
                        Container(
                          decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(12)),
                          child: Row(
                            children: [
                              IconButton(
                                onPressed: () {
                                  if (modalQty > 1) setModalState(() => modalQty--);
                                },
                                icon: const Icon(Icons.remove, size: 16),
                              ),
                              Text('$modalQty', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                              IconButton(
                                onPressed: () => setModalState(() => modalQty++),
                                icon: const Icon(Icons.add, size: 16),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () {
                              _addToCart(product, modalQty);
                              Navigator.pop(ctx);
                              _showCartDrawer();
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF059669),
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                            child: const Text('Buy Now', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _specRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
          Text(value, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black87)),
        ],
      ),
    );
  }

  // 8. Slide-over Shopping Cart & Checkout (matching store-idea.mp4)
  void _showCartDrawer() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setCartState) {
            return DraggableScrollableSheet(
              initialChildSize: 0.85,
              maxChildSize: 0.95,
              minChildSize: 0.5,
              expand: false,
              builder: (_, scrollController) {
                return Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Header
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Your Order Cart (${_cartTotalCount})', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
                          IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
                        ],
                      ),
                      const Divider(),

                      if (_cart.isEmpty)
                        const Expanded(
                          child: Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text('🛒', style: TextStyle(fontSize: 48)),
                                SizedBox(height: 8),
                                Text('Your cart is currently empty.', style: TextStyle(fontWeight: FontWeight.bold)),
                              ],
                            ),
                          ),
                        )
                      else ...[
                        // Cart Items List
                        Expanded(
                          child: ListView.separated(
                            controller: scrollController,
                            itemCount: _cart.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final item = _cart[index];
                              return Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
                                child: Row(
                                  children: [
                                    Container(
                                      width: 44,
                                      height: 44,
                                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
                                      child: const Center(child: Icon(Icons.inventory_2_outlined, color: Colors.grey, size: 24)),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(item.product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                          Text('\$${item.product.salePrice.toStringAsFixed(2)} x ${item.quantity}', style: const TextStyle(fontSize: 11, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                                        ],
                                      ),
                                    ),
                                    // Cart Stepper
                                    Container(
                                      decoration: BoxDecoration(
                                        color: Colors.white,
                                        border: Border.all(color: Colors.grey.shade300),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          InkWell(
                                            onTap: () {
                                              _decreaseQty(item.product.id);
                                              setCartState(() {});
                                              setState(() {});
                                            },
                                            child: const Padding(
                                              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              child: Text('−', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87)),
                                            ),
                                          ),
                                          Padding(
                                            padding: const EdgeInsets.symmetric(horizontal: 6),
                                            child: Text('${item.quantity}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                          ),
                                          InkWell(
                                            onTap: () {
                                              _addToCart(item.product);
                                              setCartState(() {});
                                              setState(() {});
                                            },
                                            child: const Padding(
                                              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              child: Text('+', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.black87)),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline, size: 18, color: Colors.grey),
                                      onPressed: () {
                                        setState(() {
                                          _cart.removeWhere((i) => i.product.id == item.product.id);
                                        });
                                        setCartState(() {});
                                      },
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                        ),

                        // Delivery & Checkout Form
                        const SizedBox(height: 12),
                        const Text('Customer & Delivery Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        const SizedBox(height: 6),
                        // Row 1: Full Name * | Phone / WhatsApp *
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _nameController,
                                decoration: const InputDecoration(labelText: 'Full Name *', isDense: true),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _phoneController,
                                decoration: const InputDecoration(labelText: 'Phone / WhatsApp *', isDense: true),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        // Row 2: Email Address (Optional) | City *
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _emailController,
                                decoration: const InputDecoration(labelText: 'Email Address (Optional)', isDense: true),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _cityController,
                                decoration: const InputDecoration(labelText: 'City *', isDense: true),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        // Row 3: Street Address *
                        TextField(
                          controller: _addressController,
                          decoration: const InputDecoration(labelText: 'Street Address *', isDense: true),
                        ),
                        const SizedBox(height: 12),

                        // Totals Breakdown (Full flex, zero horizontal text clipping)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Subtotal:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                            Text('\$${_cartTotalPrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                          ],
                        ),
                        const SizedBox(height: 4),
                        const Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Estimated Delivery:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                            Text('FREE', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total Order Amount:', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
                            Text('\$${_cartTotalPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: Color(0xFF059669))),
                          ],
                        ),
                        const SizedBox(height: 12),

                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _isSubmittingOrder ? null : () => _submitOrder(ctx),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF059669),
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            ),
                            child: _isSubmittingOrder
                                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                : const Text('Place Order Now', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  // 9. Order Submission & Confirmation Dialog (matching store-idea.mp4)
  Future<void> _submitOrder(BuildContext modalContext) async {
    if (_nameController.text.trim().isEmpty || _phoneController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your full name and phone number.')),
      );
      return;
    }
    if (_cityController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your city.')),
      );
      return;
    }
    if (_addressController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your delivery street address.')),
      );
      return;
    }

    setState(() => _isSubmittingOrder = true);

    try {
      final client = context.read<ApiClient>();
      final emailVal = _emailController.text.trim();
      final payload = {
        'customer_name': _nameController.text.trim(),
        'customer_phone': _phoneController.text.trim(),
        'customer_email': emailVal.isNotEmpty ? emailVal : null,
        'email': emailVal.isNotEmpty ? emailVal : null,
        'delivery_address': _addressController.text.trim(),
        'address': _addressController.text.trim(),
        'city': _cityController.text.trim(),
        'payment_method': _paymentMethod,
        'items': _cart.map((i) => {
          'id': i.product.id,
          'name': i.product.name,
          'quantity': i.quantity,
          'price': i.product.salePrice,
        }).toList(),
      };

      final response = await client.post('/api/storefront/order', data: payload);
      final saleNumber = response['sale_number']?.toString() ?? 'WEB-${DateTime.now().millisecondsSinceEpoch}';

      if (mounted) {
        Navigator.pop(modalContext);
        setState(() {
          _cart.clear();
        });
        _showOrderAcceptedDialog(saleNumber);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Order submission notice: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmittingOrder = false);
    }
  }

  void _showOrderAcceptedDialog(String saleNumber) {
    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(height: 10),
              Container(
                width: 70,
                height: 70,
                decoration: const BoxDecoration(color: Color(0xFFD1FAE5), shape: BoxShape.circle),
                child: const Icon(Icons.check, size: 40, color: Color(0xFF059669)),
              ),
              const SizedBox(height: 16),
              const Text('Your order has been accepted!', textAlign: TextAlign.center, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Text('Transaction ID: $saleNumber', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF059669), fontSize: 13)),
              const SizedBox(height: 8),
              const Text(
                'Thank you for shopping with us! The store has received your order and is preparing it.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(ctx),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF059669),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Continue Shopping', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
