import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../features/pos/screens/invoice_actions_sheet.dart';
import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../config/bootstrap_cache.dart';
import '../services/thermal/thermal_printer_service.dart' show ReceiptLine;
import 'dynamic_schema_context.dart';
import 'dynamic_schema_parser.dart';
import 'screens/dynamic_schema_page.dart';

typedef SduiRequestExecutor = Future<Map<String, dynamic>> Function(
  String endpoint, {
  required String method,
  Map<String, dynamic>? data,
});

/// Reusable action-dispatch engine for the SDUI action vocabulary
/// (navigate / form_submit / api_post / open_url / open_modal /
/// open_remote_sheet / pop / navigate_back).
///
/// Extracted from `DynamicSchemaPage`'s `_dispatchAction` so other native
/// screens (e.g. a universal POS catalog screen) can reuse the exact same
/// handling for actions embedded in server-fetched component trees,
/// without duplicating this dispatch logic. `DynamicSchemaPage` itself now
/// just delegates to an instance of this class — its behavior is
/// unchanged.
class SduiActionDispatcher {
  SduiActionDispatcher({
    required this.resolveApiClient,
    this.requestExecutor,
    required this.formKey,
    required this.formValues,
    required this.setFormValue,
    required this.onReload,
    required this.showToast,
    this.onBeforeDispatch,
  });

  final ApiClient? Function() resolveApiClient;
  final SduiRequestExecutor? requestExecutor;
  final GlobalKey<FormState> formKey;
  final Map<String, dynamic> formValues;
  final void Function(String key, dynamic value) setFormValue;
  final VoidCallback onReload;
  final void Function(String message, {bool isError}) showToast;

  /// Checked first on every dispatch (including actions nested inside a
  /// modal/sheet that re-enter [dispatch]). Return true to indicate the
  /// action was fully handled locally — dispatch stops there and never
  /// reaches the switch below. Used by a POS-style screen to intercept
  /// `add_to_cart` client-side without touching the network.
  final Future<bool> Function(
    BuildContext context,
    Map<String, dynamic> action,
  )? onBeforeDispatch;

  Future<Map<String, dynamic>> _request(
    String endpoint, {
    required String method,
    Map<String, dynamic>? data,
  }) {
    final executor = requestExecutor;
    if (executor != null) {
      return executor(endpoint, method: method, data: data);
    }

    final client = resolveApiClient();
    if (client == null) {
      throw ApiException('API client is unavailable.');
    }

    return client.requestAbsolute(endpoint, method: method, data: data);
  }

  Future<void> dispatch(BuildContext context, Map<String, dynamic> action) async {
    if (onBeforeDispatch != null && await onBeforeDispatch!(context, action)) {
      return;
    }

    final type = action['type']?.toString().toLowerCase().trim();
    final client = resolveApiClient();

    switch (type) {
      case 'navigate':
        final endpoint = action['endpoint']?.toString() ??
            action['target_endpoint']?.toString();
        final title = action['title']?.toString();

        if (endpoint != null && endpoint.isNotEmpty) {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => DynamicSchemaPage(
                endpoint: endpoint,
                initialTitle: title,
                apiClient: client,
                requestExecutor: requestExecutor,
              ),
            ),
          );
        } else {
          showToast('Navigation action is missing an endpoint.', isError: true);
        }
        break;

      case 'form_submit':
        final endpoint = action['endpoint']?.toString() ?? '';
        final method = action['method']?.toString() ?? 'POST';
        final successToast =
            action['success_toast']?.toString() ?? 'Saved successfully';
        final navigateBack = action['navigate_back'] == true;

        if (!(formKey.currentState?.validate() ?? true)) {
          showToast('Please correct the highlighted fields.', isError: true);
          return;
        }

        if (endpoint.isEmpty || (client == null && requestExecutor == null)) {
          showToast('Unable to submit this form: API client is unavailable.',
              isError: true);
          return;
        }

