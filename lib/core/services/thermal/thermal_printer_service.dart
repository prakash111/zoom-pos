import 'dart:io';

import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// A line item on a printed receipt.
class ReceiptLine {
  ReceiptLine(
      {required this.name,
      required this.quantity,
      required this.unitPrice,
      required this.lineTotal});

  final String name;
  final double quantity;
  final double unitPrice;
  final double lineTotal;
}

/// Bluetooth ESC/POS thermal-receipt printing (Android/iOS only). This is
/// only ever imported from mobile-specific screens (printer settings, the
/// "Print" action on the invoice/quotation actions sheet when running on
/// Android/iOS) — web and Windows always use the `printing` package's system
/// print dialog against the server-generated PDF instead, so nothing here
/// needs a desktop/web fallback of its own.
class ThermalPrinterService {
  static const _savedDeviceKey = 'zoom_pos.thermal_printer_mac';

  // Written by GlobalPrinterSetupScreen. Kept as plain strings so the value is
  // portable across the (few) places that read it without importing esc_pos.
  static const paperSizePrefKey = 'printer_paper_size';
  static const connectionTypePrefKey = 'printer_type';
  static const networkIpPrefKey = 'printer_ip';
  static const networkPortPrefKey = 'printer_port';

  /// Requests Android's Nearby devices permission once, after the first app
  /// frame. The print plugin owns the platform request and returns immediately
  /// on iOS, Windows, and already-authorized devices.
  static Future<bool> requestBluetoothPermission() async {
    if (kIsWeb || !Platform.isAndroid) return true;
    try {
      return await PrintBluetoothThermal.isPermissionBluetoothGranted
          .timeout(const Duration(seconds: 8), onTimeout: () => false);
    } catch (_) {
      return false;
    }
  }

