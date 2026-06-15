import 'dart:convert';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../core/api/api_client.dart';
import '../../core/utils/formula_evaluator.dart';
import '../../shared/theme/app_theme.dart';

// ---------------------------------------------------------------------------
// Rule evaluator — mirrors PHP evaluateRulesPhp / JS evaluateVisibilityRules
// AND-of-rules semantics, 8 operators
// ---------------------------------------------------------------------------
bool _evaluateRules(List<dynamic> rules, Map<String, dynamic> values) {
  if (rules.isEmpty) return false;
  for (final r in rules) {
    if (r is! Map) continue;
    final fieldKey = r['field'] as String?;
    if (fieldKey == null) continue;
    final op = (r['operator'] as String?) ?? 'equals';
    final expected = r['value'];
    final actual = values[fieldKey];

    final isArray = actual is List;
    bool contains(dynamic haystack, dynamic needle) =>
        isArray ? (haystack as List).contains(needle) : haystack?.toString() == needle?.toString();

    bool match;
    switch (op) {
      case 'equals':
        match = contains(actual, expected);
        break;
      case 'not_equals':
        match = !contains(actual, expected);
        break;
      case 'is_empty':
        match = actual == null || actual == '' || (actual is List && actual.isEmpty);
        break;
      case 'is_not_empty':
        match = !(actual == null || actual == '' || (actual is List && actual.isEmpty));
        break;
      case 'greater_than':
        final a = double.tryParse(actual?.toString() ?? '');
        final e = double.tryParse(expected?.toString() ?? '');
        match = a != null && e != null && a > e;
        break;
      case 'less_than':
        final a = double.tryParse(actual?.toString() ?? '');
        final e = double.tryParse(expected?.toString() ?? '');
        match = a != null && e != null && a < e;
        break;
      case 'in':
        if (expected is List) {
          match = expected.map((x) => x.toString()).contains(actual?.toString());
        } else {
          match = false;
        }
        break;
      case 'not_in':
        if (expected is List) {
          match = !expected.map((x) => x.toString()).contains(actual?.toString());
        } else {
          match = true;
        }
        break;
      default:
        match = false;
    }

    if (!match) return false;
  }
  return true;
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------
class FormCreateScreen extends StatefulWidget {
  final String formKey;
  const FormCreateScreen({super.key, required this.formKey});
  @override
  State<FormCreateScreen> createState() => _FormCreateScreenState();
}

class _FormCreateScreenState extends State<FormCreateScreen> {
  Map<String, dynamic>? _formSchema;
  bool _loading = true;
  bool _submitting = false;
  final Map<String, dynamic> _values = {};
  final _imagePicker = ImagePicker();
  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _loadSchema();
  }

  Future<void> _loadSchema() async {
    try {
      final r = await ApiClient.instance.get('/mobile/forms/${widget.formKey}');
      if (mounted) {
        setState(() {
          _formSchema = r.data['data'] as Map<String, dynamic>;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _submitting = true);
    try {
      final r = await ApiClient.instance.post('/mobile/forms/${widget.formKey}', data: {'fields': _values});
      if (!mounted) return;
      final refNo = r.data['data']['reference_no'] ?? '';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('ยื่นคำขอสำเร็จ: $refNo'),
        backgroundColor: const Color(0xFF10B981),
      ));
      context.go('/requests');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('เกิดข้อผิดพลาด กรุณาลองใหม่'),
          backgroundColor: Colors.red,
        ));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  // ---- Field visibility / required helpers --------------------------------

  bool _isVisible(Map<String, dynamic> field) {
    final rules = field['visibility_rules'];
    if (rules == null || rules is! List || rules.isEmpty) return true;
    return _evaluateRules(rules, _values);
  }

  bool _isRequired(Map<String, dynamic> field) {
    final base = field['is_required'] as bool? ?? false;
    final rules = field['required_rules'];
    if (rules == null || rules is! List || rules.isEmpty) return base;
    return base || _evaluateRules(rules, _values);
  }

  // ---- File / image pickers -----------------------------------------------

  Future<void> _pickFile(String key, {bool multi = false, bool imageOnly = false}) async {
    if (multi) {
      final picked = await _imagePicker.pickMultiImage(imageQuality: 70);
      if (picked.isNotEmpty && mounted) setState(() => _values[key] = picked.map((x) => x.path).toList());
    } else {
      final picked = imageOnly
          ? await _imagePicker.pickImage(source: ImageSource.gallery, imageQuality: 70)
          : await _imagePicker.pickImage(source: ImageSource.gallery, imageQuality: 80);
      if (picked != null && mounted) setState(() => _values[key] = picked.path);
    }
  }

  Future<void> _scanQr(String key) async {
    final result = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const _QrScanScreen()),
    );
    if (result != null && mounted) setState(() => _values[key] = result);
  }

  // ---- Dynamic field builder -----------------------------------------------

  Widget _buildField(Map<String, dynamic> field) {
    final key = field['field_key'] as String;
    final type = field['field_type'] as String? ?? 'text';
    final label = field['label'] as String? ?? key;
    final required = _isRequired(field);
    final readonly = field['is_readonly'] as bool? ?? false;
    final placeholder = field['placeholder'] as String? ?? '';
    final displayLabel = required ? '$label *' : label;

    switch (type) {
      // ---- Structural -------------------------------------------------------
      case 'section':
        return Padding(
          padding: const EdgeInsets.only(top: 8, bottom: 4),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
            const Divider(height: 8, thickness: 1),
          ]),
        );

      case 'page_break':
        return const Divider(height: 24, thickness: 2, color: Color(0xFFE2E8F0));

      // ---- Auto / readonly ------------------------------------------------
      case 'auto_number':
        return TextFormField(
          enabled: false,
          decoration: InputDecoration(
            labelText: displayLabel,
            hintText: 'สร้างอัตโนมัติ',
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
          ),
        );

      case 'formula':
        // Live-computed from the other fields; the raw value rides along in
        // _values so it lands in the payload (server recomputes — authoritative).
        final opts = field['options'] is Map
            ? Map<String, dynamic>.from(field['options'] as Map)
            : const <String, dynamic>{};
        final expression = opts['expression']?.toString() ?? '';
        final decimals =
            (int.tryParse(opts['decimals']?.toString() ?? '') ?? 2).clamp(0, 8);
        final holidays =
            (_formSchema?['holidays'] as List?)?.cast<String>() ?? const <String>[];
        final computed = evaluateFormula(expression, _values, holidays: holidays);
        _values[key] = computed;
        return TextFormField(
          controller: TextEditingController(
              text: computed == null ? '' : computed.toStringAsFixed(decimals)),
          enabled: false,
          decoration: InputDecoration(
            labelText: displayLabel,
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            suffixIcon: const Icon(Icons.calculate_outlined, size: 18),
          ),
        );

      // ---- Text variants ---------------------------------------------------
      case 'textarea':
        return TextFormField(
          maxLines: 4,
          initialValue: _values[key] as String?,
          onChanged: (v) { _values[key] = v; },
          enabled: !readonly,
          decoration: InputDecoration(labelText: displayLabel, hintText: placeholder, alignLabelWithHint: true),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอก $label' : null : null,
        );

      case 'email':
        return TextFormField(
          initialValue: _values[key] as String?,
          onChanged: (v) { _values[key] = v; },
          enabled: !readonly,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(labelText: displayLabel, hintText: placeholder.isNotEmpty ? placeholder : 'example@email.com'),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอกอีเมล' : null : null,
        );

      case 'phone':
        return TextFormField(
          initialValue: _values[key] as String?,
          onChanged: (v) { _values[key] = v; },
          enabled: !readonly,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: displayLabel, hintText: placeholder.isNotEmpty ? placeholder : '0xx-xxx-xxxx'),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอกเบอร์โทร' : null : null,
        );

      // ---- Number ---------------------------------------------------------
      case 'number':
        return TextFormField(
          initialValue: _values[key]?.toString(),
          // setState so formula fields recompute as numbers change
          onChanged: (v) { setState(() => _values[key] = v); },
          enabled: !readonly,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: displayLabel, hintText: placeholder),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอก $label' : null : null,
        );

      case 'currency':
        return TextFormField(
          initialValue: _values[key]?.toString(),
          onChanged: (v) { setState(() => _values[key] = v); },
          enabled: !readonly,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: displayLabel, prefixText: '฿ ', hintText: '0.00'),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอก $label' : null : null,
        );

      // ---- Date / Time ----------------------------------------------------
      case 'date':
        final dateVal = _values[key] as String?;
        return InkWell(
          onTap: readonly ? null : () async {
            final d = await showDatePicker(
              context: context,
              initialDate: DateTime.tryParse(dateVal ?? '') ?? DateTime.now(),
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
            );
            if (d != null && mounted) {
              setState(() => _values[key] = '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}');
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: displayLabel,
              suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
              errorText: (required && (dateVal == null || dateVal.isEmpty)) ? null : null,
            ),
            child: Text(dateVal ?? '', style: TextStyle(color: dateVal == null ? AppTheme.muted : null)),
          ),
        );

      case 'time':
        final timeVal = _values[key] as String?;
        return InkWell(
          onTap: readonly ? null : () async {
            final parts = timeVal?.split(':');
            final initial = parts != null
                ? TimeOfDay(hour: int.tryParse(parts[0]) ?? 0, minute: int.tryParse(parts[1]) ?? 0)
                : TimeOfDay.now();
            final t = await showTimePicker(context: context, initialTime: initial);
            if (t != null && mounted) {
              setState(() => _values[key] = '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}');
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(labelText: displayLabel, suffixIcon: const Icon(Icons.access_time, size: 18)),
            child: Text(timeVal ?? '', style: TextStyle(color: timeVal == null ? AppTheme.muted : null)),
          ),
        );

      case 'datetime':
        final dtVal = _values[key] as String?;
        return InkWell(
          onTap: readonly ? null : () async {
            final parsed = DateTime.tryParse(dtVal ?? '');
            final d = await showDatePicker(
              context: context,
              initialDate: parsed ?? DateTime.now(),
              firstDate: DateTime(2000),
              lastDate: DateTime(2100),
            );
            if (d == null || !mounted) return;
            final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(parsed ?? DateTime.now()));
            if (t != null && mounted) {
              setState(() => _values[key] = '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} ${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}');
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(labelText: displayLabel, suffixIcon: const Icon(Icons.event, size: 18)),
            child: Text(dtVal ?? '', style: TextStyle(color: dtVal == null ? AppTheme.muted : null)),
          ),
        );

      // ---- Select / Radio / Checkbox / Multi-select -----------------------
      case 'select':
        final opts = _optionStrings(field);
        final current = _values[key] as String?;
        return DropdownButtonFormField<String>(
          // ignore: deprecated_member_use
          value: (current != null && opts.contains(current)) ? current : null,
          onChanged: readonly ? null : (v) => setState(() => _values[key] = v),
          items: opts.map((o) => DropdownMenuItem(value: o, child: Text(o))).toList(),
          decoration: InputDecoration(labelText: displayLabel),
          validator: required ? (v) => v == null ? 'กรุณาเลือก $label' : null : null,
        );

      case 'radio':
        final opts = _optionStrings(field);
        final current = _values[key] as String?;
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(displayLabel, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
          ...opts.map((o) => InkWell(
            onTap: readonly ? null : () => setState(() => _values[key] = o),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(children: [
                Container(
                  width: 20, height: 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: current == o ? AppTheme.primary : AppTheme.border, width: 2),
                  ),
                  child: current == o
                      ? Center(child: Container(width: 10, height: 10, decoration: BoxDecoration(shape: BoxShape.circle, color: AppTheme.primary)))
                      : null,
                ),
                const SizedBox(width: 10),
                Expanded(child: Text(o, style: const TextStyle(fontSize: 14))),
              ]),
            ),
          )),
        ]);

      case 'checkbox':
        final checked = _values[key] == true || _values[key] == '1' || _values[key] == 'true';
        return SwitchListTile(
          dense: true,
          contentPadding: EdgeInsets.zero,
          title: Text(displayLabel),
          value: checked,
          onChanged: readonly ? null : (v) => setState(() => _values[key] = v),
        );

      case 'multi_select':
        final opts = _optionStrings(field);
        final selected = (_values[key] as List?)?.map((x) => x.toString()).toSet() ?? {};
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(displayLabel, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
          const SizedBox(height: 4),
          Wrap(spacing: 8, runSpacing: 4, children: opts.map((o) {
            final isSelected = selected.contains(o);
            return FilterChip(
              label: Text(o, style: const TextStyle(fontSize: 13)),
              selected: isSelected,
              onSelected: readonly ? null : (v) {
                setState(() {
                  final s = Set<String>.from(selected);
                  v ? s.add(o) : s.remove(o);
                  _values[key] = s.toList();
                });
              },
            );
          }).toList()),
        ]);

      // ---- Lookup ---------------------------------------------------------
      case 'lookup':
        final items = (field['lookup_items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
        if (items.isEmpty) {
          return TextFormField(
            initialValue: _values[key] as String?,
            onChanged: (v) { _values[key] = v; },
            enabled: !readonly,
            decoration: InputDecoration(labelText: displayLabel, hintText: placeholder.isNotEmpty ? placeholder : 'พิมพ์ค้นหา...'),
            validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอก $label' : null : null,
          );
        }
        final current = _values[key] as String?;
        return DropdownButtonFormField<String>(
          // ignore: deprecated_member_use
          value: (current != null && items.any((i) => i['value']?.toString() == current)) ? current : null,
          onChanged: readonly ? null : (v) => setState(() => _values[key] = v),
          items: items.map((item) {
            final val = item['value']?.toString() ?? '';
            final lbl = (item['label_th'] ?? item['label_en'] ?? val).toString();
            return DropdownMenuItem(value: val, child: Text(lbl));
          }).toList(),
          decoration: InputDecoration(labelText: displayLabel),
          validator: required ? (v) => v == null ? 'กรุณาเลือก $label' : null : null,
        );

      // ---- File / Image ---------------------------------------------------
      case 'file':
        final path = _values[key];
        return _FilePickerField(
          label: displayLabel,
          hasValue: path != null,
          onTap: () => _pickFile(key),
        );

      case 'image':
        final path = _values[key];
        return _FilePickerField(
          label: displayLabel,
          icon: Icons.image_outlined,
          buttonLabel: 'เลือกรูปภาพ',
          hasValue: path != null,
          onTap: () => _pickFile(key, imageOnly: true),
        );

      case 'multi_file':
        final paths = _values[key] as List?;
        return _FilePickerField(
          label: displayLabel,
          icon: Icons.attach_file,
          buttonLabel: 'เลือกหลายไฟล์',
          hasValue: paths != null && paths.isNotEmpty,
          count: paths?.length,
          onTap: () => _pickFile(key, multi: true),
        );

      // ---- QR code -------------------------------------------------------
      case 'qr_code':
        final qrVal = _values[key] as String?;
        return Row(children: [
          Expanded(child: InputDecorator(
            decoration: InputDecoration(labelText: displayLabel),
            child: Text(qrVal ?? 'ยังไม่ได้สแกน', style: TextStyle(color: qrVal != null ? null : AppTheme.muted, fontSize: 13)),
          )),
          const SizedBox(width: 8),
          OutlinedButton.icon(
            onPressed: readonly ? null : () => _scanQr(key),
            icon: const Icon(Icons.qr_code_scanner, size: 18),
            label: const Text('สแกน'),
          ),
        ]);

      // ---- Signature -----------------------------------------------------
      case 'signature':
        final hasSig = _values[key] != null;
        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(displayLabel, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
          const SizedBox(height: 6),
          GestureDetector(
            onTap: readonly ? null : () async {
              final result = await Navigator.of(context).push<String>(
                MaterialPageRoute(builder: (_) => const _SignaturePadScreen()),
              );
              if (result != null && mounted) setState(() => _values[key] = result);
            },
            child: Container(
              height: 100, width: double.infinity,
              decoration: BoxDecoration(
                border: Border.all(color: hasSig ? AppTheme.primary : AppTheme.border),
                borderRadius: BorderRadius.circular(10),
                color: hasSig ? AppTheme.primary.withAlpha(10) : Colors.white,
              ),
              child: Center(child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(hasSig ? Icons.check_circle : Icons.draw_outlined,
                    color: hasSig ? AppTheme.primary : AppTheme.muted, size: 20),
                const SizedBox(width: 8),
                Text(hasSig ? 'มีลายเซ็นแล้ว — แตะเพื่อแก้ไข' : 'แตะเพื่อเซ็นชื่อ',
                    style: TextStyle(color: hasSig ? AppTheme.primary : AppTheme.muted, fontSize: 13)),
              ])),
            ),
          ),
        ]);

      // ---- Group (repeating subform) -------------------------------------
      case 'group':
        return _GroupField(
          field: field,
          values: _values,
          onChanged: (v) => setState(() => _values[key] = v),
          readonly: readonly,
          required: required,
        );

      // ---- Table ---------------------------------------------------------
      case 'table':
        final cols = (dataGet(field['options'], 'columns') as List?)?.cast<Map<String, dynamic>>() ?? [];
        final rows = (_values[key] as List?)?.cast<Map<String, dynamic>>() ?? [];
        return _TableField(
          label: displayLabel,
          columns: cols,
          rows: rows,
          readonly: readonly,
          onChanged: (v) => setState(() => _values[key] = v),
        );

      // ---- Default (text) ------------------------------------------------
      default:
        return TextFormField(
          initialValue: _values[key] as String?,
          onChanged: (v) { _values[key] = v; },
          enabled: !readonly,
          decoration: InputDecoration(labelText: displayLabel, hintText: placeholder),
          validator: required ? (v) => (v?.isEmpty ?? true) ? 'กรุณากรอก $label' : null : null,
        );
    }
  }

  List<String> _optionStrings(Map<String, dynamic> field) {
    final opts = field['options'];
    if (opts is List) return opts.map((o) => o.toString()).toList();
    return [];
  }

  @override
  Widget build(BuildContext context) {
    final allFields = (_formSchema?['fields'] as List?)?.cast<Map<String, dynamic>>() ?? [];

    return Scaffold(
      appBar: AppBar(title: Text(_formSchema?['name'] as String? ?? 'กรอกฟอร์ม')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_formSchema?['description'] != null && (_formSchema!['description'] as String).isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: Text(_formSchema!['description'] as String,
                            style: const TextStyle(color: AppTheme.muted, fontSize: 13)),
                      ),
                    ...allFields.where(_isVisible).map((field) => Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: _buildField(field),
                    )),
                    const SizedBox(height: 8),
                    ElevatedButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('ยื่นคำขอ'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}

// ---------------------------------------------------------------------------
// Helper widgets
// ---------------------------------------------------------------------------

class _FilePickerField extends StatelessWidget {
  final String label;
  final IconData icon;
  final String buttonLabel;
  final bool hasValue;
  final int? count;
  final VoidCallback onTap;

  const _FilePickerField({
    required this.label,
    required this.hasValue,
    required this.onTap,
    this.icon = Icons.attach_file,
    this.buttonLabel = 'เลือกไฟล์',
    this.count,
  });

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
      const SizedBox(height: 6),
      Row(children: [
        OutlinedButton.icon(
          onPressed: onTap,
          icon: Icon(icon, size: 18),
          label: Text(buttonLabel),
        ),
        if (hasValue) ...[
          const SizedBox(width: 8),
          Icon(Icons.check_circle, color: const Color(0xFF10B981), size: 18),
          if (count != null) Text(' $count ไฟล์', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
        ],
      ]),
    ]);
  }
}