        try {
          final res = await _request(endpoint, method: method, data: formValues);
          final message = res['message']?.toString() ?? successToast;
          final rawTheme = res['theme'];
          if (rawTheme is Map) {
            await BootstrapCache.instance
                .applyThemeJson(Map<String, dynamic>.from(rawTheme));
          }
          final formUrlToOpen = res['url']?.toString() ??
              res['print_url']?.toString() ??
              res['whatsapp_url']?.toString();
          if (formUrlToOpen != null && formUrlToOpen.isNotEmpty) {
            final uri = Uri.tryParse(formUrlToOpen);
            if (uri != null) {
              try {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              } catch (_) {}
            }
          }
          showToast(message);
          if (action['reload'] == true) {
            onReload();
          }
          if (context.mounted && _isPostSaleSheetResponse(res)) {
            await _showPostSaleSheet(context, res['post_sale_sheet']['data']);
          } else if (navigateBack && context.mounted) {
            Navigator.of(context).pop();
          }
        } catch (e) {
          showToast(e is ApiException ? e.message : 'Submission failed: $e',
              isError: true);
        }
        break;

      case 'api_post':
        final endpoint = action['endpoint']?.toString() ?? '';
        final payload = (action['payload'] as Map<String, dynamic>?) ?? {};
        final successToast = action['success_toast']?.toString() ?? 'Completed';

        if ((client == null && requestExecutor == null) || endpoint.isEmpty) {
          showToast('Unable to perform this action: API client is unavailable.',
              isError: true);
          return;
        }

        try {
          final res = await _request(endpoint, method: 'POST', data: payload);
          showToast(res['message']?.toString() ?? successToast);
          final urlToOpen = res['url']?.toString() ??
              res['print_url']?.toString() ??
              res['whatsapp_url']?.toString();
          if (urlToOpen != null && urlToOpen.isNotEmpty) {
            final uri = Uri.tryParse(urlToOpen);
            if (uri != null) {
              try {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              } catch (_) {}
            }
          }
          if (action['reload'] == true) {
            onReload();
          }
          if (context.mounted && _isPostSaleSheetResponse(res)) {
            await _showPostSaleSheet(context, res['post_sale_sheet']['data']);
          }
        } catch (e) {
          showToast(e is ApiException ? e.message : 'Action failed: $e',
              isError: true);
        }
        break;

      case 'open_url':
        final rawUrl = action['url']?.toString() ?? '';
        if (rawUrl.isNotEmpty) {
          final uri = Uri.tryParse(rawUrl);
          if (uri != null) {
            try {
              await launchUrl(uri, mode: LaunchMode.externalApplication);
            } catch (e) {
              showToast('Failed to open link: $e', isError: true);
            }
          }
        }
        break;

      case 'show_post_sale_sheet':
        await _showPostSaleSheet(context, action['data']);
        break;

      case 'open_modal':
        _showComponentSheet(
          context,
          title: action['title']?.toString() ?? '',
          components: action['components'] as List<dynamic>? ?? const [],
          client: client,
        );
        break;

      case 'open_remote_sheet':
        await _openRemoteSheet(context, action, client);
        break;

      case 'pop':
      case 'navigate_back':
        Navigator.of(context).pop();
        break;

