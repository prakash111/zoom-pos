import 'package:cached_network_image/cached_network_image.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../api/api_exception.dart';
import '../models/settings_models.dart';
import '../services/dynamic_string_service.dart';
import '../widgets/sdui/sdui_controls.dart';
import 'components/navigation_tree_builder.dart';
import 'dynamic_schema_context.dart';
import 'sdui_icon_registry.dart';

/// Declarative schema parser that maps server-driven UI JSON specifications
/// to base Flutter Material widgets.
class DynamicSchemaParser {
  DynamicSchemaParser._();

  static Widget buildComponent(
      BuildContext context, Map<String, dynamic> schema) {
    final type = schema['type']?.toString().toLowerCase().trim();

    switch (type) {
      // Layouts
      case 'container':
        return _buildContainer(context, schema);
      case 'card':
        return _buildCard(context, schema);
      case 'scroll_view':
        return _buildScrollView(context, schema);
      case 'grid_view':
        return _buildGridView(context, schema);
      case 'accordion_group':
      case 'accordion':
        return _buildAccordion(context, schema);
      case 'column':
        return _buildColumn(context, schema);
      case 'row':
        return _buildRow(context, schema);
      case 'wrap':
        return _buildWrap(context, schema);
      case 'tabs':
        return _buildTabs(context, schema);
      case 'stepper':
      case 'wizard':
        return _SduiStepper(schema: schema);
      case 'tree_builder':
      case 'navigation_builder':
        return NavMenuSettingsTab(
          schema: schema,
          initial: _navigationConfigFromSchema(schema),
        );

      // Display
      case 'text':
        return _buildText(context, schema);
      case 'image_network':
        return _buildImageNetwork(context, schema);
      case 'badge':
        return _buildBadge(context, schema);
      case 'icon':
        return _buildIcon(context, schema);
      case 'divider':
        return _buildDivider(context, schema);

      // Forms & Inputs
      case 'text_input':
        return _buildTextInput(context, schema);
      case 'dropdown_select':
        return _buildDropdownSelect(context, schema);
      case 'checkbox':
        return _buildCheckbox(context, schema);
      case 'toggle_switch':
        return _buildToggleSwitch(context, schema);
      case 'date_time_picker':
        return _buildDateTimePicker(context, schema);
      case 'color_picker':
        return _buildColorPicker(context, schema);
      case 'file_upload':
        return _buildFileUpload(context, schema);
      case 'cash_tendered_field':
        return _CashTenderedField(schema: schema);

      // Lists & Tables
      case 'line_item_tile':
        return _buildLineItemTile(context, schema);
      case 'table_grid':
        return _buildTableGrid(context, schema);
      case 'step_counter':
        return _buildStepCounter(context, schema);

      // Actions
      case 'button_primary':
        return _buildButtonPrimary(context, schema);
      case 'button_outlined':
        return _buildButtonOutlined(context, schema);
      case 'button_danger':
        return _buildButtonDanger(context, schema);
      case 'fab':
        return _buildFab(context, schema);
      case 'action_sheet_trigger':
        return _buildActionSheetTrigger(context, schema);

      default:
        return const SizedBox.shrink();
    }
  }

  static List<Widget> buildChildren(BuildContext context, dynamic rawChildren) {
    if (rawChildren is! List) return const [];
    final widgets = <Widget>[];
    for (final child in rawChildren) {
      if (child is Map<String, dynamic>) {
        widgets.add(buildComponent(context, child));
      } else if (child is Map) {
        widgets.add(buildComponent(context, Map<String, dynamic>.from(child)));
      }
    }
    return widgets;
  }

  /// Reads both the canonical nav_config and the self-contained tree_data
  /// fallback used by the settings-navigation SDUI response. Older servers
  /// may omit nav_config entirely, so deriving it from the tree preserves
  /// nesting and visibility instead of rendering every row at root level.
  static NavConfig? _navigationConfigFromSchema(Map<String, dynamic> schema) {
    try {
      if (schema['nav_config'] is Map) {
        final config = NavConfig.fromJson(
            Map<String, dynamic>.from(schema['nav_config'] as Map));
        if (config.items.isNotEmpty) return config;
      }

      final rawItems = schema['items'];
      final sectionItems = rawItems is List &&
              rawItems.any((item) =>
                  item is Map &&
                  (item.containsKey('items') || item.containsKey('children')))
          ? rawItems
          : null;
      final tree = schema['tree_data'] ??
          schema['sections'] ??
          schema['menu_structure'] ??
          sectionItems;
      if (tree is List && tree.isNotEmpty) {
        return NavConfig.fromJson({'tree': tree, 'sections': tree});
      }
    } catch (_) {
      // NavMenuSettingsTab has additional catalog/default fallbacks. A bad
      // optional alias must not collapse the entire SDUI component.
    }
    return null;
  }

  // ===========================================================================
  // Layouts
  // ===========================================================================