// ---------------------------------------------------------------------------
// Group field (repeating subform)
// ---------------------------------------------------------------------------
class _GroupField extends StatefulWidget {
  final Map<String, dynamic> field;
  final Map<String, dynamic> values;
  final void Function(List<Map<String, dynamic>>) onChanged;
  final bool readonly;
  final bool required;

  const _GroupField({
    required this.field,
    required this.values,
    required this.onChanged,
    required this.readonly,
    required this.required,
  });

  @override
  State<_GroupField> createState() => _GroupFieldState();
}

class _GroupFieldState extends State<_GroupField> {
  List<Map<String, dynamic>> _rows = [];

  @override
  void initState() {
    super.initState();
    final existing = widget.values[widget.field['field_key'] as String];
    if (existing is List) _rows = existing.cast<Map<String, dynamic>>();
  }

  List<Map<String, dynamic>> get _innerFields {
    final opts = widget.field['options'] as Map?;
    final fields = opts?['fields'] as List?;
    return fields?.cast<Map<String, dynamic>>() ?? [];
  }

  void _addRow() {
    setState(() {
      _rows = [..._rows, <String, dynamic>{}];
      widget.onChanged(_rows);
    });
  }

  void _removeRow(int i) {
    setState(() {
      _rows = [..._rows]..removeAt(i);
      widget.onChanged(_rows);
    });
  }

