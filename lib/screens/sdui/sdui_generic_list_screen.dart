import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/stores/store_provider.dart';
import '../../features/auth/auth_provider.dart';
import '../hrm/widgets/pos_clock_in_dialog.dart';

/// Centralized SDUI Dynamic Form Modal Sheet
class SduiDynamicFormSheet extends StatefulWidget {
  final String formEndpoint;
  final VoidCallback? onSubmitted;

  const SduiDynamicFormSheet({
    Key? key,
    required this.formEndpoint,
    this.onSubmitted,
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
  String _method = 'POST';
  List<Map<String, dynamic>> _fields = [];
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, dynamic> _fieldValues = {};
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _fetchFormSchema();
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  String _resolveFullUrl(String endpoint, String baseUrl) {
    if (endpoint.startsWith('http://') || endpoint.startsWith('https://')) {
      return endpoint;
    }
    final cleanBase = baseUrl.replaceAll(RegExp(r'/+$'), '');
    final cleanPath = endpoint.replaceAll(RegExp(r'^/+'), '');
    return '$cleanBase/$cleanPath';
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
        final schema = data['schema'] ?? data;

        setState(() {
          _title = schema['title']?.toString() ?? 'Form';
          _submitUrl = schema['submit_url']?.toString() ?? '';
          _method = schema['method']?.toString().toUpperCase() ?? 'POST';

          final rawFields = schema['fields'] as List<dynamic>? ?? [];
          _fields = rawFields.whereType<Map>().map((f) => Map<String, dynamic>.from(f)).toList();

          final initialValues = (schema['initial_values'] as Map?) ?? {};
          for (final field in _fields) {
            final name = field['name']?.toString() ?? '';
            if (name.isNotEmpty) {
              final defaultVal = field['value'] ?? field['default'] ?? initialValues[name] ?? '';
              _controllers[name] = TextEditingController(text: defaultVal.toString());
              _fieldValues[name] = defaultVal;
            }
          }
          _isLoading = false;
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
        if (name.isNotEmpty) {
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
        Navigator.of(context).pop(true);
        widget.onSubmitted?.call();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(data['message'] ?? 'Saved successfully!'),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
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
              Flexible(
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
                                : const Text(
                                    'Submit',
                                    style: TextStyle(
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
        ),
      ),
    );
  }
}

/// Centralized SDUI Item Detail Bottom Sheet
class SduiItemDetailSheet extends StatelessWidget {
  final Map<String, dynamic> item;

  const SduiItemDetailSheet({Key? key, required this.item}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final title = item['title'] ?? item['name'] ?? 'Record Details';
    final subtitle = item['subtitle'] ?? item['role'] ?? item['designation'] ?? '';
    final badge = item['badge'] ?? item['badge_text'] ?? item['status'] ?? '';
    final badgeColorHex = item['badge_color']?.toString() ?? '#10B981';
    final badgeColor = _parseColor(badgeColorHex);
    final avatarText = (item['avatar_text'] ?? (title.toString().isNotEmpty ? title.toString().substring(0, 1) : 'R')).toString().toUpperCase();

    final details = <Map<String, String>>[];
    for (final entry in item.entries) {
      final key = entry.key.toLowerCase();
      if (['type', 'action', 'actions', 'action_type', 'action_target', 'avatar_text', 'avatar_bg', 'avatar_fg', 'badge_color', 'badge_style', 'meta_items', 'target'].contains(key)) {
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
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              child: Row(
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
                            ),
                          );
                        },
                        child: const Text('Edit', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
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

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
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
    _fetchData();
  }

  IconData _resolveIcon(dynamic icon) {
    final name = icon?.toString().toLowerCase().trim() ?? '';
    return switch (name) {
      'person_add' => Icons.person_add,
      'punch_clock' || 'schedule' => Icons.access_time,
      'event_note' || 'calendar_today' => Icons.event_note,
      'calculate' || 'payments' => Icons.calculate,
      _ => Icons.touch_app,
    };
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

  // 2. Individual List Item Tap Handler
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

    return Row(
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
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: badgeColor.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: badgeColor, width: 1),
            ),
            child: Text(
              badge.toString(),
              style: TextStyle(color: badgeColor, fontWeight: FontWeight.bold, fontSize: 11),
            ),
          ),
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
        showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          backgroundColor: const Color(0xFF0F172A),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          builder: (ctx) => SduiDynamicFormSheet(formEndpoint: target),
        ).then((val) {
          if (val == true) {
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
      showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: const Color(0xFF0F172A),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (ctx) => SduiItemDetailSheet(item: item),
      );
    } else if (item['action'] is Map) {
      _handleSduiAction(context, Map<String, dynamic>.from(item['action'] as Map));
    } else {
      showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: const Color(0xFF0F172A),
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        builder: (ctx) => SduiItemDetailSheet(item: item),
      );
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

    return Scaffold(
      backgroundColor: const Color(0xFF0B132B),
      appBar: AppBar(
        title: Text(title.toString()),
        backgroundColor: const Color(0xFF0F172A),
      ),
      body: _isLoading
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
                      ElevatedButton(onPressed: _fetchData, child: const Text('Retry')),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    for (final act in rawActions)
                      if (act is Map) ...[
                        _buildTopActionButton(context, Map<String, dynamic>.from(act)),
                        const SizedBox(height: 16),
                      ],
                    for (final it in rawItems)
                      if (it is Map)
                        _buildListItemCard(context, Map<String, dynamic>.from(it)),
                  ],
                ),
    );
  }
}
