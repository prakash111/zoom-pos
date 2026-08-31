import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Generic full-screen WebView with a "result" trap: any navigation whose
/// URL contains [resultMarker] is intercepted *before* it loads (the page
/// behind it may not even exist for this client — e.g. a web-only redirect
/// route) and the screen pops with that URL's query parameters. Used for
/// hosted-checkout redirect flows (Mercado Pago) where the gateway redirects
/// back to a URL carrying the payment result as query params.
class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key, required this.url, required this.resultMarker, this.title});

  final String url;
  final String resultMarker;
  final String? title;

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  bool _handled = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            if (!_handled && request.url.contains(widget.resultMarker)) {
              _handled = true;
              final uri = Uri.tryParse(request.url);
              Navigator.of(context).pop(uri?.queryParameters ?? const <String, String>{});
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title ?? 'Checkout'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
