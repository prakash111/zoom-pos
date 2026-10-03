import 'package:flutter/material.dart';

/// Dynamic Key-Value Custom Fields Editor widget conforming to the POS Customers pattern.
/// Renders a dynamic list of custom attributes with "Field Name", "Value", and a remove (✕) button.
class DynamicCustomFieldsEditor extends StatefulWidget {
  final List<Map<String, String>> initialFields;
  final ValueChanged<List<Map<String, String>>> onChanged;

  const DynamicCustomFieldsEditor({
    Key? key,
    this.initialFields = const [],
    required this.onChanged,
  }) : super(key: key);

  @override
  State<DynamicCustomFieldsEditor> createState() => _DynamicCustomFieldsEditorState();
}

class _DynamicCustomFieldsEditorState extends State<DynamicCustomFieldsEditor> {
  late List<Map<String, TextEditingController>> _fieldControllers;

  @override
  void initState() {
    super.initState();
    _fieldControllers = widget.initialFields.map((field) {
      return {
        'name': TextEditingController(text: field['name'] ?? ''),
        'value': TextEditingController(text: field['value'] ?? ''),
      };
    }).toList();
  }

  void _notifyParent() {
    final result = _fieldControllers
        .map((fc) {
          return {
            'name': fc['name']!.text.trim(),
            'value': fc['value']!.text.trim(),
          };
        })
        .where((item) => item['name']!.isNotEmpty)
        .toList();
    widget.onChanged(result);
  }

  void _addField() {
    setState(() {
      _fieldControllers.add({
        'name': TextEditingController(),
        'value': TextEditingController(),
      });
    });
    _notifyParent();
  }

  void _removeField(int index) {
    setState(() {
      _fieldControllers[index]['name']!.dispose();
      _fieldControllers[index]['value']!.dispose();
      _fieldControllers.removeAt(index);
    });
    _notifyParent();
  }

  @override
  void dispose() {
    for (var fc in _fieldControllers) {
      fc['name']?.dispose();
      fc['value']?.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Custom Fields',
              style: TextStyle(
                color: Color(0xFF94A3B8),
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
            TextButton.icon(
              onPressed: _addField,
              icon: const Icon(Icons.add, size: 16, color: Color(0xFF10B981)),
              label: const Text(
                '+ Add Field',
                style: TextStyle(
                  color: Color(0xFF10B981),
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                visualDensity: VisualDensity.compact,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        if (_fieldControllers.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Text(
              'No custom fields added yet (e.g. GSTIN, Blood Group, Emergency Contact).',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 12),
            ),
          ),
        ..._fieldControllers.asMap().entries.map((entry) {
          final idx = entry.key;
          final fc = entry.value;
          return Padding(
            padding: const EdgeInsets.only(bottom: 8.0),
            child: Row(
              children: [
                Expanded(
                  flex: 4,
                  child: TextFormField(
                    controller: fc['name'],
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Field Name',
                      hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                      filled: true,
                      fillColor: const Color(0xFF1E293B),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: BorderSide.none,
                      ),
                    ),
                    onChanged: (_) => _notifyParent(),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 5,
                  child: TextFormField(
                    controller: fc['value'],
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Value',
                      hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                      filled: true,
                      fillColor: const Color(0xFF1E293B),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: BorderSide.none,
                      ),
                    ),
                    onChanged: (_) => _notifyParent(),
                  ),
                ),
                IconButton(
                  onPressed: () => _removeField(idx),
                  icon: const Icon(Icons.close, color: Color(0xFF64748B), size: 18),
                  padding: const EdgeInsets.only(left: 4),
                  constraints: const BoxConstraints(),
                  splashRadius: 16,
                ),
              ],
            ),
          );
        }).toList(),
      ],
    );
  }
}