  @override
  Widget build(BuildContext context) {
    final label = widget.field['label'] as String? ?? '';
    final inner = _innerFields;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
      const SizedBox(height: 8),
      ..._rows.asMap().entries.map((entry) {
        final i = entry.key;
        final row = entry.value;
        return Card(
          margin: const EdgeInsets.only(bottom: 8),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Text('แถวที่ ${i + 1}', style: const TextStyle(fontSize: 12, color: AppTheme.muted)),
                const Spacer(),
                if (!widget.readonly)
                  IconButton(
                    icon: const Icon(Icons.delete_outline, size: 18, color: Color(0xFFEF4444)),
                    onPressed: () => _removeRow(i),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
              ]),
              ...inner.map((f) {
                final fKey = f['field_key'] as String;
                final fLabel = f['label'] as String? ?? fKey;
                final fType = f['field_type'] as String? ?? 'text';
                return Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: TextFormField(
                    initialValue: row[fKey]?.toString(),
                    onChanged: (v) {
                      final updated = Map<String, dynamic>.from(row);
                      updated[fKey] = v;
                      _rows[i] = updated;
                      widget.onChanged(_rows);
                    },
                    keyboardType: (fType == 'number' || fType == 'currency')
                        ? const TextInputType.numberWithOptions(decimal: true)
                        : TextInputType.text,
                    decoration: InputDecoration(labelText: fLabel, isDense: true),
                  ),
                );
              }),
            ]),
          ),
        );
      }),
      if (!widget.readonly)
        TextButton.icon(
          onPressed: _addRow,
          icon: const Icon(Icons.add_circle_outline, size: 18),
          label: const Text('เพิ่มแถว'),
        ),
    ]);
  }
}

