import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/theme.dart';
import '../../../core/config/theme_provider.dart';
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

  // Customer Account state
  String? _customerToken;
  Map<String, dynamic>? _customer;
  List<dynamic> _ordersList = [];
  List<dynamic> _savedAddresses = [];

  // Checkout form controllers
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  final _couponController = TextEditingController();

  String _paymentMethod = 'cod';
  List<Map<String, dynamic>> _paymentMethods = [];
  List<Map<String, dynamic>> _faqs = [];
  double _discountAmount = 0.0;
  String? _appliedCouponCode;
  bool _isSubmittingOrder = false;

  @override
  void initState() {
    super.initState();
    _loadStorefrontData();
    _loadCustomerSession();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    _couponController.dispose();
    super.dispose();
  }

  String get _effectiveStoreSlug =>
      widget.storeSlug ?? (_company['slug']?.toString() ?? 'pk-digital-a2vl');

  Future<void> _loadCustomerSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('pos_store_customer_token');
      if (token != null && token.isNotEmpty) {
        setState(() {
          _customerToken = token;
        });
        await _fetchCustomerProfile();
        await _fetchCustomerOrders();
        await _fetchCustomerAddresses();
        await _fetchCustomerWishlist();
      }
    } catch (e) {
      if (kDebugMode) print('Customer session restore notice: $e');
    }
  }

  Future<void> _fetchCustomerProfile() async {
    if (_customerToken == null) return;
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/customer/profile?store=$_effectiveStoreSlug';
      final res = await client.get(uri);
      if (res['customer'] is Map) {
        setState(() {
          _customer = res['customer'] as Map<String, dynamic>;
          _prefillCustomerForm(_customer!);
        });
      }
    } catch (_) {}
  }

  void _prefillCustomerForm(Map<String, dynamic> c) {
    if (_nameController.text.isEmpty && c['name'] != null) {
      _nameController.text = c['name'].toString();
    }
    if (_phoneController.text.isEmpty && c['phone'] != null) {
      _phoneController.text = c['phone'].toString();
    }
    if (_emailController.text.isEmpty && c['email'] != null) {
      _emailController.text = c['email'].toString();
    }
    if (_cityController.text.isEmpty && c['city'] != null) {
      _cityController.text = c['city'].toString();
    }
    if (_addressController.text.isEmpty && c['address'] != null) {
      _addressController.text = c['address'].toString();
    }
  }

  Future<void> _fetchCustomerOrders() async {
    if (_customerToken == null) return;
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/customer/orders?store=$_effectiveStoreSlug';
      final res = await client.get(uri);
      if (res['orders'] != null) {
        final raw = res['orders'];
        final list = (raw is Map && raw['data'] is List) ? raw['data'] : (raw is List ? raw : []);
        setState(() {
          _ordersList = list;
        });
      }
    } catch (_) {}
  }

  Future<void> _fetchCustomerAddresses() async {
    if (_customerToken == null) return;
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/customer/addresses?store=$_effectiveStoreSlug';
      final res = await client.get(uri);
      if (res['addresses'] is List) {
        setState(() {
          _savedAddresses = res['addresses'] as List;
        });
      }
    } catch (_) {}
  }

  Future<void> _fetchCustomerWishlist() async {
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/customer/wishlist?store=$_effectiveStoreSlug';
      final wishRes = await client.get(uri);
      if (wishRes['wishlist'] is List) {
        setState(() {
          _wishlist.clear();
          for (final item in wishRes['wishlist']) {
            if (item is Map && item['id'] != null) {
              _wishlist.add(item['id'].toString());
            }
          }
        });
      }
    } catch (_) {}
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

        await _fetchCustomerWishlist();

        final rawMethods = response['enabled_methods'] as List?;
        if (rawMethods != null && rawMethods.isNotEmpty) {
          _paymentMethods = rawMethods.map((m) => Map<String, dynamic>.from(m as Map)).toList();
          if (!_paymentMethods.any((m) => m['id'] == _paymentMethod)) {
            _paymentMethod = _paymentMethods.first['id']?.toString() ?? 'cod';
          }
        }

        final rawFaqs = response['faqs'] as List?;
        if (rawFaqs != null && rawFaqs.isNotEmpty) {
          _faqs = rawFaqs.map((f) => Map<String, dynamic>.from(f as Map)).toList();
        }
      }

      // Fetch payment methods and FAQs if catalog payload did not contain them
      if (_paymentMethods.isEmpty) {
        await _fetchPaymentMethods();
      }
      if (_faqs.isEmpty) {
        await _fetchFaqs();
      }
    } catch (e) {
      if (kDebugMode) print('Error loading storefront catalog: $e');
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

  Future<void> _fetchPaymentMethods() async {
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/payment-methods?store=$_effectiveStoreSlug';
      final res = await client.get(uri);
      if (res['enabled_methods'] is List) {
        final list = res['enabled_methods'] as List;
        if (list.isNotEmpty && mounted) {
          setState(() {
            _paymentMethods = list.map((m) => Map<String, dynamic>.from(m as Map)).toList();
            if (!_paymentMethods.any((m) => m['id'] == _paymentMethod)) {
              _paymentMethod = _paymentMethods.first['id']?.toString() ?? 'cod';
            }
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchFaqs() async {
    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/faqs?store=$_effectiveStoreSlug';
      final res = await client.get(uri);
      if (res['faqs'] is List) {
        final list = res['faqs'] as List;
        if (list.isNotEmpty && mounted) {
          setState(() {
            _faqs = list.map((f) => Map<String, dynamic>.from(f as Map)).toList();
          });
        }
      }
    } catch (_) {}
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
  double get _cartFinalPrice => (_cartTotalPrice - _discountAmount).clamp(0.0, double.infinity);

  Future<void> _applyCoupon() async {
    final code = _couponController.text.trim().toUpperCase();
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a coupon code.')),
      );
      return;
    }

    if (_cartTotalPrice <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add items to your cart before applying a coupon.')),
      );
      return;
    }

    try {
      final client = context.read<ApiClient>();
      final response = await client.post(
        '/api/v1/storefront/coupons/validate?store=$_effectiveStoreSlug',
        data: {
          'code': code,
          'subtotal': _cartTotalPrice,
          'store_slug': _effectiveStoreSlug,
          'auth_token': _customerToken,
        },
      );

      final data = response;
      if (data['valid'] == true || data['success'] == true) {
        final amt = (data['discount_amount'] as num?)?.toDouble() ?? 0.0;
        setState(() {
          _discountAmount = double.parse(amt.toStringAsFixed(2));
          _appliedCouponCode = code;
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('🎉 Coupon $code applied: -\$${_discountAmount.toStringAsFixed(2)}'),
              backgroundColor: const Color(0xFF059669),
            ),
          );
        }
      } else {
        final msg = data['message']?.toString() ?? 'Invalid or inapplicable coupon code.';
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(msg), backgroundColor: Colors.red.shade700),
          );
        }
      }
    } catch (e) {
      // Fallback to local calculation if common demo promo code while offline
      double discount = 0.0;
      if (code == 'WELCOME10') {
        discount = _cartTotalPrice * 0.10;
      } else if (code == 'SAVE20') {
        discount = _cartTotalPrice * 0.20;
      } else if (code == 'ZOOM50') {
        discount = _cartTotalPrice * 0.50;
      }
      if (discount > 0) {
        setState(() {
          _discountAmount = double.parse(discount.toStringAsFixed(2));
          _appliedCouponCode = code;
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Coupon $code applied: -\$${_discountAmount.toStringAsFixed(2)}'),
              backgroundColor: const Color(0xFF059669),
            ),
          );
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Coupon validation failed: ${e.toString().replaceAll("Exception:", "").trim()}'),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    }
  }

  void _removeCoupon() {
    setState(() {
      _discountAmount = 0.0;
      _appliedCouponCode = null;
      _couponController.clear();
    });
  }

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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final themeProvider = context.watch<ThemeProvider>();

    final bgColor = isDark ? AppTheme.darkBg : const Color(0xFFF8FAFC);
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final borderColor = isDark ? AppTheme.darkBorder : Colors.grey.shade200;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    return Scaffold(
      backgroundColor: bgColor,
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : CustomScrollView(
              slivers: [
                // 1. Top Announcement Bar (matching store-idea.mp4)
                SliverToBoxAdapter(
                  child: Container(
                    color: isDark ? const Color(0xFF064E3B) : const Color(0xFF064E3B),
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
                            // Dark/Light Mode Switcher Pill in Top Bar
                            InkWell(
                              onTap: () {
                                final next = isDark ? ThemeMode.light : ThemeMode.dark;
                                themeProvider.setThemeMode(next);
                              },
                              borderRadius: BorderRadius.circular(8),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.15),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      isDark ? Icons.light_mode : Icons.dark_mode,
                                      size: 13,
                                      color: Colors.amberAccent,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      isDark ? 'Light' : 'Dark',
                                      style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
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
                      color: cardColor,
                      border: Border(bottom: BorderSide(color: borderColor)),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.02), blurRadius: 4, offset: const Offset(0, 2)),
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
                                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: textColor),
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
                            Text('Online Storefront', style: TextStyle(fontSize: 11, color: mutedColor)),
                          ],
                        ),
                        const Spacer(),

                        // Customer Account Button
                        InkWell(
                          onTap: _showAccountPortal,
                          borderRadius: BorderRadius.circular(16),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            decoration: BoxDecoration(
                              color: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  _customer != null ? Icons.person : Icons.person_outline,
                                  size: 18,
                                  color: const Color(0xFF059669),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  _customer != null ? (_customer!['name']?.toString().split(' ').first ?? 'Account') : 'Sign In',
                                  style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                    color: textColor,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),

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
                                    '\$${_cartFinalPrice.toStringAsFixed(2)}',
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
                          style: TextStyle(color: textColor),
                          decoration: InputDecoration(
                            hintText: 'Search products in store…',
                            hintStyle: TextStyle(color: mutedColor),
                            prefixIcon: Icon(Icons.search, size: 20, color: mutedColor),
                            filled: true,
                            fillColor: cardColor,
                            contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(16),
                              borderSide: BorderSide(color: borderColor),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(16),
                              borderSide: BorderSide(color: borderColor),
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
                                backgroundColor: cardColor,
                                labelStyle: TextStyle(
                                  color: _selectedCategory == 'all' ? Colors.white : textColor,
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
                                    backgroundColor: cardColor,
                                    labelStyle: TextStyle(
                                      color: isSelected ? Colors.white : textColor,
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
                      ? SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.all(40),
                            child: Center(child: Text('No products match your search or filter.', style: TextStyle(color: mutedColor))),
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

                              return _buildProductCard(product, isFav, inCartQty, isDark, cardColor, borderColor, textColor, mutedColor);
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
                        Text(
                          'Services To Help You Shop',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: textColor),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(child: _buildServiceCard('❓', 'Frequently Asked Questions', 'Direct answers from store staff', cardColor, borderColor, textColor, mutedColor, onTap: () => _showFaqsSheet(initialCategory: 'all'))),
                            const SizedBox(width: 10),
                            Expanded(child: _buildServiceCard('💳', 'Online Payment Process', 'COD, UPI, Cards & NetBanking', cardColor, borderColor, textColor, mutedColor, onTap: () => _showFaqsSheet(initialCategory: 'Payment'))),
                            const SizedBox(width: 10),
                            Expanded(child: _buildServiceCard('🚚', 'Home Delivery Options', 'Same-day store counter dispatch', cardColor, borderColor, textColor, mutedColor, onTap: () => _showFaqsSheet(initialCategory: 'Delivery'))),
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

  Future<void> _toggleWishlist(ProductModel product) async {
    final isFav = _wishlist.contains(product.id);
    setState(() {
      if (isFav) {
        _wishlist.remove(product.id);
      } else {
        _wishlist.add(product.id);
      }
    });

    try {
      final client = context.read<ApiClient>();
      final uri = '/api/storefront/customer/wishlist/toggle?store=$_effectiveStoreSlug';
      await client.post(uri, data: {'product_id': int.tryParse(product.id) ?? product.id});
    } catch (_) {
      // Optimistic local state preserved
    }
  }

  Widget _buildProductCard(
    ProductModel product,
    bool isFav,
    int inCartQty,
    bool isDark,
    Color cardColor,
    Color borderColor,
    Color textColor,
    Color mutedColor,
  ) {
    return Container(
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: borderColor),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03), blurRadius: 8, offset: const Offset(0, 2)),
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
                    color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  child: Center(
                    child: product.imageUrl != null && product.imageUrl!.isNotEmpty
                        ? AppNetworkImage(imageUrl: product.imageUrl!, fit: BoxFit.cover)
                        : Icon(Icons.shopping_bag_outlined, size: 48, color: mutedColor),
                  ),
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: InkWell(
                  onTap: () => _toggleWishlist(product),
                  child: CircleAvatar(
                    radius: 14,
                    backgroundColor: (isDark ? AppTheme.darkCard : Colors.white).withValues(alpha: 0.9),
                    child: Icon(
                      isFav ? Icons.favorite : Icons.favorite_border,
                      size: 16,
                      color: isFav ? Colors.red : mutedColor,
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
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: textColor),
                  ),
                  const SizedBox(height: 4),

                  // Name
                  GestureDetector(
                    onTap: () => _showProductDetailModal(product),
                    child: Text(
                      product.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: textColor),
                    ),
                  ),
                  const SizedBox(height: 2),

                  // Rich Description
                  if ((product.description ?? '').trim().isNotEmpty) ...[
                    Text(
                      product.description!.trim(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 11, color: mutedColor, height: 1.2),
                    ),
                    const SizedBox(height: 2),
                  ],
                  const Spacer(),

                  // Dynamic Star Rating with rating count
                  Row(
                    children: [
                      const Icon(Icons.star, size: 12, color: Color(0xFFF59E0B)),
                      const SizedBox(width: 3),
                      Text(
                        product.averageRating.toStringAsFixed(1),
                        style: const TextStyle(color: Color(0xFF059669), fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(width: 3),
                      Text(
                        '(${product.reviewsCount > 0 ? product.reviewsCount : (80 + (int.tryParse(product.id) ?? 0) % 50)})',
                        style: TextStyle(fontSize: 10, color: mutedColor),
                      ),
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
                          backgroundColor: isDark ? const Color(0xFF064E3B).withValues(alpha: 0.3) : const Color(0xFFECFDF5),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          padding: const EdgeInsets.symmetric(vertical: 8),
                        ),
                        icon: const Icon(Icons.add, size: 16, color: Color(0xFF10B981)),
                        label: const Text('Add to Cart', style: TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold, fontSize: 12)),
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

  Widget _buildServiceCard(String emoji, String title, String subtitle, Color cardColor, Color borderColor, Color textColor, Color mutedColor, {VoidCallback? onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: borderColor),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(emoji, style: const TextStyle(fontSize: 22)),
            const SizedBox(height: 6),
            Text(title, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: textColor)),
            const SizedBox(height: 2),
            Text(subtitle, style: TextStyle(fontSize: 10, color: mutedColor)),
            if (onTap != null) ...[
              const SizedBox(height: 6),
              const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'View FAQs',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF059669),
                    ),
                  ),
                  SizedBox(width: 2),
                  Icon(Icons.arrow_forward, size: 10, color: Color(0xFF059669)),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  // 7. Product Detail Modal Sheet (matching store-idea.mp4)
  void _showProductDetailModal(ProductModel product) {
    int modalQty = 1;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    List<dynamic> reviews = [];
    bool isLoadingReviews = true;
    double avgRating = product.averageRating;
    int totalReviews = product.reviewsCount;
    bool reviewsEnabled = _company['enable_product_reviews'] != false;
    bool reviewsFetched = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: cardColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            if (!reviewsFetched) {
              reviewsFetched = true;
              Future.microtask(() async {
                try {
                  final client = context.read<ApiClient>();
                  final res = await client.get('/api/v1/storefront/products/${product.id}/reviews?store=$_effectiveStoreSlug');
                  if (res['reviews'] is List && ctx.mounted) {
                    setModalState(() {
                      reviews = res['reviews'] as List;
                      totalReviews = (res['total_reviews'] as num?)?.toInt() ?? reviews.length;
                      avgRating = (res['average_rating'] as num?)?.toDouble() ?? product.averageRating;
                      if (res['reviews_enabled'] != null) {
                        reviewsEnabled = res['reviews_enabled'] == true;
                      }
                      isLoadingReviews = false;
                    });
                  }
                } catch (_) {
                  if (ctx.mounted) {
                    setModalState(() => isLoadingReviews = false);
                  }
                }
              });
            }

            void refreshReviews() async {
              setModalState(() => isLoadingReviews = true);
              try {
                final client = context.read<ApiClient>();
                final res = await client.get('/api/v1/storefront/products/${product.id}/reviews?store=$_effectiveStoreSlug');
                if (res['reviews'] is List && ctx.mounted) {
                  setModalState(() {
                    reviews = res['reviews'] as List;
                    totalReviews = (res['total_reviews'] as num?)?.toInt() ?? reviews.length;
                    avgRating = (res['average_rating'] as num?)?.toDouble() ?? avgRating;
                    isLoadingReviews = false;
                  });
                }
              } catch (_) {
                if (ctx.mounted) setModalState(() => isLoadingReviews = false);
              }
            }

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
                        decoration: BoxDecoration(color: isDark ? Colors.white24 : Colors.grey.shade300, borderRadius: BorderRadius.circular(4)),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Big Image
                    Container(
                      height: 220,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Center(
                        child: product.imageUrl != null && product.imageUrl!.isNotEmpty
                            ? AppNetworkImage(imageUrl: product.imageUrl!, fit: BoxFit.contain)
                            : Icon(Icons.shopping_bag_outlined, size: 64, color: mutedColor),
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
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.star, size: 14, color: Color(0xFFF59E0B)),
                            const SizedBox(width: 3),
                            Text(
                              '${avgRating.toStringAsFixed(1)} ($totalReviews)',
                              style: const TextStyle(color: Color(0xFF10B981), fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(product.name, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: textColor)),
                    const SizedBox(height: 8),

                    // Price & Discount
                    Row(
                      children: [
                        Text('\$${product.salePrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF059669))),
                        const SizedBox(width: 8),
                        Text('\$${(product.salePrice * 1.25).toStringAsFixed(2)}', style: TextStyle(fontSize: 13, decoration: TextDecoration.lineThrough, color: mutedColor)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(6)),
                          child: const Text('20% OFF', style: TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Full Rich Description
                    if ((product.description ?? '').trim().isNotEmpty) ...[
                      Text('Description', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: textColor)),
                      const SizedBox(height: 4),
                      Text(product.description!.trim(), style: TextStyle(fontSize: 12, color: textColor, height: 1.4)),
                      const SizedBox(height: 16),
                    ],

                    // Specs Table
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: isDark ? AppTheme.darkBorder : Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
                      child: Column(
                        children: [
                          _specRow('SKU / Code', product.sku.isNotEmpty ? product.sku : product.barcode, textColor, mutedColor),
                          _specRow('Unit', product.unit, textColor, mutedColor),
                          _specRow('Availability', 'In Stock (${product.currentStock.toInt()} available)', textColor, mutedColor),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Stepper & Buy Now
                    Row(
                      children: [
                        Container(
                          decoration: BoxDecoration(border: Border.all(color: isDark ? AppTheme.darkBorder : Colors.grey.shade300), borderRadius: BorderRadius.circular(12)),
                          child: Row(
                            children: [
                              IconButton(
                                onPressed: () {
                                  if (modalQty > 1) setModalState(() => modalQty--);
                                },
                                icon: Icon(Icons.remove, size: 16, color: textColor),
                              ),
                              Text('$modalQty', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: textColor)),
                              IconButton(
                                onPressed: () => setModalState(() => modalQty++),
                                icon: Icon(Icons.add, size: 16, color: textColor),
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

                    // Customer Ratings & Reviews Section
                    if (reviewsEnabled) ...[
                      const SizedBox(height: 24),
                      const Divider(),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Ratings & Reviews',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: textColor),
                              ),
                              const SizedBox(height: 2),
                              Row(
                                children: [
                                  const Icon(Icons.star, size: 14, color: Color(0xFFF59E0B)),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${avgRating.toStringAsFixed(1)} out of 5',
                                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: textColor),
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    '($totalReviews ${totalReviews == 1 ? 'review' : 'reviews'})',
                                    style: TextStyle(fontSize: 11, color: mutedColor),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          OutlinedButton.icon(
                            onPressed: () => _showWriteReviewDialog(product, refreshReviews),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFF059669)),
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.rate_review_outlined, size: 14, color: Color(0xFF059669)),
                            label: const Text('Write Review', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      if (isLoadingReviews)
                        const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(strokeWidth: 2)))
                      else if (reviews.isEmpty)
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Text(
                              'No reviews yet for this product. Be the first to review!',
                              style: TextStyle(fontSize: 12, color: mutedColor),
                            ),
                          ),
                        )
                      else
                        ...reviews.take(5).map((r) {
                          final rMap = r as Map<String, dynamic>;
                          final rRating = (rMap['rating'] as num?)?.toInt() ?? 5;
                          final rName = rMap['customer_name']?.toString() ?? 'Customer';
                          final isVerified = rMap['is_verified_purchase'] == true;
                          final rComment = rMap['comment']?.toString() ?? '';
                          final rTitle = rMap['title']?.toString();
                          final rDate = rMap['created_at']?.toString() ?? '';

                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: isDark ? Colors.white10 : Colors.grey.shade200),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Row(
                                      children: [
                                        Text(rName, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: textColor)),
                                        if (isVerified) ...[
                                          const SizedBox(width: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFD1FAE5),
                                              borderRadius: BorderRadius.circular(4),
                                            ),
                                            child: const Text('Verified', style: TextStyle(fontSize: 9, color: Color(0xFF065F46), fontWeight: FontWeight.bold)),
                                          ),
                                        ],
                                      ],
                                    ),
                                    Text(rDate, style: TextStyle(fontSize: 10, color: mutedColor)),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: List.generate(5, (starIdx) {
                                    return Icon(
                                      starIdx < rRating ? Icons.star : Icons.star_border,
                                      size: 13,
                                      color: const Color(0xFFF59E0B),
                                    );
                                  }),
                                ),
                                if (rTitle != null && rTitle.isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text(rTitle, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: textColor)),
                                ],
                                if (rComment.isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text(rComment, style: TextStyle(fontSize: 11, color: textColor, height: 1.3)),
                                ],
                              ],
                            ),
                          );
                        }),
                    ],
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  void _showWriteReviewDialog(ProductModel product, VoidCallback onReviewSubmitted) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    int selectedRating = 5;
    final nameCtrl = TextEditingController(
      text: _customer?['name']?.toString() ?? _nameController.text.trim(),
    );
    final titleCtrl = TextEditingController();
    final commentCtrl = TextEditingController();
    bool isSubmitting = false;
    String? submitError;

    showDialog(
      context: context,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: cardColor,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: Text('Rate & Review', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: textColor)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              content: SizedBox(
                width: 360,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(product.name, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: textColor)),
                      const SizedBox(height: 12),

                      // Star Rating Selector
                      Center(
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List.generate(5, (index) {
                            final starValue = index + 1;
                            return IconButton(
                              onPressed: () => setDialogState(() => selectedRating = starValue),
                              icon: Icon(
                                starValue <= selectedRating ? Icons.star : Icons.star_border,
                                size: 30,
                                color: const Color(0xFFF59E0B),
                              ),
                            );
                          }),
                        ),
                      ),
                      Center(
                        child: Text(
                          selectedRating == 5
                              ? 'Excellent'
                              : selectedRating == 4
                                  ? 'Very Good'
                                  : selectedRating == 3
                                      ? 'Average'
                                      : selectedRating == 2
                                          ? 'Poor'
                                          : 'Terrible',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF059669)),
                        ),
                      ),
                      const SizedBox(height: 14),

                      TextField(
                        controller: nameCtrl,
                        decoration: InputDecoration(
                          labelText: 'Your Name',
                          labelStyle: TextStyle(fontSize: 12, color: mutedColor),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        ),
                      ),
                      const SizedBox(height: 10),

                      TextField(
                        controller: titleCtrl,
                        decoration: InputDecoration(
                          labelText: 'Review Title (Optional)',
                          labelStyle: TextStyle(fontSize: 12, color: mutedColor),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        ),
                      ),
                      const SizedBox(height: 10),

                      TextField(
                        controller: commentCtrl,
                        maxLines: 3,
                        decoration: InputDecoration(
                          labelText: 'Your Review *',
                          labelStyle: TextStyle(fontSize: 12, color: mutedColor),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          contentPadding: const EdgeInsets.all(12),
                        ),
                      ),

                      if (submitError != null) ...[
                        const SizedBox(height: 8),
                        Text(submitError!, style: TextStyle(color: Colors.red.shade700, fontSize: 11)),
                      ],
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogCtx),
                  child: Text('Cancel', style: TextStyle(color: mutedColor)),
                ),
                ElevatedButton(
                  onPressed: isSubmitting
                      ? null
                      : () async {
                          final comment = commentCtrl.text.trim();
                          if (comment.length < 3) {
                            setDialogState(() => submitError = 'Please write a brief review comment.');
                            return;
                          }

                          setDialogState(() {
                            isSubmitting = true;
                            submitError = null;
                          });

                          try {
                            final client = context.read<ApiClient>();
                            await client.post(
                              '/api/v1/storefront/products/${product.id}/reviews?store=$_effectiveStoreSlug',
                              data: {
                                'rating': selectedRating,
                                'comment': comment,
                                'title': titleCtrl.text.trim(),
                                'customer_name': nameCtrl.text.trim(),
                                'customer_email': _emailController.text.trim(),
                                'auth_token': _customerToken,
                              },
                            );

                            if (mounted) {
                              Navigator.pop(dialogCtx);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('🎉 Thank you! Your review has been submitted.'),
                                  backgroundColor: Color(0xFF059669),
                                ),
                              );
                              onReviewSubmitted();
                            }
                          } catch (e) {
                            if (mounted) {
                              setDialogState(() {
                                isSubmitting = false;
                                submitError = e.toString().replaceAll('Exception:', '').trim();
                              });
                            }
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF059669),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: isSubmitting
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Submit Review', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  Widget _specRow(String label, String value, Color textColor, Color mutedColor) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 11, color: mutedColor)),
          Text(value, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
        ],
      ),
    );
  }

  // 8. Slide-over Shopping Cart & Checkout (matching store-idea.mp4)
  void _showCartDrawer() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: cardColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setCartState) {
            return DraggableScrollableSheet(
              initialChildSize: 0.88,
              maxChildSize: 0.96,
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
                          Text('Your Order Cart (${_cartTotalCount})', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: textColor)),
                          IconButton(icon: Icon(Icons.close, color: textColor), onPressed: () => Navigator.pop(ctx)),
                        ],
                      ),
                      Divider(color: isDark ? AppTheme.darkBorder : Colors.grey.shade200),

                      if (_cart.isEmpty)
                        Expanded(
                          child: Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text('🛒', style: TextStyle(fontSize: 48)),
                                const SizedBox(height: 8),
                                Text('Your cart is currently empty.', style: TextStyle(fontWeight: FontWeight.bold, color: textColor)),
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
                                decoration: BoxDecoration(color: isDark ? AppTheme.darkBorder : Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
                                child: Row(
                                  children: [
                                    Container(
                                      width: 44,
                                      height: 44,
                                      decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(8)),
                                      child: Center(child: Icon(Icons.inventory_2_outlined, color: mutedColor, size: 24)),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(item.product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: textColor)),
                                          Text('\$${item.product.salePrice.toStringAsFixed(2)} x ${item.quantity}', style: const TextStyle(fontSize: 11, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                                        ],
                                      ),
                                    ),
                                    // Cart Stepper
                                    Container(
                                      decoration: BoxDecoration(
                                        color: cardColor,
                                        border: Border.all(color: isDark ? AppTheme.darkBorder : Colors.grey.shade300),
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
                                            child: Padding(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              child: Text('−', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: textColor)),
                                            ),
                                          ),
                                          Padding(
                                            padding: const EdgeInsets.symmetric(horizontal: 6),
                                            child: Text('${item.quantity}', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: textColor)),
                                          ),
                                          InkWell(
                                            onTap: () {
                                              _addToCart(item.product);
                                              setCartState(() {});
                                              setState(() {});
                                            },
                                            child: const Padding(
                                              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              child: Text('+', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    IconButton(
                                      icon: Icon(Icons.delete_outline, size: 18, color: mutedColor),
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
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Customer & Delivery Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: textColor)),
                            if (_savedAddresses.isNotEmpty)
                              TextButton(
                                onPressed: () {
                                  _showSavedAddressesDialog((addr) {
                                    setCartState(() {
                                      _addressController.text = addr['street_address']?.toString() ?? '';
                                      _cityController.text = addr['city']?.toString() ?? '';
                                      if (addr['phone'] != null) _phoneController.text = addr['phone'].toString();
                                      if (addr['name'] != null) _nameController.text = addr['name'].toString();
                                    });
                                  });
                                },
                                child: const Text('Saved Addresses', style: TextStyle(fontSize: 11, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                              ),
                          ],
                        ),
                        const SizedBox(height: 4),

                        // Row 1: Full Name * | Phone / WhatsApp *
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _nameController,
                                style: TextStyle(color: textColor, fontSize: 12),
                                decoration: InputDecoration(labelText: 'Full Name *', isDense: true, labelStyle: TextStyle(color: mutedColor, fontSize: 12)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _phoneController,
                                style: TextStyle(color: textColor, fontSize: 12),
                                decoration: InputDecoration(labelText: 'Phone / WhatsApp *', isDense: true, labelStyle: TextStyle(color: mutedColor, fontSize: 12)),
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
                                style: TextStyle(color: textColor, fontSize: 12),
                                decoration: InputDecoration(labelText: 'Email Address (Optional)', isDense: true, labelStyle: TextStyle(color: mutedColor, fontSize: 12)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextField(
                                controller: _cityController,
                                style: TextStyle(color: textColor, fontSize: 12),
                                decoration: InputDecoration(labelText: 'City *', isDense: true, labelStyle: TextStyle(color: mutedColor, fontSize: 12)),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        // Row 3: Street Address *
                        TextField(
                          controller: _addressController,
                          style: TextStyle(color: textColor, fontSize: 12),
                          decoration: InputDecoration(labelText: 'Street Address *', isDense: true, labelStyle: TextStyle(color: mutedColor, fontSize: 12)),
                        ),
                        const SizedBox(height: 10),

                        // Payment Methods Selector Cards (Responsive & Dynamic)
                        Text('Payment Method', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: textColor)),
                        const SizedBox(height: 6),
                        Column(
                          children: _getDisplayPaymentMethods().map((m) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: _buildPaymentOptionCard(m, setCartState),
                            );
                          }).toList(),
                        ),

                        // Coupon input
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _couponController,
                                style: TextStyle(color: textColor, fontSize: 12),
                                decoration: InputDecoration(
                                  hintText: 'Coupon Code (e.g. WELCOME10)',
                                  hintStyle: TextStyle(color: mutedColor, fontSize: 11),
                                  isDense: true,
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton(
                              onPressed: () async {
                                await _applyCoupon();
                                setCartState(() {});
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: isDark ? AppTheme.darkBorder : Colors.grey.shade800,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              child: const Text('Apply', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ),
                        if (_appliedCouponCode != null) ...[
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('🎉 Code $_appliedCouponCode: -\$${_discountAmount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 11, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                              InkWell(
                                onTap: () {
                                  _removeCoupon();
                                  setCartState(() {});
                                },
                                child: const Text('Remove', style: TextStyle(fontSize: 11, color: Colors.red, fontWeight: FontWeight.bold)),
                              ),
                            ],
                          ),
                        ],

                        // Totals Breakdown
                        const SizedBox(height: 10),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Subtotal:', style: TextStyle(fontSize: 12, color: mutedColor)),
                            Text('\$${_cartTotalPrice.toStringAsFixed(2)}', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: textColor)),
                          ],
                        ),
                        if (_discountAmount > 0) ...[
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Discount:', style: TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                              Text('-\$${_discountAmount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                            ],
                          ),
                        ],
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
                            Text('Total Order Amount:', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: textColor)),
                            Text('\$${_cartFinalPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: Color(0xFF059669))),
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

  List<Map<String, dynamic>> _getDisplayPaymentMethods() {
    if (_paymentMethods.isNotEmpty) {
      return _paymentMethods;
    }
    return [
      {
        'id': 'cod',
        'title': 'Cash on Delivery',
        'description': 'Pay in cash when order arrives at your door',
        'icon': '💵'
      },
      {
        'id': 'counter',
        'title': 'Pay at Counter / Store Pickup',
        'description': 'Pick up items and settle bill directly at the store counter',
        'icon': '🏪'
      },
      {
        'id': 'razorpay',
        'title': 'Razorpay (UPI, Cards & NetBanking)',
        'description': 'Instant UPI, debit/credit cards & NetBanking payment',
        'icon': '⚡'
      },
      {
        'id': 'stripe',
        'title': 'Credit / Debit Card (Stripe)',
        'description': 'Secure and encrypted online card processing powered by Stripe',
        'icon': '💳'
      },
    ];
  }

  Widget _buildPaymentOptionCard(Map<String, dynamic> method, StateSetter setCartState) {
    final id = method['id']?.toString() ?? 'cod';
    final isSelected = _paymentMethod == id;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final title = method['title']?.toString() ?? id.toUpperCase();
    final desc = method['description']?.toString() ?? '';
    final icon = method['icon']?.toString() ??
        (id == 'razorpay'
            ? '⚡'
            : id == 'stripe'
                ? '💳'
                : (id == 'counter' || id == 'store_pickup')
                    ? '🏪'
                    : id == 'paypal'
                        ? '🅿️'
                        : id == 'upi'
                            ? '📱'
                            : '💵');

    return InkWell(
      onTap: () {
        setCartState(() {
          _paymentMethod = id;
        });
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected
              ? (isDark ? const Color(0xFF064E3B) : const Color(0xFFECFDF5))
              : (isDark ? AppTheme.darkBorder : Colors.grey.shade100),
          border: Border.all(
            color: isSelected ? const Color(0xFF10B981) : (isDark ? Colors.white12 : Colors.grey.shade200),
            width: isSelected ? 1.5 : 1.0,
          ),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: isSelected
                    ? const Color(0x3310B981)
                    : (isDark ? Colors.white10 : Colors.white),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(icon, style: const TextStyle(fontSize: 18)),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: isSelected
                          ? const Color(0xFF059669)
                          : (isDark ? Colors.white : Colors.black87),
                    ),
                  ),
                  if (desc.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      desc,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 10,
                        color: isDark ? Colors.white54 : Colors.grey.shade600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 6),
            Icon(
              isSelected ? Icons.check_circle : Icons.radio_button_unchecked,
              size: 18,
              color: isSelected ? const Color(0xFF10B981) : Colors.grey.shade400,
            ),
          ],
        ),
      ),
    );
  }


  void _showSavedAddressesDialog(Function(Map<String, dynamic>) onSelect) {
    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: const Text('Saved Addresses', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          content: SizedBox(
            width: double.maxFinite,
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: _savedAddresses.length,
              separatorBuilder: (_, __) => const Divider(),
              itemBuilder: (context, i) {
                final addr = _savedAddresses[i] as Map<String, dynamic>;
                return ListTile(
                  title: Text(addr['street_address']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  subtitle: Text(addr['city']?.toString() ?? '', style: const TextStyle(fontSize: 11)),
                  trailing: ElevatedButton(
                    onPressed: () {
                      onSelect(addr);
                      Navigator.pop(ctx);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF059669),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    ),
                    child: const Text('Use', style: TextStyle(fontSize: 11)),
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }

  // 9. Order Submission, OTP Verification & Confirmation Dialog (matching store-idea.mp4)
  Future<void> _submitOrder(
    BuildContext modalContext, {
    String? verificationCode,
    BuildContext? otpDialogContext,
  }) async {
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
        'discount': _discountAmount,
        'coupon_code': _appliedCouponCode,
        'auth_token': _customerToken,
        if (verificationCode != null && verificationCode.trim().isNotEmpty) ...{
          'verification_code': verificationCode.trim(),
          'otp': verificationCode.trim(),
        },
        'items': _cart.map((i) => {
          'id': i.product.id,
          'name': i.product.name,
          'quantity': i.quantity,
          'price': i.product.salePrice,
        }).toList(),
      };

      final response = await client.post('/api/storefront/order?store=$_effectiveStoreSlug', data: payload);

      // Check if server demands OTP verification before finalizing order
      if (response['verification_required'] == true) {
        if (mounted) {
          _showVerificationOtpDialog(modalContext, response);
        }
        return;
      }

      final saleNumber = response['sale_number']?.toString() ?? 'WEB-${DateTime.now().millisecondsSinceEpoch}';
      final trackingCode = response['tracking_code']?.toString();
      final trackingUrl = response['tracking_url']?.toString();

      if (mounted) {
        if (otpDialogContext != null && Navigator.canPop(otpDialogContext)) {
          Navigator.pop(otpDialogContext);
        }
        if (Navigator.canPop(modalContext)) {
          Navigator.pop(modalContext);
        }
        setState(() {
          _cart.clear();
          _discountAmount = 0.0;
          _appliedCouponCode = null;
        });
        _showOrderAcceptedDialog(
          saleNumber,
          trackingCode: trackingCode,
          trackingUrl: trackingUrl,
        );
        _fetchCustomerOrders();
      }
    } on ApiException catch (e) {
      if (e.responseData?['verification_required'] == true && mounted) {
        _showVerificationOtpDialog(modalContext, e.responseData!);
        return;
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.message),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Order submission notice: $e'),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmittingOrder = false);
    }
  }

  void _showVerificationOtpDialog(
    BuildContext modalContext,
    Map<String, dynamic> verificationData,
  ) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    final otpController = TextEditingController();
    bool isVerifying = false;
    bool isResending = false;
    String? errorMessage = verificationData['verification_failed'] == true
        ? verificationData['message']?.toString()
        : null;

    final rawChannels = verificationData['channels'];
    List<String> channels = [];
    if (rawChannels is List) {
      channels = rawChannels.map((c) => c.toString().toUpperCase()).toList();
    }
    final channelsText = channels.isNotEmpty
        ? channels.join(' & ')
        : 'Email / SMS / WhatsApp';

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: cardColor,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              contentPadding: const EdgeInsets.all(24),
              content: SizedBox(
                width: 380,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: const BoxDecoration(
                        color: Color(0xFFD1FAE5),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.verified_user_outlined,
                        size: 36,
                        color: Color(0xFF059669),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Account Verification Required',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: textColor,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'To protect your store account and verify your order, a 6-digit verification code has been dispatched to your active $channelsText gateway.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 12, color: mutedColor, height: 1.4),
                    ),
                    const SizedBox(height: 20),

                    // OTP Input Field
                    TextField(
                      controller: otpController,
                      keyboardType: TextInputType.number,
                      maxLength: 6,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 26,
                        letterSpacing: 10,
                        fontWeight: FontWeight.bold,
                        color: textColor,
                      ),
                      decoration: InputDecoration(
                        counterText: '',
                        hintText: '• • • • • •',
                        hintStyle: TextStyle(
                          fontSize: 22,
                          letterSpacing: 8,
                          color: mutedColor.withValues(alpha: 0.5),
                        ),
                        filled: true,
                        fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                        contentPadding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: const BorderSide(color: Color(0xFF059669), width: 1.5),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: const BorderSide(color: Color(0xFF059669), width: 2),
                        ),
                      ),
                    ),

                    if (errorMessage != null && errorMessage!.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.red.shade50,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.red.shade200),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.error_outline, size: 16, color: Colors.red.shade700),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                errorMessage!,
                                style: TextStyle(fontSize: 11, color: Colors.red.shade800, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 20),

                    // Verify & Complete Button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: isVerifying
                            ? null
                            : () async {
                                final code = otpController.text.trim();
                                if (code.length < 4) {
                                  setDialogState(() {
                                    errorMessage = 'Please enter the verification code.';
                                  });
                                  return;
                                }

                                setDialogState(() {
                                  isVerifying = true;
                                  errorMessage = null;
                                });

                                await _submitOrder(
                                  modalContext,
                                  verificationCode: code,
                                  otpDialogContext: dialogCtx,
                                );

                                if (mounted) {
                                  setDialogState(() {
                                    isVerifying = false;
                                  });
                                }
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF059669),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: isVerifying
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : const Text('Verify & Complete Order', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Resend Code & Cancel
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.pop(dialogCtx),
                          child: Text('Cancel', style: TextStyle(fontSize: 12, color: mutedColor)),
                        ),
                        TextButton.icon(
                          onPressed: isResending
                              ? null
                              : () async {
                                  setDialogState(() => isResending = true);
                                  try {
                                    final client = context.read<ApiClient>();
                                    final phoneVal = _phoneController.text.trim();
                                    final emailVal = _emailController.text.trim();
                                    final res = await client.post(
                                      '/api/v1/storefront/customer/send-verification?store=$_effectiveStoreSlug',
                                      data: {
                                        'phone': phoneVal,
                                        'email': emailVal,
                                        'customer_phone': phoneVal,
                                        'customer_email': emailVal,
                                        'auth_token': _customerToken,
                                      },
                                    );
                                    if (mounted) {
                                      setDialogState(() {
                                        errorMessage = null;
                                        isResending = false;
                                      });
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text(res['message']?.toString() ?? 'A new verification code has been dispatched!'),
                                          backgroundColor: const Color(0xFF059669),
                                        ),
                                      );
                                    }
                                  } catch (e) {
                                    if (mounted) {
                                      setDialogState(() {
                                        isResending = false;
                                        errorMessage = 'Failed to resend code. Please try again.';
                                      });
                                    }
                                  }
                                },
                          icon: isResending
                              ? const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.refresh, size: 14, color: Color(0xFF059669)),
                          label: const Text(
                            'Resend Code',
                            style: TextStyle(fontSize: 12, color: Color(0xFF059669), fontWeight: FontWeight.bold),
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

  void _showOrderAcceptedDialog(
    String saleNumber, {
    String? trackingCode,
    String? trackingUrl,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkHeading : const Color(0xFF0F172A);
    final mutedColor = isDark ? AppTheme.darkMuted : Colors.grey;

    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: cardColor,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          contentPadding: const EdgeInsets.all(24),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 70,
                height: 70,
                decoration: const BoxDecoration(color: Color(0xFFD1FAE5), shape: BoxShape.circle),
                child: const Icon(Icons.check, size: 40, color: Color(0xFF059669)),
              ),
              const SizedBox(height: 16),
              Text(
                'Your order has been accepted!',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: textColor),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF059669).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Order #$saleNumber',
                  style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF059669), fontSize: 13),
                ),
              ),

              // Live Tracking Card
              if (trackingCode != null && trackingCode.isNotEmpty) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: isDark ? AppTheme.darkBorder : const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFF86EFAC)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.local_shipping_outlined, color: Color(0xFF059669), size: 24),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Live Tracking Code',
                              style: TextStyle(fontSize: 10, color: mutedColor, fontWeight: FontWeight.bold),
                            ),
                            Text(
                              trackingCode,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF059669),
                                letterSpacing: 1,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: trackingCode));
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Tracking code copied to clipboard!'),
                              duration: Duration(seconds: 2),
                            ),
                          );
                        },
                        icon: const Icon(Icons.copy, size: 18, color: Color(0xFF059669)),
                        tooltip: 'Copy Tracking Code',
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 12),
              Text(
                'Thank you for shopping with us! Live notifications have been configured to update you at every milestone of your order.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: mutedColor, height: 1.4),
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

  // 10. Customer Portal Sheet (Profile, Orders, Addresses, Wishlist, Auth)
  void _showAccountPortal() {
    if (_customerToken == null || _customer == null) {
      _showAuthDialog();
    } else {
      _showProfileSheet();
    }
  }

  void _showAuthDialog() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: cardColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return DefaultTabController(
          length: 2,
          child: Padding(
            padding: EdgeInsets.only(
              left: 20,
              right: 20,
              top: 20,
              bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const TabBar(
                  labelColor: Color(0xFF059669),
                  unselectedLabelColor: Colors.grey,
                  indicatorColor: Color(0xFF059669),
                  tabs: [
                    Tab(text: 'Sign In'),
                    Tab(text: 'Create Account'),
                  ],
                ),
                const SizedBox(height: 16),
                SizedBox(
                  height: 320,
                  child: TabBarView(
                    children: [
                      // Sign In View
                      _buildSignInTab(ctx),
                      // Register View
                      _buildRegisterTab(ctx),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSignInTab(BuildContext sheetContext) {
    final loginCtrl = TextEditingController();
    final passCtrl = TextEditingController();
    bool loading = false;

    return StatefulBuilder(
      builder: (context, setTabState) {
        return Column(
          children: [
            TextField(
              controller: loginCtrl,
              decoration: const InputDecoration(labelText: 'Email or Phone Number', isDense: true),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: passCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Password', isDense: true),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: loading
                    ? null
                    : () async {
                        setTabState(() => loading = true);
                        try {
                          final client = context.read<ApiClient>();
                          final res = await client.post('/api/storefront/customer/login?store=$_effectiveStoreSlug', data: {
                            'login': loginCtrl.text.trim(),
                            'password': passCtrl.text,
                          });
                          if (res['token'] != null) {
                            final token = res['token'].toString();
                            final prefs = await SharedPreferences.getInstance();
                            await prefs.setString('pos_store_customer_token', token);
                            setState(() {
                              _customerToken = token;
                              _customer = res['customer'] as Map<String, dynamic>?;
                              if (_customer != null) _prefillCustomerForm(_customer!);
                            });
                            await _fetchCustomerOrders();
                            await _fetchCustomerAddresses();
                            if (mounted) Navigator.pop(sheetContext);
                          }
                        } catch (e) {
                          ScaffoldMessenger.of(sheetContext).showSnackBar(
                            SnackBar(content: Text('Login failed: $e')),
                          );
                        } finally {
                          setTabState(() => loading = false);
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF059669),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: loading
                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Sign In', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildRegisterTab(BuildContext sheetContext) {
    final nameCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final passCtrl = TextEditingController();
    bool loading = false;

    return StatefulBuilder(
      builder: (context, setTabState) {
        return SingleChildScrollView(
          child: Column(
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Full Name *', isDense: true),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: phoneCtrl,
                decoration: const InputDecoration(labelText: 'Phone Number *', isDense: true),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: emailCtrl,
                decoration: const InputDecoration(labelText: 'Email Address (Optional)', isDense: true),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: passCtrl,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Password *', isDense: true),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: loading
                      ? null
                      : () async {
                          setTabState(() => loading = true);
                          try {
                            final client = context.read<ApiClient>();
                            final res = await client.post('/api/storefront/customer/register?store=$_effectiveStoreSlug', data: {
                              'name': nameCtrl.text.trim(),
                              'phone': phoneCtrl.text.trim(),
                              'email': emailCtrl.text.trim().isNotEmpty ? emailCtrl.text.trim() : null,
                              'password': passCtrl.text,
                            });
                            if (res['token'] != null) {
                              final token = res['token'].toString();
                              final prefs = await SharedPreferences.getInstance();
                              await prefs.setString('pos_store_customer_token', token);
                              setState(() {
                                _customerToken = token;
                                _customer = res['customer'] as Map<String, dynamic>?;
                                if (_customer != null) _prefillCustomerForm(_customer!);
                              });
                              if (mounted) Navigator.pop(sheetContext);
                            }
                          } catch (e) {
                            ScaffoldMessenger.of(sheetContext).showSnackBar(
                              SnackBar(content: Text('Registration failed: $e')),
                            );
                          } finally {
                            setTabState(() => loading = false);
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF059669),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: loading
                      ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('Create Account', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showFaqsSheet({String initialCategory = 'all'}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black87;
    final searchCtrl = TextEditingController();
    String selectedCategory = initialCategory;
    String searchQuery = '';
    int? expandedFaqIndex;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: cardColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setFaqState) {
            final categoriesSet = <String>{'all'};
            for (final f in _faqs) {
              final cat = (f['category'] ?? '').toString().trim();
              if (cat.isNotEmpty) categoriesSet.add(cat);
            }
            final categoriesList = categoriesSet.toList();

            final filtered = _faqs.where((f) {
              final cat = (f['category'] ?? '').toString().trim();
              final matchesCat = selectedCategory == 'all' || cat.toLowerCase() == selectedCategory.toLowerCase();
              final q = (f['question'] ?? '').toString().toLowerCase();
              final a = (f['answer'] ?? '').toString().toLowerCase();
              final matchesSearch = searchQuery.isEmpty || q.contains(searchQuery) || a.contains(searchQuery);
              return matchesCat && matchesSearch;
            }).toList();

            return DraggableScrollableSheet(
              initialChildSize: 0.85,
              minChildSize: 0.5,
              maxChildSize: 0.95,
              expand: false,
              builder: (ctx, scrollController) {
                return Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Center(
                        child: Container(
                          width: 40,
                          height: 4,
                          decoration: BoxDecoration(
                            color: Colors.grey.shade400,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 32,
                                height: 32,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: const Color(0x26059669),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: const Text('❓', style: TextStyle(fontSize: 16)),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                'Frequently Asked Questions',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w900,
                                  color: textColor,
                                ),
                              ),
                            ],
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 20),
                            onPressed: () => Navigator.pop(sheetContext),
                          ),
                        ],
                      ),
                      Text(
                        'Answers provided directly by ${_company['name'] ?? 'store'} staff.',
                        style: TextStyle(fontSize: 11, color: isDark ? Colors.white54 : Colors.grey.shade600),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: searchCtrl,
                        onChanged: (val) {
                          setFaqState(() {
                            searchQuery = val.trim().toLowerCase();
                          });
                        },
                        style: TextStyle(color: textColor, fontSize: 13),
                        decoration: InputDecoration(
                          hintText: 'Search questions or keywords (e.g. delivery, COD, returns)...',
                          hintStyle: TextStyle(fontSize: 11, color: isDark ? Colors.white38 : Colors.grey.shade400),
                          prefixIcon: const Icon(Icons.search, size: 18),
                          suffixIcon: searchQuery.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.clear, size: 16),
                                  onPressed: () {
                                    searchCtrl.clear();
                                    setFaqState(() => searchQuery = '');
                                  },
                                )
                              : null,
                          isDense: true,
                          filled: true,
                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(14),
                            borderSide: BorderSide.none,
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: categoriesList.map((cat) {
                            final isSel = selectedCategory.toLowerCase() == cat.toLowerCase();
                            return Padding(
                              padding: const EdgeInsets.only(right: 6),
                              child: ChoiceChip(
                                label: Text(
                                  cat == 'all' ? 'All Topics' : cat,
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                                    color: isSel ? Colors.white : (isDark ? Colors.white70 : Colors.black87),
                                  ),
                                ),
                                selected: isSel,
                                selectedColor: const Color(0xFF059669),
                                backgroundColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                onSelected: (sel) {
                                  if (sel) {
                                    setFaqState(() => selectedCategory = cat);
                                  }
                                },
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                      const SizedBox(height: 10),
                      Expanded(
                        child: filtered.isEmpty
                            ? Center(
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const Text('🔍', style: TextStyle(fontSize: 32)),
                                    const SizedBox(height: 8),
                                    Text('No FAQs match your search.', style: TextStyle(color: textColor, fontWeight: FontWeight.bold)),
                                    const SizedBox(height: 4),
                                    Text('Try another keyword or choose All Topics.', style: TextStyle(color: isDark ? Colors.white54 : Colors.grey.shade600, fontSize: 11)),
                                  ],
                                ),
                              )
                            : ListView.separated(
                                controller: scrollController,
                                itemCount: filtered.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 8),
                                itemBuilder: (context, i) {
                                  final faq = filtered[i];
                                  final isExpanded = expandedFaqIndex == i;
                                  final question = faq['question']?.toString() ?? '';
                                  final answer = faq['answer']?.toString() ?? '';
                                  final category = faq['category']?.toString() ?? 'General';

                                  return Container(
                                    decoration: BoxDecoration(
                                      color: isDark ? AppTheme.darkBorder.withValues(alpha: 0.5) : Colors.grey.shade50,
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(
                                        color: isDark ? Colors.white10 : Colors.grey.shade200,
                                      ),
                                    ),
                                    child: Theme(
                                      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                                      child: ExpansionTile(
                                        initiallyExpanded: isExpanded,
                                        onExpansionChanged: (exp) {
                                          setFaqState(() {
                                            expandedFaqIndex = exp ? i : null;
                                          });
                                        },
                                        tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
                                        childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
                                        leading: Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: const Color(0x26059669),
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          child: Text(
                                            category,
                                            style: const TextStyle(
                                              fontSize: 9,
                                              fontWeight: FontWeight.bold,
                                              color: Color(0xFF059669),
                                            ),
                                          ),
                                        ),
                                        title: Text(
                                          question,
                                          style: TextStyle(
                                            fontSize: 12.5,
                                            fontWeight: FontWeight.bold,
                                            color: textColor,
                                          ),
                                        ),
                                        children: [
                                          Align(
                                            alignment: Alignment.centerLeft,
                                            child: Text(
                                              answer,
                                              style: TextStyle(
                                                fontSize: 11.5,
                                                height: 1.4,
                                                color: isDark ? Colors.white70 : Colors.grey.shade700,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                      ),
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

  void _showProfileSheet() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black87;

    // Controllers for editing Personal Information
    final nameCtrl = TextEditingController(text: _customer?['name']?.toString() ?? '');
    final phoneCtrl = TextEditingController(text: _customer?['phone']?.toString() ?? '');
    final emailCtrl = TextEditingController(text: _customer?['email']?.toString() ?? '');
    final dobCtrl = TextEditingController(text: _customer?['date_of_birth']?.toString() ?? '');
    String gender = (_customer?['gender']?.toString() ?? 'male').toLowerCase();
    if (gender != 'male' && gender != 'female') gender = 'male';
    bool editMode = false;
    bool isSaving = false;

    // Controllers for Change Password
    final currentPassCtrl = TextEditingController();
    final newPassCtrl = TextEditingController();
    final confirmPassCtrl = TextEditingController();
    bool isChangingPass = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: cardColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setProfileState) {
            return DefaultTabController(
              length: 6,
              child: DraggableScrollableSheet(
                initialChildSize: 0.9,
                minChildSize: 0.5,
                maxChildSize: 0.95,
                expand: false,
                builder: (ctx2, scrollController) {
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Center(
                          child: Container(
                            width: 40,
                            height: 4,
                            decoration: BoxDecoration(
                              color: Colors.grey.shade400,
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),

                        // Header Profile Card (matching user-dashboard-should-look-like-this.webp)
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF047857), Color(0xFF065F46), Color(0xFF0F172A)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0x26000000),
                                blurRadius: 10,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Stack(
                                children: [
                                  CircleAvatar(
                                    radius: 28,
                                    backgroundColor: Colors.white,
                                    child: CircleAvatar(
                                      radius: 26,
                                      backgroundColor: const Color(0xFF10B981),
                                      child: Text(
                                        (_customer?['name']?.toString() ?? 'U').isNotEmpty
                                            ? (_customer!['name'].toString().substring(0, 1).toUpperCase())
                                            : 'U',
                                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    bottom: 0,
                                    right: 0,
                                    child: Container(
                                      padding: const EdgeInsets.all(4),
                                      decoration: const BoxDecoration(
                                        color: Colors.white,
                                        shape: BoxShape.circle,
                                      ),
                                      child: const Icon(Icons.edit, size: 12, color: Color(0xFF047857)),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Hello,', style: TextStyle(fontSize: 11, color: Colors.white70)),
                                    Text(
                                      _customer?['name']?.toString() ?? 'Customer',
                                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Colors.white),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      _customer?['phone']?.toString() ?? (_customer?['email']?.toString() ?? 'Verified Member'),
                                      style: const TextStyle(fontSize: 11, color: Colors.white70),
                                    ),
                                  ],
                                ),
                              ),
                              IconButton(
                                tooltip: 'Sign Out',
                                icon: const Icon(Icons.logout, color: Colors.white),
                                onPressed: () async {
                                  final prefs = await SharedPreferences.getInstance();
                                  await prefs.remove('pos_store_customer_token');
                                  setState(() {
                                    _customerToken = null;
                                    _customer = null;
                                    _ordersList.clear();
                                    _savedAddresses.clear();
                                  });
                                  Navigator.pop(ctx);
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Navigation Tabs
                        TabBar(
                          isScrollable: true,
                          tabAlignment: TabAlignment.start,
                          labelColor: const Color(0xFF059669),
                          unselectedLabelColor: Colors.grey,
                          indicatorColor: const Color(0xFF059669),
                          indicatorWeight: 2.5,
                          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                          tabs: const [
                            Tab(text: 'Personal Info'),
                            Tab(text: 'My Orders'),
                            Tab(text: 'Wishlist'),
                            Tab(text: 'Addresses'),
                            Tab(text: 'Returns & Cancel'),
                            Tab(text: 'Security'),
                          ],
                        ),
                        const SizedBox(height: 12),

                        // Tab Bar Views
                        Expanded(
                          child: TabBarView(
                            children: [
                              // 1. Personal Information Tab (matching user-dashboard-should-look-like-this.webp)
                              SingleChildScrollView(
                                controller: scrollController,
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 6),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text('Personal Information', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: textColor)),
                                          TextButton.icon(
                                            onPressed: () {
                                              setProfileState(() {
                                                editMode = !editMode;
                                              });
                                            },
                                            icon: Icon(editMode ? Icons.check : Icons.edit, size: 14, color: const Color(0xFF059669)),
                                            label: Text(
                                              editMode ? 'Done' : 'Change Profile Information',
                                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF059669)),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 10),

                                      // Full Name Field
                                      Text('Full Name', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
                                      const SizedBox(height: 4),
                                      TextField(
                                        controller: nameCtrl,
                                        enabled: editMode,
                                        style: TextStyle(color: textColor, fontSize: 13),
                                        decoration: InputDecoration(
                                          prefixIcon: const Icon(Icons.person_outline, size: 18),
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 12),

                                      // Date of Birth Field with Calendar Picker
                                      Text('Date of Birth', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
                                      const SizedBox(height: 4),
                                      InkWell(
                                        onTap: editMode
                                            ? () async {
                                                final now = DateTime.now();
                                                DateTime initial = DateTime(2000, 1, 1);
                                                if (dobCtrl.text.isNotEmpty) {
                                                  final parsed = DateTime.tryParse(dobCtrl.text);
                                                  if (parsed != null) initial = parsed;
                                                }
                                                final picked = await showDatePicker(
                                                  context: context,
                                                  initialDate: initial,
                                                  firstDate: DateTime(1920),
                                                  lastDate: now,
                                                );
                                                if (picked != null) {
                                                  setProfileState(() {
                                                    dobCtrl.text = "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
                                                  });
                                                }
                                              }
                                            : null,
                                        child: IgnorePointer(
                                          child: TextField(
                                            controller: dobCtrl,
                                            enabled: editMode,
                                            style: TextStyle(color: textColor, fontSize: 13),
                                            decoration: InputDecoration(
                                              prefixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
                                              hintText: 'YYYY-MM-DD',
                                              isDense: true,
                                              filled: true,
                                              fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                            ),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 12),

                                       // Gender Radio Selector
                                       Text('Gender', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
                                       const SizedBox(height: 6),
                                       Row(
                                         children: [
                                           InkWell(
                                             onTap: editMode
                                                 ? () {
                                                     setProfileState(() {
                                                       gender = 'male';
                                                     });
                                                   }
                                                 : null,
                                             borderRadius: BorderRadius.circular(8),
                                             child: Padding(
                                               padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 6),
                                               child: Row(
                                                 children: [
                                                   Icon(
                                                     gender == 'male' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                                                     size: 18,
                                                     color: gender == 'male' ? const Color(0xFF059669) : Colors.grey,
                                                   ),
                                                   const SizedBox(width: 6),
                                                   Text('Male', style: TextStyle(fontSize: 12, color: textColor)),
                                                 ],
                                               ),
                                             ),
                                           ),
                                           const SizedBox(width: 20),
                                           InkWell(
                                             onTap: editMode
                                                 ? () {
                                                     setProfileState(() {
                                                       gender = 'female';
                                                     });
                                                   }
                                                 : null,
                                             borderRadius: BorderRadius.circular(8),
                                             child: Padding(
                                               padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 6),
                                               child: Row(
                                                 children: [
                                                   Icon(
                                                     gender == 'female' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                                                     size: 18,
                                                     color: gender == 'female' ? const Color(0xFF059669) : Colors.grey,
                                                   ),
                                                   const SizedBox(width: 6),
                                                   Text('Female', style: TextStyle(fontSize: 12, color: textColor)),
                                                 ],
                                               ),
                                             ),
                                           ),
                                         ],
                                       ),
                                       const SizedBox(height: 12),

                                      // Mobile Number Field
                                      Text('Mobile Number', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
                                      const SizedBox(height: 4),
                                      TextField(
                                        controller: phoneCtrl,
                                        enabled: editMode,
                                        style: TextStyle(color: textColor, fontSize: 13),
                                        decoration: InputDecoration(
                                          prefixIcon: Padding(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                                            child: Text('📱', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: textColor)),
                                          ),
                                          prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 12),

                                      // Email Address Field
                                      Text('Email Address', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textColor)),
                                      const SizedBox(height: 4),
                                      TextField(
                                        controller: emailCtrl,
                                        enabled: editMode,
                                        style: TextStyle(color: textColor, fontSize: 13),
                                        decoration: InputDecoration(
                                          prefixIcon: const Icon(Icons.email_outlined, size: 18),
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 18),

                                      if (editMode)
                                        SizedBox(
                                          width: double.infinity,
                                          child: ElevatedButton(
                                            onPressed: isSaving
                                                ? null
                                                : () async {
                                                    setProfileState(() => isSaving = true);
                                                    try {
                                                      final client = context.read<ApiClient>();
                                                      final uri = '/api/storefront/customer/profile?store=$_effectiveStoreSlug';
                                                      final res = await client.post(uri, data: {
                                                        'name': nameCtrl.text.trim(),
                                                        'phone': phoneCtrl.text.trim(),
                                                        'email': emailCtrl.text.trim().isNotEmpty ? emailCtrl.text.trim() : null,
                                                        'date_of_birth': dobCtrl.text.trim().isNotEmpty ? dobCtrl.text.trim() : null,
                                                        'gender': gender,
                                                      });
                                                      if (res['customer'] is Map) {
                                                        setState(() {
                                                          _customer = res['customer'] as Map<String, dynamic>;
                                                          _prefillCustomerForm(_customer!);
                                                        });
                                                        setProfileState(() {
                                                          editMode = false;
                                                        });
                                                        if (mounted) {
                                                          ScaffoldMessenger.of(context).showSnackBar(
                                                            const SnackBar(content: Text('Profile information updated successfully!')),
                                                          );
                                                        }
                                                      }
                                                    } catch (e) {
                                                      if (mounted) {
                                                        ScaffoldMessenger.of(context).showSnackBar(
                                                          SnackBar(content: Text('Update failed: $e')),
                                                        );
                                                      }
                                                    } finally {
                                                      setProfileState(() => isSaving = false);
                                                    }
                                                  },
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: const Color(0xFF059669),
                                              foregroundColor: Colors.white,
                                              padding: const EdgeInsets.symmetric(vertical: 14),
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                            ),
                                            child: isSaving
                                                ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                                : const Text('Save Profile Changes', style: TextStyle(fontWeight: FontWeight.bold)),
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                              ),

                              // 2. My Orders Tab
                              _ordersList.isEmpty
                                  ? const Center(child: Text('No orders yet.'))
                                  : ListView.separated(
                                      controller: scrollController,
                                      itemCount: _ordersList.length,
                                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                                      itemBuilder: (context, i) {
                                        final o = _ordersList[i] as Map<String, dynamic>;
                                        final status = (o['status'] ?? 'pending').toString().toLowerCase();
                                        final trackingCode = o['tracking_code']?.toString();
                                        final statusColor = (status == 'completed' || status == 'delivered')
                                            ? const Color(0xFF059669)
                                            : (status == 'shipped' || status == 'dispatched')
                                                ? const Color(0xFF2563EB)
                                                : status == 'cancelled'
                                                    ? Colors.red
                                                    : const Color(0xFFD97706);

                                        return Container(
                                          padding: const EdgeInsets.all(12),
                                          decoration: BoxDecoration(
                                            color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                                            borderRadius: BorderRadius.circular(14),
                                            border: Border.all(color: isDark ? Colors.white10 : Colors.grey.shade200),
                                          ),
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                children: [
                                                  Row(
                                                    children: [
                                                      Text(o['sale_number']?.toString() ?? 'Order', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                                      const SizedBox(width: 8),
                                                      Container(
                                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                        decoration: BoxDecoration(
                                                          color: statusColor.withValues(alpha: 0.15),
                                                          borderRadius: BorderRadius.circular(6),
                                                        ),
                                                        child: Text(
                                                          status.toUpperCase(),
                                                          style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: statusColor),
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                  Text(
                                                    '\$${o['total'] ?? o['net_amount'] ?? '0.00'}',
                                                    style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF059669), fontSize: 14),
                                                  ),
                                                ],
                                              ),
                                              const SizedBox(height: 4),
                                              Text('${o['created_at'] ?? ''} • ${o['payment_method'] ?? 'COD'}', style: TextStyle(fontSize: 11, color: isDark ? Colors.white54 : Colors.grey.shade600)),
                                              if (trackingCode != null && trackingCode.isNotEmpty) ...[
                                                const SizedBox(height: 8),
                                                InkWell(
                                                  onTap: () {
                                                    Clipboard.setData(ClipboardData(text: trackingCode));
                                                    ScaffoldMessenger.of(context).showSnackBar(
                                                      const SnackBar(
                                                        content: Text('Tracking code copied to clipboard!'),
                                                        duration: Duration(seconds: 1),
                                                      ),
                                                    );
                                                  },
                                                  child: Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                    decoration: BoxDecoration(
                                                      color: const Color(0xFF059669).withValues(alpha: 0.1),
                                                      borderRadius: BorderRadius.circular(6),
                                                      border: Border.all(color: const Color(0xFF86EFAC)),
                                                    ),
                                                    child: Row(
                                                      mainAxisSize: MainAxisSize.min,
                                                      children: [
                                                        const Icon(Icons.local_shipping_outlined, size: 13, color: Color(0xFF059669)),
                                                        const SizedBox(width: 4),
                                                        Text(
                                                          'Tracking: $trackingCode',
                                                          style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669)),
                                                        ),
                                                        const SizedBox(width: 4),
                                                        const Icon(Icons.copy, size: 11, color: Color(0xFF059669)),
                                                      ],
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ],
                                          ),
                                        );
                                      },
                                    ),

                              // 3. Wishlist Tab
                              _wishlist.isEmpty
                                  ? const Center(child: Text('Your wishlist is empty.'))
                                  : Builder(
                                      builder: (context) {
                                        final wishlistList = _wishlist.toList();
                                        return ListView.separated(
                                          controller: scrollController,
                                          itemCount: wishlistList.length,
                                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                                          itemBuilder: (context, i) {
                                            final pid = wishlistList[i];
                                            final matches = _products.where((prod) => prod.id == pid);
                                            if (matches.isEmpty) return const SizedBox.shrink();
                                            final p = matches.first;
                                            return Container(
                                              padding: const EdgeInsets.all(10),
                                              decoration: BoxDecoration(
                                                color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                                                borderRadius: BorderRadius.circular(14),
                                                border: Border.all(color: isDark ? Colors.white10 : Colors.grey.shade200),
                                              ),
                                              child: Row(
                                                children: [
                                                  Expanded(
                                                    child: Column(
                                                      crossAxisAlignment: CrossAxisAlignment.start,
                                                      children: [
                                                        Text(p.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                                        Text('\$${p.salePrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 11, color: Color(0xFF059669), fontWeight: FontWeight.bold)),
                                                      ],
                                                    ),
                                                  ),
                                                  ElevatedButton(
                                                    onPressed: () {
                                                      _addToCart(p);
                                                      ScaffoldMessenger.of(context).showSnackBar(
                                                        SnackBar(content: Text('${p.name} added to cart!'), duration: const Duration(seconds: 1)),
                                                      );
                                                    },
                                                    style: ElevatedButton.styleFrom(
                                                      backgroundColor: const Color(0xFF059669),
                                                      foregroundColor: Colors.white,
                                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                                    ),
                                                    child: const Text('+ Cart', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                                  ),
                                                ],
                                              ),
                                            );
                                          },
                                        );
                                      },
                                    ),

                              // 4. Saved Addresses Tab
                              _savedAddresses.isEmpty
                                  ? const Center(child: Text('No saved addresses.'))
                                  : ListView.separated(
                                      controller: scrollController,
                                      itemCount: _savedAddresses.length,
                                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                                      itemBuilder: (context, i) {
                                        final a = _savedAddresses[i] as Map<String, dynamic>;
                                        return Container(
                                          padding: const EdgeInsets.all(12),
                                          decoration: BoxDecoration(
                                            color: isDark ? AppTheme.darkBorder : Colors.grey.shade50,
                                            borderRadius: BorderRadius.circular(14),
                                            border: Border.all(color: isDark ? Colors.white10 : Colors.grey.shade200),
                                          ),
                                          child: Row(
                                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                            children: [
                                              Expanded(
                                                child: Column(
                                                  crossAxisAlignment: CrossAxisAlignment.start,
                                                  children: [
                                                    Text(a['street_address']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                                    Text(a['city']?.toString() ?? '', style: TextStyle(fontSize: 11, color: isDark ? Colors.white54 : Colors.grey.shade600)),
                                                  ],
                                                ),
                                              ),
                                              Text(a['type']?.toString().toUpperCase() ?? 'HOME', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF059669))),
                                            ],
                                          ),
                                        );
                                      },
                                    ),

                              // 5. Returns & Cancel Tab
                              Padding(
                                padding: const EdgeInsets.all(10),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Returns & Cancellations', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                                    const SizedBox(height: 8),
                                    Text(
                                      'Orders in "Placed" or "Confirmed" status can be cancelled prior to driver dispatch.\n\nItems in original packaging can be returned within 7 days of delivery.\n\nNeed to cancel or return an item? Reach out to store support:',
                                      style: TextStyle(fontSize: 12, height: 1.5, color: isDark ? Colors.white70 : Colors.grey.shade700),
                                    ),
                                    const SizedBox(height: 16),
                                    if (_company['phone'] != null)
                                      ElevatedButton.icon(
                                        onPressed: () {},
                                        icon: const Icon(Icons.phone, size: 16),
                                        label: Text('Call Store: ${_company['phone']}'),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: const Color(0xFF059669),
                                          foregroundColor: Colors.white,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                        ),
                                      ),
                                  ],
                                ),
                              ),

                              // 6. Change Password Tab
                              SingleChildScrollView(
                                child: Padding(
                                  padding: const EdgeInsets.all(10),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Change Account Password', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                                      const SizedBox(height: 10),
                                      TextField(
                                        controller: currentPassCtrl,
                                        obscureText: true,
                                        decoration: InputDecoration(
                                          labelText: 'Current Password',
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 10),
                                      TextField(
                                        controller: newPassCtrl,
                                        obscureText: true,
                                        decoration: InputDecoration(
                                          labelText: 'New Password',
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 10),
                                      TextField(
                                        controller: confirmPassCtrl,
                                        obscureText: true,
                                        decoration: InputDecoration(
                                          labelText: 'Confirm New Password',
                                          isDense: true,
                                          filled: true,
                                          fillColor: isDark ? AppTheme.darkBorder : Colors.grey.shade100,
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                                        ),
                                      ),
                                      const SizedBox(height: 16),
                                      SizedBox(
                                        width: double.infinity,
                                        child: ElevatedButton(
                                          onPressed: isChangingPass
                                              ? null
                                              : () async {
                                                  if (newPassCtrl.text != confirmPassCtrl.text) {
                                                    ScaffoldMessenger.of(context).showSnackBar(
                                                      const SnackBar(content: Text('New passwords do not match.')),
                                                    );
                                                    return;
                                                  }
                                                  setProfileState(() => isChangingPass = true);
                                                  try {
                                                    final client = context.read<ApiClient>();
                                                    await client.post('/api/storefront/customer/profile?store=$_effectiveStoreSlug', data: {
                                                      'current_password': currentPassCtrl.text,
                                                      'new_password': newPassCtrl.text,
                                                    });
                                                    if (mounted) {
                                                      ScaffoldMessenger.of(context).showSnackBar(
                                                        const SnackBar(content: Text('Password changed successfully!')),
                                                      );
                                                    }
                                                    currentPassCtrl.clear();
                                                    newPassCtrl.clear();
                                                    confirmPassCtrl.clear();
                                                  } catch (e) {
                                                    if (mounted) {
                                                      ScaffoldMessenger.of(context).showSnackBar(
                                                        SnackBar(content: Text('Error: $e')),
                                                      );
                                                    }
                                                  } finally {
                                                    setProfileState(() => isChangingPass = false);
                                                  }
                                                },
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: const Color(0xFF059669),
                                            foregroundColor: Colors.white,
                                            padding: const EdgeInsets.symmetric(vertical: 14),
                                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                          ),
                                          child: isChangingPass
                                              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                              : const Text('Update Password', style: TextStyle(fontWeight: FontWeight.bold)),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            );
          },
        );
      },
    );
  }
}