  /// Receipt paper width the operator picked in Printer & Hardware Setup.
  /// Defaults to 80mm (the previous hard-coded value) when unset.
  Future<PaperSize> savedPaperSize() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = (prefs.getString(paperSizePrefKey) ?? '80mm').toLowerCase();
      return raw.contains('58') ? PaperSize.mm58 : PaperSize.mm80;
    } catch (_) {
      return PaperSize.mm80;
    }
  }

  Future<bool> get bluetoothEnabled async {
    if (kIsWeb || !Platform.isAndroid) return false;
    try {
      return await PrintBluetoothThermal.bluetoothEnabled.timeout(
        const Duration(seconds: 3),
        onTimeout: () => false,
      );
    } catch (_) {
      return false;
    }
  }

  Future<List<BluetoothInfo>> pairedDevices() async {
    if (kIsWeb || !Platform.isAndroid) return <BluetoothInfo>[];
    try {
      return await PrintBluetoothThermal.pairedBluetooths.timeout(
        const Duration(seconds: 4),
        onTimeout: () => <BluetoothInfo>[],
      );
    } catch (_) {
      return <BluetoothInfo>[];
    }
  }

  Future<String?> savedDeviceAddress() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_savedDeviceKey);
    } catch (_) {
      return null;
    }
  }

  Future<void> saveDefaultDevice(String macAddress) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_savedDeviceKey, macAddress);
    } catch (_) {}
  }

  Future<bool> connect(String macAddress) async {
    if (kIsWeb || !Platform.isAndroid) return false;
    try {
      return await PrintBluetoothThermal.connect(macPrinterAddress: macAddress)
          .timeout(
        const Duration(seconds: 6),
        onTimeout: () => false,
      );
    } catch (_) {
      return false;
    }
  }

  Future<bool> get isConnected async {
    if (kIsWeb || !Platform.isAndroid) return false;
    try {
      return await PrintBluetoothThermal.connectionStatus.timeout(
        const Duration(seconds: 3),
        onTimeout: () => false,
      );
    } catch (_) {
      return false;
    }
  }

  Future<bool> disconnect() async {
    try {
      return await PrintBluetoothThermal.disconnect.timeout(
        const Duration(seconds: 3),
        onTimeout: () => false,
      );
    } catch (_) {
      return false;
    }
  }

  /// Formats and sends a simple 80mm receipt. Used for both sales and
  /// quotations — the same data already shown on the PDF/preview.
  Future<bool> printReceipt({
    required String companyName,
    required String
        documentLabel, // e.g. "Sale #POS-1234" or "Quotation #Q-1234"
    required List<ReceiptLine> lines,
    required double subtotal,
    required double discount,
    required double tax,
    required double total,
    String? customerName,
    String currencySymbol = '\$',
    String? taxId,
    String taxLabel = 'Tax',
    bool isIndia = false,
    double taxRate = 0,
    double? paidAmount,
    double dueAmount = 0,
    double? cashTendered,
    double changeDue = 0,
  }) async {
    final connected = await isConnected;
    if (!connected) {
      final saved = await savedDeviceAddress();
      if (saved == null) return false;
      final ok = await connect(saved);
      if (!ok) return false;
    }

    final profile = await CapabilityProfile.load();
    final generator = Generator(await savedPaperSize(), profile);
    final bytes = <int>[];

    bytes.addAll(generator.text(
      companyName,
      styles: const PosStyles(
          align: PosAlign.center,
          bold: true,
          height: PosTextSize.size2,
          width: PosTextSize.size2),
    ));
    if (taxId != null && taxId.isNotEmpty) {
      bytes.addAll(generator.text('${isIndia ? 'GSTIN' : 'Tax ID'}: $taxId',
          styles: const PosStyles(align: PosAlign.center)));
    }
    bytes.addAll(generator.text(documentLabel,
        styles: const PosStyles(align: PosAlign.center)));
    if (customerName != null && customerName.isNotEmpty) {
      bytes.addAll(generator.text('Customer: $customerName'));
    }
    bytes.addAll(generator.hr());

    for (final line in lines) {
      bytes.addAll(
          generator.text(line.name, styles: const PosStyles(bold: true)));
      bytes.addAll(generator.row([
        PosColumn(
            text:
                '${line.quantity.toStringAsFixed(line.quantity == line.quantity.roundToDouble() ? 0 : 2)} x $currencySymbol${line.unitPrice.toStringAsFixed(2)}',
            width: 8),
        PosColumn(
          text: '$currencySymbol${line.lineTotal.toStringAsFixed(2)}',
          width: 4,
          styles: const PosStyles(align: PosAlign.right),
        ),
      ]));
    }

    bytes.addAll(generator.hr());
    bytes.addAll(_totalsRow(generator, 'Subtotal', subtotal, currencySymbol));
    if (discount > 0)
      bytes
          .addAll(_totalsRow(generator, 'Discount', -discount, currencySymbol));
    if (tax > 0) {
      if (isIndia && taxRate > 0) {
        final halfTax = tax / 2;
        final halfRate = taxRate / 2;
        bytes.addAll(_totalsRow(generator,
            'CGST (${halfRate.toStringAsFixed(1)}%)', halfTax, currencySymbol));
        bytes.addAll(_totalsRow(generator,
            'SGST (${halfRate.toStringAsFixed(1)}%)', halfTax, currencySymbol));
      } else {
        bytes.addAll(_totalsRow(generator, taxLabel, tax, currencySymbol));
      }
    }
    bytes.addAll(
        _totalsRow(generator, 'Total', total, currencySymbol, emphasize: true));

    if (dueAmount > 0.001) {
      bytes.addAll(generator.hr());
      bytes.addAll(_totalsRow(
          generator, 'Amount Paid', paidAmount ?? total, currencySymbol));
      bytes.addAll(_totalsRow(
          generator, 'Due Balance', dueAmount, currencySymbol,
          emphasize: true));
    } else if (cashTendered != null) {
      bytes.addAll(generator.hr());
      bytes.addAll(
          _totalsRow(generator, 'Cash Tendered', cashTendered, currencySymbol));
      bytes.addAll(
          _totalsRow(generator, 'Change Due', changeDue, currencySymbol));
    }

    bytes.addAll(generator.feed(2));
    bytes.addAll(generator.text('Thank you!',
        styles: const PosStyles(align: PosAlign.center)));
    bytes.addAll(generator.cut());

    try {
      return await PrintBluetoothThermal.writeBytes(bytes).timeout(
        const Duration(seconds: 6),
        onTimeout: () => false,
      );
    } catch (_) {
      return false;
    }
  }

  /// Prints a compact text token (repair drop-off slip, pickup tag, ...) —
  /// a centered title followed by plain body lines, no totals. Connects to the
  /// saved printer if not already connected.
  Future<bool> printToken({
    required String title,
    required List<String> lines,
    String? heading,
  }) async {
    final connected = await isConnected;
    if (!connected) {
      final saved = await savedDeviceAddress();
      if (saved == null) return false;
      if (!await connect(saved)) return false;
    }

    final profile = await CapabilityProfile.load();
    final generator = Generator(await savedPaperSize(), profile);
    final bytes = <int>[];

    if (heading != null && heading.isNotEmpty) {
      bytes.addAll(generator.text(heading,
          styles: const PosStyles(align: PosAlign.center, bold: true)));
    }
    bytes.addAll(generator.text(title,
        styles: const PosStyles(
            align: PosAlign.center,
            bold: true,
            height: PosTextSize.size2,
            width: PosTextSize.size2)));
    bytes.addAll(generator.hr());
    for (final line in lines) {
      if (line.trim().isEmpty) {
        bytes.addAll(generator.feed(1));
      } else {
        bytes.addAll(generator.text(line));
      }
    }
    bytes.addAll(generator.feed(2));
    bytes.addAll(generator.cut());

    try {
      return await PrintBluetoothThermal.writeBytes(bytes)
          .timeout(const Duration(seconds: 6), onTimeout: () => false);
    } catch (_) {
      return false;
    }
  }

  /// Builds a short, human-readable "printer works" ticket at the configured
  /// paper width. Shared by every test-print button.
  Future<List<int>> buildTestTicketBytes({String? subtitle}) async {
    final profile = await CapabilityProfile.load();
    final size = await savedPaperSize();
    final generator = Generator(size, profile);
    final bytes = <int>[
      ...generator.text('ZoomNearby POS',
          styles: const PosStyles(
              align: PosAlign.center,
              bold: true,
              height: PosTextSize.size2,
              width: PosTextSize.size2)),
      ...generator.text('Printer Test',
          styles: const PosStyles(align: PosAlign.center, bold: true)),
      ...generator.hr(),
      ...generator.text(
          'Paper: ${size == PaperSize.mm58 ? '58mm (2 inch)' : '80mm (3 inch)'}'),
      if (subtitle != null && subtitle.isNotEmpty) ...generator.text(subtitle),
      ...generator.text('Time : ${DateTime.now().toIso8601String()}'),
      ...generator.text('Alignment check |....|....|....|....|'),
      ...generator.feed(2),
      ...generator.text('If you can read this, printing works.',
          styles: const PosStyles(align: PosAlign.center)),
      ...generator.feed(2),
      ...generator.cut(),
    ];
    return bytes;
  }

  /// Test print over the currently saved / connected Bluetooth printer.
  Future<bool> testBluetoothPrint() async {
    final connected = await isConnected;
    if (!connected) {
      final saved = await savedDeviceAddress();
      if (saved == null) return false;
      if (!await connect(saved)) return false;
    }
    try {
      final bytes = await buildTestTicketBytes(subtitle: 'Link : Bluetooth');
      return await PrintBluetoothThermal.writeBytes(bytes)
          .timeout(const Duration(seconds: 6), onTimeout: () => false);
    } catch (_) {
      return false;
    }
  }

  /// Opens a raw TCP socket to a network / WiFi ESC/POS printer (JetDirect,
  /// port 9100 by default), writes a test ticket and closes. No plugin needed.
  Future<bool> testNetworkPrint(String host, int port) async {
    if (host.trim().isEmpty) return false;
    Socket? socket;
    try {
      socket = await Socket.connect(host.trim(), port,
          timeout: const Duration(seconds: 5));
      final bytes = await buildTestTicketBytes(subtitle: 'Link : $host:$port');
      socket.add(bytes);
      await socket.flush();
      await socket.close();
      return true;
    } catch (_) {
      return false;
    } finally {
      socket?.destroy();
    }
  }

  List<int> _totalsRow(
      Generator generator, String label, double value, String currencySymbol,
      {bool emphasize = false}) {
    return generator.row([
      PosColumn(
        text: label,
        width: 8,
        styles: PosStyles(
            bold: emphasize,
            height: emphasize ? PosTextSize.size2 : PosTextSize.size1),
      ),
      PosColumn(
        text: '$currencySymbol${value.toStringAsFixed(2)}',
        width: 4,
        styles: PosStyles(
          bold: emphasize,
          height: emphasize ? PosTextSize.size2 : PosTextSize.size1,
          align: PosAlign.right,
        ),
      ),
    ]);
  }
}
