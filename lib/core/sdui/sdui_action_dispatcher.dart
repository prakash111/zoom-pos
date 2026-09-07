import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../features/pos/rx_cart_handoff.dart';
import '../../features/pos/screens/invoice_actions_sheet.dart';
import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../config/app_config.dart';
import '../config/bootstrap_cache.dart';
import '../services/thermal/thermal_printer_service.dart' show ReceiptLine;
import 'dynamic_schema_context.dart';
import 'dynamic_schema_parser.dart';
import 'sdui_component_registry.dart';

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

  Future<void> dispatch(
      BuildContext context, Map<String, dynamic> action) async {
    if (onBeforeDispatch != null && await onBeforeDispatch!(context, action)) {
      return;
    }

    final type = action['type']?.toString().toLowerCase().trim();
    final client = resolveApiClient();

    switch (type) {
      case 'navigate':
        final endpoint = action['endpoint']?.toString() ??
            action['target_endpoint']?.toString() ??
            action['route']?.toString();

        if (endpoint != null && endpoint.isNotEmpty) {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => SduiComponentRegistry.resolveRoute(
                endpoint,
                arguments: action,
              ),
            ),
          );
        } else {
          showToast('Navigation action is missing an endpoint.', isError: true);
        }
        break;

      case 'toast_and_navigate':
        final message = action['message']?.toString() ??
            action['success_toast']?.toString() ??
            'Success';
        showToast(message);
        final route = action['route']?.toString() ??
            action['endpoint']?.toString() ??
            action['target_endpoint']?.toString();
        if (route != null && route.isNotEmpty && context.mounted) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (_) => SduiComponentRegistry.resolveRoute(
                route,
                arguments: action,
              ),
            ),
          );
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
          final submitData = <String, dynamic>{};
          final rawPayload = action['payload'];
          if (rawPayload is Map) {
            submitData.addAll(Map<String, dynamic>.from(rawPayload));
          }
          // Live form values take precedence over server defaults. This lets
          // checkout sheets inject immutable line-item defaults while still
          // respecting customer/payment/staff changes made in the drawer.
          submitData.addAll(formValues);

          final res =
              await _request(endpoint, method: method, data: submitData);
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
          final resAction = res['action']?.toString();
          final redirectRoute = res['route']?.toString() ??
              res['redirect_route']?.toString() ??
              action['redirect_route']?.toString() ??
              action['route']?.toString();

          if (context.mounted && _isPostSaleSheetResponse(res)) {
            await _showPostSaleSheet(context, res['post_sale_sheet']['data']);
          } else if (resAction == 'toast_and_navigate' ||
              (redirectRoute != null && redirectRoute.isNotEmpty)) {
            if (context.mounted && redirectRoute != null && redirectRoute.isNotEmpty) {
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                  builder: (_) => SduiComponentRegistry.resolveRoute(
                    redirectRoute,
                    arguments: res,
                  ),
                ),
              );
            }
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
          String fullUrl = rawUrl;
          if (!rawUrl.startsWith('http://') && !rawUrl.startsWith('https://')) {
            final client = resolveApiClient();
            String? base;
            if (client != null) {
              base = await client.currentBaseUrl();
            }
            base ??= AppConfig.defaultBaseUrl;
            fullUrl = '${base.replaceAll(RegExp(r'/+$'), '')}/${rawUrl.replaceAll(RegExp(r'^/+'), '')}';
          }
          final uri = Uri.tryParse(fullUrl);
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
        // Fire-and-forget: dispatch() returns once the sheet is shown, not
        // when it is dismissed (the sheet future resolves on dismiss).
        unawaited(_showComponentSheet(
          context,
          title: action['title']?.toString() ?? '',
          components: action['components'] as List<dynamic>? ?? const [],
          client: client,
        ));
        break;

      case 'open_remote_sheet':
        await _openRemoteSheet(context, action, client);
        break;

      case 'pop':
      case 'navigate_back':
        Navigator.of(context).pop();
        break;

      case 'load_rx_to_pos':
        _loadRxToPos(context, action);
        break;

      case 'filter_view':
        _filterView(context, action);
        break;

      default:
        break;
    }
  }

  /// Re-opens the current SDUI view with the named form fields appended as
  /// query params (a "search / filter this list" primitive). Replaces the
  /// current page so repeated searches don't pile up on the nav stack.
  void _filterView(BuildContext context, Map<String, dynamic> action) {
    final endpoint = action['endpoint']?.toString() ?? '';
    if (endpoint.isEmpty) {
      showToast('This search action is missing an endpoint.', isError: true);
      return;
    }
    final fields = (action['fields'] as List?)?.map((e) => '$e').toList() ??
        formValues.keys.toList();

    final params = <String, String>{};
    for (final f in fields) {
      final v = formValues[f];
      if (v == null || v is List || v is Map) continue;
      final s = '$v'.trim();
      if (s.isNotEmpty) params[f] = s;
    }

    var target = endpoint;
    if (params.isNotEmpty) {
      final sep = endpoint.contains('?') ? '&' : '?';
      target = '$endpoint$sep${params.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&')}';
    }

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) =>
            SduiComponentRegistry.resolveRoute(target, arguments: action),
      ),
    );
  }

  /// Hands a prescription's resolved line items, patient and doctor straight
  /// to the native POS cart state, then opens the interactive POS screen. The
  /// payload is parked in [RxCartHandoff]; [PosScreen] drains it while building
  /// its [PosProvider] (which is a screen-local provider, not a global one).
  void _loadRxToPos(BuildContext context, Map<String, dynamic> action) {
    final payload = action['payload'];
    RxCartHandoff.instance.stage(
      payload is Map ? Map<String, dynamic>.from(payload) : <String, dynamic>{},
    );
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SduiComponentRegistry.resolveRoute('pos'),
      ),
    );
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
      final raw = res['schema'] ?? res['cart_sheet'] ?? res;
      if (raw is Map<String, dynamic>) {
        sheetSchema = raw;
      } else if (raw is Map) {
        sheetSchema = Map<String, dynamic>.from(raw);
      } else {
        throw const FormatException(
            'The server did not return a sheet schema.');
      }
    } catch (e) {
      showToast(e is ApiException ? e.message : 'Failed to load sheet: $e',
          isError: true);
      return;
    }

    if (!context.mounted) return;

    // Fire-and-forget: this returns after the sheet is shown; callers that
    // need to wait for dismissal (the keep_parent_sheet flow) await
    // [_showComponentSheet] directly.
    unawaited(_showComponentSheet(
      context,
      title:
          sheetSchema['title']?.toString() ?? action['title']?.toString() ?? '',
      components: sheetSchema['components'] as List<dynamic>? ?? const [],
      client: client,
      sourceEndpoint: sheetEndpoint,
    ));
  }

  /// Re-fetches [endpoint] and returns its `{title, components}` — used by an
  /// open sheet to rebuild its own body in place (see `refresh_in_place`).
  Future<Map<String, dynamic>?> _fetchSheetBody(String endpoint) async {
    try {
      final res = await _request(endpoint, method: 'GET');
      final rawTheme = res['theme'];
      if (rawTheme is Map) {
        await BootstrapCache.instance
            .applyThemeJson(Map<String, dynamic>.from(rawTheme));
      }
      final raw = res['schema'] ?? res['cart_sheet'] ?? res;
      if (raw is Map) return Map<String, dynamic>.from(raw);
    } catch (e) {
      showToast(e is ApiException ? e.message : 'Failed to refresh: $e',
          isError: true);
    }
    return null;
  }

  /// Appends the current scalar form values as query params so a
  /// `refresh_in_place` GET can preview modal-entered state (customer,
  /// discount, notes, split amounts). Lists/maps (e.g. `items`) are skipped.
  String _withFormValueQuery(String endpoint) {
    final params = <String, String>{};
    formValues.forEach((key, value) {
      if (value == null || value is List || value is Map) return;
      params[key] = '$value';
    });
    if (params.isEmpty) return endpoint;
    final sep = endpoint.contains('?') ? '&' : '?';
    final query = params.entries
        .map((e) =>
            '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}')
        .join('&');
    return '$endpoint$sep$query';
  }

  Future<void> _showComponentSheet(
    BuildContext context, {
    required String title,
    required List<dynamic> components,
    required ApiClient? client,
    String? sourceEndpoint,
  }) {
    final sheetFormKey = GlobalKey<FormState>();
    var currentTitle = title;
    var currentComponents = components;
    var currentSource = sourceEndpoint;

    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (modalCtx) => StatefulBuilder(
        builder: (modalCtx, setSheetState) {
          Future<void> reloadInPlace(String endpoint) async {
            final body = await _fetchSheetBody(_withFormValueQuery(endpoint));
            if (body == null || !modalCtx.mounted) return;
            setSheetState(() {
              currentComponents =
                  (body['components'] as List<dynamic>?) ?? currentComponents;
              currentTitle = body['title']?.toString() ?? currentTitle;
              currentSource = endpoint;
            });
          }

          return DynamicSchemaContext(
            formValues: formValues,
            setFormValue: setFormValue,
            dispatchAction: (modalAction) async {
              final t = modalAction['type']?.toString().toLowerCase().trim();

              // A pop/back inside a sheet closes THAT sheet only — it must
              // never bubble to the page underneath.
              if (t == 'pop' || t == 'navigate_back') {
                if (Navigator.of(modalCtx).canPop()) {
                  Navigator.of(modalCtx).pop();
                }
                return;
              }

              if (t == 'form_submit' &&
                  !(sheetFormKey.currentState?.validate() ?? true)) {
                showToast('Please correct the highlighted fields.',
                    isError: true);
                return;
              }

              // (1) Rebuild this sheet's body in place — no dismiss, no
              //     parent-page reload, keyboard & scroll preserved.
              if (modalAction['refresh_in_place'] == true &&
                  (t == 'open_remote_sheet' ||
                      t == 'navigate' ||
                      t == 'refresh_sheet')) {
                final ep = (modalAction['sheet_endpoint'] ??
                        modalAction['endpoint'] ??
                        currentSource)
                    ?.toString();
                if (ep != null && ep.isNotEmpty) {
                  await reloadInPlace(ep);
                  return;
                }
              }

              // (2) Stack a child overlay OVER this sheet, keeping it alive;
              //     refresh this sheet once the child closes if asked.
              if (modalAction['keep_parent_sheet'] == true &&
                  (t == 'open_modal' ||
                      t == 'open_remote_sheet' ||
                      t == 'show_post_sale_sheet')) {
                if (t == 'open_modal') {
                  // Await the child sheet's dismissal (the sheet future
                  // resolves on pop) so the parent can refresh afterward.
                  await _showComponentSheet(
                    modalCtx,
                    title: modalAction['title']?.toString() ?? '',
                    components:
                        modalAction['components'] as List<dynamic>? ?? const [],
                    client: client,
                  );
                } else if (t == 'open_remote_sheet') {
                  final childEp =
                      modalAction['sheet_endpoint']?.toString() ?? '';
                  final childBody =
                      childEp.isEmpty ? null : await _fetchSheetBody(childEp);
                  if (childBody != null && modalCtx.mounted) {
                    await _showComponentSheet(
                      modalCtx,
                      title: childBody['title']?.toString() ??
                          modalAction['title']?.toString() ??
                          '',
                      components: (childBody['components'] as List<dynamic>?) ??
                          const [],
                      client: client,
                      sourceEndpoint: childEp,
                    );
                  }
                } else {
                  await dispatch(modalCtx, modalAction);
                }
                final refreshEp = (modalAction['refresh_endpoint'] ??
                        (modalAction['refresh_in_place'] == true
                            ? currentSource
                            : null))
                    ?.toString();
                if (refreshEp != null && refreshEp.isNotEmpty) {
                  await reloadInPlace(refreshEp);
                }
                return;
              }

              // (legacy) pop this sheet, then dispatch on the parent —
              // unchanged for every sheet that doesn't opt into the flags.
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
                  // with a context that is a DESCENDANT of the
                  // DynamicSchemaContext above, so
                  // DynamicSchemaContext.of(context) can actually find it.
                  child: Builder(
                    builder: (innerContext) => Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (currentTitle.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Text(currentTitle,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold, fontSize: 16)),
                          ),
                        for (final c in currentComponents)
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
          );
        },
      ),
    );
  }
}
