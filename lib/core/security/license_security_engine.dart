import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:crypto/crypto.dart';
import 'package:http/http.dart' as http;

class LicenseSecurityEngine {
  // Static salt matching server HMAC key
  static const String _authKey = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
  static const int _xorMask = 0x6E; // Bitwise masking scalar

  // Split, masked byte fragments for dynamic endpoint reconstitution
  static final List<int> _segProto = [0x06, 0x1A, 0x1A, 0x1E, 0x1D, 0x54, 0x41, 0x41];
  static final List<int> _segSub   = [0x02, 0x07, 0x0D, 0x0B, 0x00, 0x1D, 0x0B];
  static final List<int> _segHost  = [0x40, 0x14, 0x01, 0x01, 0x03, 0x00, 0x0B, 0x0F, 0x1C, 0x0C, 0x17];
  static final List<int> _segTld   = [0x40, 0x0D, 0x01, 0x03];
  static final List<int> _segPath  = [
    0x41, 0x0F, 0x1E, 0x07, 0x41, 0x18, 0x5C, 0x41, 0x18, 0x0B, 0x1C, 0x07,
    0x08, 0x17, 0x43, 0x0B, 0x00, 0x1A, 0x07, 0x1A, 0x02, 0x0B, 0x03, 0x0B,
    0x00, 0x1A
  ];

  /// Optional client override for testing and verification
  static http.Client? clientOverride;

  static String _unmask(List<int> bytes) {
    final Uint8List out = Uint8List(bytes.length);
    for (int i = 0; i < bytes.length; i++) {
      out[i] = bytes[i] ^ _xorMask;
    }
    return utf8.decode(out);
  }

  /// Dynamically reconstitutes verification endpoint at runtime
  static String resolveLicenseEndpoint() {
    return _unmask(_segProto) +
        _unmask(_segSub) +
        _unmask(_segHost) +
        _unmask(_segTld) +
        _unmask(_segPath);
  }

  /// Dispatches signed verification payload
  static Future<Map<String, dynamic>> verifyServerDomain(String targetServerUrl) async {
    final cleanUrl = targetServerUrl.trim().toLowerCase().replaceAll(RegExp(r'/+$'), '');
    final timestamp = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final platform = kIsWeb ? 'web' : Platform.operatingSystem;

    // Generate SHA-256 HMAC signature
    final payloadRaw = '$cleanUrl|$timestamp|$platform';
    final hmacSha256 = Hmac(sha256, utf8.encode(_authKey));
    final signature = hmacSha256.convert(utf8.encode(payloadRaw)).toString();

    final endpoint = Uri.parse(resolveLicenseEndpoint());

    final headers = {
      'Content-Type': 'application/json',
      'X-Client-Platform': platform,
    };
    final body = jsonEncode({
      'server_url': cleanUrl,
      'platform': platform,
      'timestamp': timestamp,
      'signature': signature,
    });

    final http.Response response;
    if (clientOverride != null) {
      response = await clientOverride!.post(
        endpoint,
        headers: headers,
        body: body,
      ).timeout(const Duration(seconds: 12));
    } else {
      response = await http.post(
        endpoint,
        headers: headers,
        body: body,
      ).timeout(const Duration(seconds: 12));
    }

    return jsonDecode(response.body) as Map<String, dynamic>;
  }
}
