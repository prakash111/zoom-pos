class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.details, this.responseData});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? details;
  final Map<String, dynamic>? responseData;

  bool get isUnauthenticated => statusCode == 401;

  @override
  String toString() => message;
}