// ---------------------------------------------------------------------------
// Table field
// ---------------------------------------------------------------------------
class _TableField extends StatefulWidget {
  final String label;
  final List<Map<String, dynamic>> columns;
  final List<Map<String, dynamic>> rows;
  final bool readonly;
  final void Function(List<Map<String, dynamic>>) onChanged;

  const _TableField({
    required this.label,
    required this.columns,
    required this.rows,
    required this.readonly,
    required this.onChanged,
  });

  @override
  State<_TableField> createState() => _TableFieldState();
}

class _TableFieldState extends State<_TableField> {
  late List<Map<String, dynamic>> _rows;

  @override
  void initState() {
    super.initState();
    _rows = List.from(widget.rows);
  }

  @override
  Widget build(BuildContext context) {
    if (widget.columns.isEmpty) return const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(widget.label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
      const SizedBox(height: 8),
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          columnSpacing: 12,
          dataRowMinHeight: 36,
          dataRowMaxHeight: 52,
          columns: [
            ...widget.columns.map((c) => DataColumn(label: Text(c['label'] as String? ?? '', style: const TextStyle(fontSize: 12)))),
            if (!widget.readonly) const DataColumn(label: SizedBox.shrink()),
          ],
          rows: _rows.asMap().entries.map((entry) {
            final i = entry.key;
            final row = entry.value;
            return DataRow(cells: [
              ...widget.columns.map((c) {
                final cKey = c['key'] as String? ?? '';
                return DataCell(SizedBox(
                  width: 100,
                  child: TextFormField(
                    initialValue: row[cKey]?.toString() ?? '',
                    onChanged: (v) {
                      _rows[i] = Map<String, dynamic>.from(row)..[cKey] = v;
                      widget.onChanged(_rows);
                    },
                    style: const TextStyle(fontSize: 12),
                    decoration: const InputDecoration(isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: 4, vertical: 4)),
                  ),
                ));
              }),
              if (!widget.readonly)
                DataCell(IconButton(
                  icon: const Icon(Icons.delete_outline, size: 16, color: Color(0xFFEF4444)),
                  onPressed: () => setState(() {
                    _rows.removeAt(i);
                    widget.onChanged(_rows);
                  }),
                )),
            ]);
          }).toList(),
        ),
      ),
      if (!widget.readonly)
        TextButton.icon(
          onPressed: () {
            setState(() {
              final newRow = <String, dynamic>{for (final c in widget.columns) c['key'] as String? ?? '': ''};
              _rows.add(newRow);
              widget.onChanged(_rows);
            });
          },
          icon: const Icon(Icons.add, size: 18),
          label: const Text('เพิ่มแถว'),
        ),
    ]);
  }
}

