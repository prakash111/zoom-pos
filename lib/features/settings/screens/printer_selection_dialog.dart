import 'dart:async';

import 'package:flutter/material.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/services/thermal/thermal_printer_service.dart';

/// Explicit Bluetooth printer picker. Opens straight onto the list of devices
/// already paired in Android / iOS settings (no forced discovery scan), with a
/// refresh button to re-query. Tapping a row saves it as the default output
/// device and returns it to the caller.
class PrinterSelectionDialog extends StatefulWidget {
  const PrinterSelectionDialog({super.key, this.onPrinterSelected});

  /// Optional callback fired with the chosen [BluetoothInfo] before the dialog
  /// closes. The dialog also pops with that value, so
  /// `await showDialog<BluetoothInfo>(...)` works on its own.
  final void Function(BluetoothInfo printer)? onPrinterSelected;

  /// Returns the MAC address to print to. If one is already saved it is
  /// returned immediately; otherwise the picker is shown. Null means the user
  /// cancelled or no printer is available.
  static Future<String?> ensureSelected(BuildContext context) async {
    final service = ThermalPrinterService();
    final saved = await service.savedDeviceAddress();
    if (saved != null && saved.isNotEmpty) return saved;
    if (!context.mounted) return null;
    final picked = await showDialog<BluetoothInfo>(
      context: context,
      builder: (_) => const PrinterSelectionDialog(),
    );
    return picked?.macAdress;
  }

  @override
  State<PrinterSelectionDialog> createState() => _PrinterSelectionDialogState();
}

class _PrinterSelectionDialogState extends State<PrinterSelectionDialog> {
  static const _green = Color(0xFF15803D);

  final _service = ThermalPrinterService();

  List<BluetoothInfo> _devices = [];
  bool _isLoading = true;
  bool _bluetoothOff = false;
  String? _savedAddress;

  @override
  void initState() {
    super.initState();
    _loadPairedDevices();
  }

  Future<void> _loadPairedDevices() async {
    setState(() {
      _isLoading = true;
      _bluetoothOff = false;
    });
    try {
      _savedAddress = await _service.savedDeviceAddress();
      if (!await _service.bluetoothEnabled) {
        if (mounted) {
          setState(() {
            _bluetoothOff = true;
            _isLoading = false;
          });
        }
        return;
      }
      // Bonded / already-paired devices — the plugin also raises the runtime
      // BLUETOOTH_CONNECT permission prompt here on first use.
      final paired = await _service.pairedDevices();
      if (!mounted) return;
      setState(() {
        _devices = paired;
        _isLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _select(BluetoothInfo device) async {
    await _service.saveDefaultDevice(device.macAdress);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('printer_name', device.name);
      await prefs.setString('printer_address', device.macAdress);
      await prefs.setInt(ThermalPrinterService.connectionTypePrefKey, 0); // BT
    } catch (_) {}
    // Best-effort connect; printing will retry if this misses.
    unawaited(_service.connect(device.macAdress));
    widget.onPrinterSelected?.call(device);
    if (mounted) Navigator.of(context).pop(device);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Flexible(
            child: Row(
              children: [
                Icon(Icons.bluetooth, color: _green),
                SizedBox(width: 8),
                Flexible(
                  child: Text('Select Bluetooth Printer',
                      style: TextStyle(fontSize: 18)),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Re-scan',
            onPressed: _isLoading ? null : _loadPairedDevices,
          ),
        ],
      ),
      content: SizedBox(
        width: double.maxFinite,
        height: 320,
        child: _buildBody(),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
      ],
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_bluetoothOff) {
      return _EmptyState(
        icon: Icons.bluetooth_disabled,
        message: 'Bluetooth is turned off. Enable it in your device settings, '
            'then re-scan.',
        onRetry: _loadPairedDevices,
      );
    }
    if (_devices.isEmpty) {
      return _EmptyState(
        icon: Icons.print_disabled_outlined,
        message: 'No paired Bluetooth printers found.\n'
            'Pair the printer in Android Settings → Connected Devices, then '
            're-scan.',
        onRetry: _loadPairedDevices,
      );
    }
    return ListView.builder(
      itemCount: _devices.length,
      itemBuilder: (context, index) {
        final device = _devices[index];
        final isSaved = _savedAddress == device.macAdress;
        return ListTile(
          leading: Icon(Icons.print, color: isSaved ? _green : null),
          title: Text(device.name.isEmpty ? 'Unknown device' : device.name),
          subtitle: Text(device.macAdress),
          trailing: isSaved
              ? const Icon(Icons.check_circle, color: _green)
              : null,
          onTap: () => _select(device),
        );
      },
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.message,
    required this.onRetry,
  });

  final IconData icon;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 44, color: Colors.grey),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Re-scan'),
          ),
        ],
      ),
    );
  }
}
