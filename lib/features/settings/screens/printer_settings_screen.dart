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
    try {
      final enabled = await _service.bluetoothEnabled;
      if (!enabled) {
        setState(() {
          _error = 'Turn on Bluetooth to pair a receipt printer.';
          _loading = false;
        });
        return;
      }
      final devices = await _service.pairedDevices();
      final saved = await _service.savedDeviceAddress();
      setState(() {
        _devices = devices;
        _selectedMac = saved;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = 'Could not load paired devices: $e';
        _loading = false;
      });
    }
  }

  Future<void> _select(BluetoothInfo device) async {
    setState(() => _selectedMac = device.macAdress);
    await _service.saveDefaultDevice(device.macAdress);
    if (!mounted) return;
    final connected = await _service.connect(device.macAdress);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(connected ? 'Connected to ${device.name}.' : 'Could not connect to ${device.name}.')),
    );
  }

  Future<void> _testPrint() async {
    if (_selectedMac == null) return;
    setState(() => _testing = true);
    final ok = await _service.printReceipt(
      companyName: 'Test Receipt',
      documentLabel: 'Printer test',
      lines: [ReceiptLine(name: 'Sample item', quantity: 1, unitPrice: 1, lineTotal: 1)],
      subtotal: 1,
      discount: 0,
      tax: 0,
      total: 1,
    );
    if (!mounted) return;
    setState(() => _testing = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(ok ? 'Test receipt sent.' : 'Test print failed.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Receipt Printer'),
        actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _load)],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
              : _devices.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Text('No paired Bluetooth devices found. Pair your printer in system Bluetooth settings first.'),
                      ),
                    )
                  : ListView.builder(
                      itemCount: _devices.length,
                      itemBuilder: (context, index) {
                        final device = _devices[index];
                        final selected = device.macAdress == _selectedMac;
                        return ListTile(
                          leading: Icon(selected ? Icons.print : Icons.print_outlined),
                          title: Text(device.name),
                          subtitle: Text(device.macAdress),
                          trailing: selected ? const Icon(Icons.check_circle, color: Colors.green) : null,
                          onTap: () => _select(device),
                        );
                      },
                    ),
      bottomNavigationBar: _selectedMac == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: ElevatedButton(
                  onPressed: _testing ? null : _testPrint,
                  child: Text(_testing ? 'Printing…' : 'Test print'),
                ),
              ),
            ),
    );
  }
}
