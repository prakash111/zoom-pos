class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.details});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? details;

  bool get isUnauthenticated => statusCode == 401;

  @override
  String toString() => message;
}
