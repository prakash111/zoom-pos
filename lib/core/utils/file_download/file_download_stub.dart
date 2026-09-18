import 'dart:typed_data';

Future<bool> downloadFileImpl({
  required String fileName,
  required Uint8List bytes,
  String? mimeType,
}) async {
  throw UnsupportedError('File saving is not supported on this platform.');
}
