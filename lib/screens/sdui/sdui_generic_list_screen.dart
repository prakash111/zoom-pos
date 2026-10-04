import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/stores/store_provider.dart';
import '../../features/auth/auth_provider.dart';
import '../../widgets/dynamic_custom_fields_editor.dart';
import '../hrm/widgets/pos_clock_in_dialog.dart';

String _resolveFullUrl(String endpoint, String baseUrl) {
  if (endpoint.startsWith('http://') || endpoint.startsWith('https://')) {
    return endpoint;
  }
  final cleanBase = baseUrl.replaceAll(RegExp(r'/+$'), '');
  final cleanPath = endpoint.replaceAll(RegExp(r'^/+'), '');
  return '$cleanBase/$cleanPath';
}

/// Centralized SDUI Dynamic Form Modal Sheet
class SduiDynamicFormSheet extends StatefulWidget {
  final String formEndpoint;
  final Map<String, dynamic>? initialSchema;
  final VoidCallback? onSubmitted;
  final bool isBottomSheet;

  const SduiDynamicFormSheet({
    Key? key,
    required this.formEndpoint,
    this.initialSchema,
    this.onSubmitted,
    this.isBottomSheet = true,
  }) : super(key: key);

  @override
  State<SduiDynamicFormSheet> createState() => _SduiDynamicFormSheetState();
}