  static Widget _buildContainer(
      BuildContext context, Map<String, dynamic> schema) {
    final children = _extractChildren(schema);
    final padding = _parseEdgeInsets(schema['padding']);
    final margin = _parseEdgeInsets(schema['margin']);
    final width = _parseDouble(schema['width']);
    final height = _parseDouble(schema['height']);
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : null;
    final borderRadius = _parseDouble(schema['border_radius']);
    final borderColor = schema['border_color'] != null
        ? SduiIconRegistry.parseColor(schema['border_color'].toString())
        : null;
    final borderWidth = _parseDouble(schema['border_width']) ?? 1.0;

    return Container(
      width: width,
      height: height,
      padding: padding,
      margin: margin,
      decoration: (color != null || borderRadius != null || borderColor != null)
          ? BoxDecoration(
              color: color,
              borderRadius: borderRadius != null
                  ? BorderRadius.circular(borderRadius)
                  : null,
              border: borderColor != null
                  ? Border.all(color: borderColor, width: borderWidth)
                  : null,
            )
          : null,
      child: children.length == 1
          ? buildComponent(context, children.first)
          : Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: buildChildren(context, children),
            ),
    );
  }

  static Widget _buildCard(BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final children = _extractChildren(schema);
    final padding =
        _parseEdgeInsets(schema['padding'], fallback: const EdgeInsets.all(16));
    final margin = _parseEdgeInsets(schema['margin'],
        fallback: const EdgeInsets.only(bottom: 12));
    final elevation = _parseDouble(schema['elevation']) ?? 0.0;
    final borderRadius = _parseDouble(schema['border_radius']) ?? 12.0;
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : theme.colorScheme.surface;
    final borderColor = schema['border_color'] != null
        ? SduiIconRegistry.parseColor(schema['border_color'].toString())
        : theme.colorScheme.outlineVariant.withValues(alpha: 0.5);

    return Container(
      margin: margin,
      child: Card(
        elevation: elevation,
        color: color,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          side: BorderSide(color: borderColor),
        ),
        child: Padding(
          padding: padding,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: buildChildren(context, children),
          ),
        ),
      ),
    );
  }

  static Widget _buildScrollView(
      BuildContext context, Map<String, dynamic> schema) {
    final children = _extractChildren(schema);
    final padding =
        _parseEdgeInsets(schema['padding'], fallback: const EdgeInsets.all(16));

    return ListView(
      padding: padding,
      shrinkWrap: true,
      primary: false,
      physics: const NeverScrollableScrollPhysics(),
      children: buildChildren(context, children),
    );
  }

  static Widget _buildGridView(
      BuildContext context, Map<String, dynamic> schema) {
    final children = _extractChildren(schema);
    final columns =
        ((schema['cross_axis_count'] as num?)?.toInt() ?? 2).clamp(1, 12);
    final spacing = _parseDouble(schema['spacing']) ?? 12.0;
    final runSpacing = _parseDouble(schema['run_spacing']) ?? 12.0;

    return LayoutBuilder(
      builder: (context, constraints) {
        final availableWidth = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : MediaQuery.sizeOf(context).width;
        final itemWidth =
            ((availableWidth - (spacing * (columns - 1))) / columns)
                .clamp(0.0, double.infinity);
        return Wrap(
          spacing: spacing,
          runSpacing: runSpacing,
          children: [
            for (final child in children)
              SizedBox(
                width: itemWidth,
                child: buildComponent(context, child),
              ),
          ],
        );
      },
    );
  }

  static Widget _buildAccordion(
      BuildContext context, Map<String, dynamic> schema) {
    final title = context.tr(schema['title']?.toString() ?? '');
    final subtitle = schema['subtitle'] == null
        ? null
        : context.tr(schema['subtitle'].toString());
    final iconName = schema['icon']?.toString();
    final initiallyExpanded = schema['initially_expanded'] == true;
    final children = _extractChildren(schema);

    return ExpansionTile(
      initiallyExpanded: initiallyExpanded,
      leading:
          iconName != null ? Icon(SduiIconRegistry.resolve(iconName)) : null,
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: subtitle != null
          ? Text(subtitle, style: const TextStyle(fontSize: 12))
          : null,
      childrenPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      children: buildChildren(context, children),
    );
  }

  static Widget _buildColumn(
      BuildContext context, Map<String, dynamic> schema) {
    final children = _extractChildren(schema);
    final spacing = _parseDouble(schema['spacing']);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: _parseCrossAxis(schema['cross_axis_alignment']),
      mainAxisAlignment: _parseMainAxis(schema['main_axis_alignment']),
      children: [
        for (var i = 0; i < children.length; i++) ...[
          buildComponent(context, children[i]),
          if (spacing != null && i < children.length - 1)
            SizedBox(height: spacing),
        ],
      ],
    );
  }

  static Widget _buildRow(BuildContext context, Map<String, dynamic> schema) {
    if (schema['wrap'] == true) {
      return _buildWrap(context, schema);
    }
    final children = _extractChildren(schema);
    final spacing = _parseDouble(schema['spacing']);

    bool isExpanded(Map<String, dynamic> child) =>
        child['expanded'] == true || child['flex'] is num;
    bool isFixed(Map<String, dynamic> child) => child['flexible'] == false;

    // `space_between` / `center` / `end` only mean something when the Row is
    // allowed to fill its parent, and an `expanded` child needs bounded width
    // to lay out — so widen the Row for either. A plain content Row (no
    // alignment, no expanded child) keeps hugging its children as before.
    final hasMainAxis = schema['main_axis_alignment'] != null;
    final anyExpanded = children.any(isExpanded);

    return Row(
      mainAxisSize:
          (hasMainAxis || anyExpanded) ? MainAxisSize.max : MainAxisSize.min,
      crossAxisAlignment: _parseCrossAxis(schema['cross_axis_alignment']),
      mainAxisAlignment: _parseMainAxis(schema['main_axis_alignment']),
      children: [
        for (var i = 0; i < children.length; i++) ...[
          if (isExpanded(children[i]))
            Expanded(
              flex: (children[i]['flex'] as num?)?.toInt() ?? 1,
              child: buildComponent(context, children[i]),
            )
          else if (isFixed(children[i]))
            buildComponent(context, children[i])
          else
            Flexible(child: buildComponent(context, children[i])),
          if (spacing != null && i < children.length - 1)
            SizedBox(width: spacing),
        ],
      ],
    );
  }

  static Widget _buildWrap(BuildContext context, Map<String, dynamic> schema) {
    final children = _extractChildren(schema);
    final spacing = _parseDouble(schema['spacing']) ?? 8.0;
    final runSpacing = _parseDouble(schema['run_spacing']) ?? 8.0;

    return Wrap(
      spacing: spacing,
      runSpacing: runSpacing,
      alignment: _parseWrapAlignment(schema['alignment']),
      crossAxisAlignment:
          _parseWrapCrossAlignment(schema['cross_axis_alignment']),
      children: [
        for (final child in children) buildComponent(context, child),
      ],
    );
  }

  static WrapAlignment _parseWrapAlignment(dynamic value) {
    switch (value?.toString().toLowerCase()) {
      case 'center':
        return WrapAlignment.center;
      case 'end':
      case 'trailing':
        return WrapAlignment.end;
      case 'space_between':
      case 'spacebetween':
        return WrapAlignment.spaceBetween;
      case 'space_around':
      case 'spacearound':
        return WrapAlignment.spaceAround;
      case 'space_evenly':
      case 'spaceevenly':
        return WrapAlignment.spaceEvenly;
      case 'start':
      case 'leading':
      default:
        return WrapAlignment.start;
    }
  }

  static WrapCrossAlignment _parseWrapCrossAlignment(dynamic value) {
    switch (value?.toString().toLowerCase()) {
      case 'center':
        return WrapCrossAlignment.center;
      case 'end':
      case 'trailing':
        return WrapCrossAlignment.end;
      case 'start':
      case 'leading':
      default:
        return WrapCrossAlignment.start;
    }
  }

  static Widget _buildTabs(BuildContext context, Map<String, dynamic> schema) {
    final rawTabs = schema['tabs'] as List<dynamic>? ?? const [];
    if (rawTabs.isEmpty) return const SizedBox.shrink();

    final tabItems = rawTabs
        .whereType<Map>()
        .map((tab) => Map<String, dynamic>.from(tab))
        .toList();

    return DefaultTabController(
      length: tabItems.length,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TabBar(
            isScrollable: tabItems.length > 3,
            tabs: [
              for (final t in tabItems)
                Tab(
                  text: context.tr(t['title']?.toString() ?? ''),
                  icon: t['icon'] != null
                      ? Icon(SduiIconRegistry.resolve(t['icon'].toString()),
                          size: 18)
                      : null,
                ),
            ],
          ),
          SizedBox(
            height: 400,
            child: TabBarView(
              children: [
                for (final t in tabItems)
                  ListView(
                    padding: const EdgeInsets.all(16),
                    children: buildChildren(context, _extractChildren(t)),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ===========================================================================
  // Display
  // ===========================================================================

  static Widget _buildText(BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final text = context.tr(schema['text']?.toString() ?? '');
    final styleKey = schema['style']?.toString().toLowerCase();
    final isBold = schema['bold'] == true;
    final isItalic = schema['italic'] == true;
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : null;
    final maxLines = (schema['max_lines'] as num?)?.toInt();
    final textAlign = _parseTextAlign(schema['align']);

    TextStyle? baseStyle;
    switch (styleKey) {
      case 'headline_large':
        baseStyle = theme.textTheme.headlineLarge;
        break;
      case 'headline_medium':
        baseStyle = theme.textTheme.headlineMedium;
        break;
      case 'headline_small':
        baseStyle = theme.textTheme.headlineSmall;
        break;
      case 'title_large':
        baseStyle = theme.textTheme.titleLarge;
        break;
      case 'title_medium':
        baseStyle = theme.textTheme.titleMedium;
        break;
      case 'title_small':
        baseStyle = theme.textTheme.titleSmall;
        break;
      case 'body_large':
        baseStyle = theme.textTheme.bodyLarge;
        break;
      case 'body_small':
        baseStyle = theme.textTheme.bodySmall;
        break;
      case 'label_large':
        baseStyle = theme.textTheme.labelLarge;
        break;
      case 'label_medium':
        baseStyle = theme.textTheme.labelMedium;
        break;
      case 'label_small':
        baseStyle = theme.textTheme.labelSmall;
        break;
      case 'caption':
        baseStyle = theme.textTheme.bodySmall
            ?.copyWith(color: theme.colorScheme.onSurfaceVariant);
        break;
      case 'body_medium':
      default:
        baseStyle = theme.textTheme.bodyMedium;
        break;
    }

    final computedStyle = (baseStyle ?? const TextStyle()).copyWith(
      color: color,
      fontWeight: isBold ? FontWeight.bold : null,
      fontStyle: isItalic ? FontStyle.italic : null,
    );

    return Text(
      text,
      style: computedStyle,
      textAlign: textAlign,
      maxLines: maxLines,
      overflow: maxLines != null ? TextOverflow.ellipsis : null,
    );
  }

  static Widget _buildImageNetwork(
      BuildContext context, Map<String, dynamic> schema) {
    final url = schema['url']?.toString() ?? '';
    final width = _parseDouble(schema['width']);
    final height = _parseDouble(schema['height']);
    final borderRadius = _parseDouble(schema['border_radius']) ?? 8.0;

    if (url.isEmpty) return const SizedBox.shrink();

    Widget img = CachedNetworkImage(
      imageUrl: url,
      width: width,
      height: height,
      fit: BoxFit.cover,
      placeholder: (_, __) => Container(
        width: width,
        height: height,
        color: Colors.grey.shade200,
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
      ),
      errorWidget: (_, __, ___) => Container(
        width: width,
        height: height,
        color: Colors.grey.shade200,
        child: const Icon(Icons.broken_image, color: Colors.grey),
      ),
    );

    if (borderRadius > 0) {
      img = ClipRRect(
          borderRadius: BorderRadius.circular(borderRadius), child: img);
    }

    return img;
  }

  static Widget _buildBadge(BuildContext context, Map<String, dynamic> schema) {
    final label = context
        .tr(schema['label']?.toString() ?? schema['text']?.toString() ?? '');
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : Colors.blue;
    final isSolid = schema['badge_style'] == 'solid';

    final badge = SduiStatusBadge(
      label: label,
      color: color,
      isSolid: isSolid,
    );
    final maxWidth = _parseDouble(schema['max_width']);
    if (maxWidth == null) {
      return badge;
    }

    return ConstrainedBox(
      constraints: BoxConstraints(maxWidth: maxWidth),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.centerRight,
        child: badge,
      ),
    );
  }

  static Widget _buildIcon(BuildContext context, Map<String, dynamic> schema) {
    final iconName = schema['icon']?.toString() ?? 'widgets';
    final size = _parseDouble(schema['size']) ?? 24.0;
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : null;

    return Icon(
      SduiIconRegistry.resolve(iconName),
      size: size,
      color: color,
    );
  }

  static Widget _buildDivider(
      BuildContext context, Map<String, dynamic> schema) {
    final height = _parseDouble(schema['height']) ?? 16.0;
    final thickness = _parseDouble(schema['thickness']) ?? 1.0;
    final color = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString())
        : null;

    return Divider(height: height, thickness: thickness, color: color);
  }

  // ===========================================================================
  // Forms & Inputs
  // ===========================================================================

  static Widget _buildTextInput(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? '');
    final placeholder = context.tr(
        schema['placeholder']?.toString() ?? schema['hint']?.toString() ?? '');
    final initialValue = sduiContext?.formValues[name]?.toString() ??
        schema['initial_value']?.toString() ??
        '';
    final isPassword =
        schema['is_password'] == true || schema['obscure'] == true;
    final maxLines =
        isPassword ? 1 : ((schema['max_lines'] as num?)?.toInt() ?? 1);
    final keyboardTypeStr = schema['keyboard_type']?.toString().toLowerCase();
    final disabled = schema['disabled'] == true || schema['read_only'] == true;

    TextInputType keyboardType = TextInputType.text;
    if (keyboardTypeStr == 'number') keyboardType = TextInputType.number;
    if (keyboardTypeStr == 'phone') keyboardType = TextInputType.phone;
    if (keyboardTypeStr == 'email') keyboardType = TextInputType.emailAddress;
    if (keyboardTypeStr == 'multiline' || maxLines > 1)
      keyboardType = TextInputType.multiline;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: TextFormField(
        initialValue: initialValue,
        enabled: !disabled,
        obscureText: isPassword,
        maxLines: maxLines,
        keyboardType: keyboardType,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: _textValidator(schema),
        decoration: InputDecoration(
          labelText: label,
          hintText: placeholder,
          border: const OutlineInputBorder(),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        ),
        onChanged: (val) {
          sduiContext?.setFormValue(name, val);
        },
      ),
    );
  }

  static Widget _buildDropdownSelect(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? '');
    final rawOptions = schema['options'] as List<dynamic>? ?? const [];
    final currentVal = sduiContext?.formValues[name]?.toString() ??
        schema['initial_value']?.toString();

    final options = <DropdownMenuItem<String>>[];
    for (final opt in rawOptions) {
      if (opt is Map) {
        final optLabel =
            opt['label']?.toString() ?? opt['name']?.toString() ?? '';
        final optVal =
            opt['value']?.toString() ?? opt['code']?.toString() ?? optLabel;
        options.add(
            DropdownMenuItem(value: optVal, child: Text(context.tr(optLabel))));
      } else {
        final str = opt.toString();
        options.add(DropdownMenuItem(value: str, child: Text(context.tr(str))));
      }
    }

    final effectiveValue = options.any((o) => o.value == currentVal)
        ? currentVal
        : (options.isNotEmpty ? options.first.value : null);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: DropdownButtonFormField<String>(
        initialValue: effectiveValue,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: (value) {
          if (_isRequired(schema) && (value == null || value.isEmpty)) {
            return _requiredMessage(schema);
          }
          return null;
        },
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        ),
        items: options,
        onChanged: schema['disabled'] == true
            ? null
            : (newVal) {
                if (newVal != null) {
                  sduiContext?.setFormValue(name, newVal);
                }
              },
      ),
    );
  }

  static Widget _buildCheckbox(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? '');
    final subtitle = schema['subtitle'] == null
        ? null
        : context.tr(schema['subtitle'].toString());
    final isChecked = sduiContext?.formValues[name] == true ||
        (sduiContext?.formValues[name] == null &&
            schema['initial_value'] == true);

    return FormField<bool>(
      initialValue: isChecked,
      validator: (value) => _isRequired(schema) && value != true
          ? _requiredMessage(schema)
          : null,
      builder: (field) => StatefulBuilder(
        builder: (context, setState) {
          return CheckboxListTile(
            title: Text(label),
            subtitle: field.hasError
                ? Text(
                    field.errorText!,
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.error,
                    ),
                  )
                : subtitle != null
                    ? Text(subtitle, style: const TextStyle(fontSize: 12))
                    : null,
            value: sduiContext?.formValues[name] as bool? ?? isChecked,
            onChanged: schema['disabled'] == true
                ? null
                : (val) {
                    final newVal = val ?? false;
                    field.didChange(newVal);
                    setState(() {
                      sduiContext?.setFormValue(name, newVal);
                    });
                  },
          );
        },
      ),
    );
  }

  static Widget _buildToggleSwitch(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? '');
    final subtitle = schema['subtitle'] == null
        ? null
        : context.tr(schema['subtitle'].toString());
    final isToggled = sduiContext?.formValues[name] == true ||
        (sduiContext?.formValues[name] == null &&
            schema['initial_value'] == true);

    return StatefulBuilder(
      builder: (context, setState) {
        return SwitchListTile(
          title:
              Text(label, style: const TextStyle(fontWeight: FontWeight.w500)),
          subtitle: subtitle != null
              ? Text(subtitle, style: const TextStyle(fontSize: 12))
              : null,
          value: sduiContext?.formValues[name] as bool? ?? isToggled,
          onChanged: schema['disabled'] == true || schema['read_only'] == true
              ? null
              : (val) {
                  setState(() {
                    sduiContext?.setFormValue(name, val);
                  });
                },
        );
      },
    );
  }

  static Widget _buildDateTimePicker(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? 'Select Date');
    final initial = sduiContext?.formValues[name]?.toString() ??
        schema['initial_value']?.toString() ??
        '';
    final mode = schema['mode']?.toString().toLowerCase() ?? 'date';
    final disabled = schema['disabled'] == true || schema['read_only'] == true;

    return StatefulBuilder(
      builder: (context, setState) {
        final current = sduiContext?.formValues[name]?.toString() ?? initial;
        return FormField<String>(
          initialValue: current,
          validator: (value) {
            if (_isRequired(schema) && (value == null || value.isEmpty)) {
              return _requiredMessage(schema);
            }
            return null;
          },
          builder: (field) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ListTile(
                  enabled: !disabled,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                    side: BorderSide(
                      color: field.hasError
                          ? Theme.of(context).colorScheme.error
                          : Theme.of(context).colorScheme.outlineVariant,
                    ),
                  ),
                  title: Text(label,
                      style: const TextStyle(fontSize: 13, color: Colors.grey)),
                  subtitle: Text(
                      current.isNotEmpty
                          ? current
                          : context.tr('Tap to select'),
                      style: const TextStyle(fontSize: 15)),
                  trailing: Icon(mode == 'time'
                      ? Icons.schedule
                      : Icons.calendar_today_outlined),
                  onTap: disabled
                      ? null
                      : () async {
                          final now = DateTime.now();
                          DateTime? selectedDate;
                          TimeOfDay? selectedTime;

                          if (mode != 'time') {
                            final parsedInitial = DateTime.tryParse(current);
                            selectedDate = await showDatePicker(
                              context: context,
                              initialDate: parsedInitial ?? now,
                              firstDate: DateTime.tryParse(
                                      schema['first_date']?.toString() ?? '') ??
                                  DateTime(1900),
                              lastDate: DateTime.tryParse(
                                      schema['last_date']?.toString() ?? '') ??
                                  DateTime(2100),
                            );
                            if (selectedDate == null) return;
                          }

                          if (mode == 'time' ||
                              mode == 'datetime' ||
                              mode == 'date_time') {
                            selectedTime = await showTimePicker(
                              context: context,
                              initialTime: TimeOfDay.fromDateTime(
                                  DateTime.tryParse(current) ?? now),
                            );
                            if (selectedTime == null) return;
                          }

                          String formatted;
                          if (mode == 'time') {
                            formatted =
                                '${selectedTime!.hour.toString().padLeft(2, '0')}:${selectedTime.minute.toString().padLeft(2, '0')}';
                          } else {
                            final date = selectedDate!;
                            formatted =
                                '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
                            if (selectedTime != null) {
                              formatted +=
                                  ' ${selectedTime.hour.toString().padLeft(2, '0')}:${selectedTime.minute.toString().padLeft(2, '0')}';
                            }
                          }

                          field.didChange(formatted);
                          setState(
                              () => sduiContext?.setFormValue(name, formatted));
                        },
                ),
                if (field.hasError)
                  Padding(
                    padding: const EdgeInsets.only(left: 12, top: 6),
                    child: Text(
                      field.errorText!,
                      style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                          fontSize: 12),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  static Widget _buildColorPicker(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? 'Color');
    final rawPresets = (schema['presets'] as List<dynamic>? ??
            [
              '#1d4ed8',
              '#10b981',
              '#f59e0b',
              '#ef4444',
              '#6366f1',
              '#8b5cf6',
              '#0284c7',
            ])
        .map((p) => p.toString())
        .toList();
    final currentColor = sduiContext?.formValues[name]?.toString() ??
        schema['initial_value']?.toString() ??
        '#1d4ed8';

    return _SduiColorPickerField(
      name: name,
      label: label,
      presets: rawPresets,
      initialColor: currentColor,
      sduiContext: sduiContext,
      customLabel: context
          .tr(schema['custom_label']?.toString() ?? 'Choose custom color'),
      hueLabel: context.tr(schema['hue_label']?.toString() ?? 'Hue'),
      saturationLabel:
          context.tr(schema['saturation_label']?.toString() ?? 'Saturation'),
      brightnessLabel:
          context.tr(schema['brightness_label']?.toString() ?? 'Brightness'),
      cancelLabel: context.tr(schema['cancel_label']?.toString() ?? 'Cancel'),
      applyLabel:
          context.tr(schema['apply_label']?.toString() ?? 'Apply color'),
    );
  }

  static Widget _buildFileUpload(
      BuildContext context, Map<String, dynamic> schema) {
    return _SduiFileUploadField(schema: schema);
  }

  // ===========================================================================
  // Lists & Tables
  // ===========================================================================

  static Widget _buildLineItemTile(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final title = context.tr(schema['title']?.toString() ?? '');
    final subtitle = schema['subtitle'] == null
        ? null
        : context.tr(schema['subtitle'].toString());
    final iconName = schema['leading_icon']?.toString();
    final action = schema['action'] as Map<String, dynamic>?;

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      child: ListTile(
        leading:
            iconName != null ? Icon(SduiIconRegistry.resolve(iconName)) : null,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: subtitle != null
            ? Text(subtitle, style: const TextStyle(fontSize: 12))
            : null,
        trailing: action != null
            ? const Icon(Icons.arrow_forward_ios, size: 14)
            : null,
        onTap:
            action != null ? () => sduiContext?.dispatchAction(action) : null,
      ),
    );
  }

  static Widget _buildTableGrid(
      BuildContext context, Map<String, dynamic> schema) {
    final headers = (schema['headers'] as List<dynamic>? ?? const [])
        .map((e) => context.tr(e.toString()))
        .toList();
    final rows = schema['rows'] as List<dynamic>? ?? const [];

    if (headers.isEmpty) return const SizedBox.shrink();

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columns: [
          for (final h in headers)
            DataColumn(
                label: Text(h,
                    style: const TextStyle(fontWeight: FontWeight.bold))),
        ],
        rows: [
          for (final r in rows)
            if (r is List)
              DataRow(cells: [
                for (var index = 0; index < headers.length; index++)
                  DataCell(Text(index < r.length ? r[index].toString() : '')),
              ]),
        ],
      ),
    );
  }

  static Widget _buildStepCounter(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? 'Quantity');
    final initial = (sduiContext?.formValues[name] as num?)?.toInt() ??
        (schema['initial_value'] as num?)?.toInt() ??
        1;
    final min = (schema['min'] as num?)?.toInt() ?? 0;
    final max = (schema['max'] as num?)?.toInt() ?? 999;

    return StatefulBuilder(
      builder: (context, setState) {
        final currentVal =
            (sduiContext?.formValues[name] as num?)?.toInt() ?? initial;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label, style: const TextStyle(fontWeight: FontWeight.w500)),
              SduiStepCounter(
                value: currentVal,
                min: min,
                max: max,
                onChanged: (newVal) {
                  setState(() {
                    sduiContext?.setFormValue(name, newVal);
                  });
                },
              ),
            ],
          ),
        );
      },
    );
  }

  // ===========================================================================
  // Actions
  // ===========================================================================

  /// System-standard forest green for every SDUI primary button, so the
  /// action colour never drifts to the tenant's Material seed (navy/slate).
  /// A schema `background_color` still wins when the server sets one.
  static const Color _primaryButtonGreen = Color(0xFF166534);
  static const Color _outlinedButtonGreen = Color(0xFF15803D);

  static Widget _buildButtonPrimary(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? 'Submit');
    final iconName = schema['icon']?.toString();
    final action = schema['action'] as Map<String, dynamic>? ?? const {};
    final isFullWidth = schema['full_width'] != false;
    final enabled = schema['enabled'] != false;
    final bgColor = schema['background_color'] != null
        ? SduiIconRegistry.parseColor(schema['background_color'].toString(),
            fallback: _primaryButtonGreen)
        : _primaryButtonGreen;
    final fgColor = schema['foreground_color'] != null
        ? SduiIconRegistry.parseColor(schema['foreground_color'].toString(),
            fallback: Colors.white)
        : Colors.white;
    final radius = _parseDouble(schema['border_radius']) ?? 10.0;

    Widget btn = ElevatedButton.icon(
      icon: iconName != null
          ? Icon(SduiIconRegistry.resolve(iconName))
          : const SizedBox.shrink(),
      label: Text(label),
      style: ElevatedButton.styleFrom(
        backgroundColor: bgColor,
        foregroundColor: fgColor,
        disabledBackgroundColor: bgColor.withValues(alpha: 0.4),
        disabledForegroundColor: fgColor.withValues(alpha: 0.8),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius)),
      ),
      onPressed: enabled ? () => sduiContext?.dispatchAction(action) : null,
    );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: btn,
    );
  }

  static Widget _buildButtonOutlined(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? '');
    final iconName = schema['icon']?.toString();
    final action = schema['action'] as Map<String, dynamic>? ?? const {};
    final isFullWidth = schema['full_width'] != false;
    final enabled = schema['enabled'] != false;
    final accent = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString(),
            fallback: _outlinedButtonGreen)
        : (schema['border_color'] != null
            ? SduiIconRegistry.parseColor(schema['border_color'].toString(),
                fallback: _outlinedButtonGreen)
            : _outlinedButtonGreen);
    final radius = _parseDouble(schema['border_radius']) ?? 10.0;

    Widget btn = OutlinedButton.icon(
      icon: iconName != null
          ? Icon(SduiIconRegistry.resolve(iconName))
          : const SizedBox.shrink(),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        foregroundColor: accent,
        side: BorderSide(color: accent.withValues(alpha: 0.6)),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius)),
      ),
      onPressed: enabled ? () => sduiContext?.dispatchAction(action) : null,
    );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: btn,
    );
  }

  static Widget _buildButtonDanger(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? 'Delete');
    final iconName = schema['icon']?.toString();
    final action = schema['action'] as Map<String, dynamic>? ?? const {};
    final isFullWidth = schema['full_width'] != false;
    final enabled = schema['enabled'] != false;

    Future<void> handlePress() async {
      final confirmMessage = action['confirm_message']?.toString() ??
          schema['confirm_message']?.toString();
      if (confirmMessage != null && confirmMessage.isNotEmpty) {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (dialogCtx) => AlertDialog(
            title: Text(context.tr('Confirm Action')),
            content: Text(context.tr(confirmMessage)),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogCtx).pop(false),
                child: Text(context.tr('Cancel')),
              ),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.red.shade700,
                  foregroundColor: Colors.white,
                ),
                onPressed: () => Navigator.of(dialogCtx).pop(true),
                child: Text(context.tr('Proceed')),
              ),
            ],
          ),
        );
        if (confirmed != true) return;
      }
      sduiContext?.dispatchAction(action);
    }

    Widget btn = ElevatedButton.icon(
      icon: iconName != null
          ? Icon(SduiIconRegistry.resolve(iconName))
          : const SizedBox.shrink(),
      label: Text(label),
      style: ElevatedButton.styleFrom(
        backgroundColor: Colors.red.shade600,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      onPressed: enabled ? handlePress : null,
    );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: btn,
    );
  }

  static Widget _buildFab(BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final iconName = schema['icon']?.toString() ?? 'add';
    final label =
        schema['label'] == null ? null : context.tr(schema['label'].toString());
    final action = schema['action'] as Map<String, dynamic>? ?? const {};

    if (label != null && label.isNotEmpty) {
      return FloatingActionButton.extended(
        onPressed: () => sduiContext?.dispatchAction(action),
        icon: Icon(SduiIconRegistry.resolve(iconName)),
        label: Text(label),
      );
    }

    return FloatingActionButton(
      onPressed: () => sduiContext?.dispatchAction(action),
      child: Icon(SduiIconRegistry.resolve(iconName)),
    );
  }

  static Widget _buildActionSheetTrigger(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? 'Actions');
    final iconName = schema['icon']?.toString() ?? 'more_vert';
    final sheetTitle = context.tr(schema['sheet_title']?.toString() ?? label);
    final options = schema['options'] as List<dynamic>? ?? const [];

    return ListTile(
      leading: Icon(SduiIconRegistry.resolve(iconName)),
      title: Text(label),
      trailing: const Icon(Icons.arrow_forward_ios, size: 14),
      onTap: () {
        showModalBottomSheet(
          context: context,
          builder: (ctx) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(sheetTitle,
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 16)),
                ),
                const Divider(height: 1),
                for (final opt in options)
                  if (opt is Map)
                    ListTile(
                      leading: opt['icon'] != null
                          ? Icon(
                              SduiIconRegistry.resolve(opt['icon'].toString()))
                          : null,
                      title: Text(context.tr(opt['label']?.toString() ?? '')),
                      onTap: () {
                        Navigator.of(ctx).pop();
                        final optAction =
                            opt['action'] as Map<String, dynamic>?;
                        if (optAction != null) {
                          sduiContext?.dispatchAction(optAction);
                        }
                      },
                    ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ===========================================================================
  // Helpers
  // ===========================================================================

  static List<Map<String, dynamic>> _extractChildren(
      Map<String, dynamic> schema) {
    final raw = schema['components'] ?? schema['children'] ?? schema['child'];
    if (raw is List) {
      return raw
          .whereType<Map>()
          .map((child) => Map<String, dynamic>.from(child))
          .toList();
    }
    if (raw is Map) {
      return [Map<String, dynamic>.from(raw)];
    }
    return const [];
  }

  static EdgeInsets _parseEdgeInsets(dynamic val,
      {EdgeInsets fallback = EdgeInsets.zero}) {
    if (val == null) return fallback;
    if (val is num) return EdgeInsets.all(val.toDouble());
    if (val is List) {
      if (val.length == 2) {
        return EdgeInsets.symmetric(
          horizontal: (val[0] as num).toDouble(),
          vertical: (val[1] as num).toDouble(),
        );
      }
      if (val.length == 4) {
        return EdgeInsets.fromLTRB(
          (val[0] as num).toDouble(),
          (val[1] as num).toDouble(),
          (val[2] as num).toDouble(),
          (val[3] as num).toDouble(),
        );
      }
    }
    return fallback;
  }

  static double? _parseDouble(dynamic val) {
    if (val is num) return val.toDouble();
    if (val is String) return double.tryParse(val);
    return null;
  }

  static bool _isRequired(Map<String, dynamic> schema) {
    final validation = schema['validation'];
    return schema['required'] == true ||
        (validation is Map && validation['required'] == true);
  }

  static String _requiredMessage(Map<String, dynamic> schema) {
    final validation = schema['validation'];
    if (validation is Map && validation['required_message'] != null) {
      return validation['required_message'].toString();
    }
    return '${schema['label'] ?? 'This field'} is required.';
  }

  static FormFieldValidator<String> _textValidator(
      Map<String, dynamic> schema) {
    return (value) {
      final text = value ?? '';
      final validation = schema['validation'];
      final rules = validation is Map ? validation : const <String, dynamic>{};

      if (_isRequired(schema) && text.trim().isEmpty) {
        return _requiredMessage(schema);
      }
      if (text.isEmpty) return null;

      final minLength = (rules['min_length'] as num?)?.toInt() ??
          (schema['min_length'] as num?)?.toInt();
      final maxLength = (rules['max_length'] as num?)?.toInt() ??
          (schema['max_length'] as num?)?.toInt();
      if (minLength != null && text.length < minLength) {
        return rules['min_length_message']?.toString() ??
            'Enter at least $minLength characters.';
      }
      if (maxLength != null && text.length > maxLength) {
        return rules['max_length_message']?.toString() ??
            'Enter no more than $maxLength characters.';
      }

      final pattern =
          rules['pattern']?.toString() ?? schema['pattern']?.toString();
      if (pattern != null && pattern.isNotEmpty) {
        try {
          if (!RegExp(pattern).hasMatch(text)) {
            return rules['pattern_message']?.toString() ??
                'Enter a valid value.';
          }
        } on FormatException {
          return 'This field has an invalid validation rule.';
        }
      }

      final keyboardType = schema['keyboard_type']?.toString().toLowerCase();
      if (keyboardType == 'email' &&
          !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text)) {
        return rules['email_message']?.toString() ??
            'Enter a valid email address.';
      }
      if (keyboardType == 'number') {
        final number = num.tryParse(text);
        if (number == null) return 'Enter a valid number.';
        final min = _parseDouble(rules['min'] ?? schema['min']);
        final max = _parseDouble(rules['max'] ?? schema['max']);
        if (min != null && number < min) return 'Value must be at least $min.';
        if (max != null && number > max) return 'Value must be at most $max.';
      }

      return null;
    };
  }

  static CrossAxisAlignment _parseCrossAxis(dynamic val) {
    switch (val?.toString().toLowerCase()) {
      case 'start':
        return CrossAxisAlignment.start;
      case 'end':
        return CrossAxisAlignment.end;
      case 'center':
        return CrossAxisAlignment.center;
      case 'stretch':
        return CrossAxisAlignment.stretch;
      default:
        return CrossAxisAlignment.start;
    }
  }

  static MainAxisAlignment _parseMainAxis(dynamic val) {
    switch (val?.toString().toLowerCase()) {
      case 'start':
        return MainAxisAlignment.start;
      case 'end':
        return MainAxisAlignment.end;
      case 'center':
        return MainAxisAlignment.center;
      case 'space_between':
      case 'spacebetween':
        return MainAxisAlignment.spaceBetween;
      case 'space_around':
      case 'spacearound':
        return MainAxisAlignment.spaceAround;
      default:
        return MainAxisAlignment.start;
    }
  }

  static TextAlign _parseTextAlign(dynamic val) {
    switch (val?.toString().toLowerCase()) {
      case 'center':
        return TextAlign.center;
      case 'right':
      case 'end':
        return TextAlign.right;
      case 'justify':
        return TextAlign.justify;
      case 'left':
      case 'start':
      default:
        return TextAlign.left;
    }
  }
}

