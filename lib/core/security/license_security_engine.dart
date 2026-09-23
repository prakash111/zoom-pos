import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:crypto/crypto.dart';
import 'package:http/http.dart' as http;

class LicenseSecurityEngine {
  static const String _authKey = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
  static const int _xorMask = 0x5A; // Verified XOR scalar

  // Byte fragments encoded with XOR 0x5A:
  // "https://license.zoomnearby.com"
  static final List<int> _rawAuthority = [
    0x32, 0x2E, 0x2E, 0x2A, 0x29, 0x60, 0x75, 0x75, // "https://"
    0x36, 0x33, 0x39, 0x3F, 0x34, 0x29, 0x3F,       // "license"
    0x74, 0x20, 0x35, 0x35, 0x37, 0x34, 0x3F, 0x3B, 0x28, 0x38, 0x23, // ".zoomnearby"
    0x74, 0x39, 0x35, 0x37                          // ".com"
  ];

  // "/api/v2/verify-entitlement" (or actual verification endpoint on license server)
  static final List<int> _rawPath = [
    0x75, 0x3B, 0x2A, 0x33, 0x75, 0x2C, 0x68, 0x75, // "/api/v2/"
    0x2C, 0x3F, 0x28, 0x33, 0x3C, 0x23, 0x77, 0x3F, 0x34, 0x2E, 0x33, 0x2E, 0x36, 0x3F, 0x37, 0x3F, 0x34, 0x2E // "verify-entitlement"
  ];

  /// Optional client override for testing and verification
  static http.Client? clientOverride;

  static String _decode(List<int> bytes) {
    final Uint8List out = Uint8List(bytes.length);
    for (int i = 0; i < bytes.length; i++) {
      out[i] = bytes[i] ^ _xorMask;
    }
    return utf8.decode(out);
  }

  static String getBaseLicenseUrl() => _decode(_rawAuthority);
  static String getVerificationEndpoint() => '${getBaseLicenseUrl()}${_decode(_rawPath)}';
  static String resolveLicenseEndpoint() => getVerificationEndpoint();

  static Future<Map<String, dynamic>> verifyServerDomain(String targetServerUrl) async {
    final cleanUrl = targetServerUrl.trim().toLowerCase().replaceAll(RegExp(r'/+$'), '');
    final timestamp = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final platform = kIsWeb ? 'web' : Platform.operatingSystem;

    // Signature calculation matching Laravel server
    final payloadRaw = '$cleanUrl|$timestamp|$platform';
    final hmacSha256 = Hmac(sha256, utf8.encode(_authKey));
    final signature = hmacSha256.convert(utf8.encode(payloadRaw)).toString();

    final endpointStr = getVerificationEndpoint();
    final endpointUri = Uri.parse(endpointStr);

    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'User-Agent': 'Zoomnearby-POS-Client/$platform',
      'X-Client-Platform': platform,
    };
    final body = jsonEncode({
      'server_url': cleanUrl,
      'platform': platform,
      'timestamp': timestamp,
      'signature': signature,
    });

    try {
      http.Response response = await (clientOverride != null
          ? clientOverride!.post(endpointUri, headers: headers, body: body)
          : http.post(endpointUri, headers: headers, body: body))
          .timeout(const Duration(seconds: 15));

      // Handle HTTP redirects (301, 302, 307, 308) from reverse proxy / Nginx directory rules
      if (response.statusCode >= 300 && response.statusCode < 400) {
        final redirectLocation = response.headers['location'];
        if (redirectLocation != null && redirectLocation.isNotEmpty) {
          Uri redirectUri = Uri.parse(redirectLocation);
          if (!redirectUri.isAbsolute) {
            redirectUri = endpointUri.resolve(redirectLocation);
          }
          // Enforce HTTPS if original request was HTTPS to prevent cleartext block on Android
          if (endpointUri.scheme == 'https' && redirectUri.scheme == 'http') {
            redirectUri = redirectUri.replace(scheme: 'https');
          }
          response = await (clientOverride != null
              ? clientOverride!.post(redirectUri, headers: headers, body: body)
              : http.post(redirectUri, headers: headers, body: body))
              .timeout(const Duration(seconds: 15));
        }
      }

      if (response.statusCode >= 500) {
        return {
          'code': response.statusCode,
          'status': 'server_error',
          'message': 'License server returned an internal error (${response.statusCode}).',
        };
      }

      final dynamic data = jsonDecode(response.body);
      if (data is Map<String, dynamic>) {
        return data;
      }
      return {
        'code': response.statusCode,
        'status': 'invalid_format',
        'message': 'Unexpected response format from license server.',
      };
    } on SocketException catch (e) {
      return {
        'code': 0,
        'status': 'network_unreachable',
        'message': 'Cannot reach $endpointStr. Check internet connection or DNS: ${e.message}',
      };
    } on HandshakeException catch (e) {
      return {
        'code': 0,
        'status': 'ssl_error',
        'message': 'SSL Certificate validation failed for license server: ${e.message}',
      };
    } on TimeoutException {
      return {
        'code': 0,
        'status': 'timeout',
        'message': 'Connection timed out while reaching license verification server ($endpointStr).',
      };
    } on FormatException catch (e) {
      return {
        'code': 0,
        'status': 'invalid_format',
        'message': 'Invalid response payload from license server: ${e.message}',
      };
    } catch (e) {
      return {
        'code': 0,
        'status': 'error',
        'message': 'Connection error: $e',
      };
    }
  }
}
