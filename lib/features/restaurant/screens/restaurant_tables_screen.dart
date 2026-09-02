import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/restaurant_models.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../restaurant_repository.dart';
import '../widgets/qr_stand_dialog.dart';
import 'restaurant_order_screen.dart';

/// Restaurant Mode home screen: floor plan of dining tables, colored by
/// status, plus floor/table management and a way to start a takeaway or
/// delivery order with no table attached.
class RestaurantTablesScreen extends StatefulWidget {
  const RestaurantTablesScreen({super.key});

  @override
  State<RestaurantTablesScreen> createState() => _RestaurantTablesScreenState();
}

class _RestaurantTablesScreenState extends State<RestaurantTablesScreen> {
  late final RestaurantRepository _repository;
  late Future<List<DiningFloorModel>> _future;

  @override
  void initState() {
    super.initState();
    _repository = RestaurantRepository(context.read<ApiClient>());
    _future = _repository.fetchFloors();
  }

  void _reload() => setState(() => _future = _repository.fetchFloors());

  Future<void> _openTable(DiningTableModel table) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => RestaurantOrderScreen(repository: _repository, table: table)),
    );
    if (changed == true) _reload();
  }

  Future<void> _startTakeawayOrDelivery(String serviceType) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => RestaurantOrderScreen(repository: _repository, serviceType: serviceType)),
    );
    if (changed == true) _reload();
  }

  Future<void> _addFloor() async {
    final controller = TextEditingController();
    final name = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Add Floor / Area'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Name (e.g. Main Hall, Patio)')),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(controller.text.trim()), child: const Text('Add')),
        ],
      ),
    );
    if (name == null || name.isEmpty) return;

    try {
      await _repository.saveFloor(name: name);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _deleteFloor(DiningFloorModel floor) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete "${floor.name}"?'),
        content: const Text('Tables on this floor will need to be reassigned or deleted separately.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.deleteFloor(floor.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openTableForm(List<DiningFloorModel> floors, {DiningTableModel? table, String? defaultFloorId}) async {
    final numberController = TextEditingController(text: table?.tableNumber ?? '');
    final capacityController = TextEditingController(text: (table?.seatingCapacity ?? 4).toString());
    String? floorId = table?.diningFloorId ?? defaultFloorId ?? (floors.isNotEmpty ? floors.first.id : null);
    String status = table?.status ?? 'available';

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(table == null ? 'Add Table' : 'Edit Table'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: numberController, decoration: const InputDecoration(labelText: 'Table Number')),
                const SizedBox(height: 12),
                TextField(
                  controller: capacityController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Seating Capacity'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: floorId,
                  decoration: const InputDecoration(labelText: 'Floor / Area'),
                  items: [for (final f in floors) DropdownMenuItem(value: f.id, child: Text(f.name))],
                  onChanged: (v) => setDialogState(() => floorId = v),
                ),
                if (table != null) ...[
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: status,
                    decoration: const InputDecoration(labelText: 'Status'),
                    items: [for (final s in kDiningTableStatuses) DropdownMenuItem(value: s, child: Text(kDiningTableStatusLabels[s]!))],
                    onChanged: (v) => setDialogState(() => status = v ?? status),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
            TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Save')),
          ],
        ),
      ),
    );
    if (saved != true) return;

    final number = numberController.text.trim();
    final capacity = int.tryParse(capacityController.text.trim()) ?? 4;
    if (number.isEmpty) return;

    try {
      await _repository.saveTable(
        id: table?.id,
        tableNumber: number,
        diningFloorId: floorId,
        seatingCapacity: capacity,
        status: table != null ? status : null,
      );
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  void _showQrStand(DiningTableModel table) {
    final company = context.read<AuthProvider>().company;
    showDialog(
      context: context,
      builder: (_) => QrStandDialog(table: table, businessName: company?.tradeName ?? company?.name ?? 'Restaurant Business'),
    );
  }

  Future<void> _deleteTable(DiningTableModel table) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete table ${table.tableNumber}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.deleteTable(table.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tables'),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert),
            onSelected: (value) {
              if (value == 'takeaway') _startTakeawayOrDelivery('takeaway');
              if (value == 'delivery') _startTakeawayOrDelivery('delivery');
              if (value == 'add_floor') _addFloor();
            },
            itemBuilder: (context) => const [
              PopupMenuItem(value: 'takeaway', child: Text('New Takeaway Order')),
              PopupMenuItem(value: 'delivery', child: Text('New Delivery Order')),
              PopupMenuDivider(),
              PopupMenuItem(value: 'add_floor', child: Text('Add Floor / Area')),
            ],
          ),
        ],
      ),
      floatingActionButton: FutureBuilder<List<DiningFloorModel>>(
        future: _future,
        builder: (context, snapshot) {
          final floors = snapshot.data ?? [];
          if (floors.isEmpty) return const SizedBox.shrink();
          return FloatingActionButton(onPressed: () => _openTableForm(floors), child: const Icon(Icons.add));
        },
      ),
      body: RefreshIndicator(
        onRefresh: () async => _reload(),
        child: FutureBuilder<List<DiningFloorModel>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
            if (snapshot.hasError) return ErrorView(message: 'Could not load tables.', onRetry: _reload);

            final floors = snapshot.data ?? [];
            if (floors.isEmpty) {
              return ListView(
                children: [
                  const SizedBox(height: 80),
                  const Center(child: Text('No floors yet.')),
                  const SizedBox(height: 12),
                  Center(child: OutlinedButton(onPressed: _addFloor, child: const Text('Add your first floor'))),
                ],
              );
            }

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
              children: [
                for (final floor in floors) ...[
                  Row(
                    children: [
                      Expanded(child: Text(floor.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16))),
                      IconButton(
                        icon: const Icon(Icons.delete_outline, size: 20),
                        onPressed: () => _deleteFloor(floor),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      for (final table in floor.tables)
                        _TableCard(
                          table: table,
                          onTap: () => _openTable(table),
                          onEdit: () => _openTableForm(floors, table: table),
                          onDelete: () => _deleteTable(table),
                          onQrStand: () => _showQrStand(table),
                        ),
                    ],
                  ),
                  const SizedBox(height: 20),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _TableCard extends StatelessWidget {
  const _TableCard({required this.table, required this.onTap, required this.onEdit, required this.onDelete, required this.onQrStand});

  final DiningTableModel table;
  final VoidCallback onTap;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onQrStand;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      onLongPress: () => showModalBottomSheet(
        context: context,
        builder: (context) => SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.qr_code_2_outlined),
                title: const Text('QR Stand'),
                onTap: () {
                  Navigator.of(context).pop();
                  onQrStand();
                },
              ),
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('Edit table'),
                onTap: () {
                  Navigator.of(context).pop();
                  onEdit();
                },
              ),
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Delete table'),
                onTap: () {
                  Navigator.of(context).pop();
                  onDelete();
                },
              ),
            ],
          ),
        ),
      ),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 110,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: table.statusColor.withValues(alpha: 0.12),
          border: Border.all(color: table.statusColor.withValues(alpha: 0.5)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(table.tableNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            const SizedBox(height: 4),
            Text('${table.seatingCapacity} seats', style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: table.statusColor, borderRadius: BorderRadius.circular(20)),
              child: Text(
                table.statusLabel,
                style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
