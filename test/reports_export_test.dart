import 'dart:convert';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:zoom_pos_mobile/core/utils/file_download/file_download.dart';

class _FakeFilePickerPlatform extends Fake
    with MockPlatformInterfaceMixin
    implements FilePicker {
  @override
  Future<String?> saveFile({
    String? dialogTitle,
    String? fileName,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Uint8List? bytes,
    bool lockParentWindow = false,
  }) async {
    return '/tmp/$fileName';
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FilePicker.platform = _FakeFilePickerPlatform();
  });

  group('FileDownloadHelper', () {
    test('saveOrDownloadFile handles byte encoding without UnimplementedError', () async {
      const csvData = 'Date,Sales,Tax\n2026-09-01,100.00,10.00\n';
      final bytes = Uint8List.fromList(utf8.encode(csvData));

      expect(bytes.isNotEmpty, isTrue);
      final result = await FileDownloadHelper.saveOrDownloadFile(
        fileName: 'test_report.csv',
        bytes: bytes,
        mimeType: 'text/csv',
      );
      expect(result, isTrue);
    });
  });
}
