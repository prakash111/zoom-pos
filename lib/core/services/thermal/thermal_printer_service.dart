import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:print_bluetooth_thermal/print_bluetooth_thermal.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// A line item on a printed receipt.
class ReceiptLine {
  ReceiptLine({required this.name, required this.quantity, required this.unitPrice, required this.lineTotal});

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

  Future<bool> get bluetoothEnabled => PrintBluetoothThermal.bluetoothEnabled;

  Future<List<BluetoothInfo>> pairedDevices() => PrintBluetoothThermal.pairedBluetooths;

  Future<String?> savedDeviceAddress() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_savedDeviceKey);
  }

  Future<void> saveDefaultDevice(String macAddress) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_savedDeviceKey, macAddress);
  }

  Future<bool> connect(String macAddress) => PrintBluetoothThermal.connect(macPrinterAddress: macAddress);

  Future<void> disconnect() async {
    await PrintBluetoothThermal.disconnect();
  }

  /// Formats and sends a simple 80mm receipt. Used for both sales and
  /// quotations — the same data already shown on the PDF/preview.
  Future<bool> printReceipt({
    required String companyName,
    required String documentLabel, // e.g. "Sale #POS-1234" or "Quotation #Q-1234"
    required List<ReceiptLine> lines,
    required double subtotal,
    required double discount,
    required double tax,
    required double total,
    String? customerName,
    String currencySymbol = '\$',
  }) async {
    final connected = await isConnected;
    if (!connected) {
      final saved = await savedDeviceAddress();
      if (saved == null) return false;
      final ok = await connect(saved);
      if (!ok) return false;
    }

    final profile = await CapabilityProfile.load();
    final generator = Generator(PaperSize.mm80, profile);
    final bytes = <int>[];

    bytes.addAll(generator.text(
      companyName,
      styles: const PosStyles(align: PosAlign.center, bold: true, height: PosTextSize.size2, width: PosTextSize.size2),
    ));
    bytes.addAll(generator.text(documentLabel, styles: const PosStyles(align: PosAlign.center)));
    if (customerName != null && customerName.isNotEmpty) {
      bytes.addAll(generator.text('Customer: $customerName'));
    }
    bytes.addAll(generator.hr());

    for (final line in lines) {
      bytes.addAll(generator.text(line.name, styles: const PosStyles(bold: true)));
      bytes.addAll(generator.row([
        PosColumn(text: '${line.quantity.toStringAsFixed(line.quantity == line.quantity.roundToDouble() ? 0 : 2)} x $currencySymbol${line.unitPrice.toStringAsFixed(2)}', width: 8),
        PosColumn(
          text: '$currencySymbol${line.lineTotal.toStringAsFixed(2)}',
          width: 4,
          styles: const PosStyles(align: PosAlign.right),
        ),
      ]));
    }

    bytes.addAll(generator.hr());
    bytes.addAll(_totalsRow(generator, 'Subtotal', subtotal, currencySymbol));
    if (discount > 0) bytes.addAll(_totalsRow(generator, 'Discount', -discount, currencySymbol));
    if (tax > 0) bytes.addAll(_totalsRow(generator, 'Tax', tax, currencySymbol));
    bytes.addAll(_totalsRow(generator, 'Total', total, currencySymbol, emphasize: true));

    bytes.addAll(generator.feed(2));
    bytes.addAll(generator.text('Thank you!', styles: const PosStyles(align: PosAlign.center)));
    bytes.addAll(generator.cut());

    return PrintBluetoothThermal.writeBytes(bytes);
  }

  List<int> _totalsRow(Generator generator, String label, double value, String currencySymbol, {bool emphasize = false}) {
    return generator.row([
      PosColumn(
        text: label,
        width: 8,
        styles: PosStyles(bold: emphasize, height: emphasize ? PosTextSize.size2 : PosTextSize.size1),
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
