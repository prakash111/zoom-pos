import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';

class ProductReviewsScreen extends StatefulWidget {
  const ProductReviewsScreen({super.key});

  @override
  State<ProductReviewsScreen> createState() => _ProductReviewsScreenState();
}

class _ProductReviewsScreenState extends State<ProductReviewsScreen> {
  bool _isLoading = false;
  List<Map<String, dynamic>> _reviews = [];
  Map<String, dynamic> _stats = {};
  Map<String, dynamic> _settings = {};

  String _selectedFilter = 'all'; // 'all', 'pending', 'approved', '5', '4', '3', '2', '1'
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadReviews();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadReviews() async {
    if (!mounted) return;
    setState(() => _isLoading = true);

    try {
      final client = context.read<ApiClient>();
      final queryParams = <String, String>{};

      if (_selectedFilter == 'pending') {
        queryParams['status'] = 'pending';
      } else if (_selectedFilter == 'approved') {
        queryParams['status'] = 'approved';
      } else if (['5', '4', '3', '2', '1'].contains(_selectedFilter)) {
        queryParams['rating'] = _selectedFilter;
      }

      final search = _searchController.text.trim();
      if (search.isNotEmpty) {
        queryParams['search'] = search;
      }

      final res = await client.get(
        '/tenant/storefront/reviews',
        query: queryParams,
      );

      if (!mounted) return;

      if (res is Map && res['success'] == true) {
        final data = res['data'] as Map<String, dynamic>? ?? {};
        final rawList = data['reviews'] as List? ?? [];
        setState(() {
          _reviews = rawList.map((e) => Map<String, dynamic>.from(e as Map)).toList();
          _stats = Map<String, dynamic>.from(data['stats'] as Map? ?? {});
          _settings = Map<String, dynamic>.from(data['settings'] as Map? ?? {});
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load reviews: $e')),
      );
    }
  }

  Future<void> _toggleApproval(String id, bool currentStatus) async {
    final nextStatus = !currentStatus;
    try {
      final client = context.read<ApiClient>();
      final res = await client.post(
        '/tenant/storefront/reviews/$id/toggle-approval',
        data: {'is_approved': nextStatus},
      );

      if (!mounted) return;

      if (res is Map && res['success'] == true) {
        setState(() {
          final idx = _reviews.indexWhere((item) => item['id']?.toString() == id);
          if (idx != -1) {
            _reviews[idx]['is_approved'] = nextStatus;
          }
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(nextStatus ? 'Review approved and published' : 'Review hidden from store'),
            backgroundColor: nextStatus ? Colors.green : Colors.grey[800],
          ),
        );
        _loadReviews();
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to update review: $e')),
      );
    }
  }

  Future<void> _deleteReview(String id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Review'),
        content: const Text('Are you sure you want to permanently delete this customer review?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final client = context.read<ApiClient>();
      await client.delete('/tenant/storefront/reviews/$id');

      if (!mounted) return;

      setState(() {
        _reviews.removeWhere((item) => item['id']?.toString() == id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Review deleted successfully')),
      );
      _loadReviews();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to delete review: $e')),
      );
    }
  }

  void _showSettingsDialog() {
    bool enableReviews = _settings['enable_product_reviews'] ?? true;
    bool requireApproval = _settings['require_review_approval'] ?? false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: EdgeInsets.only(
            top: 20,
            left: 20,
            right: 20,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Review Moderation Settings',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.of(ctx).pop(),
                  ),
                ],
              ),
              const Divider(),
              SwitchListTile(
                title: const Text('Enable Product Reviews'),
                subtitle: const Text('Show ratings and reviews on public product pages'),
                value: enableReviews,
                onChanged: (val) => setModalState(() => enableReviews = val),
              ),
              SwitchListTile(
                title: const Text('Require Admin Approval'),
                subtitle: const Text('Keep incoming reviews hidden until approved'),
                value: requireApproval,
                onChanged: (val) => setModalState(() => requireApproval = val),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF059669),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () async {
                    Navigator.of(ctx).pop();
                    try {
                      final client = context.read<ApiClient>();
                      final res = await client.post(
                        '/tenant/storefront/reviews/settings',
                        data: {
                          'enable_product_reviews': enableReviews,
                          'require_review_approval': requireApproval,
                        },
                      );
                      if (res is Map && res['success'] == true) {
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Review settings updated successfully')),
                          );
                          _loadReviews();
                        }
                      }
                    } catch (e) {
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Failed to update settings: $e')),
                        );
                      }
                    }
                  },
                  child: const Text('Save Settings', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStars(int rating, {double size = 18}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(5, (index) {
        return Icon(
          index < rating ? Icons.star : Icons.star_border,
          color: Colors.amber[700],
          size: size,
        );
      }),
    );
  }

  Widget _buildFilterChip(String label, String value, {IconData? icon}) {
    final isSelected = _selectedFilter == value;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        avatar: icon != null
            ? Icon(icon, size: 16, color: isSelected ? Colors.white : Colors.grey[700])
            : null,
        label: Text(label),
        selected: isSelected,
        selectedColor: const Color(0xFF1E3A8A),
        backgroundColor: Colors.grey[100],
        labelStyle: TextStyle(
          color: isSelected ? Colors.white : Colors.black87,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          fontSize: 13,
        ),
        checkmarkColor: Colors.white,
        onSelected: (selected) {
          setState(() {
            _selectedFilter = selected ? value : 'all';
          });
          _loadReviews();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final pendingCount = (_stats['pending_count'] as num?)?.toInt() ?? 0;
    final totalCount = (_stats['total_count'] as num?)?.toInt() ?? 0;
    final approvedCount = (_stats['approved_count'] as num?)?.toInt() ?? 0;
    final avgRating = (_stats['average_rating'] as num?)?.toDouble() ?? 5.0;

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const Text('Product Ratings & Reviews'),
            if (pendingCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.amber[800],
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '$pendingCount pending',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Settings',
            onPressed: _showSettingsDialog,
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: _loadReviews,
          ),
        ],
      ),
      body: Column(
        children: [
          // Stats summary card
          Card(
            margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            elevation: 1,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  Column(
                    children: [
                      Text(
                        '$totalCount',
                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                      ),
                      const SizedBox(height: 2),
                      const Text('Total', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                  Column(
                    children: [
                      Text(
                        '$approvedCount',
                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF059669)),
                      ),
                      const SizedBox(height: 2),
                      const Text('Approved', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                  Column(
                    children: [
                      Text(
                        '$pendingCount',
                        style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.amber[800]),
                      ),
                      const SizedBox(height: 2),
                      const Text('Pending', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                  Column(
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.star, color: Colors.amber[700], size: 20),
                          const SizedBox(width: 2),
                          Text(
                            avgRating.toStringAsFixed(1),
                            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      const Text('Average', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                ],
              ),
            ),
          ),

          // Search bar
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search reviews, products, or customers...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _loadReviews();
                        },
                      )
                    : null,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onSubmitted: (_) => _loadReviews(),
            ),
          ),

          // Filter chips
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                _buildFilterChip('All Reviews', 'all'),
                _buildFilterChip('Pending', 'pending', icon: Icons.pending_actions),
                _buildFilterChip('Approved', 'approved', icon: Icons.check_circle_outline),
                _buildFilterChip('5 ★', '5'),
                _buildFilterChip('4 ★', '4'),
                _buildFilterChip('3 ★', '3'),
                _buildFilterChip('2 ★', '2'),
                _buildFilterChip('1 ★', '1'),
              ],
            ),
          ),

          const SizedBox(height: 6),

          // Review list or states
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _reviews.isEmpty
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(32),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.rate_review_outlined, size: 64, color: Colors.grey[400]),
                              const SizedBox(height: 16),
                              const Text(
                                'No Customer Reviews Found',
                                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _selectedFilter != 'all' || _searchController.text.isNotEmpty
                                    ? 'Try changing your search keywords or filter.'
                                    : 'When customers submit ratings and reviews on your storefront, they will appear here.',
                                textAlign: TextAlign.center,
                                style: TextStyle(fontSize: 14, color: Colors.grey[600]),
                              ),
                              const SizedBox(height: 16),
                              ElevatedButton.icon(
                                icon: const Icon(Icons.refresh),
                                label: const Text('Refresh'),
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() => _selectedFilter = 'all');
                                  _loadReviews();
                                },
                              ),
                            ],
                          ),
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _loadReviews,
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                          itemCount: _reviews.length,
                          itemBuilder: (context, index) {
                            final review = _reviews[index];
                            final id = review['id']?.toString() ?? '';
                            final productName = review['product_name'] ?? 'Product';
                            final customerName = review['customer_name'] ?? 'Customer';
                            final rating = (review['rating'] as num?)?.toInt() ?? 5;
                            final isApproved = review['is_approved'] == true;
                            final isVerified = review['is_verified_purchase'] == true;
                            final title = review['title']?.toString() ?? '';
                            final comment = review['comment']?.toString() ?? '';
                            final createdAt = review['created_at_human']?.toString() ?? '';

                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              elevation: 1.5,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: BorderSide(
                                  color: isApproved ? Colors.grey[200]! : Colors.amber[300]!,
                                  width: isApproved ? 1 : 1.5,
                                ),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    // Row 1: Stars and status badge
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        _buildStars(rating),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                          decoration: BoxDecoration(
                                            color: isApproved ? const Color(0xFFECFDF5) : const Color(0xFFFFFBEB),
                                            borderRadius: BorderRadius.circular(6),
                                            border: BorderSide(
                                              color: isApproved ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                                            ),
                                          ),
                                          child: Text(
                                            isApproved ? 'APPROVED' : 'PENDING APPROVAL',
                                            style: TextStyle(
                                              color: isApproved ? const Color(0xFF047857) : const Color(0xFFB45309),
                                              fontSize: 11,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),

                                    const SizedBox(height: 10),

                                    // Product & verified purchase tags
                                    Wrap(
                                      spacing: 6,
                                      runSpacing: 4,
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: Colors.indigo[50],
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          child: Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              Icon(Icons.inventory_2_outlined, size: 14, color: Colors.indigo[700]),
                                              const SizedBox(width: 4),
                                              Text(
                                                productName,
                                                style: TextStyle(
                                                  color: Colors.indigo[800],
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        if (isVerified)
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: Colors.blue[50],
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: Row(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Icon(Icons.verified, size: 14, color: Colors.blue[700]),
                                                const SizedBox(width: 4),
                                                Text(
                                                  'Verified Purchase',
                                                  style: TextStyle(
                                                    color: Colors.blue[800],
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w600,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                      ],
                                    ),

                                    if (title.isNotEmpty) ...[
                                      const SizedBox(height: 8),
                                      Text(
                                        title,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 15,
                                        ),
                                      ),
                                    ],

                                    const SizedBox(height: 6),

                                    // Comment
                                    Text(
                                      comment,
                                      style: const TextStyle(fontSize: 14, height: 1.3),
                                    ),

                                    const SizedBox(height: 8),

                                    // Author & Date
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          'By $customerName',
                                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                                        ),
                                        if (createdAt.isNotEmpty)
                                          Text(
                                            createdAt,
                                            style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                                          ),
                                      ],
                                    ),

                                    const Divider(height: 20),

                                    // Action buttons
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.end,
                                      children: [
                                        // Approve / Hide button
                                        if (isApproved)
                                          OutlinedButton.icon(
                                            icon: const Icon(Icons.visibility_off, size: 16),
                                            label: const Text('Hide'),
                                            style: OutlinedButton.styleFrom(
                                              foregroundColor: Colors.grey[700],
                                              visualDensity: VisualDensity.compact,
                                            ),
                                            onPressed: () => _toggleApproval(id, isApproved),
                                          )
                                        else
                                          ElevatedButton.icon(
                                            icon: const Icon(Icons.check_circle_outline, size: 16),
                                            label: const Text('Approve & Publish'),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: const Color(0xFF059669),
                                              foregroundColor: Colors.white,
                                              visualDensity: VisualDensity.compact,
                                            ),
                                            onPressed: () => _toggleApproval(id, isApproved),
                                          ),

                                        const SizedBox(width: 8),

                                        // Delete button
                                        IconButton(
                                          icon: const Icon(Icons.delete_outline, color: Colors.red, size: 20),
                                          tooltip: 'Delete Review',
                                          visualDensity: VisualDensity.compact,
                                          onPressed: () => _deleteReview(id),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