      default:
        break;
    }
  }

  /// True when a form_submit / api_post response carries the native
  /// `show_post_sale_sheet` envelope every checkout / settle endpoint now
  /// returns under `post_sale_sheet` — instead of a web receipt URL the
  /// client would launch in an external browser.
  static bool _isPostSaleSheetResponse(Map<String, dynamic> res) {
    final sheet = res['post_sale_sheet'];
    return sheet is Map &&
        sheet['action']?.toString() == 'show_post_sale_sheet' &&
        sheet['data'] is Map;
  }

  /// Opens the polished native invoice actions sheet (Preview & Print /
  /// Bluetooth thermal / Share via WhatsApp / Send via Email) from a
  /// server `show_post_sale_sheet` data payload. Every option resolves
  /// in-app — "Preview & Print" streams the token-authed PDF, WhatsApp is a
  /// deep link, email/SMS post to the send-invoice endpoint.
  Future<void> _showPostSaleSheet(BuildContext context, dynamic rawData) async {
    final data = rawData is Map
        ? Map<String, dynamic>.from(rawData)
        : <String, dynamic>{};

    double toDouble(dynamic v) {
      if (v is num) return v.toDouble();
      return double.tryParse('${v ?? ''}') ?? 0.0;
    }

    String? nonEmpty(dynamic v) {
      final s = (v ?? '').toString().trim();
      return s.isEmpty ? null : s;
    }

    final lines = <ReceiptLine>[];
    final rawLines = data['lines'];
    if (rawLines is List) {
      for (final l in rawLines) {
        if (l is Map) {
          final qty = l['quantity'] == null ? 1.0 : toDouble(l['quantity']);
          lines.add(ReceiptLine(
            name: (l['name'] ?? 'Item').toString(),
            quantity: qty,
            unitPrice: toDouble(l['unit_price']),
            lineTotal: l['line_total'] == null
                ? qty * toDouble(l['unit_price'])
                : toDouble(l['line_total']),
          ));
        }
      }
    }

    final invoiceData = InvoiceActionsData(
      documentType: 'invoice',
      documentId: (data['sale_id'] ?? '').toString(),
      documentNumber: (data['invoice_number'] ?? '').toString(),
      companyName: (data['company_name'] ?? '').toString(),
      customerName: nonEmpty(data['customer_name']),
      customerPhone: nonEmpty(data['customer_phone']),
      customerEmail: nonEmpty(data['customer_email']),
      currencySymbol: (data['currency_symbol'] ?? '\$').toString(),
      subtotal: toDouble(data['subtotal']),
      discount: toDouble(data['discount']),
      tax: toDouble(data['tax']),
      total: toDouble(data['total']),
      taxId: nonEmpty(data['tax_id']),
      taxLabel: (data['tax_label'] ?? 'Tax').toString(),
      isIndia: data['is_india'] == true,
      taxRate: toDouble(data['tax_rate']),
      paidAmount:
          data['paid_amount'] == null ? null : toDouble(data['paid_amount']),
      dueAmount: toDouble(data['due_amount']),
      lines: lines,
      pdfPathOverride: nonEmpty(data['pdf_endpoint']),
    );

    if (!context.mounted) return;
    await showInvoiceActionsSheet(context, invoiceData);
  }

  Future<void> _openRemoteSheet(
    BuildContext context,
    Map<String, dynamic> action,
    ApiClient? client,
  ) async {
    final sheetEndpoint = action['sheet_endpoint']?.toString() ?? '';
    if (sheetEndpoint.isEmpty) {
      showToast('This action is missing a sheet endpoint.', isError: true);
      return;
    }

    Map<String, dynamic> sheetSchema;
    try {
      final res = await _request(sheetEndpoint, method: 'GET');
      final raw = res['schema'] ?? res;
      if (raw is Map<String, dynamic>) {
        sheetSchema = raw;
      } else if (raw is Map) {
        sheetSchema = Map<String, dynamic>.from(raw);
      } else {
        throw const FormatException('The server did not return a sheet schema.');
      }
    } catch (e) {
      showToast(e is ApiException ? e.message : 'Failed to load sheet: $e',
          isError: true);
      return;
    }

    if (!context.mounted) return;

    _showComponentSheet(
      context,
      title: sheetSchema['title']?.toString() ?? action['title']?.toString() ?? '',
      components: sheetSchema['components'] as List<dynamic>? ?? const [],
      client: client,
    );
  }

  void _showComponentSheet(
    BuildContext context, {
    required String title,
    required List<dynamic> components,
    required ApiClient? client,
  }) {
    final sheetFormKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (modalCtx) => DynamicSchemaContext(
        formValues: formValues,
        setFormValue: setFormValue,
        dispatchAction: (modalAction) async {
          if (modalAction['type']?.toString() == 'form_submit' &&
              !(sheetFormKey.currentState?.validate() ?? true)) {
            showToast('Please correct the highlighted fields.', isError: true);
            return;
          }
          if (Navigator.of(modalCtx).canPop()) {
            Navigator.of(modalCtx).pop();
          }
          await dispatch(context, modalAction);
        },
        apiClient: client,
        child: SafeArea(
          child: Form(
            key: sheetFormKey,
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(
                16,
                16,
                16,
                16 + MediaQuery.viewInsetsOf(modalCtx).bottom,
              ),
              // A fresh Builder is required here: components must be built
              // with a context that is a DESCENDANT of the DynamicSchemaContext
              // above, so DynamicSchemaContext.of(context) can actually find
              // it. Building them directly with `modalCtx` (the context the
              // showModalBottomSheet builder callback itself received) would
              // look for an ancestor from a point that sits ABOVE where this
              // DynamicSchemaContext is being inserted, so it would never be
              // found — every button/input inside the sheet would silently
              // fail to dispatch actions or record form values.
              child: Builder(
                builder: (innerContext) => Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (title.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(title,
                            style: const TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 16)),
                      ),
                    for (final c in components)
                      if (c is Map)
                        DynamicSchemaParser.buildComponent(
                          innerContext,
                          Map<String, dynamic>.from(c),
                        ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