class _SduiFileUploadField extends StatefulWidget {
  const _SduiFileUploadField({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiFileUploadField> createState() => _SduiFileUploadFieldState();
}

class _SduiFileUploadFieldState extends State<_SduiFileUploadField> {
  String? _url;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _url = widget.schema['current_url']?.toString();
  }

  Future<void> _pickAndUpload() async {
    final sdui = DynamicSchemaContext.of(context);
    final client = sdui?.apiClient;
    final endpoint = widget.schema['upload_endpoint']?.toString() ?? '';
    if (client == null || endpoint.isEmpty) return;

    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final file = result?.files.single;
    if (file?.bytes == null) return;

    setState(() => _busy = true);
    try {
      final response = await client.postMultipartAbsolute(
        endpoint,
        fieldName: widget.schema['field_name']?.toString() ??
            widget.schema['name']?.toString() ??
            'file',
        bytes: file!.bytes!,
        filename: file.name,
      );
      final nextUrl = _readPath(
        response,
        widget.schema['response_url_path']?.toString() ?? 'url',
      )?.toString();
      if (mounted) setState(() => _url = nextUrl ?? _url);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _remove() async {
    final sdui = DynamicSchemaContext.of(context);
    final client = sdui?.apiClient;
    final endpoint = widget.schema['delete_endpoint']?.toString() ?? '';
    if (client == null || endpoint.isEmpty) return;

    setState(() => _busy = true);
    try {
      await client.requestAbsolute(endpoint, method: 'DELETE');
      if (mounted) setState(() => _url = null);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  static dynamic _readPath(Map<String, dynamic> source, String path) {
    dynamic value = source;
    for (final segment in path.split('.')) {
      if (value is! Map || !value.containsKey(segment)) return null;
      value = value[segment];
    }
    return value;
  }

  @override
  Widget build(BuildContext context) {
    final label = context.tr(widget.schema['label']?.toString() ?? 'File');
    final selectLabel =
        context.tr(widget.schema['select_label']?.toString() ?? 'Choose File');
    final removeLabel =
        context.tr(widget.schema['remove_label']?.toString() ?? 'Remove');
    final hasFile = _url?.isNotEmpty == true;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
          if (hasFile) ...[
            const SizedBox(height: 8),
            CachedNetworkImage(
              imageUrl: _url!,
              height: 88,
              fit: BoxFit.contain,
              errorWidget: (_, __, ___) => const Icon(Icons.broken_image),
            ),
          ],
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: _busy ? null : _pickAndUpload,
                icon: _busy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.upload_file),
                label: Text(selectLabel),
              ),
              if (hasFile)
                TextButton.icon(
                  onPressed: _busy ? null : _remove,
                  icon: const Icon(Icons.delete_outline),
                  label: Text(removeLabel),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SduiColorPickerField extends StatefulWidget {
  const _SduiColorPickerField({
    required this.name,
    required this.label,
    required this.presets,
    required this.initialColor,
    required this.sduiContext,
    required this.customLabel,
    required this.hueLabel,
    required this.saturationLabel,
    required this.brightnessLabel,
    required this.cancelLabel,
    required this.applyLabel,
  });

  final String name;
  final String label;
  final List<String> presets;
  final String initialColor;
  final DynamicSchemaContext? sduiContext;
  final String customLabel;
  final String hueLabel;
  final String saturationLabel;
  final String brightnessLabel;
  final String cancelLabel;
  final String applyLabel;

  @override
  State<_SduiColorPickerField> createState() => _SduiColorPickerFieldState();
}

class _SduiColorPickerFieldState extends State<_SduiColorPickerField> {
  late String _currentHex;

  @override
  void initState() {
    super.initState();
    _currentHex = _normalizeHex(
      widget.sduiContext?.formValues[widget.name]?.toString() ??
          widget.initialColor,
    );
  }

  @override
  void didUpdateWidget(covariant _SduiColorPickerField oldWidget) {
    super.didUpdateWidget(oldWidget);
    final contextVal = widget.sduiContext?.formValues[widget.name]?.toString();
    if (contextVal != null) {
      final normalized = _normalizeHex(contextVal);
      if (normalized != _currentHex) _currentHex = normalized;
    }
  }

  String _normalizeHex(String raw) {
    var clean = raw.trim().replaceAll('#', '').toUpperCase();
    // Existing installations may contain #AARRGGBB. The SDUI form contract
    // now always writes opaque #RRGGBB values.
    if (clean.length == 8) clean = clean.substring(2);
    if (clean.length != 6 || int.tryParse(clean, radix: 16) == null) {
      clean = '1D4ED8';
    }
    return '#$clean';
  }

  String _hexFromColor(Color color) {
    final rgb = color.toARGB32() & 0x00FFFFFF;
    return '#${rgb.toRadixString(16).padLeft(6, '0').toUpperCase()}';
  }

  void _onColorSelected(String hex) {
    final formatted = _normalizeHex(hex);
    setState(() => _currentHex = formatted);
    widget.sduiContext?.setFormValue(widget.name, formatted);
  }

  Future<void> _openInteractivePicker() async {
    var hsv = HSVColor.fromColor(SduiIconRegistry.parseColor(_currentHex));
    final selected = await showDialog<String>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          final color = hsv.toColor();
          final hex = _hexFromColor(color);
          return AlertDialog(
            title: Text(widget.label),
            content: SingleChildScrollView(
              child: SizedBox(
                width: 360,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      height: 88,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: color,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                            color: Theme.of(context).colorScheme.outline),
                      ),
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: ThemeData.estimateBrightnessForColor(color) ==
                                  Brightness.dark
                              ? Colors.black54
                              : Colors.white70,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 6),
                          child: Text(
                            hex,
                            style: const TextStyle(
                                fontFamily: 'monospace',
                                fontWeight: FontWeight.bold),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    _HsvColorSlider(
                      label: widget.hueLabel,
                      value: hsv.hue,
                      max: 360,
                      gradientColors: [
                        for (final hue in const [
                          0.0,
                          60.0,
                          120.0,
                          180.0,
                          240.0,
                          300.0,
                          360.0
                        ])
                          HSVColor.fromAHSV(1, hue, 1, 1).toColor(),
                      ],
                      onChanged: (value) =>
                          setDialogState(() => hsv = hsv.withHue(value)),
                    ),
                    _HsvColorSlider(
                      label: widget.saturationLabel,
                      value: hsv.saturation,
                      max: 1,
                      gradientColors: [
                        HSVColor.fromAHSV(1, hsv.hue, 0, hsv.value).toColor(),
                        HSVColor.fromAHSV(1, hsv.hue, 1, hsv.value).toColor(),
                      ],
                      onChanged: (value) =>
                          setDialogState(() => hsv = hsv.withSaturation(value)),
                    ),
                    _HsvColorSlider(
                      label: widget.brightnessLabel,
                      value: hsv.value,
                      max: 1,
                      gradientColors: [
                        Colors.black,
                        HSVColor.fromAHSV(1, hsv.hue, hsv.saturation, 1)
                            .toColor(),
                      ],
                      onChanged: (value) =>
                          setDialogState(() => hsv = hsv.withValue(value)),
                    ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: Text(widget.cancelLabel),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(hex),
                child: Text(widget.applyLabel),
              ),
            ],
          );
        },
      ),
    );

    if (selected != null && mounted) _onColorSelected(selected);
  }

  @override
  Widget build(BuildContext context) {
    final activeColor = SduiIconRegistry.parseColor(_currentHex);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.label,
              style:
                  const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 10,
            runSpacing: 8,
            children: [
              for (final p in widget.presets)
                GestureDetector(
                  onTap: () => _onColorSelected(p),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: SduiIconRegistry.parseColor(p),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: _currentHex.toLowerCase() == p.toLowerCase()
                            ? Colors.black
                            : Colors.transparent,
                        width: 2.5,
                      ),
                    ),
                    child: _currentHex.toLowerCase() == p.toLowerCase()
                        ? const Icon(Icons.check, size: 18, color: Colors.white)
                        : null,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Semantics(
            button: true,
            label: widget.customLabel,
            child: InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: _openInteractivePicker,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.grey.shade400),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: activeColor,
                        borderRadius: BorderRadius.circular(7),
                        border: Border.all(color: Colors.grey.shade400),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(widget.customLabel,
                              style:
                                  const TextStyle(fontWeight: FontWeight.w600)),
                          Text(_currentHex,
                              style: TextStyle(
                                  fontSize: 12,
                                  fontFamily: 'monospace',
                                  color: Colors.grey.shade700)),
                        ],
                      ),
                    ),
                    const Icon(Icons.colorize_outlined),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HsvColorSlider extends StatelessWidget {
  const _HsvColorSlider({
    required this.label,
    required this.value,
    required this.max,
    required this.gradientColors,
    required this.onChanged,
  });

  final String label;
  final double value;
  final double max;
  final List<Color> gradientColors;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    final displayed =
        max == 1 ? '${(value * 100).round()}%' : '${value.round()}°';
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
              Text(displayed,
                  style:
                      const TextStyle(fontFamily: 'monospace', fontSize: 12)),
            ],
          ),
          const SizedBox(height: 2),
          SizedBox(
            height: 38,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Positioned(
                  left: 12,
                  right: 12,
                  child: Container(
                    height: 10,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(5),
                      gradient: LinearGradient(colors: gradientColors),
                      border: Border.all(color: Colors.black26),
                    ),
                  ),
                ),
                SliderTheme(
                  data: SliderTheme.of(context).copyWith(
                    activeTrackColor: Colors.transparent,
                    inactiveTrackColor: Colors.transparent,
                    trackHeight: 10,
                    overlayShape: SliderComponentShape.noOverlay,
                    thumbColor: Theme.of(context).colorScheme.onSurface,
                  ),
                  child: Slider(
                    value: value.clamp(0, max),
                    min: 0,
                    max: max,
                    onChanged: onChanged,
                    semanticFormatterCallback: (_) => '$label $displayed',
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Multi-step form wizard. Renders one step's components at a time with a
/// progress header and Back / Next / Submit controls. All steps share the
/// page-global form scope (DynamicSchemaContext.formValues), so values entered
/// on earlier steps survive navigating away and back, and the final Submit
/// posts the entire accumulated form via [submit_action].
class _SduiStepper extends StatefulWidget {
  const _SduiStepper({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiStepper> createState() => _SduiStepperState();
}

class _SduiStepperState extends State<_SduiStepper> {
  int _current = 0;
  bool _submitting = false;

  List<Map<String, dynamic>> get _steps {
    final raw = widget.schema['steps'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((s) => Map<String, dynamic>.from(s))
        .toList();
  }

  /// Names of required input fields in [step] that are still empty.
  List<String> _missingRequired(
      Map<String, dynamic> step, DynamicSchemaContext? ctx) {
    final missing = <String>[];

    void walk(dynamic node) {
      if (node is List) {
        for (final child in node) {
          walk(child);
        }
        return;
      }
      if (node is! Map) return;
      final map = Map<String, dynamic>.from(node);
      final type = map['type']?.toString().toLowerCase().trim();
      const inputTypes = {
        'text_input',
        'dropdown_select',
        'date_time_picker',
        'file_upload',
      };
      if (type != null &&
          inputTypes.contains(type) &&
          DynamicSchemaParser._isRequired(map)) {
        final name = map['name']?.toString() ?? '';
        final value = ctx?.formValues[name];
        final isEmpty =
            value == null || (value is String && value.trim().isEmpty);
        if (name.isNotEmpty && isEmpty) {
          missing.add(map['label']?.toString() ?? name);
        }
      }
      walk(map['components'] ?? map['children'] ?? map['child']);
    }

    walk(step['components'] ?? step['children'] ?? step['child']);
    return missing;
  }

  Future<void> _next() async {
    final ctx = DynamicSchemaContext.of(context);
    final steps = _steps;
    if (_current >= steps.length) return;

    final missing = _missingRequired(steps[_current], ctx);
    if (missing.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.tr('Please complete: ') + missing.join(', ')),
          backgroundColor: Colors.red.shade700,
        ),
      );
      return;
    }

    if (_current < steps.length - 1) {
      setState(() => _current++);
      return;
    }

    // Last step -> submit the whole form.
    final action = widget.schema['submit_action'];
    if (action is Map) {
      setState(() => _submitting = true);
      try {
        await ctx?.dispatchAction(Map<String, dynamic>.from(action));
      } finally {
        if (mounted) setState(() => _submitting = false);
      }
    }
  }

  void _back() {
    if (_current > 0) setState(() => _current--);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final steps = _steps;
    if (steps.isEmpty) return const SizedBox.shrink();

    final current = _current.clamp(0, steps.length - 1);
    final step = steps[current];
    final isLast = current == steps.length - 1;
    final title = context.tr(step['title']?.toString() ?? '');
    final subtitle = step['subtitle'] == null
        ? null
        : context.tr(step['subtitle'].toString());

    final submitLabel =
        context.tr(widget.schema['submit_label']?.toString() ?? 'Submit');
    final nextLabel =
        context.tr(widget.schema['next_label']?.toString() ?? 'Next');
    final backLabel =
        context.tr(widget.schema['back_label']?.toString() ?? 'Back');

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Progress header
        Padding(
          padding: const EdgeInsets.fromLTRB(4, 0, 4, 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.tr('Step {current} of {total}', {
                  'current': current + 1,
                  'total': steps.length,
                }),
                style: theme.textTheme.labelMedium
                    ?.copyWith(color: theme.colorScheme.primary),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  for (var i = 0; i < steps.length; i++)
                    Expanded(
                      child: Container(
                        margin: EdgeInsets.only(
                            right: i == steps.length - 1 ? 0 : 6),
                        height: 4,
                        decoration: BoxDecoration(
                          color: i <= current
                              ? theme.colorScheme.primary
                              : theme.colorScheme.surfaceContainerHighest,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                ],
              ),
              if (title.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(title,
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold)),
              ],
              if (subtitle != null && subtitle.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(subtitle,
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
              ],
            ],
          ),
        ),

        // Active step body. Keyed by index so field elements are rebuilt fresh
        // from formValues when switching steps.
        Column(
          key: ValueKey<int>(current),
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: DynamicSchemaParser.buildChildren(
              context, step['components'] ?? step['children'] ?? const []),
        ),

        const SizedBox(height: 16),

        // Navigation controls
        Row(
          children: [
            if (current > 0)
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _submitting ? null : _back,
                  icon: const Icon(Icons.arrow_back, size: 18),
                  label: Text(backLabel),
                ),
              ),
            if (current > 0) const SizedBox(width: 12),
            Expanded(
              child: FilledButton.icon(
                onPressed: _submitting ? null : _next,
                icon: _submitting
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : Icon(isLast ? Icons.check : Icons.arrow_forward,
                        size: 18),
                label: Text(isLast ? submitLabel : nextLabel),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
      ],
    );
  }
}

/// Self-contained "Cash Tendered by Customer" field for the SDUI checkout
/// drawer. The input's onChanged and the quick-cash chips recompute
/// CHANGE DUE TO CUSTOMER entirely on-device — no network round trip and no
/// sheet reload per keystroke — and mirror the tendered amount into the
/// shared SDUI form values so the checkout submit picks it up.
class _CashTenderedField extends StatefulWidget {
  const _CashTenderedField({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_CashTenderedField> createState() => _CashTenderedFieldState();
}

class _CashTenderedFieldState extends State<_CashTenderedField> {
  late final TextEditingController _controller;
  late final String _name;
  late final double _total;
  late final String _currency;
  double _tendered = 0;

  static double _num(dynamic v) {
    if (v is num) return v.toDouble();
    return double.tryParse('${v ?? ''}'.replaceAll(RegExp(r'[^0-9.\-]'), '')) ??
        0.0;
  }

  double get _changeDue => _tendered > _total ? _tendered - _total : 0.0;

  @override
  void initState() {
    super.initState();
    _name = widget.schema['name']?.toString() ?? 'tendered';
    _total = _num(widget.schema['total']);
    _currency = widget.schema['currency']?.toString() ?? r'$';
    final initial = widget.schema['initial_value']?.toString() ?? '';
    _controller = TextEditingController(text: initial);
    _tendered = _num(initial);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        DynamicSchemaContext.of(context)
            ?.setFormValue(_name, _tendered.toStringAsFixed(2));
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _commit(double value, {bool writeField = false}) {
    DynamicSchemaContext.of(context)
        ?.setFormValue(_name, value.toStringAsFixed(2));
    setState(() {
      _tendered = value;
      if (writeField) {
        _controller.text = value.toStringAsFixed(2);
        _controller.selection =
            TextSelection.collapsed(offset: _controller.text.length);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final label = context
        .tr(widget.schema['label']?.toString() ?? 'Cash Tendered by Customer');
    final chips =
        ((widget.schema['quick_cash'] as List?) ?? const []).map(_num).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: TextField(
            controller: _controller,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: label,
              prefixIcon: const Icon(Icons.payments_outlined),
              border: const OutlineInputBorder(),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
            onChanged: (val) => _commit(_num(val)),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFECFDF5),
            border: Border.all(color: const Color(0xFF86EFAC)),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'CHANGE DUE TO CUSTOMER',
                      style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 11,
                          color: Color(0xFF166534)),
                    ),
                    Text(
                      'Change Due to Customer',
                      style: TextStyle(fontSize: 12, color: Color(0xFF15803D)),
                    ),
                  ],
                ),
              ),
              Text(
                '$_currency${_changeDue.toStringAsFixed(2)}',
                style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 22,
                    color: Color(0xFF166534)),
              ),
            ],
          ),
        ),
        if (chips.isNotEmpty) ...[
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final amt in chips) _quickChip(amt)],
          ),
        ],
      ],
    );
  }

  Widget _quickChip(double amt) {
    final selected = (amt - _tendered).abs() < 0.001;
    final isExact = (amt - _total).abs() < 0.001;
    final label = isExact ? 'EXACT' : '$_currency${amt.toStringAsFixed(2)}';
    final shape =
        RoundedRectangleBorder(borderRadius: BorderRadius.circular(16));
    return selected
        ? ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF166534),
              foregroundColor: Colors.white,
              shape: shape,
            ),
            onPressed: () => _commit(amt, writeField: true),
            child: Text(label),
          )
        : OutlinedButton(
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFF15803D),
              side: const BorderSide(color: Color(0x9915803D)),
              shape: shape,
            ),
            onPressed: () => _commit(amt, writeField: true),
            child: Text(label),
          );
  }
}
