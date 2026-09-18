import 'dart:typed_data';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../config/app_config.dart';
import '../config/theme.dart';
import '../sdui/screens/dynamic_schema_page.dart';
import '../services/thermal/thermal_printer_service.dart';
import '../utils/responsive.dart';
import 'adaptive_sheet.dart';
import '../../features/settings/screens/printer_selection_dialog.dart';

bool get _supportsThermalPrint =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

/// Unified data container for omnichannel document dispatch across all verticals:
/// Retail, Restaurant, Salon, Pharmacy, and Service & Repairs.
class UnifiedDocumentDispatchData {
  UnifiedDocumentDispatchData({
    required this.documentType,
    required this.documentId,
    required this.documentNumber,
    required this.companyName,
    this.title,
    this.customerName,
    this.tableName,
    this.patientName,
    this.customerPhone,
    this.customerEmail,
    this.taxId,
    this.taxLabel = 'Tax ID',
    this.isIndia = false,
    this.formattedTimestamp,
    this.dateTime,
    this.lines = const [],
    this.subtotal = 0.0,
    this.discount = 0.0,
    this.tax = 0.0,
    this.total = 0.0,
    this.taxRate = 0.0,
    this.paidAmount,
    this.dueAmount = 0.0,
    this.currencySymbol = '\$',
    this.pdfPathOverride,
    this.actionsPathOverride,
    this.dispatchEndpoint = '/api/v1/documents/dispatch',
    this.notes,
    this.status = 'Paid',
    this.defect,
    this.deviceModel,
    this.doctorName,
    this.stylistName,
    this.onPreviewPdf,
    this.onDispatch,
    this.onChannelsDispatch,
    this.initialChannels,
    this.showPreview = true,
  });

  final String documentType;
  final String documentId;
  final String documentNumber;
  final String companyName;
  final String? title;
  final String? customerName;
  final String? tableName;
  final String? patientName;
  final String? customerPhone;
  final String? customerEmail;
  final String? taxId;
  final String taxLabel;
  final bool isIndia;
  final String? formattedTimestamp;
  final DateTime? dateTime;
  final List<ReceiptLine> lines;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final double taxRate;
  final double? paidAmount;
  final double dueAmount;
  final String currencySymbol;
  final String? pdfPathOverride;
  final String? actionsPathOverride;
  final String dispatchEndpoint;
  final String? notes;
  final String status;
  final String? defect;
  final String? deviceModel;
  final String? doctorName;
  final String? stylistName;
  final VoidCallback? onPreviewPdf;
  final void Function(bool sendWhatsApp, bool sendEmail)? onDispatch;
  final void Function(List<String> channels, Map<String, dynamic> payload)?
      onChannelsDispatch;
  final List<DispatchChannelItem>? initialChannels;
  final bool showPreview;

  String get displayTitle => documentNumber.trim();

  String get subtitleContext {
    if ((formattedTimestamp ?? '').startsWith('Table:')) {
      return formattedTimestamp!;
    }
    final segments = <String>[];

    // 1. Primary entity
    if ((tableName ?? '').isNotEmpty) {
      segments.add('Table $tableName');
    } else if ((patientName ?? '').isNotEmpty) {
      segments.add(patientName!);
    } else {
      if ((customerName ?? '').isNotEmpty) {
        segments.add(customerName!);
      }
      if ((deviceModel ?? '').isNotEmpty) {
        segments.add(deviceModel!);
      }
      if (segments.isEmpty) {
        segments.add('Customer');
      }
    }

    // 2. Timestamp
    if ((formattedTimestamp ?? '').isNotEmpty) {
      segments.add(formattedTimestamp!);
    } else {
      final dt = dateTime ?? DateTime.now();
      segments.add(DateFormat('d MMM yyyy, h:mm a').format(dt));
    }

    // 3. Tax ID
    if ((taxId ?? '').isNotEmpty) {
      final label =
          isIndia ? 'GSTIN' : (taxLabel.isNotEmpty ? taxLabel : 'Tax ID');
      segments.add('$label: $taxId');
    }

    return segments.join(' • ');
  }

  String get pdfPath {
    if ((pdfPathOverride ?? '').isNotEmpty) return pdfPathOverride!;
    final normalized = documentType.toLowerCase().trim();
    if (normalized == 'quotation' || normalized == 'quote') {
      return ApiEndpoints.quotationPdf(documentId);
    }
    if (normalized == 'repair' ||
        normalized == 'ticket' ||
        normalized == 'job_sheet') {
      return '/api/tenant/repair/tickets/$documentId/intake-sheet';
    }
    return ApiEndpoints.salePdf(documentId);
  }

  bool get isPdfPathAbsolute =>
      pdfPath.startsWith('/api/') || pdfPath.startsWith('http');

  String pdfPathForFormat(String format) {
    final base = pdfPath;
    final uri = Uri.parse(base);
    final query = Map<String, String>.from(uri.queryParameters);
    query['format'] = format;
    return uri.replace(queryParameters: query).toString();
  }

  bool isPdfPathAbsoluteFor(String path) =>
      path.startsWith('/api/') || path.startsWith('http');
}

/// Universal entry point for opening the enterprise-grade omnichannel
/// Document Dispatch Bottom Sheet.
Future<void> showUnifiedDocumentDispatchSheet(
  BuildContext context,
  UnifiedDocumentDispatchData data,
) {
  final isDark = Theme.of(context).brightness == Brightness.dark;
  // Match dark elevated midnight navy token (0xFF131E29 / AppTheme.darkCard)
  final background = isDark ? const Color(0xFF131E29) : AppTheme.lightCard;

  return showAdaptiveSheet(
    context,
    backgroundColor: background,
    builder: (sheetContext) => UnifiedDocumentDispatchSheet(
      parentContext: context,
      data: data,
    ),
  );
}

/// Represents an omnichannel dispatch channel dynamically discovered from tenant integration settings.
class DispatchChannelItem {
  DispatchChannelItem({
    required this.id,
    required this.channel,
    required this.title,
    required this.subtitle,
    this.channelId,
    this.target,
    this.icon = Icons.send_rounded,
    this.iconColor = const Color(0xFF38BDF8),
    this.isSelected = false,
    this.isEnabled = true,
    this.provider,
    this.apiEnabled = true,
    this.launchUrl,
  });

  final String id;
  final String channel; // 'whatsapp', 'email', 'sms', 'webhook', 'custom'
  final int? channelId;
  String title;
  String subtitle;
  String? target;
  IconData icon;
  Color iconColor;
  bool isSelected;
  bool isEnabled;
  String? provider;
  final bool apiEnabled;
  final String? launchUrl;

  bool get isLocalIntent =>
      (!apiEnabled || !isEnabled) &&
      ['whatsapp', 'email', 'sms'].contains(channel);
  bool get isCloudChannel =>
      apiEnabled &&
      isEnabled &&
      !['thermal_print', 'pdf_preview'].contains(channel);

  Uri deviceUri(
      {String? phone,
      String? email,
      required String message,
      required String subject}) {
    final original = Uri.tryParse(launchUrl ?? '');
    final body =
        original?.queryParameters[channel == 'whatsapp' ? 'text' : 'body'] ??
            message;
    final originalRecipient = switch (channel) {
      'whatsapp' => original?.queryParameters['phone'],
      'email' || 'sms' => original?.path,
      _ => null,
    };
    final recipient = (channel == 'email' ? email : phone) ??
        target ??
        originalRecipient ??
        '';
    switch (channel) {
      case 'whatsapp':
        final digits = recipient
            .replaceAll(RegExp(r'\D'), '')
            .replaceFirst(RegExp(r'^0+'), '');
        return Uri.parse(
            'whatsapp://send?${digits.isEmpty ? '' : 'phone=$digits&'}text=${Uri.encodeComponent(body)}');
      case 'email':
        final mailSubject = original?.queryParameters['subject'] ?? subject;
        return Uri.parse(
            'mailto:${Uri.encodeComponent(recipient)}?subject=${Uri.encodeComponent(mailSubject)}&body=${Uri.encodeComponent(body)}');
      case 'sms':
        final cleanPhone = recipient.replaceAll(RegExp(r'[^0-9+]'), '');
        final separator =
            !kIsWeb && defaultTargetPlatform == TargetPlatform.iOS ? '&' : '?';
        return Uri.parse(
            'sms:$cleanPhone${separator}body=${Uri.encodeComponent(body)}');
      default:
        throw StateError('This channel has no device intent.');
    }
  }

