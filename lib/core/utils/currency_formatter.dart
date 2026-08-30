import 'package:intl/intl.dart';

class CurrencyFormatter {
  CurrencyFormatter(this.symbol) : _format = NumberFormat.currency(symbol: symbol, decimalDigits: 2);

  final String symbol;
  final NumberFormat _format;

  String format(num amount) => _format.format(amount);
}
