import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';

import '../../../core/services/thermal/thermal_printer_service.dart';

/// Pair a Bluetooth thermal receipt printer (Android/iOS only — this screen
/// is only ever reachable from mobile-specific settings entry points).
class PrinterSettingsScreen extends StatefulWidget {
  const PrinterSettingsScreen({super.key});

  @override
  State<PrinterSettingsScreen> createState() => _PrinterSettingsScreenState();
}

class _PrinterSettingsScreenState extends State<PrinterSettingsScreen> {
  final _service = ThermalPrinterService();
  List<BluetoothInfo> _devices = [];
  String? _selectedMac;
  bool _loading = true;
  String? _error;
  bool _testing = false;
  bool _isConnecting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    if (kIsWeb || (defaultTargetPlatform != TargetPlatform.android && defaultTargetPlatform != TargetPlatform.iOS)) {
      if (!mounted) return;
      setState(() {
        _error = 'Bluetooth thermal receipt printing is designed for Android and iOS mobile devices. On Web and Desktop, system printing is used automatically.';
        _loading = false;
      });
      return;
    }

    try {
      final enabled = await _service.bluetoothEnabled;
      if (!enabled) {
        if (!mounted) return;
        setState(() {
          _error = 'Bluetooth is turned off. Please enable Bluetooth in your device settings and try again.';
          _loading = false;
        });
        return;
      }
      final devices = await _service.pairedDevices();
      final saved = await _service.savedDeviceAddress();
      if (!mounted) return;
      setState(() {
        _devices = devices;
        _selectedMac = saved;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not discover Bluetooth devices: $e';
        _loading = false;
      });
    }
  }

  Future<void> _select(BluetoothInfo device) async {
    setState(() {
      _isConnecting = true;
      _selectedMac = device.macAdress;
    });
    await _service.saveDefaultDevice(device.macAdress);
    if (!mounted) return;
    final connected = await _service.connect(device.macAdress);
    if (!mounted) return;
    setState(() => _isConnecting = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(connected ? 'Connected to ${device.name}.' : 'Could not connect to ${device.name}. Ensure printer is powered on.'),
        backgroundColor: connected ? Colors.green.shade700 : Colors.red.shade700,
      ),
    );
  }

  Future<void> _disconnect() async {
    await _service.disconnect();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Printer disconnected.')),
    );
  }

  Future<void> _testPrint() async {
    if (_selectedMac == null) return;
    setState(() => _testing = true);
    final ok = await _service.printReceipt(
      companyName: 'Sales & Inventory Test',
      documentLabel: 'Printer Test Receipt',
      lines: [
        ReceiptLine(name: 'Thermal Print Test Item', quantity: 1, unitPrice: 1.00, lineTotal: 1.00),
      ],
      subtotal: 1.00,
      discount: 0,
      tax: 0,
      total: 1.00,
      customerName: 'System Test',
      currencySymbol: '\$',
    );
    if (!mounted) return;
    setState(() => _testing = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(ok ? 'Test receipt printed successfully! 🎉' : 'Test print failed. Please check printer power and paper.'),
        backgroundColor: ok ? Colors.green.shade700 : Colors.red.shade700,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Receipt Printer'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Scan for printers',
            onPressed: _loading ? null : _load,
          ),
        ],
      ),
      body: _loading
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 16),
                  Text('Searching for paired printers…', style: TextStyle(color: Colors.grey)),
                ],
              ),
            )
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.bluetooth_disabled_outlined, size: 64, color: Colors.grey.shade400),
                        const SizedBox(height: 16),
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.grey.shade800, fontSize: 15),
                        ),
                        const SizedBox(height: 24),
                        ElevatedButton.icon(
                          onPressed: _load,
                          icon: const Icon(Icons.refresh),
                          label: const Text('Retry Scan'),
                        ),
                      ],
                    ),
                  ),
                )
              : _devices.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.print_disabled_outlined, size: 64, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            const Text(
                              'No Paired Bluetooth Printers Found',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '1. Turn on your thermal printer.\n2. Open your device’s Bluetooth settings and pair with the printer.\n3. Return here and tap "Scan for Printers".',
                              textAlign: TextAlign.left,
                              style: TextStyle(color: Colors.grey.shade600, fontSize: 13, height: 1.5),
                            ),
                            const SizedBox(height: 24),
                            ElevatedButton.icon(
                              onPressed: _load,
                              icon: const Icon(Icons.bluetooth_searching),
                              label: const Text('Scan for Printers'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Card(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          color: Colors.blue.shade50,
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              children: [
                                Icon(Icons.info_outline, color: Colors.blue.shade800, size: 20),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'Select your 58mm or 80mm ESC/POS Bluetooth printer to print receipts automatically.',
                                    style: TextStyle(color: Colors.blue.shade900, fontSize: 12),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Paired Devices',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        for (final device in _devices) ...[
                          Card(
                            elevation: device.macAdress == _selectedMac ? 2 : 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(
                                color: device.macAdress == _selectedMac ? primaryColor : Colors.grey.shade200,
                                width: device.macAdress == _selectedMac ? 2 : 1,
                              ),
                            ),
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: device.macAdress == _selectedMac ? primaryColor.withOpacity(0.15) : Colors.grey.shade100,
                                child: Icon(
                                  Icons.print_outlined,
                                  color: device.macAdress == _selectedMac ? primaryColor : Colors.grey.shade700,
                                ),
                              ),
                              title: Text(device.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                              subtitle: Text(device.macAdress, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                              trailing: device.macAdress == _selectedMac
                                  ? const Icon(Icons.check_circle, color: Colors.green)
                                  : OutlinedButton(
                                      onPressed: _isConnecting ? null : () => _select(device),
                                      child: const Text('Connect'),
                                    ),
                              onTap: _isConnecting ? null : () => _select(device),
                            ),
                          ),
                          const SizedBox(height: 6),
                        ],
                      ],
                    ),
      bottomNavigationBar: _selectedMac == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _disconnect,
                        icon: const Icon(Icons.bluetooth_disabled),
                        label: const Text('Disconnect'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _testing ? null : _testPrint,
                        icon: const Icon(Icons.print),
                        label: Text(_testing ? 'Printing…' : 'Test Print'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
