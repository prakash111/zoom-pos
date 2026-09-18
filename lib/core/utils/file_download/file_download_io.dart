import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

Future<bool> downloadFileImpl({
  required String fileName,
  required Uint8List bytes,
  String? mimeType,
}) async {
  final isDesktop = Platform.isWindows || Platform.isLinux || Platform.isMacOS;
  if (isDesktop) {
    final extension = fileName.contains('.') ? fileName.split('.').last : null;
    final savedPath = await FilePicker.platform.saveFile(
      dialogTitle: 'Save report',
      fileName: fileName,
      type: extension != null ? FileType.custom : FileType.any,
      allowedExtensions: extension != null ? [extension] : null,
    );
    if (savedPath == null) return false;
    await File(savedPath).writeAsBytes(bytes);
    return true;
  } else {
    final savedPath = await FilePicker.platform.saveFile(
      dialogTitle: 'Save report',
      fileName: fileName,
      bytes: bytes,
    );
    return savedPath != null;
  }
}