  static String localTitle(String channel) => switch (channel) {
        'whatsapp' => 'Open WhatsApp App',
        'email' => 'Open Mail App',
        'sms' => 'Open Messages / SMS',
        _ => _defaultTitle(channel),
      };

  factory DispatchChannelItem.fromJson(
    Map<String, dynamic> json, {
    String? targetPhone,
    String? targetEmail,
  }) {
    final rawChannel = (json['channel'] ?? 'custom').toString().toLowerCase();
    final channel = rawChannel == 'custom_webhook' ? 'webhook' : rawChannel;
    final action = json['action'] is Map ? json['action'] as Map : const {};
    final uri = Uri.tryParse(
        action['url']?.toString() ?? json['url']?.toString() ?? '');
    final local = json['api_enabled'] == false ||
        json['is_enabled'] == false ||
        json['available'] == false ||
        json['delivery_mode'] == 'device' ||
        json['mode'] == 'local_intent' ||
        (['whatsapp', 'email', 'sms'].contains(channel) &&
            json['selectable'] == false) ||
        ['whatsapp', 'mailto', 'sms'].contains(uri?.scheme);
    final apiEnabled = !local;
    final id = (json['id'] ?? 'channel_$channel').toString();
    final title = local
        ? localTitle(channel)
        : (json['title'] ?? json['label'] ?? _defaultTitle(channel)).toString();
    var subtitle = (json['subtitle'] ?? '').toString();
    var target = json['target']?.toString();
    if (channel == 'whatsapp' || channel == 'sms') {
      target = (targetPhone ?? '').isNotEmpty ? targetPhone : target;
    } else if (channel == 'email') {
      target = (targetEmail ?? '').isNotEmpty ? targetEmail : target;
    }
    if ((target ?? '').isNotEmpty) subtitle = 'To: $target';
    final leading = json['leading'] is Map ? json['leading'] as Map : const {};
    final hasSelection = json.containsKey('default') ||
        json.containsKey('initial_value') ||
        json.containsKey('is_selected');
    final selected = json['default'] == true ||
        json['initial_value'] == true ||
        json['is_selected'] == true ||
        (!hasSelection &&
            (channel == 'whatsapp' ||
                (channel == 'email' && (target ?? '').isNotEmpty)));

    return DispatchChannelItem(
      id: id,
      channel: channel,
      channelId: json['channel_id'] is int
          ? json['channel_id'] as int
          : int.tryParse('${json['channel_id']}'),
      title: title,
      subtitle: subtitle,
      target: target,
      icon: _resolveIcon(
          json['icon']?.toString() ?? leading['icon']?.toString() ?? channel),
      iconColor: _resolveColor(
          json['color']?.toString() ?? leading['color']?.toString() ?? channel),
      apiEnabled: apiEnabled,
      isSelected: apiEnabled && selected,
      isEnabled: (local && ['whatsapp', 'email', 'sms'].contains(channel)) ||
          (apiEnabled &&
              json['available'] != false &&
              json['is_enabled'] != false),
      provider: json['provider']?.toString(),
      launchUrl: uri?.toString(),
    );
  }

  factory DispatchChannelItem.fromSdui(Map<String, dynamic> json,
      {String? targetPhone, String? targetEmail}) {
    final copy = Map<String, dynamic>.from(json);
    final id = copy['id']?.toString() ?? '';
    copy['channel'] ??=
        id.startsWith('channel_') ? id.substring('channel_'.length) : 'custom';
    return DispatchChannelItem.fromJson(copy,
        targetPhone: targetPhone, targetEmail: targetEmail);
  }

  static String _defaultTitle(String channel) {
    switch (channel.toLowerCase()) {
      case 'whatsapp':
        return 'Send via WhatsApp';
      case 'sms':
        return 'Send via SMS (Text Message)';
      case 'email':
        return 'Send via Email';
      case 'webhook':
      case 'custom_webhook':
        return 'Trigger External Webhook';
      default:
        return 'Send via ${channel.toUpperCase()}';
    }
  }

  static IconData _resolveIcon(String iconStr) {
    switch (iconStr.toLowerCase()) {
      case 'whatsapp':
      case 'chat':
        return Icons.chat_rounded;
      case 'sms':
      case 'textsms':
        return Icons.textsms_outlined;
      case 'email':
      case 'mark_email_read':
        return Icons.email_outlined;
      case 'webhook':
      case 'hub':
        return Icons.hub_outlined;
      case 'notifications':
        return Icons.notifications_active_outlined;
      default:
        return Icons.send_rounded;
    }
  }

  static Color _resolveColor(String colorStr) {
    if (colorStr.startsWith('#')) {
      final hex = colorStr.replaceAll('#', '');
      if (hex.length == 6) {
        final parsed = int.tryParse('FF$hex', radix: 16);
        if (parsed != null) return Color(parsed);
      }
    }
    switch (colorStr.toLowerCase()) {
      case 'whatsapp':
        return const Color(0xFF25D366);
      case 'sms':
        return const Color(0xFF38BDF8);
      case 'email':
        return const Color(0xFF818CF8);
      case 'webhook':
      case 'custom_webhook':
        return const Color(0xFFA855F7);
      default:
        return const Color(0xFF10B981);
    }
  }
}

/// The unified Document Dispatch Bottom Sheet widget.
class UnifiedDocumentDispatchSheet extends StatefulWidget {
  const UnifiedDocumentDispatchSheet({
    super.key,
    required this.parentContext,
    required this.data,
  });

  final BuildContext parentContext;
  final UnifiedDocumentDispatchData data;

  @override
  State<UnifiedDocumentDispatchSheet> createState() =>
      _UnifiedDocumentDispatchSheetState();
}

