import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/services/thermal/thermal_printer_service.dart';

/// How the terminal is wired to the receipt printer.
enum PrinterConnectionType { bluetooth, usb, network }

/// Global, mode-agnostic hardware pairing screen. Reachable from the drawer
/// ("Printer & Hardware Setup", under Administration) and from Receipt
/// Settings in every operating mode. The selection is persisted to
/// [SharedPreferences] with the keys [ThermalPrinterService] reads, so it is
/// picked up automatically by every receipt / invoice / token print.
class GlobalPrinterSetupScreen extends StatefulWidget {
  const GlobalPrinterSetupScreen({super.key});

  @override
  State<GlobalPrinterSetupScreen> createState() =>
      _GlobalPrinterSetupScreenState();
}

class _GlobalPrinterSetupScreenState extends State<GlobalPrinterSetupScreen> {
  static const _greenHex = Color(0xFF15803D);

  final _service = ThermalPrinterService();

  PrinterConnectionType _selectedType = PrinterConnectionType.bluetooth;
  String _paperSize = '58mm'; // '58mm' or '80mm'
  bool _isScanning = false;
  bool _isTesting = false;
  String? _connectedDeviceName;
  String? _connectedDeviceAddress;

  final _ipController = TextEditingController();
  final _portController = TextEditingController(text: '9100');

  bool get _isMobile =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  @override
  void initState() {
    super.initState();
    _loadSavedPrinter();
  }

  @override
  void dispose() {
    _ipController.dispose();
    _portController.dispose();
    super.dispose();
  }