// ---------------------------------------------------------------------------
// QR scanner screen
// ---------------------------------------------------------------------------
class _QrScanScreen extends StatelessWidget {
  const _QrScanScreen();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('สแกน QR Code')),
      body: MobileScanner(
        onDetect: (capture) {
          final value = capture.barcodes.firstOrNull?.rawValue;
          if (value != null) Navigator.of(context).pop(value);
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Signature pad screen
// ---------------------------------------------------------------------------
class _SignaturePadScreen extends StatefulWidget {
  const _SignaturePadScreen();
  @override
  State<_SignaturePadScreen> createState() => _SignaturePadScreenState();
}

class _SignaturePadScreenState extends State<_SignaturePadScreen> {
  final List<Offset?> _points = [];

  Future<void> _save() async {
    const double w = 600, h = 200;
    final recorder = ui.PictureRecorder();
    final canvas = ui.Canvas(recorder, const Rect.fromLTWH(0, 0, w, h));
    canvas.drawRect(
      const Rect.fromLTWH(0, 0, w, h),
      Paint()..color = const Color(0xFFFFFFFF),
    );
    _SignaturePainter(_points).paint(canvas, const Size(w, h));
    final picture = recorder.endRecording();
    final img = await picture.toImage(w.toInt(), h.toInt());
    final bytes = await img.toByteData(format: ui.ImageByteFormat.png);
    if (!mounted) return;
    final b64 = 'data:image/png;base64,${base64Encode(bytes!.buffer.asUint8List())}';
    Navigator.of(context).pop(b64);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('เซ็นชื่อ'),
        actions: [
          TextButton(onPressed: () => setState(() => _points.clear()), child: const Text('ล้าง')),
          TextButton(
            onPressed: _points.isEmpty ? null : _save,
            child: const Text('บันทึก'),
          ),
        ],
      ),
      body: Column(children: [
        Expanded(
          child: GestureDetector(
            onPanUpdate: (d) => setState(() => _points.add(d.localPosition)),
            onPanEnd: (_) => setState(() => _points.add(null)),
            child: CustomPaint(
              painter: _SignaturePainter(_points),
              size: Size.infinite,
            ),
          ),
        ),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          color: const Color(0xFFF8FAFC),
          child: const Text('วาดลายเซ็นด้านบน', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.muted, fontSize: 13)),
        ),
      ]),
    );
  }
}

class _SignaturePainter extends CustomPainter {
  final List<Offset?> points;
  _SignaturePainter(this.points);

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF0F172A)
      ..strokeWidth = 2.5
      ..strokeCap = StrokeCap.round;
    for (int i = 0; i < points.length - 1; i++) {
      if (points[i] != null && points[i + 1] != null) {
        canvas.drawLine(points[i]!, points[i + 1]!, paint);
      }
    }
  }

  @override
  bool shouldRepaint(_SignaturePainter old) => old.points != points;
}

// ---------------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------------
dynamic dataGet(dynamic obj, String key) {
  if (obj is Map) return obj[key];
  return null;
}