class _UnifiedDocumentDispatchSheetState
    extends State<UnifiedDocumentDispatchSheet> {
  late String _targetPhone;
  late String _targetEmail;
  bool _isDispatching = false;
  List<DispatchChannelItem> _channels = [];

  @override
  void initState() {
    super.initState();
    _targetPhone = (widget.data.customerPhone ?? '').trim();
    _targetEmail = (widget.data.customerEmail ?? '').trim();

    _channels = _withUniversalChannels(widget.data.initialChannels ?? []);

    _fetchEnabledChannels();
  }

  Future<void> _fetchEnabledChannels() async {
    final candidateEndpoints = <String>[
      if ((widget.data.actionsPathOverride ?? '').isNotEmpty)
        widget.data.actionsPathOverride!,
      '/api/v1/documents/${widget.data.documentType}/${widget.data.documentId}/dispatch-options',
      '/api/v1/tenant/dispatch/channels',
      '/api/v1/documents/channels',
    ];

    try {
      final apiClient = widget.parentContext.read<ApiClient>();
      for (final endpoint in candidateEndpoints) {
        try {
          final res = await apiClient.requestAbsolute(endpoint, method: 'GET');
          if (res.isNotEmpty && mounted) {
            if (_targetPhone.isEmpty) {
              final p = res['customer']?['phone'] ??
                  res['customer_phone'] ??
                  res['phone'];
              if (p != null && p.toString().trim().isNotEmpty) {
                _targetPhone = p.toString().trim();
              }
            }
            if (_targetEmail.isEmpty) {
              final e = res['customer']?['email'] ??
                  res['customer_email'] ??
                  res['email'];
              if (e != null && e.toString().trim().isNotEmpty) {
                _targetEmail = e.toString().trim();
              }
            }
            final parsed = _parseChannels(res);
            if (parsed.isNotEmpty) {
              setState(() {
                _channels = _withUniversalChannels(parsed);
              });
              return;
            }
          }
        } catch (_) {}
      }
    } catch (_) {}
  }

  List<DispatchChannelItem> _parseChannels(Map<String, dynamic> res) {
    final result = <DispatchChannelItem>[];
    final seen = <String>{};

    void addChannel(DispatchChannelItem item) {
      if (['thermal_print', 'pdf_preview'].contains(item.channel)) return;
      if (!item.isEnabled &&
          !['whatsapp', 'email', 'sms'].contains(item.channel)) return;
      final key = item.channel == 'custom' && item.channelId != null
          ? 'custom:${item.channelId}'
          : item.channel;
      if (!seen.contains(key)) {
        seen.add(key);
        result.add(item);
      }
    }

    void parseEntries(dynamic entries) {
      if (entries is List) {
        for (final entry in entries) {
          if (entry is Map)
            addChannel(DispatchChannelItem.fromSdui(
                Map<String, dynamic>.from(entry),
                targetPhone: _targetPhone,
                targetEmail: _targetEmail));
        }
      } else if (entries is Map) {
        for (final entry in entries.entries) {
          if (entry.value is! Map) continue;
          final copy = Map<String, dynamic>.from(entry.value as Map);
          copy['channel'] ??= entry.key.toString();
          addChannel(DispatchChannelItem.fromJson(copy,
              targetPhone: _targetPhone, targetEmail: _targetEmail));
        }
      }
    }

    // Full channel definitions take precedence over the API-only subset.
    parseEntries(res['channels']);
    void parseComponents(dynamic components) {
      if (components is! List) return;
      for (final component in components) {
        if (component is! Map) continue;
        if (component['channel'] != null ||
            (component['id']?.toString() ?? '').startsWith('channel_')) {
          addChannel(DispatchChannelItem.fromSdui(
              Map<String, dynamic>.from(component),
              targetPhone: _targetPhone,
              targetEmail: _targetEmail));
        }
        parseComponents(component['components'] ?? component['children']);
      }
    }

    parseComponents(res['components'] ?? res['schema']?['components']);
    parseEntries(res['device_channels']);
    parseEntries(res['secondary_options']);
    parseEntries(res['enabled_channels']);

    return result;
  }

  List<DispatchChannelItem> _withUniversalChannels(
      List<DispatchChannelItem> channels) {
    return [
      for (final channel in ['whatsapp', 'email', 'sms'])
        channels.where((item) => item.channel == channel).firstOrNull ??
            DispatchChannelItem.fromJson({
              'channel': channel,
              'api_enabled': false,
              'mode': 'local_intent',
              'available': true,
            }, targetPhone: _targetPhone, targetEmail: _targetEmail),
      ...channels.where((item) => ![
            'whatsapp',
            'email',
            'sms',
            'thermal_print',
            'pdf_preview'
          ].contains(item.channel)),
    ];
  }

  Future<void> _handleOpenDeviceApp(DispatchChannelItem channel) async {
    final data = widget.data;
    final message =
        'Hello ${data.customerName ?? data.patientName ?? 'Customer'}, your ${data.documentType} ${data.documentNumber} from ${data.companyName}.';
    final uri = channel.deviceUri(
      phone: _targetPhone.isNotEmpty ? _targetPhone : channel.target,
      email: _targetEmail.isNotEmpty ? _targetEmail : channel.target,
      message: message,
      subject: '${data.documentType} ${data.documentNumber}',
    );
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content:
                Text('Could not open the app. Check that it is installed.')));
      }
    } catch (_) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content:
                Text('Could not open the app. Check that it is installed.')));
    }
  }

  Future<void> _handleEditTarget(String type) async {
    final isEmail = type == 'email';
    final controller = TextEditingController(
      text: isEmail ? _targetEmail : _targetPhone,
    );

    final res = await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: Theme.of(context).brightness == Brightness.dark
              ? AppTheme.darkCard
              : AppTheme.lightCard,
          title:
              Text(isEmail ? 'Edit Recipient Email' : 'Edit Recipient Phone'),
          content: TextField(
            controller: controller,
            autofocus: true,
            keyboardType:
                isEmail ? TextInputType.emailAddress : TextInputType.phone,
            decoration: InputDecoration(
              labelText:
                  isEmail ? 'Email Address' : 'Phone Number with Country Code',
              hintText:
                  isEmail ? 'e.g. user@example.com' : 'e.g. +1 555 123 4567',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () =>
                  Navigator.of(dialogContext).pop(controller.text.trim()),
              child: const Text('Confirm'),
            ),
          ],
        );
      },
    );

    if (res != null) {
      setState(() {
        if (isEmail) {
          _targetEmail = res;
          for (final ch in _channels) {
            if (ch.channel == 'email') {
              ch.target = res;
              ch.subtitle = 'To: $res';
              if (res.isNotEmpty && ch.isCloudChannel) ch.isSelected = true;
            }
          }
        } else {
          _targetPhone = res;
          for (final ch in _channels) {
            if (ch.channel == 'whatsapp' || ch.channel == 'sms') {
              ch.target = res;
              ch.subtitle = 'To: $res';
              if (res.isNotEmpty && ch.isCloudChannel) ch.isSelected = true;
            }
          }
        }
      });
    }
  }

  Future<void> _handleDispatch() async {
    final selected =
        _channels.where((c) => c.isCloudChannel && c.isSelected).toList();
    if (selected.isEmpty) {
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(
        const SnackBar(
          content: Text('Please select at least one channel to dispatch.'),
          backgroundColor: AppTheme.warning,
        ),
      );
      return;
    }

    final selectedChannels = selected.map((c) {
      if (c.channel == 'custom' && c.channelId != null) {
        return 'custom:${c.channelId}';
      }
      return c.channel;
    }).toList();

    final sendWhatsApp = selectedChannels.contains('whatsapp');
    final sendEmail = selectedChannels.contains('email');
    final sendSms = selectedChannels.contains('sms');

    final hasDeskOrTable = (widget.data.tableName ?? '').isNotEmpty;

    if (sendWhatsApp && _targetPhone.isEmpty && !hasDeskOrTable) {
      await _handleEditTarget('phone');
      if (_targetPhone.isEmpty) return;
    }

    if (sendSms && _targetPhone.isEmpty && !hasDeskOrTable) {
      await _handleEditTarget('phone');
      if (_targetPhone.isEmpty) return;
    }

    if (sendEmail && _targetEmail.isEmpty) {
      await _handleEditTarget('email');
      if (_targetEmail.isEmpty) return;
    }

    final payload = {
      'document_type': widget.data.documentType,
      'document_id': widget.data.documentId,
      'channels': selectedChannels,
      'api_only': true,
      'send_whatsapp': sendWhatsApp,
      'send_email': sendEmail,
      'send_sms': sendSms,
      'phone': _targetPhone,
      'email': _targetEmail,
    };

    if (!mounted) return;
    if (widget.data.onChannelsDispatch != null) {
      Navigator.of(context).pop();
      widget.data.onChannelsDispatch!(selectedChannels, payload);
      return;
    }
    // The legacy callback can represent only WhatsApp and email.
    if (widget.data.onDispatch != null &&
        selectedChannels
            .every((channel) => ['whatsapp', 'email'].contains(channel))) {
      Navigator.of(context).pop();
      widget.data.onDispatch!(sendWhatsApp, sendEmail);
      return;
    }

    setState(() => _isDispatching = true);
    try {
      final apiClient = widget.parentContext.read<ApiClient>();
      final response = await apiClient.requestAbsolute(
          widget.data.dispatchEndpoint,
          method: 'POST',
          data: payload);
      if (!mounted) return;
      final success = response['success'] == true || response['success'] == 1;
      final partial = response['status'] == 'partial';
      final failures = response['failed'] is Map
          ? (response['failed'] as Map)
              .entries
              .map((entry) => '${entry.key}: ${entry.value}')
              .join('; ')
          : '';
      final message = [
        response['message']?.toString() ??
            (success ? 'Document sent.' : 'Document dispatch failed.'),
        if (failures.isNotEmpty) failures
      ].join(' ');
      if (success && !partial) Navigator.of(context).pop();
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(SnackBar(
        content: Text(message),
        backgroundColor: !success
            ? AppTheme.danger
            : partial
                ? AppTheme.warning
                : AppTheme.success,
      ));
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(widget.parentContext).showSnackBar(SnackBar(
          content: Text(error is ApiException
              ? error.message
              : 'Document dispatch failed. Please try again.'),
          backgroundColor: AppTheme.danger,
        ));
    } finally {
      if (mounted) setState(() => _isDispatching = false);
    }
  }

  Future<void> _handleThermalPrint() async {
    Navigator.of(context).pop();
    final target =
        await PrinterSelectionDialog.ensureSelected(widget.parentContext);
    if (target == null || !widget.parentContext.mounted) return;

    final messenger = ScaffoldMessenger.of(widget.parentContext);
    messenger.showSnackBar(const SnackBar(content: Text('Printing receipt…')));

    final service = ThermalPrinterService();
    final ok = await service.printReceipt(
      companyName: widget.data.companyName,
      documentLabel:
          '${widget.data.title ?? widget.data.documentType.toUpperCase()} ${widget.data.displayTitle}',
      lines: widget.data.lines,
      subtotal: widget.data.subtotal,
      discount: widget.data.discount,
      tax: widget.data.tax,
      total: widget.data.total,
      customerName: widget.data.customerName,
      currencySymbol: widget.data.currencySymbol,
      taxId: widget.data.taxId,
      taxLabel: widget.data.taxLabel,
      isIndia: widget.data.isIndia,
      taxRate: widget.data.taxRate,
      paidAmount: widget.data.paidAmount,
      dueAmount: widget.data.dueAmount,
    );

    messenger.showSnackBar(SnackBar(
      content: Text(
          ok ? 'Sent to receipt printer.' : 'Could not reach receipt printer.'),
      backgroundColor: ok ? AppTheme.success : AppTheme.danger,
    ));
  }

  void _handlePreview() {
    if (widget.data.onPreviewPdf != null) {
      Navigator.of(context).pop();
      widget.data.onPreviewPdf!();
      return;
    }

    final apiClient = widget.parentContext.read<ApiClient>();
    final type = widget.data.documentType.toLowerCase().trim();
    final usesDocumentSchema =
        ['repair', 'ticket', 'job_sheet'].contains(type) ||
            widget.data.pdfPath.contains('/preview-modal');
    Navigator.of(context).pop();
    Navigator.of(widget.parentContext).push(
      MaterialPageRoute(
        builder: (_) => usesDocumentSchema
            ? DynamicSchemaPage(
                apiClient: apiClient,
                endpoint:
                    '/api/v1/tenant/documents/${Uri.encodeComponent(type)}/${Uri.encodeComponent(widget.data.documentId)}/preview-modal?format=a4&preview_document=1',
                initialTitle: widget.data.documentNumber,
              )
            : UnifiedDocumentPreviewScreen(
                apiClient: apiClient,
                data: widget.data,
              ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primaryText = isDark ? AppTheme.darkHeading : AppTheme.lightHeading;
    final secondaryText = isDark ? AppTheme.darkMuted : AppTheme.lightMuted;
    final borderColor = isDark ? AppTheme.darkBorder : AppTheme.lightBorder;

    return SafeArea(
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Top header bar with drag handle and close button
            Stack(
              alignment: Alignment.center,
              children: [
                if (!isWide(context))
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      margin: const EdgeInsets.only(top: 10, bottom: 12),
                      decoration: BoxDecoration(
                        color: isDark
                            ? const Color(0xFF334155)
                            : const Color(0xFFCBD5E1),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  )
                else
                  const SizedBox(height: 16),
                Positioned(
                  right: 8,
                  top: 2,
                  child: IconButton(
                    tooltip: 'Close',
                    icon: Icon(Icons.close, size: 20, color: secondaryText),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ),
              ],
            ),

            // Centered Document Identifier Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: [
                  Text(
                    widget.data.displayTitle,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                      color: primaryText,
                    ),
                  ),
                  if ((widget.data.taxId ?? '').isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      '${widget.data.isIndia ? 'GSTIN' : (widget.data.taxLabel.isNotEmpty ? widget.data.taxLabel : 'Tax ID')}: ${widget.data.taxId}',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11,
                        color: secondaryText,
                      ),
                    ),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    widget.data.subtitleContext,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 12,
                      color: secondaryText,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 12),
            Divider(height: 1, color: borderColor),
            const SizedBox(height: 4),

            // Repair sharing omits the preview; other sheets keep it visible.
            if (widget.data.showPreview)
              ListTile(
                leading: const Icon(Icons.picture_as_pdf_outlined,
                    color: AppTheme.activeLink, size: 22),
                title: const Text(
                  'PDF Preview',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                subtitle: Text(
                  'Shared full-screen thermal/A4 preview with Print and Share',
                  style: TextStyle(fontSize: 12, color: secondaryText),
                ),
                trailing: Icon(Icons.chevron_right_rounded,
                    color: secondaryText, size: 20),
                onTap: _handlePreview,
              ),

            // 2. Print on Receipt Printer
            ListTile(
              leading: const Icon(Icons.print_outlined,
                  color: AppTheme.success, size: 22),
              title: const Text(
                'Thermal Print',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
              subtitle: Text(
                'Bluetooth / Network ESC/POS thermal printer',
                style: TextStyle(fontSize: 12, color: secondaryText),
              ),
              trailing: Icon(Icons.arrow_forward_ios_rounded,
                  color: secondaryText, size: 14),
              onTap: () {
                if (_supportsThermalPrint) {
                  _handleThermalPrint();
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                      content: Text(
                          'Thermal printing is available on Android and iOS.')));
                }
              },
            ),

            // Dynamic channel checkboxes (WhatsApp, SMS, Email, Webhook, Custom channels)
            ..._channels.map((channel) {
              final isPhone =
                  channel.channel == 'whatsapp' || channel.channel == 'sms';
              final isEmail = channel.channel == 'email';
              final canEdit = isPhone || isEmail;

              String targetDisplay;
              if (isPhone) {
                targetDisplay = _targetPhone.isNotEmpty
                    ? 'To: $_targetPhone'
                    : ((widget.data.tableName ?? '').isNotEmpty
                        ? 'To: Kitchen / Intake Desk'
                        : 'Tap to enter recipient phone');
              } else if (isEmail) {
                targetDisplay = _targetEmail.isNotEmpty
                    ? 'To: $_targetEmail'
                    : 'Tap to enter recipient email';
              } else {
                targetDisplay = channel.subtitle.isNotEmpty
                    ? channel.subtitle
                    : (channel.provider ?? 'Configured in Settings');
              }

              if (channel.isLocalIntent) {
                return ListTile(
                  key: ValueKey(channel.id),
                  leading:
                      Icon(channel.icon, color: channel.iconColor, size: 22),
                  title: Text(channel.title,
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w600)),
                  subtitle: Row(children: [
                    Expanded(
                        child: Text(targetDisplay,
                            style:
                                TextStyle(fontSize: 12, color: secondaryText))),
                    if (canEdit)
                      TextButton(
                          onPressed: () =>
                              _handleEditTarget(isEmail ? 'email' : 'phone'),
                          child: const Text('Edit')),
                  ]),
                  trailing: TextButton.icon(
                      onPressed: () => _handleOpenDeviceApp(channel),
                      icon: const Icon(Icons.open_in_new, size: 16),
                      label: const Text('Open')),
                  onTap: () => _handleOpenDeviceApp(channel),
                );
              }

              return CheckboxListTile(
                key: ValueKey(channel.id),
                value: channel.isSelected,
                activeColor: AppTheme.success,
                controlAffinity: ListTileControlAffinity.leading,
                secondary:
                    Icon(channel.icon, color: channel.iconColor, size: 22),
                title: Text(
                  channel.title,
                  style: const TextStyle(
                      fontSize: 14, fontWeight: FontWeight.w600),
                ),
                subtitle: Row(
                  children: [
                    Expanded(
                      child: Text(
                        targetDisplay,
                        style: TextStyle(fontSize: 12, color: secondaryText),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (canEdit)
                      InkWell(
                        onTap: () =>
                            _handleEditTarget(isEmail ? 'email' : 'phone'),
                        child: const Padding(
                          padding:
                              EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          child: Text(
                            'Edit',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.activeLink,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
                onChanged: _isDispatching || !channel.isCloudChannel
                    ? null
                    : (val) =>
                        setState(() => channel.isSelected = val ?? false),
              );
            }),

            const SizedBox(height: 12),

            // Sticky Bottom Primary CTA Button
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton.icon(
                  onPressed: _isDispatching ||
                          !_channels.any((channel) =>
                              channel.isCloudChannel && channel.isSelected)
                      ? null
                      : _handleDispatch,
                  icon: _isDispatching
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.send_rounded, size: 18),
                  label: Text(
                    _isDispatching
                        ? 'Dispatching...'
                        : 'Send to Selected Channels',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.3,
                    ),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppTheme.success,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Shared Full-Screen Thermal and A4 Preview Screen.
/// Displays working Print and Share actions across every vertical document type.
class UnifiedDocumentPreviewScreen extends StatefulWidget {
  const UnifiedDocumentPreviewScreen({
    super.key,
    required this.apiClient,
    required this.data,
    this.initialFormatIndex = 0,
  });

  final ApiClient apiClient;
  final UnifiedDocumentDispatchData data;
  final int initialFormatIndex;

  @override
  State<UnifiedDocumentPreviewScreen> createState() =>
      _UnifiedDocumentPreviewScreenState();
}

class _UnifiedDocumentPreviewScreenState
    extends State<UnifiedDocumentPreviewScreen> {
  late int _selectedFormatIndex;

  @override
  void initState() {
    super.initState();
    _selectedFormatIndex = widget.initialFormatIndex;
  }

  String _formatParamFor(int index) {
    return switch (index) {
      1 => 'a4',
      2 => '58mm',
      _ => '80mm',
    };
  }

  Future<void> _handleShare() async {
    final data = widget.data;
    final summary = [
      '${data.companyName} - ${data.displayTitle}',
      'Date: ${data.formattedTimestamp ?? DateFormat('d MMM yyyy').format(data.dateTime ?? DateTime.now())}',
      if ((data.customerName ?? '').isNotEmpty)
        'Customer: ${data.customerName}',
      if ((data.tableName ?? '').isNotEmpty) 'Table: ${data.tableName}',
      if ((data.deviceModel ?? '').isNotEmpty) 'Device: ${data.deviceModel}',
      'Total: ${data.currencySymbol}${data.total.toStringAsFixed(2)}',
      'Status: ${data.status}',
    ].join('\n');

    await Share.share(summary,
        subject: '${data.companyName} - ${data.displayTitle}');
  }

  Future<void> _handlePrint() async {
    final messenger = ScaffoldMessenger.of(context);
    final data = widget.data;
    final formatParam = _formatParamFor(_selectedFormatIndex);

    if (_selectedFormatIndex == 1) {
      // Standard A4 PDF Print
      try {
        final path = widget.data.pdfPathForFormat('a4');
        final bytes = widget.data.isPdfPathAbsoluteFor(path)
            ? await widget.apiClient.getBytesAbsolute(path)
            : await widget.apiClient.getBytes(path);
        await Printing.layoutPdf(
          onLayout: (format) async => Uint8List.fromList(bytes),
          name: '${data.displayTitle}.pdf',
          format: PdfPageFormat.a4,
        );
      } catch (e) {
        try {
          final localBytes = await _buildLocalDocumentPdf(widget.data, 'a4');
          await Printing.layoutPdf(
            onLayout: (format) async => localBytes,
            name: '${data.displayTitle}.pdf',
            format: PdfPageFormat.a4,
          );
        } catch (localError) {
          messenger.showSnackBar(SnackBar(content: Text('Print error: $localError')));
        }
      }
    } else {
      // Thermal Print
      if (!_supportsThermalPrint) {
        // Web / Windows / Desktop: print using system layoutPdf with roll paper dimensions
        try {
          final path = widget.data.pdfPathForFormat(formatParam);
          final bytes = widget.data.isPdfPathAbsoluteFor(path)
              ? await widget.apiClient.getBytesAbsolute(path)
              : await widget.apiClient.getBytes(path);
          final rollFormat = _selectedFormatIndex == 2
              ? PdfPageFormat(58 * PdfPageFormat.mm, double.infinity,
                  marginAll: 4 * PdfPageFormat.mm)
              : PdfPageFormat(80 * PdfPageFormat.mm, double.infinity,
                  marginAll: 4 * PdfPageFormat.mm);
          await Printing.layoutPdf(
            onLayout: (format) async => Uint8List.fromList(bytes),
            name: '${data.displayTitle}.pdf',
            format: rollFormat,
          );
          return;
        } catch (_) {
          final localBytes =
              await _buildLocalDocumentPdf(widget.data, formatParam);
          await Printing.layoutPdf(
            onLayout: (format) async => localBytes,
            name: '${data.displayTitle}.pdf',
          );
          return;
        }
      }

      final target = await PrinterSelectionDialog.ensureSelected(context);
      if (target == null || !mounted) return;

      messenger.showSnackBar(
          const SnackBar(content: Text('Printing to receipt printer…')));
      final ok = await ThermalPrinterService().printReceipt(
        companyName: data.companyName,
        documentLabel:
            '${data.title ?? data.documentType.toUpperCase()} ${data.displayTitle}',
        lines: data.lines,
        subtotal: data.subtotal,
        discount: data.discount,
        tax: data.tax,
        total: data.total,
        customerName: data.customerName,
        currencySymbol: data.currencySymbol,
        taxId: data.taxId,
        taxLabel: data.taxLabel,
        isIndia: data.isIndia,
        taxRate: data.taxRate,
        paidAmount: data.paidAmount,
        dueAmount: data.dueAmount,
      );

      messenger.showSnackBar(SnackBar(
        content: Text(ok
            ? 'Sent to receipt printer.'
            : 'Could not reach receipt printer.'),
        backgroundColor: ok ? AppTheme.success : AppTheme.danger,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? AppTheme.darkBg : const Color(0xFFF1F5F9);
    final data = widget.data;

    return Scaffold(
      backgroundColor: scaffoldBg,
      appBar: AppBar(
        title: Text(data.displayTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            tooltip: 'Share Document',
            onPressed: _handleShare,
          ),
          IconButton(
            icon: const Icon(Icons.print_outlined),
            tooltip: 'Print',
            onPressed: _handlePrint,
          ),
        ],
      ),
      body: Column(
        children: [
          // Format switcher bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: isDark ? AppTheme.darkCard : Colors.white,
            child: Row(
              children: [
                _buildFormatChip('Thermal 80mm', 0),
                const SizedBox(width: 8),
                _buildFormatChip('Standard A4', 1),
                const SizedBox(width: 8),
                _buildFormatChip('Thermal 58mm', 2),
              ],
            ),
          ),
          Divider(
              height: 1,
              color: isDark ? AppTheme.darkBorder : AppTheme.lightBorder),

          // Main preview viewport
          Expanded(
            child: _buildPdfPreview(_selectedFormatIndex),
          ),

          // Bottom Action Dock
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkCard : Colors.white,
              border: Border(
                top: BorderSide(
                  color: isDark ? AppTheme.darkBorder : AppTheme.lightBorder,
                ),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _handleShare,
                    icon: const Icon(Icons.share_rounded, size: 18),
                    label: const Text('Share'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor:
                          isDark ? AppTheme.darkHeading : AppTheme.lightHeading,
                      side: BorderSide(
                        color:
                            isDark ? AppTheme.darkBorder : AppTheme.lightBorder,
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _handlePrint,
                    icon: const Icon(Icons.print_rounded, size: 18),
                    label: const Text('Print Now'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFormatChip(String label, int index) {
    final isSelected = _selectedFormatIndex == index;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Expanded(
      child: GestureDetector(
        onTap: () => setState(() => _selectedFormatIndex = index),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? AppTheme.success
                : (isDark ? AppTheme.darkInput : const Color(0xFFF1F5F9)),
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              color: isSelected
                  ? Colors.white
                  : (isDark ? AppTheme.darkMuted : const Color(0xFF475569)),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPdfPreview(int index) {
    final formatParam = _formatParamFor(index);
    final isA4 = formatParam == 'a4';
    final is58mm = formatParam == '58mm';

    final targetPageFormat = isA4
        ? PdfPageFormat.a4
        : (is58mm
            ? PdfPageFormat(58 * PdfPageFormat.mm, double.infinity,
                marginAll: 4 * PdfPageFormat.mm)
            : PdfPageFormat(80 * PdfPageFormat.mm, double.infinity,
                marginAll: 4 * PdfPageFormat.mm));

    final maxPageWidth = isA4 ? 794.0 : (is58mm ? 320.0 : 400.0);

    return PdfPreview(
      key: ValueKey('preview_${widget.data.documentId}_$formatParam'),
      build: (pageFormat) async {
        try {
          final path = widget.data.pdfPathForFormat(formatParam);
          final bytes = widget.data.isPdfPathAbsoluteFor(path)
              ? await widget.apiClient.getBytesAbsolute(path)
              : await widget.apiClient.getBytes(path);
          if (bytes.isNotEmpty) {
            return Uint8List.fromList(bytes);
          }
        } catch (_) {}
        return _buildLocalDocumentPdf(widget.data, formatParam);
      },
      allowPrinting: false,
      allowSharing: false,
      canChangeOrientation: false,
      canChangePageFormat: false,
      initialPageFormat: targetPageFormat,
      pageFormats: {
        isA4 ? 'A4' : (is58mm ? '58mm' : '80mm'): targetPageFormat,
      },
      maxPageWidth: maxPageWidth,
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
      loadingWidget: const Center(
        child: CircularProgressIndicator(color: AppTheme.success),
      ),
      onError: (context, error) => isA4
          ? _buildLocalA4CardFallback()
          : _buildThermalMonospaceCard(is58mm),
    );
  }

  /// Clean white paper card with dark #0F172A typography and subtle tint contrast badges.
  Widget _buildThermalMonospaceCard(bool is58mm) {
    final data = widget.data;
    final cardWidth = is58mm ? 290.0 : 380.0;
    final isPaid = data.dueAmount <= 0.001;

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
      child: Center(
        child: Container(
          width: cardWidth,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.12),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: DefaultTextStyle(
            style: const TextStyle(
              fontFamily: 'Courier',
              fontFamilyFallback: ['monospace'],
              color: Color(0xFF0F172A),
              fontSize: 12,
              height: 1.4,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Header
                Text(
                  data.companyName.toUpperCase(),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  data.displayTitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                if ((data.taxId ?? '').isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 11),
                  ),
                ],
                const SizedBox(height: 6),
                Text(
                  data.subtitleContext,
                  textAlign: TextAlign.center,
                  style:
                      const TextStyle(fontSize: 10, color: Color(0xFF475569)),
                ),

                const SizedBox(height: 12),
                const Text(
                    '--------------------------------------------------'),
                const SizedBox(height: 6),

                // Line items
                if (data.lines.isNotEmpty) ...[
                  for (final line in data.lines)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              '${line.quantity.toStringAsFixed(line.quantity.truncateToDouble() == line.quantity ? 0 : 2)}x ${line.name}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Text(
                              '${data.currencySymbol}${line.lineTotal.toStringAsFixed(2)}'),
                        ],
                      ),
                    ),
                ] else ...[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(data.title ?? 'Service / Order Payload'),
                      Text(
                          '${data.currencySymbol}${data.total.toStringAsFixed(2)}'),
                    ],
                  ),
                ],

                const SizedBox(height: 6),
                const Text(
                    '--------------------------------------------------'),
                const SizedBox(height: 6),

                // Financial summary
                if (data.subtotal > 0 && data.subtotal != data.total)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Subtotal:'),
                      Text(
                          '${data.currencySymbol}${data.subtotal.toStringAsFixed(2)}'),
                    ],
                  ),
                if (data.tax > 0)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('${data.taxLabel} (${data.taxRate}%):'),
                      Text(
                          '${data.currencySymbol}${data.tax.toStringAsFixed(2)}'),
                    ],
                  ),
                if (data.discount > 0)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Discount:'),
                      Text(
                          '-${data.currencySymbol}${data.discount.toStringAsFixed(2)}'),
                    ],
                  ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('TOTAL:',
                        style: TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 13)),
                    Text(
                      '${data.currencySymbol}${data.total.toStringAsFixed(2)}',
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                // Contrast Badges: subtle tint pills
                Center(
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: isPaid
                          ? const Color(0xFFECFDF5)
                          : const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      isPaid
                          ? 'PAID IN FULL'
                          : 'PAYMENT DUE: ${data.currencySymbol}${data.dueAmount.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: isPaid
                            ? const Color(0xFF065F46)
                            : const Color(0xFF991B1B),
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 16),
                const Center(
                  child: Text(
                    'THANK YOU FOR YOUR BUSINESS!',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildLocalA4CardFallback() {
    final data = widget.data;
    final isPaid = data.dueAmount <= 0.001;

    Widget tableHeader(String title, {TextAlign align = TextAlign.left}) =>
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
          child: Text(
            title,
            textAlign: align,
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 12,
              color: Color(0xFF334155),
            ),
          ),
        );

    Widget tableCell(String text,
            {TextAlign align = TextAlign.left, bool isBold = false}) =>
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
          child: Text(
            text,
            textAlign: align,
            style: TextStyle(
              fontSize: 12,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              color: const Color(0xFF0F172A),
            ),
          ),
        );

    Widget summaryLine(String label, String value,
            {bool isBold = false, Color? color}) =>
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: isBold ? 14 : 12,
                  fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
                  color: color ?? const Color(0xFF334155),
                ),
              ),
              Text(
                value,
                style: TextStyle(
                  fontSize: isBold ? 14 : 12,
                  fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
                  color: color ?? const Color(0xFF0F172A),
                ),
              ),
            ],
          ),
        );

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
      child: Center(
        child: Container(
          width: 620.0,
          constraints: const BoxConstraints(minHeight: 840.0),
          padding: const EdgeInsets.all(32),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(6),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.12),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          data.companyName,
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        if ((data.taxId ?? '').isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            '${data.isIndia ? 'GSTIN' : data.taxLabel}: ${data.taxId}',
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF64748B),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        (data.title ?? '${data.documentType.toUpperCase()} INVOICE').toUpperCase(),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '#${data.displayTitle}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF475569),
                        ),
                      ),
                      Text(
                        data.formattedTimestamp ??
                            DateFormat('d MMM yyyy, h:mm a')
                                .format(data.dateTime ?? DateTime.now()),
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Bill To: ${data.customerName ?? 'Walk-in Customer'}',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF1E293B),
                      ),
                    ),
                    Text(
                      data.subtitleContext,
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              // Items Table
              Table(
                columnWidths: const {
                  0: FlexColumnWidth(4),
                  1: FlexColumnWidth(1.2),
                  2: FlexColumnWidth(1.5),
                  3: FlexColumnWidth(1.5),
                },
                children: [
                  TableRow(
                    decoration: const BoxDecoration(
                      color: Color(0xFFF1F5F9),
                    ),
                    children: [
                      tableHeader('Item'),
                      tableHeader('Qty', align: TextAlign.right),
                      tableHeader('Unit Price', align: TextAlign.right),
                      tableHeader('Total', align: TextAlign.right),
                    ],
                  ),
                  if (data.lines.isNotEmpty)
                    for (final line in data.lines)
                      TableRow(
                        decoration: const BoxDecoration(
                          border: Border(
                            bottom: BorderSide(
                              color: Color(0xFFE2E8F0),
                              width: 0.8,
                            ),
                          ),
                        ),
                        children: [
                          tableCell(line.name),
                          tableCell(
                            line.quantity.toStringAsFixed(
                              line.quantity.truncateToDouble() == line.quantity
                                  ? 0
                                  : 2,
                            ),
                            align: TextAlign.right,
                          ),
                          tableCell(
                            '${data.currencySymbol}${line.unitPrice.toStringAsFixed(2)}',
                            align: TextAlign.right,
                          ),
                          tableCell(
                            '${data.currencySymbol}${line.lineTotal.toStringAsFixed(2)}',
                            align: TextAlign.right,
                            isBold: true,
                          ),
                        ],
                      )
                  else
                    TableRow(
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(
                            color: Color(0xFFE2E8F0),
                            width: 0.8,
                          ),
                        ),
                      ),
                      children: [
                        tableCell(data.title ?? 'Service / Order Details'),
                        tableCell('1', align: TextAlign.right),
                        tableCell(
                          '${data.currencySymbol}${data.total.toStringAsFixed(2)}',
                          align: TextAlign.right,
                        ),
                        tableCell(
                          '${data.currencySymbol}${data.total.toStringAsFixed(2)}',
                          align: TextAlign.right,
                          isBold: true,
                        ),
                      ],
                    ),
                ],
              ),
              const SizedBox(height: 24),
              // Totals
              Align(
                alignment: Alignment.centerRight,
                child: SizedBox(
                  width: 280,
                  child: Column(
                    children: [
                      if (data.subtotal > 0 && data.subtotal != data.total)
                        summaryLine('Subtotal',
                            '${data.currencySymbol}${data.subtotal.toStringAsFixed(2)}'),
                      if (data.discount > 0)
                        summaryLine('Discount',
                            '-${data.currencySymbol}${data.discount.toStringAsFixed(2)}',
                            color: const Color(0xFF16A34A)),
                      if (data.tax > 0)
                        summaryLine(data.taxLabel,
                            '+${data.currencySymbol}${data.tax.toStringAsFixed(2)}'),
                      const Divider(height: 16),
                      summaryLine('Total Amount',
                          '${data.currencySymbol}${data.total.toStringAsFixed(2)}',
                          isBold: true),
                      if (data.paidAmount != null)
                        summaryLine('Paid',
                            '${data.currencySymbol}${data.paidAmount!.toStringAsFixed(2)}'),
                      if (data.dueAmount > 0.001)
                        summaryLine('Due Amount',
                            '${data.currencySymbol}${data.dueAmount.toStringAsFixed(2)}',
                            isBold: true, color: const Color(0xFFDC2626)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                  decoration: BoxDecoration(
                    color: isPaid
                        ? const Color(0xFFECFDF5)
                        : const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    isPaid
                        ? 'PAID IN FULL'
                        : 'PAYMENT DUE: ${data.currencySymbol}${data.dueAmount.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: isPaid
                          ? const Color(0xFF065F46)
                          : const Color(0xFF991B1B),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

Future<Uint8List> _buildLocalDocumentPdf(
    UnifiedDocumentDispatchData data, String formatParam) async {
  final doc = pw.Document();
  final isA4 = formatParam == 'a4';
  final is58mm = formatParam == '58mm';
  final pageFormat = isA4
      ? PdfPageFormat.a4
      : (is58mm
          ? PdfPageFormat(58 * PdfPageFormat.mm, double.infinity,
              marginAll: 4 * PdfPageFormat.mm)
          : PdfPageFormat(80 * PdfPageFormat.mm, double.infinity,
              marginAll: 4 * PdfPageFormat.mm));

  pw.Font? regularFont;
  pw.Font? boldFont;
  try {
    regularFont = await PdfGoogleFonts.notoSansRegular();
    boldFont = await PdfGoogleFonts.notoSansBold();
  } catch (_) {
    regularFont = null;
    boldFont = null;
  }

  final theme = regularFont != null
      ? pw.ThemeData.withFont(base: regularFont, bold: boldFont)
      : null;

  doc.addPage(
    pw.Page(
      pageFormat: pageFormat,
      theme: theme,
      build: (context) => isA4
          ? _buildLocalA4Layout(data)
          : _buildLocalThermalLayout(data, is58mm: is58mm),
    ),
  );

  return doc.save();
}

pw.Widget _buildLocalA4Layout(UnifiedDocumentDispatchData data) {
  final currency = data.currencySymbol;
  String fmtPrice(double amount) => '$currency${amount.toStringAsFixed(2)}';

  return pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.start,
    children: [
      pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(
                data.companyName,
                style:
                    pw.TextStyle(fontSize: 18, fontWeight: pw.FontWeight.bold),
              ),
              if ((data.taxId ?? '').isNotEmpty) ...[
                pw.SizedBox(height: 2),
                pw.Text(
                  '${data.isIndia ? 'GSTIN' : data.taxLabel}: ${data.taxId}',
                  style:
                      const pw.TextStyle(fontSize: 10, color: PdfColors.grey700),
                ),
              ],
            ],
          ),
          pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.end,
            children: [
              pw.Text(
                (data.title ?? '${data.documentType.toUpperCase()} INVOICE')
                    .toUpperCase(),
                style:
                    pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
              ),
              pw.SizedBox(height: 2),
              pw.Text(
                '#${data.displayTitle}',
                style:
                    const pw.TextStyle(fontSize: 11, color: PdfColors.grey700),
              ),
              pw.Text(
                data.formattedTimestamp ??
                    DateFormat('d MMM yyyy, h:mm a')
                        .format(data.dateTime ?? DateTime.now()),
                style:
                    const pw.TextStyle(fontSize: 9, color: PdfColors.grey600),
              ),
            ],
          ),
        ],
      ),
      pw.SizedBox(height: 16),
      if ((data.customerName ?? '').isNotEmpty ||
          (data.tableName ?? '').isNotEmpty ||
          (data.deviceModel ?? '').isNotEmpty)
        pw.Container(
          padding: const pw.EdgeInsets.all(8),
          decoration: const pw.BoxDecoration(
            color: PdfColors.grey100,
            borderRadius: pw.BorderRadius.all(pw.Radius.circular(4)),
          ),
          child: pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            children: [
              if ((data.customerName ?? '').isNotEmpty)
                pw.Text('Bill To: ${data.customerName}',
                    style: pw.TextStyle(
                        fontSize: 10, fontWeight: pw.FontWeight.bold)),
              pw.Text(data.subtitleContext,
                  style: const pw.TextStyle(
                      fontSize: 9, color: PdfColors.grey700)),
            ],
          ),
        ),
      pw.SizedBox(height: 16),
      pw.Table(
        border: const pw.TableBorder(
          top: pw.BorderSide(width: 0.5, color: PdfColors.grey400),
          bottom: pw.BorderSide(width: 0.5, color: PdfColors.grey400),
          horizontalInside:
              pw.BorderSide(width: 0.3, color: PdfColors.grey300),
        ),
        columnWidths: const {
          0: pw.FlexColumnWidth(4),
          1: pw.FlexColumnWidth(1.2),
          2: pw.FlexColumnWidth(1.5),
          3: pw.FlexColumnWidth(1.5),
        },
        children: [
          pw.TableRow(
            decoration: const pw.BoxDecoration(color: PdfColors.grey200),
            children: [
              _pdfCell('Item', bold: true),
              _pdfCell('Qty', bold: true, align: pw.TextAlign.right),
              _pdfCell('Unit Price', bold: true, align: pw.TextAlign.right),
              _pdfCell('Amount', bold: true, align: pw.TextAlign.right),
            ],
          ),
          if (data.lines.isNotEmpty)
            for (final line in data.lines)
              pw.TableRow(
                children: [
                  _pdfCell(line.name),
                  _pdfCell(
                    line.quantity.toStringAsFixed(
                        line.quantity.truncateToDouble() == line.quantity
                            ? 0
                            : 2),
                    align: pw.TextAlign.right,
                  ),
                  _pdfCell(fmtPrice(line.unitPrice),
                      align: pw.TextAlign.right),
                  _pdfCell(fmtPrice(line.lineTotal),
                      align: pw.TextAlign.right),
                ],
              )
          else
            pw.TableRow(
              children: [
                _pdfCell(data.title ?? 'Service / Order Details'),
                _pdfCell('1', align: pw.TextAlign.right),
                _pdfCell(fmtPrice(data.total), align: pw.TextAlign.right),
                _pdfCell(fmtPrice(data.total), align: pw.TextAlign.right),
              ],
            ),
        ],
      ),
      pw.SizedBox(height: 16),
      pw.Align(
        alignment: pw.Alignment.centerRight,
        child: pw.SizedBox(
          width: 220,
          child: pw.Column(
            children: [
              if (data.subtotal > 0 && data.subtotal != data.total)
                _pdfTotalRow('Subtotal', fmtPrice(data.subtotal)),
              if (data.discount > 0)
                _pdfTotalRow('Discount', '-${fmtPrice(data.discount)}'),
              if (data.tax > 0)
                if (data.isIndia) ...[
                  _pdfTotalRow('CGST', '+${fmtPrice(data.tax / 2)}'),
                  _pdfTotalRow('SGST', '+${fmtPrice(data.tax / 2)}'),
                ] else
                  _pdfTotalRow(data.taxLabel, '+${fmtPrice(data.tax)}'),
              pw.Divider(thickness: 0.5, color: PdfColors.grey400),
              _pdfTotalRow('Total', fmtPrice(data.total), isBold: true),
              if (data.paidAmount != null)
                _pdfTotalRow('Paid', fmtPrice(data.paidAmount!)),
              if (data.dueAmount > 0.001)
                _pdfTotalRow('Due', fmtPrice(data.dueAmount), isBold: true),
            ],
          ),
        ),
      ),
    ],
  );
}

pw.Widget _buildLocalThermalLayout(UnifiedDocumentDispatchData data,
    {required bool is58mm}) {
  final currency = data.currencySymbol;
  String fmtPrice(double amount) => '$currency${amount.toStringAsFixed(2)}';
  final fontSize = is58mm ? 7.0 : 8.5;

  return pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.stretch,
    children: [
      pw.Center(
        child: pw.Text(
          data.companyName.toUpperCase(),
          style: pw.TextStyle(
            fontSize: fontSize + 3,
            fontWeight: pw.FontWeight.bold,
          ),
        ),
      ),
      if ((data.taxId ?? '').isNotEmpty)
        pw.Center(
          child: pw.Text(
            '${data.isIndia ? 'GSTIN' : data.taxLabel}: ${data.taxId}',
            style: pw.TextStyle(fontSize: fontSize - 1),
          ),
        ),
      pw.SizedBox(height: 3),
      pw.Center(
        child: pw.Text(
          '*** ${(data.title ?? 'TAX INVOICE / RECEIPT').toUpperCase()} ***',
          style: pw.TextStyle(
            fontSize: fontSize,
            fontWeight: pw.FontWeight.bold,
          ),
        ),
      ),
      pw.Center(
        child: pw.Text(
          '------------------------------------------------',
          style: pw.TextStyle(fontSize: fontSize - 1),
        ),
      ),
      pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text('Receipt #: ${data.displayTitle}',
              style: pw.TextStyle(fontSize: fontSize - 1)),
          pw.Text(
            data.formattedTimestamp ??
                DateFormat('dd/MM/yyyy HH:mm')
                    .format(data.dateTime ?? DateTime.now()),
            style: pw.TextStyle(fontSize: fontSize - 1),
          ),
        ],
      ),
      if ((data.customerName ?? '').isNotEmpty)
        pw.Text('Customer: ${data.customerName}',
            style: pw.TextStyle(fontSize: fontSize - 1)),
      pw.Center(
        child: pw.Text(
          '------------------------------------------------',
          style: pw.TextStyle(fontSize: fontSize - 1),
        ),
      ),
      for (final line in data.lines)
        pw.Padding(
          padding: const pw.EdgeInsets.symmetric(vertical: 1.5),
          child: pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            children: [
              pw.Expanded(
                child: pw.Text(
                  '${line.quantity.toStringAsFixed(line.quantity.truncateToDouble() == line.quantity ? 0 : 2)}x ${line.name}',
                  style: pw.TextStyle(fontSize: fontSize),
                ),
              ),
              pw.Text(fmtPrice(line.lineTotal),
                  style: pw.TextStyle(fontSize: fontSize)),
            ],
          ),
        ),
      pw.Center(
        child: pw.Text(
          '------------------------------------------------',
          style: pw.TextStyle(fontSize: fontSize - 1),
        ),
      ),
      if (data.subtotal > 0 && data.subtotal != data.total)
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('Subtotal:', style: pw.TextStyle(fontSize: fontSize)),
            pw.Text(fmtPrice(data.subtotal),
                style: pw.TextStyle(fontSize: fontSize)),
          ],
        ),
      if (data.discount > 0)
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('Discount:', style: pw.TextStyle(fontSize: fontSize)),
            pw.Text('-${fmtPrice(data.discount)}',
                style: pw.TextStyle(fontSize: fontSize)),
          ],
        ),
      if (data.tax > 0)
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('${data.taxLabel}:',
                style: pw.TextStyle(fontSize: fontSize)),
            pw.Text('+${fmtPrice(data.tax)}',
                style: pw.TextStyle(fontSize: fontSize)),
          ],
        ),
      pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text('TOTAL:',
              style: pw.TextStyle(
                  fontSize: fontSize + 1, fontWeight: pw.FontWeight.bold)),
          pw.Text(fmtPrice(data.total),
              style: pw.TextStyle(
                  fontSize: fontSize + 1, fontWeight: pw.FontWeight.bold)),
        ],
      ),
      if (data.paidAmount != null)
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('Paid:', style: pw.TextStyle(fontSize: fontSize)),
            pw.Text(fmtPrice(data.paidAmount!),
                style: pw.TextStyle(fontSize: fontSize)),
          ],
        ),
      if (data.dueAmount > 0.001)
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('Due:',
                style: pw.TextStyle(
                    fontSize: fontSize, fontWeight: pw.FontWeight.bold)),
            pw.Text(fmtPrice(data.dueAmount),
                style: pw.TextStyle(
                    fontSize: fontSize, fontWeight: pw.FontWeight.bold)),
          ],
        ),
      pw.SizedBox(height: 6),
      pw.Center(
        child: pw.Text(
          '*** THANK YOU ***',
          style: pw.TextStyle(
              fontSize: fontSize, fontWeight: pw.FontWeight.bold),
        ),
      ),
    ],
  );
}

pw.Widget _pdfCell(String text,
    {bool bold = false, pw.TextAlign align = pw.TextAlign.left}) {
  return pw.Padding(
    padding: const pw.EdgeInsets.symmetric(vertical: 4, horizontal: 4),
    child: pw.Text(
      text,
      textAlign: align,
      style: pw.TextStyle(
        fontSize: 9,
        fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
      ),
    ),
  );
}

pw.Widget _pdfTotalRow(String label, String value, {bool isBold = false}) {
  return pw.Padding(
    padding: const pw.EdgeInsets.symmetric(vertical: 2),
    child: pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
      children: [
        pw.Text(
          label,
          style: pw.TextStyle(
            fontSize: 9.5,
            fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal,
          ),
        ),
        pw.Text(
          value,
          style: pw.TextStyle(
            fontSize: 9.5,
            fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal,
          ),
        ),
      ],
    ),
  );
}