  Future<void> _loadSavedPrinter() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (!mounted) return;
      setState(() {
        final typeIndex = prefs.getInt(ThermalPrinterService.connectionTypePrefKey) ?? 0;
        _selectedType = PrinterConnectionType
            .values[typeIndex.clamp(0, PrinterConnectionType.values.length - 1)];
        _paperSize = prefs.getString(ThermalPrinterService.paperSizePrefKey) ?? '58mm';
        _connectedDeviceName = prefs.getString('printer_name');
        _connectedDeviceAddress = prefs.getString('printer_address');
        _ipController.text = prefs.getString(ThermalPrinterService.networkIpPrefKey) ?? '';
        _portController.text =
            prefs.getString(ThermalPrinterService.networkPortPrefKey) ?? '9100';
      });
    } catch (_) {
      // A blank slate is fine — the defaults above still render.
    }
  }

  Future<void> _persist({String? name, String? address}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(
          ThermalPrinterService.connectionTypePrefKey, _selectedType.index);
      await prefs.setString(
          ThermalPrinterService.paperSizePrefKey, _paperSize);
      if (name != null) await prefs.setString('printer_name', name);
      if (address != null) await prefs.setString('printer_address', address);
      if (_selectedType == PrinterConnectionType.network) {
        await prefs.setString(
            ThermalPrinterService.networkIpPrefKey, _ipController.text.trim());
        await prefs.setString(ThermalPrinterService.networkPortPrefKey,
            _portController.text.trim());
      }
    } catch (_) {}
  }

  void _toast(String message, {bool ok = true}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: ok ? _greenHex : Colors.red.shade700,
    ));
  }

  Future<void> _savePrinterConfig({
    required String name,
    required String address,
  }) async {
    await _persist(name: name, address: address);
    if (!mounted) return;
    setState(() {
      _connectedDeviceName = name;
      _connectedDeviceAddress = address;
    });
    _toast('Saved default printer: $name');
  }

  // --- Bluetooth -----------------------------------------------------------

  Future<void> _scanBluetoothDevices() async {
    if (!_isMobile) {
      _toast('Bluetooth pairing is available on the Android / iOS app.',
          ok: false);
      return;
    }
    setState(() => _isScanning = true);
    try {
      if (!await _service.bluetoothEnabled) {
        _toast('Turn on Bluetooth in your device settings, then scan again.',
            ok: false);
        return;
      }
      final devices = await _service.pairedDevices();
      if (!mounted) return;
      if (devices.isEmpty) {
        _toast('No paired Bluetooth printers. Pair the printer in Android '
            'Settings first, then scan again.', ok: false);
        return;
      }
      await _showDevicePicker(devices);
    } finally {
      if (mounted) setState(() => _isScanning = false);
    }
  }

  Future<void> _showDevicePicker(List<BluetoothInfo> devices) async {
    final picked = await showModalBottomSheet<BluetoothInfo>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(
              title: Text('Paired Bluetooth printers',
                  style: TextStyle(fontWeight: FontWeight.bold)),
            ),
            for (final d in devices)
              ListTile(
                leading: const Icon(Icons.print_outlined),
                title: Text(d.name),
                subtitle: Text(d.macAdress),
                onTap: () => Navigator.of(sheetContext).pop(d),
              ),
          ],
        ),
      ),
    );
    if (picked == null) return;

    await _service.saveDefaultDevice(picked.macAdress);
    final connected = await _service.connect(picked.macAdress);
    await _savePrinterConfig(name: picked.name, address: picked.macAdress);
    _toast(connected
        ? 'Connected to ${picked.name}.'
        : 'Saved ${picked.name}. Power the printer on to connect.');
  }

  // --- USB ---------------------------------------------------------------

  Future<void> _detectUsbPrinters() async {
    // Raw USB-OTG ESC/POS varies by vendor and needs a per-device driver;
    // most Android thermal printers also expose a Bluetooth or LAN interface
    // that works out of the box. We still let the operator pin "USB" as the
    // intended interface so the choice is remembered.
    await _savePrinterConfig(
      name: 'USB / OTG thermal printer',
      address: 'usb',
    );
    _toast('USB selected. Connect the printer by cable; if it is not detected, '
        'use its Bluetooth or LAN/WiFi interface instead.');
  }

  // --- Network ---------------------------------------------------------------

  Future<void> _saveNetworkPrinter() async {
    final ip = _ipController.text.trim();
    final port = int.tryParse(_portController.text.trim()) ?? 9100;
    if (ip.isEmpty) {
      _toast('Enter the printer IP address.', ok: false);
      return;
    }
    await _savePrinterConfig(
      name: 'Network printer ($ip)',
      address: '$ip:$port',
    );
  }

  // --- Test print ----------------------------------------------------------

  Future<void> _testPrintReceipt() async {
    setState(() => _isTesting = true);
    try {
      bool ok;
      switch (_selectedType) {
        case PrinterConnectionType.network:
          ok = await _service.testNetworkPrint(
            _ipController.text.trim(),
            int.tryParse(_portController.text.trim()) ?? 9100,
          );
          break;
        case PrinterConnectionType.bluetooth:
        case PrinterConnectionType.usb:
          ok = await _service.testBluetoothPrint();
          break;
      }
      _toast(
        ok
            ? 'Test ticket sent to the printer.'
            : 'Could not print. Check power, paper and the connection.',
        ok: ok,
      );
    } finally {
      if (mounted) setState(() => _isTesting = false);
    }
  }

  // --- UI ----------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Printer & Hardware Setup')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _connectionCard(),
          const SizedBox(height: 16),
          if (_connectedDeviceName != null) ...[
            _activeDeviceCard(),
            const SizedBox(height: 16),
          ],
          _discoveryForType(),
        ],
      ),
    );
  }

  Widget _connectionCard() {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Connection Interface',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: SegmentedButton<PrinterConnectionType>(
                segments: const [
                  ButtonSegment(
                    value: PrinterConnectionType.bluetooth,
                    label: Text('Bluetooth'),
                    icon: Icon(Icons.bluetooth),
                  ),
                  ButtonSegment(
                    value: PrinterConnectionType.usb,
                    label: Text('USB Cable'),
                    icon: Icon(Icons.usb),
                  ),
                  ButtonSegment(
                    value: PrinterConnectionType.network,
                    label: Text('LAN/WiFi'),
                    icon: Icon(Icons.wifi),
                  ),
                ],
                selected: {_selectedType},
                showSelectedIcon: false,
                onSelectionChanged: (set) {
                  setState(() => _selectedType = set.first);
                  _persist();
                },
              ),
            ),
            const SizedBox(height: 16),
            const Text('Receipt Paper Width',
                style: TextStyle(fontWeight: FontWeight.w600)),
            RadioGroup<String>(
              groupValue: _paperSize,
              onChanged: _onPaperSizeChanged,
              child: const Row(
                children: [
                  Radio<String>(value: '58mm'),
                  Flexible(child: Text('58mm (2-inch)')),
                  SizedBox(width: 8),
                  Radio<String>(value: '80mm'),
                  Flexible(child: Text('80mm (3-inch)')),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _onPaperSizeChanged(String? val) {
    if (val == null) return;
    setState(() => _paperSize = val);
    _persist();
  }

  Widget _activeDeviceCard() {
    return Card(
      color: Colors.green.shade50,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.green.shade300),
      ),
      child: ListTile(
        leading: const Icon(Icons.check_circle, color: _greenHex),
        title: Text(_connectedDeviceName!,
            style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text('Target: ${_connectedDeviceAddress ?? 'Active'}  ·  '
            '$_paperSize'),
        trailing: TextButton(
          onPressed: _isTesting ? null : _testPrintReceipt,
          child: Text(_isTesting ? 'Printing…' : 'Test Print'),
        ),
      ),
    );
  }

  Widget _discoveryForType() {
    switch (_selectedType) {
      case PrinterConnectionType.bluetooth:
        return ElevatedButton.icon(
          style: ElevatedButton.styleFrom(
            backgroundColor: _greenHex,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 12),
          ),
          onPressed: _isScanning ? null : _scanBluetoothDevices,
          icon: _isScanning
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                      strokeWidth: 2, color: Colors.white),
                )
              : const Icon(Icons.bluetooth_searching),
          label: Text(_isScanning
              ? 'Searching nearby printers…'
              : 'Scan Bluetooth Printers'),
        );
      case PrinterConnectionType.usb:
        return ElevatedButton.icon(
          style: ElevatedButton.styleFrom(
            backgroundColor: _greenHex,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 12),
          ),
          onPressed: _detectUsbPrinters,
          icon: const Icon(Icons.cable),
          label: const Text('Detect Connected USB / OTG Printer'),
        );
      case PrinterConnectionType.network:
        return Card(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _ipController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Printer IP Address',
                    hintText: 'e.g. 192.168.1.150',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _portController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Port (default 9100)',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _saveNetworkPrinter,
                        child: const Text('Save'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _greenHex,
                          foregroundColor: Colors.white,
                        ),
                        onPressed: _isTesting
                            ? null
                            : () async {
                                await _saveNetworkPrinter();
                                await _testPrintReceipt();
                              },
                        child: Text(_isTesting ? 'Printing…' : 'Save & Test'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
    }
  }
}