class _SduiDynamicFormSheetState extends State<SduiDynamicFormSheet> {
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _errorMessage;
  String _title = 'Form';
  String _submitUrl = '';
  String? _submitButtonLabel;
  String _method = 'POST';
  List<Map<String, dynamic>> _fields = [];
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, dynamic> _fieldValues = {};
  final Map<String, List<Map<String, String>>> _keyValuePairs = {};
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    if (widget.initialSchema != null) {
      _loadSchemaData(widget.initialSchema!);
    } else {
      _fetchFormSchema();
    }
  }

  void _loadSchemaData(Map<String, dynamic> raw) {
    final schema = raw['schema'] is Map ? (raw['schema'] as Map) : raw;

    _title = schema['title']?.toString() ?? 'Form';
    _submitUrl = schema['submit_url']?.toString() ?? schema['submit_endpoint']?.toString() ?? schema['endpoint']?.toString() ?? '';
    _submitButtonLabel = (schema['submit_button'] is Map ? schema['submit_button']['label'] : null) ??
        schema['submit_text']?.toString() ??
        schema['submit_label']?.toString() ??
        'Save';
    _method = schema['method']?.toString().toUpperCase() ?? 'POST';

    final rawFields = schema['fields'] as List<dynamic>? ?? [];
    _fields = rawFields.whereType<Map>().map((f) => Map<String, dynamic>.from(f)).toList();

    final initialValues = (schema['initial_values'] as Map?) ?? (schema['data'] as Map?) ?? {};
    for (final field in _fields) {
      final name = field['name']?.toString() ?? '';
      final type = field['type']?.toString().toLowerCase() ?? 'text';
      if (name.isNotEmpty) {
        if (type == 'key_value_pairs') {
          final rawVal = field['value'] ?? initialValues[name];
          if (rawVal is List) {
            _keyValuePairs[name] = rawVal.whereType<Map>().map((m) {
              return {
                'name': (m['name'] ?? m['key'] ?? '').toString(),
                'value': (m['value'] ?? '').toString(),
              };
            }).toList();
          } else if (rawVal is Map) {
            _keyValuePairs[name] = rawVal.entries.map((e) {
              return {
                'name': e.key.toString(),
                'value': e.value.toString(),
              };
            }).toList();
          } else {
            _keyValuePairs[name] = [];
          }
        } else if (type == 'switch' || type == 'toggle' || type == 'toggle_switch') {
          final rawVal = field['value'] ?? field['default'] ?? initialValues[name] ?? false;
          final isChecked = (rawVal == true || rawVal == 1 || rawVal == '1' || rawVal == 'true');
          _controllers[name] = TextEditingController(text: isChecked ? '1' : '0');
          _fieldValues[name] = isChecked;
        } else {
          final defaultVal = field['value'] ?? field['default'] ?? initialValues[name] ?? '';
          _controllers[name] = TextEditingController(text: defaultVal.toString());
          _fieldValues[name] = defaultVal;
        }
      }
    }
    _isLoading = false;
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _fetchFormSchema() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final authProvider = Provider.of<AuthProvider?>(context, listen: false);
      final storeProvider = Provider.of<StoreProvider?>(context, listen: false);
      final token = authProvider?.token ?? '';
      final baseUrl = AppConfig.defaultBaseUrl;
      final fullUrl = _resolveFullUrl(widget.formEndpoint, baseUrl);

      final response = await http.get(
        Uri.parse(fullUrl),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
          if (storeProvider?.current?.id != null)
            'X-Store-Id': storeProvider!.current!.id.toString(),
        },
      );

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final data = jsonDecode(response.body);
        setState(() {
          _loadSchemaData(data is Map<String, dynamic> ? data : Map<String, dynamic>.from(data));
        });
      } else {
        setState(() {
          _errorMessage = 'Failed to load form (${response.statusCode})';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Connection error: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _submitForm() async {
    if (!(_formKey.currentState?.validate() ?? true)) {
      return;
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      final authProvider = Provider.of<AuthProvider?>(context, listen: false);
      final storeProvider = Provider.of<StoreProvider?>(context, listen: false);
      final token = authProvider?.token ?? '';
      final baseUrl = AppConfig.defaultBaseUrl;
      final fullUrl = _resolveFullUrl(_submitUrl.isNotEmpty ? _submitUrl : widget.formEndpoint, baseUrl);

      final payload = <String, dynamic>{};
      for (final field in _fields) {
        final name = field['name']?.toString() ?? '';
        final type = field['type']?.toString().toLowerCase() ?? 'text';
        if (type == 'key_value_pairs') {
          payload[name] = _keyValuePairs[name] ?? [];
        } else if (type == 'switch' || type == 'toggle' || type == 'toggle_switch') {
          payload[name] = (_fieldValues[name] == true || _fieldValues[name] == 1 || _fieldValues[name] == '1');
        } else if (name.isNotEmpty) {
          final text = _controllers[name]?.text.trim() ?? '';
          if (type == 'number') {
            payload[name] = num.tryParse(text) ?? text;
          } else {
            payload[name] = text;
          }
        }
      }

      if (storeProvider?.current?.id != null && !payload.containsKey('store_id')) {
        payload['store_id'] = storeProvider!.current!.id;
      }

      final uri = Uri.parse(fullUrl);
      final headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        if (storeProvider?.current?.id != null)
          'X-Store-Id': storeProvider!.current!.id.toString(),
      };

      http.Response response;
      if (_method == 'PUT') {
        response = await http.put(uri, headers: headers, body: jsonEncode(payload));
      } else {
        response = await http.post(uri, headers: headers, body: jsonEncode(payload));
      }

      final data = jsonDecode(response.body);

      if (response.statusCode >= 200 && response.statusCode < 300 && (data['success'] != false)) {
        if (!mounted) return;
        if (widget.isBottomSheet) {
          Navigator.of(context).pop(true);
        }
        widget.onSubmitted?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(data['message'] ?? 'Saved successfully!'),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
        if (!widget.isBottomSheet) {
          setState(() {
            _isSubmitting = false;
          });
        }
      } else {
        setState(() {
          _errorMessage = data['message'] ?? 'Form submission failed.';
          _isSubmitting = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error submitting: $e';
        _isSubmitting = false;
      });
    }
  }

  Widget _buildFieldWidget(Map<String, dynamic> field) {
    final name = field['name']?.toString() ?? '';
    final label = field['label']?.toString() ?? name;
    final type = field['type']?.toString().toLowerCase() ?? 'text';
    final isRequired = field['required'] == true;
    final controller = _controllers[name];

    if (type == 'key_value_pairs') {
      final initial = _keyValuePairs[name] ?? [];
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: DynamicCustomFieldsEditor(
          initialFields: initial,
          onChanged: (updated) {
            _keyValuePairs[name] = updated;
          },
        ),
      );
    }

    if (type == 'switch' || type == 'toggle' || type == 'toggle_switch') {
      final isChecked = _fieldValues[name] == true || _fieldValues[name] == 1 || _fieldValues[name] == '1' || _fieldValues[name] == 'true';
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFF334155)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    if (field['helper_text'] != null && field['helper_text'].toString().isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        field['helper_text'].toString(),
                        style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                      ),
                    ],
                  ],
                ),
              ),
              Switch(
                value: isChecked,
                activeThumbColor: const Color(0xFF10B981),
                onChanged: (val) {
                  setState(() {
                    _fieldValues[name] = val;
                    _controllers[name]?.text = val ? '1' : '0';
                  });
                },
              ),
            ],
          ),
        ),
      );
    }

    if (type == 'select' && field['options'] != null) {
      final rawOptions = field['options'];
      final List<DropdownMenuItem<String>> items = [];
      if (rawOptions is Map) {
        rawOptions.forEach((k, v) {
          items.add(DropdownMenuItem(value: k.toString(), child: Text(v.toString())));
        });
      } else if (rawOptions is List) {
        for (final opt in rawOptions) {
          if (opt is Map) {
            items.add(DropdownMenuItem(value: opt['value']?.toString() ?? '', child: Text(opt['label']?.toString() ?? '')));
          } else {
            items.add(DropdownMenuItem(value: opt.toString(), child: Text(opt.toString())));
          }
        }
      }

      String? currentVal = controller?.text;
      if (currentVal == null || currentVal.isEmpty) {
        currentVal = items.isNotEmpty ? items.first.value : null;
        controller?.text = currentVal ?? '';
      }

      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: DropdownButtonFormField<String>(
          initialValue: items.any((i) => i.value == currentVal) ? currentVal : null,
          dropdownColor: const Color(0xFF1E293B),
          style: const TextStyle(color: Colors.white, fontSize: 14),
          decoration: InputDecoration(
            labelText: label + (isRequired ? ' *' : ''),
            labelStyle: const TextStyle(color: Color(0xFF94A3B8)),
            filled: true,
            fillColor: const Color(0xFF1E293B),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
          ),
          items: items,
          onChanged: (val) {
            if (val != null) controller?.text = val;
          },
        ),
      );
    }

    if (type == 'date') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: InkWell(
          onTap: () async {
            final now = DateTime.now();
            final picked = await showDatePicker(
              context: context,
              initialDate: now,
              firstDate: DateTime(2020),
              lastDate: DateTime(2035),
            );
            if (picked != null) {
              controller?.text = "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
              setState(() {});
            }
          },
          child: IgnorePointer(
            child: TextFormField(
              controller: controller,
              style: const TextStyle(color: Colors.white, fontSize: 14),
              decoration: InputDecoration(
                labelText: label + (isRequired ? ' *' : ''),
                labelStyle: const TextStyle(color: Color(0xFF94A3B8)),
                suffixIcon: const Icon(Icons.calendar_today, color: Color(0xFF94A3B8), size: 20),
                filled: true,
                fillColor: const Color(0xFF1E293B),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
              ),
              validator: isRequired ? (v) => (v == null || v.isEmpty) ? '$label is required' : null : null,
            ),
          ),
        ),
      );
    }

    TextInputType keyboardType = TextInputType.text;
    bool obscureText = false;
    int maxLines = 1;

    if (type == 'phone') {
      keyboardType = TextInputType.phone;
    } else if (type == 'number') {
      keyboardType = TextInputType.number;
    } else if (type == 'password') {
      obscureText = true;
    } else if (type == 'textarea') {
      maxLines = 3;
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscureText,
        maxLines: maxLines,
        maxLength: field['max_length'] is int ? field['max_length'] : null,
        style: const TextStyle(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          labelText: label + (isRequired ? ' *' : ''),
          labelStyle: const TextStyle(color: Color(0xFF94A3B8)),
          helperText: field['helper_text']?.toString(),
          helperStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
          counterStyle: const TextStyle(color: Color(0xFF64748B)),
          filled: true,
          fillColor: const Color(0xFF1E293B),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF334155))),
        ),
        validator: isRequired
            ? (v) => (v == null || v.trim().isEmpty) ? '$label is required' : null
            : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final formBody = Column(
      mainAxisSize: widget.isBottomSheet ? MainAxisSize.min : MainAxisSize.max,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.isBottomSheet) ...[
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFF334155),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, color: Color(0xFF94A3B8)),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),
          const Divider(color: Color(0xFF334155), height: 1),
        ],
        if (_isLoading)
          const Padding(
            padding: EdgeInsets.all(40),
            child: Center(
              child: CircularProgressIndicator(color: Color(0xFF10B981)),
            ),
          )
        else if (_errorMessage != null)
          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, color: Colors.redAccent, size: 40),
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.redAccent, fontSize: 14),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF334155)),
                  onPressed: _fetchFormSchema,
                  child: const Text('Retry', style: TextStyle(color: Colors.white)),
                ),
              ],
            ),
          )
        else
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final field in _fields) _buildFieldWidget(field),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF10B981),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        onPressed: _isSubmitting ? null : _submitForm,
                        child: _isSubmitting
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(
                                _submitButtonLabel ?? 'Save',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 15,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );

    if (!widget.isBottomSheet) {
      return formBody;
    }

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        decoration: const BoxDecoration(
          color: Color(0xFF0F172A),
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: formBody,
      ),
    );
  }
}

/// Centralized SDUI Item Detail Bottom Sheet
class SduiItemDetailSheet extends StatelessWidget {
  final Map<String, dynamic> item;
  final VoidCallback? onReload;

  const SduiItemDetailSheet({
    Key? key,
    required this.item,
    this.onReload,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final title = item['title'] ?? item['name'] ?? 'Record Details';
    final subtitle = item['subtitle'] ?? item['role'] ?? item['designation'] ?? '';
    final badge = item['badge'] ?? item['badge_text'] ?? item['status'] ?? '';
    final badgeColorHex = item['badge_color']?.toString() ?? '#10B981';
    final badgeColor = _parseColor(badgeColorHex);
    final avatarText = (item['avatar_text'] ?? (title.toString().isNotEmpty ? title.toString().substring(0, 1) : 'R')).toString().toUpperCase();

    final status = (item['status'] ?? item['badge'] ?? '').toString().toLowerCase();
    final isLeaveItem = item.containsKey('leave_id') ||
        item.containsKey('leave_type') ||
        (item.containsKey('reason') && ['pending', 'approved', 'rejected'].contains(status)) ||
        (item['subtitle']?.toString().contains(' - ') == true && ['pending', 'approved', 'rejected'].contains(status));

    final details = <Map<String, String>>[];
    for (final entry in item.entries) {
      final key = entry.key.toLowerCase();
      if (['type', 'action', 'actions', 'action_type', 'action_target', 'avatar_text', 'avatar_bg', 'avatar_fg', 'badge_color', 'badge_style', 'meta_items', 'target', 'leave_id', 'id'].contains(key)) {
        continue;
      }
      final val = entry.value;
      if (val != null && val.toString().isNotEmpty && val is! Map && val is! List) {
        final label = key.replaceAll('_', ' ').split(' ').map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '').join(' ');
        details.add({'label': label, 'value': val.toString()});
      }
    }

    final rawMeta = item['meta_items'] as List<dynamic>? ?? const [];
    for (final m in rawMeta) {
      if (m is Map) {
        final text = (m['text'] ?? m['label'] ?? m['value'])?.toString() ?? '';
        if (text.isNotEmpty) {
          details.add({'label': (m['icon'] ?? 'Info').toString().toUpperCase(), 'value': text});
        }
      }
    }

    final hasActionTarget = item['action_target'] != null;

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.75,
        ),
        decoration: const BoxDecoration(
          color: Color(0xFF0F172A),
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 12, bottom: 8),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFF334155),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 12, 16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: const Color(0xFF1E293B),
                    child: Text(
                      avatarText,
                      style: const TextStyle(
                        color: Color(0xFF10B981),
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title.toString(),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (subtitle.toString().isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            subtitle.toString(),
                            style: const TextStyle(
                              color: Color(0xFF94A3B8),
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (badge.toString().isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: badgeColor.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: badgeColor, width: 1),
                      ),
                      child: Text(
                        badge.toString(),
                        style: TextStyle(
                          color: badgeColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Color(0xFF94A3B8)),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            const Divider(color: Color(0xFF334155), height: 1),
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                padding: const EdgeInsets.all(20),
                itemCount: details.length,
                separatorBuilder: (_, __) => const Divider(color: Color(0xFF1E293B), height: 16),
                itemBuilder: (ctx, idx) {
                  final row = details[idx];
                  return Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        row['label'] ?? '',
                        style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Text(
                          row['value'] ?? '',
                          textAlign: TextAlign.end,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            if (item['history'] is List && (item['history'] as List).isNotEmpty) ...[
              const Padding(
                padding: EdgeInsets.fromLTRB(20, 4, 20, 6),
                child: Row(
                  children: [
                    Icon(Icons.history_rounded, color: Color(0xFF64748B), size: 14),
                    SizedBox(width: 4),
                    Text(
                      'Recent Top-ups & Activity:',
                      style: TextStyle(color: Color(0xFF64748B), fontSize: 11, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  children: [
                    for (final tx in (item['history'] as List))
                      if (tx is Map)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 3),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                children: [
                                  Icon(
                                    tx['is_credit'] == true ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
                                    color: tx['is_credit'] == true ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                                    size: 14,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    "${tx['type'] ?? 'Top-up'} (${tx['method'] ?? 'Cash'})",
                                    style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 12),
                                  ),
                                  if (tx['bonus'] != null && tx['bonus'].toString().isNotEmpty) ...[
                                    const SizedBox(width: 4),
                                    Text(
                                      tx['bonus'].toString(),
                                      style: const TextStyle(color: Color(0xFFF59E0B), fontSize: 11, fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                ],
                              ),
                              Text(
                                "${tx['is_credit'] == true ? '+' : '-'}${tx['amount'] ?? ''}",
                                style: TextStyle(
                                  color: tx['is_credit'] == true ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
            ],
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              child: isLeaveItem
                  ? _buildLeaveActionButtons(context, item, () {
                      onReload?.call();
                    })
                  : Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFF334155)),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            onPressed: () => Navigator.of(context).pop(),
                            child: const Text('Close', style: TextStyle(color: Colors.white)),
                          ),
                        ),
                        if (hasActionTarget) ...[
                          const SizedBox(width: 12),
                          Expanded(
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF10B981),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              onPressed: () {
                                Navigator.of(context).pop();
                                showModalBottomSheet(
                                  context: context,
                                  isScrollControlled: true,
                                  backgroundColor: const Color(0xFF0F172A),
                                  shape: const RoundedRectangleBorder(
                                    borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                                  ),
                                  builder: (ctx) => SduiDynamicFormSheet(
                                    formEndpoint: item['action_target'].toString(),
                                    onSubmitted: onReload,
                                  ),
                                ).then((val) {
                                  if (val == true) onReload?.call();
                                });
                              },
                              child: Text(
                                (item['action_label'] ?? (item['action_target'].toString().contains('topup') ? '+ Top-up' : 'Edit')).toString(),
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLeaveActionButtons(BuildContext context, Map<String, dynamic> leaveItem, VoidCallback onSuccess) {
    final status = (leaveItem['status'] ?? leaveItem['badge'] ?? '').toString().toLowerCase();
    final leaveId = leaveItem['id'] ?? leaveItem['leave_id'];

    if (status != 'pending') {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF1E293B),
            padding: const EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          onPressed: () => Navigator.pop(context),
          child: const Text('Close', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      );
    }

    return Row(
      children: [
        // Reject Button
        Expanded(
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            icon: const Icon(Icons.close, color: Colors.white, size: 18),
            label: const Text('Reject', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            onPressed: () => _updateLeaveStatus(context, leaveId, 'rejected', onSuccess),
          ),
        ),
        const SizedBox(width: 12),
        // Approve Button
        Expanded(
          child: ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF10B981),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            icon: const Icon(Icons.check, color: Colors.white, size: 18),
            label: const Text('Approve', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            onPressed: () => _updateLeaveStatus(context, leaveId, 'approved', onSuccess),
          ),
        ),
      ],
    );
  }

  Future<void> _updateLeaveStatus(BuildContext context, dynamic leaveId, String newStatus, VoidCallback onSuccess) async {
    final authProvider = Provider.of<AuthProvider?>(context, listen: false);
    final storeProvider = Provider.of<StoreProvider?>(context, listen: false);
    final token = authProvider?.token ?? '';
    final baseUrl = AppConfig.defaultBaseUrl;
    final url = _resolveFullUrl('api/tenant/hrm/leaves/$leaveId/status', baseUrl);

    try {
      final response = await http.post(
        Uri.parse(url),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
          if (storeProvider?.current?.id != null)
            'X-Store-Id': storeProvider!.current!.id.toString(),
        },
        body: jsonEncode({'status': newStatus}),
      );

      final data = jsonDecode(response.body);
      if (response.statusCode >= 200 && response.statusCode < 300 && (data['success'] == true || data['success'] == 1)) {
        if (context.mounted) {
          Navigator.pop(context, true);
        }
        onSuccess(); // Triggers reload of Leave Requests list
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Leave request ${newStatus == 'approved' ? 'approved' : 'rejected'} successfully.'),
              backgroundColor: newStatus == 'approved' ? const Color(0xFF10B981) : const Color(0xFFEF4444),
            ),
          );
        }
      } else {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Failed to update leave status.')),
          );
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Network error. Could not update status.')),
        );
      }
    }
  }

  Color _parseColor(String hex) {
    try {
      final clean = hex.replaceAll('#', '').trim();
      if (clean.length == 6) {
        return Color(int.parse('FF$clean', radix: 16));
      } else if (clean.length == 8) {
        return Color(int.parse(clean, radix: 16));
      }
    } catch (_) {}
    return const Color(0xFF10B981);
  }
}

/// Generic SDUI List Screen Component conforming to Section 2 specification
class SduiGenericListScreen extends StatefulWidget {
  final String endpoint;
  final String? initialTitle;

  const SduiGenericListScreen({
    Key? key,
    required this.endpoint,
    this.initialTitle,
  }) : super(key: key);

  @override
  State<SduiGenericListScreen> createState() => _SduiGenericListScreenState();
}

class _SduiGenericListScreenState extends State<SduiGenericListScreen> {
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _data;
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchData({String query = ''}) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final authProvider = Provider.of<AuthProvider?>(context, listen: false);
      final storeProvider = Provider.of<StoreProvider?>(context, listen: false);
      final token = authProvider?.token ?? '';
      final baseUrl = AppConfig.defaultBaseUrl;

      String fullUrl = widget.endpoint;
      if (query.isNotEmpty) {
        final separator = fullUrl.contains('?') ? '&' : '?';
        fullUrl = '$fullUrl${separator}search=${Uri.encodeComponent(query)}';
      }

      if (!fullUrl.startsWith('http://') && !fullUrl.startsWith('https://')) {
        fullUrl = '${baseUrl.replaceAll(RegExp(r'/+$'), '')}/${fullUrl.replaceAll(RegExp(r'^/+'), '')}';
      }

      final response = await http.get(
        Uri.parse(fullUrl),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
          if (storeProvider?.current?.id != null)
            'X-Store-Id': storeProvider!.current!.id.toString(),
        },
      );

      if (response.statusCode == 200) {
        final decoded = jsonDecode(response.body);
        setState(() {
          _data = decoded is Map<String, dynamic> ? decoded : Map<String, dynamic>.from(decoded);
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = 'Failed to load list (${response.statusCode})';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Error: $e';
        _isLoading = false;
      });
    }
  }

  void _refreshCurrentView() {
    _fetchData(query: _searchController.text.trim());
  }

  Widget _buildSearchBar() {
    String placeholder = 'Search customer by name or phone...';
    if (_data?['components'] is List) {
      final comp = (_data!['components'] as List).firstWhere(
        (c) => c is Map && c['type'] == 'search_bar',
        orElse: () => null,
      );
      if (comp is Map && comp['placeholder'] != null) {
        placeholder = comp['placeholder'].toString();
      }
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16.0, 10.0, 16.0, 6.0),
      child: TextField(
        controller: _searchController,
        style: const TextStyle(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          hintText: placeholder,
          hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
          prefixIcon: const Icon(Icons.search, color: Color(0xFF64748B), size: 20),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear, color: Color(0xFF64748B), size: 18),
                  onPressed: () {
                    _searchController.clear();
                    setState(() {});
                    _fetchData(query: '');
                  },
                )
              : null,
          filled: true,
          fillColor: const Color(0xFF1E293B),
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(color: Color(0xFF334155), width: 0.5),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(color: Color(0xFF10B981), width: 1.0),
          ),
        ),
        onChanged: (value) {
          setState(() {});
          if (_searchDebounce?.isActive ?? false) _searchDebounce!.cancel();
          _searchDebounce = Timer(const Duration(milliseconds: 350), () {
            _fetchData(query: value.trim());
          });
        },
      ),
    );
  }

  IconData _resolveIcon(dynamic icon) {
    final name = icon?.toString().toLowerCase().trim() ?? '';
    return switch (name) {
      'person_add' => Icons.person_add,
      'punch_clock' || 'schedule' => Icons.access_time,
      'event_note' || 'calendar_today' => Icons.event_note,
      'calculate' || 'payments' => Icons.calculate,
      'military_tech' => Icons.military_tech,
      'account_balance_wallet' || 'wallet' => Icons.account_balance_wallet,
      'stars' || 'star' => Icons.stars,
      'groups' || 'group' || 'people' => Icons.groups,
      'add_circle' || 'add' => Icons.add_circle,
      'tune' || 'settings' => Icons.tune,
      'history' => Icons.history,
      'save' => Icons.save,
      _ => Icons.touch_app,
    };
  }

  Widget _buildMetricsRow() {
    List<dynamic> metricsList = [];
    if (_data?['components'] is List) {
      final comp = (_data!['components'] as List).firstWhere(
        (c) => c is Map && (c['type'] == 'metrics_row' || c['type'] == 'stats_row'),
        orElse: () => null,
      );
      if (comp is Map && comp['metrics'] is List) {
        metricsList = comp['metrics'] as List;
      }
    }
    if (metricsList.isEmpty && _data?['metrics'] is Map) {
      final m = _data!['metrics'] as Map;
      metricsList = [
        if (m['total_wallet_balance'] != null)
          {
            'label': 'Total Store Wallet',
            'value': '₹${((m['total_wallet_balance'] as num).toDouble()).toStringAsFixed(2)}',
            'icon': 'account_balance_wallet',
            'color': '#10B981',
          },
        if (m['total_points_issued'] != null)
          {
            'label': 'Total Points',
            'value': '${((m['total_points_issued'] as num).toInt())}',
            'icon': 'stars',
            'color': '#F59E0B',
          },
        if (m['active_wallets_count'] != null)
          {
            'label': 'Active Wallets',
            'value': '${m['active_wallets_count']}',
            'icon': 'groups',
            'color': '#3B82F6',
          },
      ];
    }
    if (metricsList.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final item in metricsList)
              if (item is Map) ...[
                Container(
                  width: 155,
                  margin: const EdgeInsets.only(right: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF334155)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              (item['label'] ?? '').toString(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                          ),
                          Icon(
                            _resolveIcon(item['icon']),
                            color: item['color'] != null ? _parseColor(item['color'].toString()) : const Color(0xFF10B981),
                            size: 16,
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        (item['value'] ?? '').toString(),
                        style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
              ],
          ],
        ),
      ),
    );
  }

  // 1. Primary Top Action Button Handler
  Widget _buildTopActionButton(BuildContext context, Map<String, dynamic> action) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton.icon(
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF10B981),
          padding: const EdgeInsets.symmetric(vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        icon: Icon(_resolveIcon(action['icon']), color: Colors.white, size: 20),
        label: Text(
          action['label'] ?? 'Action',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
        ),
        onPressed: () {
          _handleSduiAction(context, action);
        },
      ),
    );
  }

  // 2. Individual List Item Card Handler
  Widget _buildListItemCard(BuildContext context, Map<String, dynamic> item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF334155).withValues(alpha: 0.5)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () {
            _handleItemTap(context, item);
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: _buildCardContent(item),
          ),
        ),
      ),
    );
  }

  Widget _buildCardContent(Map<String, dynamic> item) {
    final title = item['title'] ?? item['name'] ?? 'Record';
    final subtitle = item['subtitle'] ?? '';
    final badge = item['badge'] ?? '';
    final badgeColor = item['badge_color'] != null ? _parseColor(item['badge_color'].toString()) : const Color(0xFF10B981);
    final initials = (item['avatar_text'] ?? (title.toString().isNotEmpty ? title.toString().substring(0, 1) : 'R')).toString().toUpperCase();
    final rawHistory = item['history'] as List<dynamic>? ?? const [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: const Color(0xFF0F172A),
              child: Text(
                initials,
                style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold, fontSize: 14),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title.toString(),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  if (subtitle.toString().isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      subtitle.toString(),
                      style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                    ),
                  ],
                ],
              ),
            ),
            if (badge.toString().isNotEmpty)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: badgeColor, width: 1),
                ),
                child: Text(
                  badge.toString(),
                  style: TextStyle(color: badgeColor, fontWeight: FontWeight.bold, fontSize: 13),
                ),
              ),
          ],
        ),

        // Recent Top-up History Sub-Section
        if (rawHistory.isNotEmpty) ...[
          const SizedBox(height: 12),
          const Divider(color: Color(0xFF334155), height: 1),
          const SizedBox(height: 8),
          const Row(
            children: [
              Icon(Icons.history_rounded, color: Color(0xFF64748B), size: 14),
              SizedBox(width: 4),
              Text(
                'Recent Top-ups & Activity:',
                style: TextStyle(color: Color(0xFF64748B), fontSize: 11, fontWeight: FontWeight.w600),
              ),
            ],
          ),
          const SizedBox(height: 6),
          for (final tx in rawHistory)
            if (tx is Map) ...[
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Icon(
                            tx['is_credit'] == true ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
                            color: tx['is_credit'] == true ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                            size: 14,
                          ),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              "${tx['type'] ?? 'Top-up'} (${tx['method'] ?? 'Cash'})",
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 12),
                            ),
                          ),
                          if (tx['bonus'] != null && tx['bonus'].toString().isNotEmpty) ...[
                            const SizedBox(width: 4),
                            Text(
                              tx['bonus'].toString(),
                              style: const TextStyle(color: Color(0xFFF59E0B), fontSize: 11, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          "${tx['is_credit'] == true ? '+' : '-'}${tx['amount'] ?? ''}",
                          style: TextStyle(
                            color: tx['is_credit'] == true ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                        if (tx['date'] != null)
                          Text(
                            tx['date'].toString(),
                            style: const TextStyle(color: Color(0xFF64748B), fontSize: 10),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
        ],
      ],
    );
  }

  // 3. Centralized SDUI Action Resolver
  void _handleSduiAction(BuildContext context, Map<String, dynamic> action) {
    final actionType = action['type']?.toString().toLowerCase() ?? '';
    final target = action['target']?.toString() ?? action['endpoint']?.toString() ?? '';

    switch (actionType) {
      case 'dialog':
        if (target == 'pos_pin_dialog' || target.contains('pos_pin') || target.contains('clock')) {
          final authProvider = Provider.of<AuthProvider?>(context, listen: false);
          final storeProvider = Provider.of<StoreProvider?>(context, listen: false);
          showDialog(
            context: context,
            builder: (ctx) => PosClockInDialog(
              apiUrl: AppConfig.defaultBaseUrl.replaceAll(RegExp(r'/+$'), ''),
              authToken: authProvider?.token ?? '',
              storeId: storeProvider?.current?.id ?? 1,
            ),
          ).then((_) => _refreshCurrentView());
        }
        break;

      case 'action_sheet':
      case 'modal_form':
        showModalBottomSheet<bool>(
          context: context,
          isScrollControlled: true,
          backgroundColor: const Color(0xFF0F172A),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          builder: (ctx) => SduiDynamicFormSheet(
            formEndpoint: target,
            onSubmitted: _refreshCurrentView,
          ),
        ).then((val) {
          if (val == true || mounted) {
            _refreshCurrentView();
          }
        });
        break;

      default:
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Action: ${action['label'] ?? 'Triggered'}')),
        );
        break;
    }
  }

  void _handleItemTap(BuildContext context, Map<String, dynamic> item) {
    if (item['action_target'] != null) {
      showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: const Color(0xFF0F172A),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (ctx) => SduiItemDetailSheet(
          item: item,
          onReload: () => _refreshCurrentView(),
        ),
      ).then((val) {
        if (val == true || mounted) {
          _refreshCurrentView();
        }
      });
    } else if (item['action'] is Map) {
      _handleSduiAction(context, Map<String, dynamic>.from(item['action'] as Map));
    } else {
      showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        backgroundColor: const Color(0xFF0F172A),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (ctx) => SduiItemDetailSheet(
          item: item,
          onReload: () => _refreshCurrentView(),
        ),
      ).then((val) {
        if (val == true || mounted) {
          _refreshCurrentView();
        }
      });
    }
  }

  Color _parseColor(String hex) {
    try {
      final clean = hex.replaceAll('#', '').trim();
      if (clean.length == 6) {
        return Color(int.parse('FF$clean', radix: 16));
      } else if (clean.length == 8) {
        return Color(int.parse(clean, radix: 16));
      }
    } catch (_) {}
    return const Color(0xFF10B981);
  }

  @override
  Widget build(BuildContext context) {
    final title = _data?['title'] ?? widget.initialTitle ?? 'List';
    final rawActions = _data?['actions'] as List<dynamic>? ?? const [];
    final rawItems = _data?['items'] as List<dynamic>? ?? const [];
    final rawFields = _data?['fields'] as List<dynamic>? ?? const [];
    final isFormView = _data?['layout'] == 'form_view' || (rawFields.isNotEmpty && rawItems.isEmpty);
    final emptyState = _data?['empty_state'] as Map<String, dynamic>?;
    final showSearchBar = !isFormView && (_data?['search_endpoint'] != null || (_data?['components'] is List && (_data!['components'] as List).any((c) => c is Map && c['type'] == 'search_bar')) || widget.endpoint.contains('wallet') || widget.endpoint.contains('customer'));

    return Scaffold(
      backgroundColor: const Color(0xFF0B132B),
      appBar: AppBar(
        title: Text(title.toString()),
        backgroundColor: const Color(0xFF0F172A),
      ),
      body: isFormView
          ? SduiDynamicFormSheet(
              formEndpoint: widget.endpoint,
              initialSchema: _data,
              isBottomSheet: false,
              onSubmitted: _refreshCurrentView,
            )
          : Column(
              children: [
                if (showSearchBar) _buildSearchBar(),
                Expanded(
                  child: _isLoading
                      ? const Center(child: CircularProgressIndicator(color: Color(0xFF10B981)))
                      : _errorMessage != null
                          ? Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.error_outline, color: Colors.redAccent, size: 40),
                                  const SizedBox(height: 12),
                                  Text(_errorMessage!, style: const TextStyle(color: Colors.redAccent)),
                                  const SizedBox(height: 16),
                                  ElevatedButton(
                                    onPressed: () => _fetchData(query: _searchController.text.trim()),
                                    child: const Text('Retry'),
                                  ),
                                ],
                              ),
                            )
                          : ListView(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              children: [
                                _buildMetricsRow(),
                                for (final act in rawActions)
                                  if (act is Map) ...[
                                    _buildTopActionButton(context, Map<String, dynamic>.from(act)),
                                    const SizedBox(height: 14),
                                  ],
                                if (rawItems.isEmpty) ...[
                                  Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
                                    child: Center(
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(Icons.search_off_rounded, color: Color(0xFF64748B), size: 48),
                                          const SizedBox(height: 12),
                                          Text(
                                            emptyState?['title']?.toString() ?? (_searchController.text.isNotEmpty ? 'No Customers Matched' : 'No Records Found'),
                                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                                          ),
                                          const SizedBox(height: 6),
                                          Text(
                                            emptyState?['description']?.toString() ?? emptyState?['subtitle']?.toString() ?? (_searchController.text.isNotEmpty ? "No customer records matching '${_searchController.text}'." : ''),
                                            textAlign: TextAlign.center,
                                            style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                                          ),
                                          if (emptyState?['action_label'] != null && rawActions.isNotEmpty) ...[
                                            const SizedBox(height: 16),
                                            ElevatedButton.icon(
                                              style: ElevatedButton.styleFrom(
                                                backgroundColor: const Color(0xFF10B981),
                                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                              ),
                                              icon: const Icon(Icons.add_circle, color: Colors.white, size: 18),
                                              label: Text(
                                                emptyState!['action_label'].toString(),
                                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                                              ),
                                              onPressed: () {
                                                final act = rawActions.firstWhere(
                                                  (a) => a is Map && (a['id'] == emptyState['action_key'] || a['type'] == 'modal_form' || a['type'] == 'action_sheet'),
                                                  orElse: () => rawActions.first,
                                                );
                                                if (act is Map) {
                                                  _handleSduiAction(context, Map<String, dynamic>.from(act));
                                                }
                                              },
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                  ),
                                ] else ...[
                                  for (final it in rawItems)
                                    if (it is Map)
                                      _buildListItemCard(context, Map<String, dynamic>.from(it)),
                                ],
                              ],
                            ),
                ),
              ],
            ),
    );
  }
}
