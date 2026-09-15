import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../api/api_exception.dart';
import '../models/settings_models.dart';
import '../services/dynamic_string_service.dart';
import '../widgets/sdui/sdui_controls.dart';
import 'components/navigation_tree_builder.dart';
import 'dynamic_schema_context.dart';
import 'sdui_icon_registry.dart';
import 'sdui_tab_advancer.dart';

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
      case 'code_snippet':
      case 'code_block':
        return _buildCodeSnippet(context, schema);
      case 'section_header':
        return _buildSectionHeader(context, schema);
      case 'empty_state':
        return _buildEmptyState(context, schema);
      case 'document_preview_card':
        return _buildDocumentPreviewCard(context, schema);

      // Forms & Inputs
      case 'text_input':
        return _buildTextInput(context, schema);
      case 'dropdown_select':
        return _buildDropdownSelect(context, schema);
      case 'creatable_select':
        return _SduiCreatableSelect(schema: schema);
      case 'search_bar':
        return _SduiSearchBar(schema: schema);
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
      case 'file_picker':
        return _SduiFilePickerField(schema: schema);
      case 'cash_tendered_field':
        return _CashTenderedField(schema: schema);
      case 'customer_picker':
      case 'customer_selector':
        return _CustomerSelector(schema: schema);

      // Lists & Tables
      case 'line_item_tile':
        return _buildLineItemTile(context, schema);
      case 'list_tile':
        return _buildListTile(context, schema);
      case 'notification_item':
        return _buildNotificationItem(context, schema);
      case 'table_grid':
        return _buildTableGrid(context, schema);
      case 'step_counter':
        return _buildStepCounter(context, schema);
      case 'entity_record_card':
        return _buildEntityRecordCard(context, schema);
      case 'pipeline_stage_tracker':
        return _buildPipelineStageTracker(context, schema);
      case 'progress_bar_stat':
        return _buildProgressBarStat(context, schema);
      case 'segmented_filter_chips':
        return _buildSegmentedFilterChips(context, schema);
      case 'segmented_tabs':
        return _buildSegmentedTabs(context, schema);

      // Actions
      case 'button':
        return _buildGenericButton(context, schema);
      case 'button_primary':
        return _buildButtonPrimary(context, schema);
      case 'button_outlined':
        return _buildButtonOutlined(context, schema);
      case 'button_danger':
        return _buildButtonDanger(context, schema);
      case 'fab':
      case 'fab_action':
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final children = _extractChildren(schema);
    final padding = _parseEdgeInsets(schema['padding']);
    final margin = _parseEdgeInsets(schema['margin']);
    final width = _parseDouble(schema['width']);
    final height = _parseDouble(schema['height']);

    Color? color;
    final rawColor = schema['color']?.toString().trim();
    if (rawColor != null && rawColor.isNotEmpty) {
      final parsed = _semanticColor(context, rawColor,
          fallback: theme.colorScheme.surface);
      if (!rawColor.startsWith('theme.') &&
          rawColor != 'surface' &&
          rawColor != 'card') {
        if (isDark &&
            schema['force_light'] != true &&
            ThemeData.estimateBrightnessForColor(parsed) == Brightness.light) {
          color = theme.colorScheme.surface;
        } else {
          color = parsed;
        }
      } else {
        color = parsed;
      }
    }

    final borderRadius = _parseDouble(schema['border_radius']);

    Color? borderColor;
    final rawBorderColor = schema['border_color']?.toString().trim();
    if (rawBorderColor != null && rawBorderColor.isNotEmpty) {
      final parsedBorder =
          _semanticColor(context, rawBorderColor, fallback: theme.dividerColor);
      if (!rawBorderColor.startsWith('theme.') &&
          rawBorderColor != 'outline' &&
          rawBorderColor != 'border') {
        if (isDark &&
            schema['force_light'] != true &&
            ThemeData.estimateBrightnessForColor(parsedBorder) ==
                Brightness.light) {
          borderColor = const Color(0xFF334155);
        } else {
          borderColor = parsedBorder;
        }
      } else {
        borderColor = parsedBorder;
      }
    }
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
    final isDark = theme.brightness == Brightness.dark;
    final children = _extractChildren(schema);
    final padding =
        _parseEdgeInsets(schema['padding'], fallback: const EdgeInsets.all(16));
    final margin = _parseEdgeInsets(schema['margin'],
        fallback: const EdgeInsets.only(bottom: 12));
    final elevation = _parseDouble(schema['elevation']) ?? 0.0;
    final borderRadius = _parseDouble(schema['border_radius']) ?? 12.0;

    Color color;
    final rawColor = schema['color']?.toString().trim();
    if (rawColor == null ||
        rawColor.isEmpty ||
        rawColor == 'surface' ||
        rawColor == 'card' ||
        rawColor == 'theme.surface') {
      color = theme.colorScheme.surface;
    } else {
      final parsed = _semanticColor(context, rawColor,
          fallback: theme.colorScheme.surface);
      if (isDark &&
          schema['force_light'] != true &&
          ThemeData.estimateBrightnessForColor(parsed) == Brightness.light) {
        color = theme.colorScheme.surface;
      } else {
        color = parsed;
      }
    }

    Color borderColor;
    final rawBorderColor = schema['border_color']?.toString().trim();
    if (rawBorderColor == null ||
        rawBorderColor.isEmpty ||
        rawBorderColor == 'outline' ||
        rawBorderColor == 'border' ||
        rawBorderColor == 'theme.outlineVariant') {
      borderColor = theme.dividerColor;
    } else {
      final parsedBorder =
          _semanticColor(context, rawBorderColor, fallback: theme.dividerColor);
      if (isDark &&
          schema['force_light'] != true &&
          ThemeData.estimateBrightnessForColor(parsedBorder) ==
              Brightness.light) {
        borderColor = const Color(0xFF334155);
      } else {
        borderColor = parsedBorder;
      }
    }

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

    // A horizontally scrollable row: children keep their natural width (no
    // Flexible/Expanded, so nothing is squeezed into an ellipsis) and the
    // user swipes if the run is wider than the screen. Used for filter-chip
    // strips and any tab-bar-style control.
    final scrollable = schema['scrollable'] == true ||
        schema['scroll_horizontal'] == true ||
        schema['is_scrollable'] == true;
    if (scrollable) {
      return SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: _parseEdgeInsets(schema['padding'], fallback: EdgeInsets.zero),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: _parseCrossAxis(schema['cross_axis_alignment']),
          children: [
            for (var i = 0; i < children.length; i++) ...[
              buildComponent(context, children[i]),
              if (spacing != null && i < children.length - 1)
                SizedBox(width: spacing),
            ],
          ],
        ),
      );
    }

    bool isExpanded(Map<String, dynamic> child) =>
        child['expanded'] == true || child['flex'] is num;
    bool isFixed(Map<String, dynamic> child) =>
        child['flexible'] == false ||
        child['expanded'] == false ||
        child['type'] == 'icon' ||
        child['type'] == 'badge';

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
    return _SchemaTabsView(schema: schema);
  }

  // ===========================================================================
  // Display
  // ===========================================================================

  static Widget _buildText(BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final text = context.tr(schema['text']?.toString() ?? '');
    final styleKey = schema['style']?.toString().toLowerCase();
    final isBold = schema['bold'] == true;
    final isItalic = schema['italic'] == true;
    Color? color = schema['color'] != null
        ? _semanticColor(context, schema['color'],
            fallback: theme.colorScheme.onSurface)
        : null;

    // In dark mode, ensure low-contrast muted grays are boosted to slate-400 (#94a3b8)
    // and dark texts like #111827 are inverted to slate-50 (#f8fafc).
    if (isDark && schema['color'] != null) {
      final rawHex = schema['color'].toString().toLowerCase().trim();
      if (rawHex == '#6b7280' || rawHex == '#64748b' || rawHex == '#4b5563') {
        color = const Color(0xFF94A3B8);
      } else if (rawHex == '#111827' ||
          rawHex == '#1e293b' ||
          rawHex == '#0f172a' ||
          rawHex == '#000000' ||
          rawHex == '#000') {
        color = const Color(0xFFF8FAFC);
      }
    }

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
      fontWeight: isBold ? FontWeight.w700 : null,
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
        ? _semanticColor(context, schema['color'],
            fallback: Theme.of(context).colorScheme.onSurface)
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
        ? _semanticColor(context, schema['color'],
            fallback: Theme.of(context).dividerColor)
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
        ? _semanticColor(context, schema['color'],
            fallback: Theme.of(context).dividerColor)
        : null;

    return Divider(height: height, thickness: thickness, color: color);
  }

  static Widget _buildSectionHeader(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final title = context.tr(schema['title']?.toString() ?? '');
    final subtitle = schema['subtitle'] == null
        ? null
        : context.tr(schema['subtitle'].toString());
    final textColor = _semanticColor(
      context,
      schema['text_color'],
      fallback: theme.colorScheme.onSurface,
    );
    final dividerColor = _semanticColor(
      context,
      schema['divider_color'],
      fallback: theme.dividerColor,
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 18, 4, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: theme.textTheme.titleMedium?.copyWith(
                color: textColor,
                fontWeight: FontWeight.w700,
              )),
          if (subtitle != null && subtitle.isNotEmpty) ...[
            const SizedBox(height: 3),
            Text(subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                )),
          ],
          const SizedBox(height: 8),
          Divider(height: 1, color: dividerColor),
        ],
      ),
    );
  }

  static Widget _buildEmptyState(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final background = _semanticColor(
      context,
      schema['background_color'],
      fallback: theme.colorScheme.surface,
    );
    final textColor = _semanticColor(
      context,
      schema['text_color'],
      fallback: theme.colorScheme.onSurface,
    );

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: theme.dividerColor),
      ),
      child: Column(
        children: [
          Icon(
            SduiIconRegistry.resolve(
                schema['icon']?.toString() ?? 'notifications_none'),
            size: 34,
            color: theme.colorScheme.onSurfaceVariant,
          ),
          const SizedBox(height: 10),
          Text(
            context.tr(schema['message']?.toString() ?? ''),
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(color: textColor),
          ),
        ],
      ),
    );
  }

  static Widget _buildDocumentPreviewCard(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final rawDocument = schema['document'];
    final document = rawDocument is Map
        ? Map<String, dynamic>.from(rawDocument)
        : <String, dynamic>{};
    final rawSummary = schema['summary'];
    final summary = rawSummary is Map
        ? Map<String, dynamic>.from(rawSummary)
        : <String, dynamic>{};
    final lines = (document['lines'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((line) => Map<String, dynamic>.from(line))
        .toList();
    final format = schema['format']?.toString() ?? 'a4';
    final currency = document['currency_symbol']?.toString() ?? '';

    double number(dynamic value) {
      if (value is num) return value.toDouble();
      return double.tryParse('${value ?? ''}') ?? 0;
    }

    String money(dynamic value) =>
        '$currency${number(value).toStringAsFixed(2)}';

    final logicalWidth = switch (format) {
      'thermal_58mm' => 260.0,
      'thermal_80mm' => 340.0,
      'slip' => 360.0,
      _ => 620.0,
    };
    final logicalHeight = switch (format) {
      'thermal_58mm' => 430.0 + lines.length.clamp(0, 8).toDouble() * 34,
      'thermal_80mm' => 450.0 + lines.length.clamp(0, 8).toDouble() * 38,
      'slip' => 640.0,
      _ => 877.0,
    };
    final viewportHeight = format == 'a4' ? 430.0 : 390.0;
    final compact = format.startsWith('thermal_');
    final paperPadding = compact ? 18.0 : 28.0;
    final accent = theme.colorScheme.primary;
    final outerSurface = _semanticColor(
      context,
      schema['background_color'],
      fallback: theme.colorScheme.surface,
    );
    final border = _semanticColor(
      context,
      schema['border_color'],
      fallback: theme.dividerColor,
    );

    Widget amountRow(String label, dynamic amount,
        {bool strong = false, bool negative = false}) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label,
                style: TextStyle(
                    fontSize: compact ? 11 : 13,
                    fontWeight: strong ? FontWeight.w800 : FontWeight.w500)),
            Text('${negative ? '-' : ''}${money(amount)}',
                style: TextStyle(
                    fontSize:
                        strong ? (compact ? 15 : 18) : (compact ? 11 : 13),
                    fontWeight: strong ? FontWeight.w800 : FontWeight.w600)),
          ],
        ),
      );
    }

    final paper = Container(
      width: logicalWidth,
      height: logicalHeight,
      padding: EdgeInsets.all(paperPadding),
      color: Colors.white,
      child: DefaultTextStyle(
        style: const TextStyle(color: Color(0xFF0F172A), fontSize: 13),
        child: Column(
          crossAxisAlignment:
              compact ? CrossAxisAlignment.center : CrossAxisAlignment.start,
          children: [
            Text(
              document['company_name']?.toString() ?? 'Store',
              textAlign: compact ? TextAlign.center : TextAlign.left,
              style: TextStyle(
                color: accent,
                fontSize: compact ? 18 : 24,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${document['tax_label'] ?? 'GSTIN'}: ${document['tax_id'] ?? 'Unregistered'}',
              style: const TextStyle(color: Color(0xFF64748B)),
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(document['document_label']?.toString() ?? 'DOCUMENT',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
                Text('#${document['reference'] ?? ''}',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ],
            ),
            const Divider(height: 24, color: Color(0xFFCBD5E1)),
            Text(
              'Customer: ${document['customer_name'] ?? summary['client_name'] ?? 'Walk-in Client'}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
              color: accent,
              child: const Row(
                children: [
                  Expanded(
                      child: Text('ITEM',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800))),
                  SizedBox(
                      width: 42,
                      child: Text('QTY',
                          textAlign: TextAlign.right,
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800))),
                  SizedBox(
                      width: 82,
                      child: Text('AMOUNT',
                          textAlign: TextAlign.right,
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800))),
                ],
              ),
            ),
            for (final line in lines.take(8))
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                child: Row(
                  children: [
                    Expanded(
                        child: Text(line['name']?.toString() ?? 'Item',
                            maxLines: 2, overflow: TextOverflow.ellipsis)),
                    SizedBox(
                        width: 42,
                        child: Text('${number(line['quantity'])}',
                            textAlign: TextAlign.right)),
                    SizedBox(
                        width: 82,
                        child: Text(money(line['line_total']),
                            textAlign: TextAlign.right,
                            style:
                                const TextStyle(fontWeight: FontWeight.w600))),
                  ],
                ),
              ),
            if (lines.isEmpty)
              const Padding(
                padding: EdgeInsets.all(12),
                child: Text('No line items',
                    style: TextStyle(color: Color(0xFF64748B))),
              ),
            const Divider(color: Color(0xFFCBD5E1)),
            amountRow('Subtotal', document['subtotal']),
            if (number(document['discount']) > 0)
              amountRow('Discount', document['discount'], negative: true),
            amountRow('Tax', document['tax_amount']),
            amountRow('TOTAL', document['total_amount'], strong: true),
            if (!compact) ...[
              const Spacer(),
              Text(
                document['notes']?.toString() ?? '',
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
              ),
            ],
          ],
        ),
      ),
    );

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: outerSurface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border),
      ),
      child: SizedBox(
        height: viewportHeight,
        width: double.infinity,
        child: FittedBox(
          fit: BoxFit.contain,
          alignment: Alignment.topCenter,
          child: DecoratedBox(
            decoration: BoxDecoration(
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.18),
                  blurRadius: 18,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: paper,
          ),
        ),
      ),
    );
  }

  static Widget _buildCodeSnippet(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final code = schema['code']?.toString() ?? schema['text']?.toString() ?? '';
    final description =
        schema['description']?.toString() ?? schema['subtitle']?.toString();
    final copyToast = context.tr(
        schema['copy_toast']?.toString() ?? 'Endpoint copied to clipboard!');

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 4),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0B1120) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                SelectableText(
                  code,
                  style: TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? const Color(0xFFF8FAFC)
                        : const Color(0xFF0F172A),
                  ),
                ),
                if (description != null && description.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    context.tr(description),
                    style: TextStyle(
                      fontSize: 11,
                      color: isDark
                          ? const Color(0xFF94A3B8)
                          : const Color(0xFF64748B),
                    ),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.copy_rounded, size: 18),
            tooltip: context.tr('Copy Endpoint'),
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: code));
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(copyToast),
                  duration: const Duration(seconds: 2),
                ),
              );
            },
          ),
        ],
      ),
    );
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
    // `read_only` keeps the text crisp + selectable (not greyed out) and lets
    // us hang a copy button off it; `disabled` is the fully-inert state.
    final readOnly = schema['read_only'] == true;
    final disabled = schema['disabled'] == true;
    final copyable = schema['copyable'] == true ||
        schema['copy_to_clipboard'] == true ||
        (readOnly && initialValue.trim().isNotEmpty);
    final copyTooltip =
        context.tr(schema['copy_tooltip']?.toString() ?? 'Copy to clipboard');
    final copyToast =
        context.tr(schema['copy_toast']?.toString() ?? 'Copied to clipboard');

    TextInputType keyboardType = TextInputType.text;
    if (keyboardTypeStr == 'number') keyboardType = TextInputType.number;
    if (keyboardTypeStr == 'phone') keyboardType = TextInputType.phone;
    if (keyboardTypeStr == 'email') keyboardType = TextInputType.emailAddress;
    if (keyboardTypeStr == 'multiline' || maxLines > 1)
      keyboardType = TextInputType.multiline;

    // Optional: an action fired when the user presses the keyboard's
    // search/done key — used by search rows so "Enter" filters the list.
    final submitAction = schema['submit_action'] is Map
        ? Map<String, dynamic>.from(schema['submit_action'] as Map)
        : null;

    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;
    final readOnlyFill =
        isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9);
    final defaultInputBorderColor =
        isDark ? const Color(0xFF334155) : scheme.outlineVariant;
    final inputFill = isDark
        ? (readOnly ? const Color(0xFF1E293B) : const Color(0xFF0F172A))
        : (readOnly ? readOnlyFill : null);

    Widget? copyButton;
    if (copyable) {
      copyButton = IconButton(
        tooltip: copyTooltip,
        icon: const Icon(Icons.copy_rounded, size: 20),
        onPressed: () async {
          final value =
              sduiContext?.formValues[name]?.toString() ?? initialValue;
          await Clipboard.setData(ClipboardData(text: value));
          if (!context.mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
                content: Text(copyToast), duration: const Duration(seconds: 2)),
          );
        },
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: TextFormField(
        initialValue: initialValue,
        enabled: !disabled,
        readOnly: readOnly,
        obscureText: isPassword,
        maxLines: maxLines,
        keyboardType: keyboardType,
        textInputAction: submitAction != null ? TextInputAction.search : null,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: _textValidator(schema),
        style: TextStyle(
          color: isDark ? const Color(0xFFF8FAFC) : null,
          fontSize: 14,
        ),
        decoration: InputDecoration(
          labelText: label,
          labelStyle: TextStyle(
            color: isDark ? const Color(0xFF94A3B8) : null,
          ),
          hintText: placeholder,
          hintStyle: TextStyle(
            color: isDark ? const Color(0xFF64748B) : null,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: defaultInputBorderColor),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: defaultInputBorderColor),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(
              color: isDark ? const Color(0xFF10B981) : scheme.primary,
              width: 1.5,
            ),
          ),
          filled: isDark || readOnly,
          fillColor: inputFill,
          suffixIcon: copyButton,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        ),
        onChanged: (val) {
          sduiContext?.setFormValue(name, val);
        },
        onFieldSubmitted: submitAction == null
            ? null
            : (val) {
                sduiContext?.setFormValue(name, val);
                sduiContext?.dispatchAction(submitAction);
              },
      ),
    );
  }

  static Widget _buildDropdownSelect(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final isDark = theme.brightness == Brightness.dark;
    final defaultInputBorderColor =
        isDark ? const Color(0xFF334155) : scheme.outlineVariant;
    final sduiContext = DynamicSchemaContext.of(context);
    final name = schema['name']?.toString() ?? '';
    final label = context.tr(schema['label']?.toString() ?? '');
    final rawOptions = schema['options'] as List<dynamic>? ?? const [];
    final currentVal = sduiContext?.formValues[name]?.toString() ??
        schema['initial_value']?.toString();

    // Long option lists (timezones, countries…) get a searchable picker
    // instead of an unfilterable native menu.
    final searchable = schema['searchable'] == true ||
        (schema['searchable'] != false && rawOptions.length > 12);
    if (searchable) {
      return _SduiSearchableSelect(schema: schema);
    }

    final options = <DropdownMenuItem<String>>[];
    for (final opt in rawOptions) {
      if (opt is Map) {
        final optLabel =
            opt['label']?.toString() ?? opt['name']?.toString() ?? '';
        final optVal =
            opt['value']?.toString() ?? opt['code']?.toString() ?? optLabel;
        options.add(DropdownMenuItem(
          value: optVal,
          child: Text(
            context.tr(optLabel),
            style: TextStyle(
              color: Theme.of(context).brightness == Brightness.dark
                  ? Colors.white
                  : null,
            ),
          ),
        ));
      } else {
        final str = opt.toString();
        options.add(DropdownMenuItem(
          value: str,
          child: Text(
            context.tr(str),
            style: TextStyle(
              color: Theme.of(context).brightness == Brightness.dark
                  ? Colors.white
                  : null,
            ),
          ),
        ));
      }
    }

    final effectiveValue = options.any((o) => o.value == currentVal)
        ? currentVal
        : (options.isNotEmpty ? options.first.value : null);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: DropdownButtonFormField<String>(
        initialValue: effectiveValue,
        dropdownColor: isDark ? const Color(0xFF1E293B) : null,
        style: TextStyle(
          color: isDark ? const Color(0xFFF8FAFC) : null,
          fontSize: 14,
        ),
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: (value) {
          if (_isRequired(schema) && (value == null || value.isEmpty)) {
            return _requiredMessage(schema);
          }
          return null;
        },
        decoration: InputDecoration(
          labelText: label,
          labelStyle: TextStyle(
            color: isDark ? const Color(0xFF94A3B8) : null,
          ),
          filled: isDark,
          fillColor: isDark ? const Color(0xFF0F172A) : null,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: defaultInputBorderColor),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: defaultInputBorderColor),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(
              color: isDark ? const Color(0xFF10B981) : scheme.primary,
              width: 1.5,
            ),
          ),
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

  static Widget _buildListTile(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final sduiContext = DynamicSchemaContext.of(context);
    final leadingSchema = schema['leading'] is Map
        ? Map<String, dynamic>.from(schema['leading'] as Map)
        : <String, dynamic>{};
    final trailingSchema = schema['trailing'] is Map
        ? Map<String, dynamic>.from(schema['trailing'] as Map)
        : <String, dynamic>{};
    final action = _componentAction(schema);
    final iconName = leadingSchema['icon']?.toString() ??
        schema['leading_icon']?.toString() ??
        schema['icon']?.toString();
    final iconColor = _semanticColor(
      context,
      leadingSchema['color'] ?? schema['color'],
      fallback: theme.colorScheme.primary,
    );
    final background = _semanticColor(
      context,
      schema['background_color'],
      fallback: theme.colorScheme.surface,
    );
    final divider = _semanticColor(
      context,
      schema['divider_color'] ?? schema['border_color'],
      fallback: theme.dividerColor,
    );

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 4),
      child: Material(
        color: background,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: divider),
        ),
        clipBehavior: Clip.antiAlias,
        child: ListTile(
          leading: iconName == null
              ? null
              : Icon(SduiIconRegistry.resolve(iconName), color: iconColor),
          title: Text(
            context.tr(schema['title']?.toString() ?? ''),
            style: TextStyle(
              color: theme.colorScheme.onSurface,
              fontWeight: FontWeight.w600,
            ),
          ),
          subtitle: schema['subtitle'] == null
              ? null
              : Text(
                  context.tr(schema['subtitle'].toString()),
                  style: TextStyle(color: theme.colorScheme.onSurfaceVariant),
                ),
          trailing: trailingSchema.isNotEmpty
              ? Icon(
                  SduiIconRegistry.resolve(
                      trailingSchema['icon']?.toString() ?? 'chevron_right'),
                  size: (trailingSchema['size'] as num?)?.toDouble() ?? 18,
                  color: _semanticColor(
                    context,
                    trailingSchema['color'],
                    fallback: theme.colorScheme.onSurfaceVariant,
                  ),
                )
              : (action == null
                  ? null
                  : Icon(Icons.chevron_right,
                      color: theme.colorScheme.onSurfaceVariant)),
          onTap:
              action == null ? null : () => sduiContext?.dispatchAction(action),
        ),
      ),
    );
  }

  static Widget _buildNotificationItem(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final sduiContext = DynamicSchemaContext.of(context);
    final action = _componentAction(schema);
    final iconColor = _semanticColor(
      context,
      schema['icon_color'],
      fallback: theme.colorScheme.primary,
    );
    final timestamp = schema['timestamp']?.toString();
    final category = schema['category']?.toString();

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 5),
      child: Material(
        color: _semanticColor(context, schema['background_color'],
            fallback: theme.colorScheme.surface),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: _semanticColor(context, schema['divider_color'],
                fallback: theme.dividerColor),
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap:
              action == null ? null : () => sduiContext?.dispatchAction(action),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: iconColor.withValues(alpha: 0.13),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    SduiIconRegistry.resolve(
                        schema['icon']?.toString() ?? 'notifications_active'),
                    color: iconColor,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.tr(schema['title']?.toString() ?? ''),
                        style: TextStyle(
                          color: theme.colorScheme.onSurface,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        context.tr(schema['subtitle']?.toString() ?? ''),
                        style: TextStyle(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontSize: 12,
                        ),
                      ),
                      if ((timestamp != null && timestamp.isNotEmpty) ||
                          (category != null && category.isNotEmpty)) ...[
                        const SizedBox(height: 7),
                        Text(
                          [
                            if (category != null && category.isNotEmpty)
                              category.toUpperCase(),
                            if (timestamp != null && timestamp.isNotEmpty)
                              timestamp.replaceFirst('T', ' ').split('.').first,
                          ].join('  •  '),
                          style: TextStyle(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (action != null)
                  Icon(Icons.chevron_right,
                      size: 18, color: theme.colorScheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
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

  static Widget _buildEntityRecordCard(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final sduiContext = DynamicSchemaContext.of(context);

    final title = context.tr(schema['title']?.toString() ??
        schema['name']?.toString() ??
        schema['heading']?.toString() ??
        '');
    final subtitle = schema['subtitle']?.toString() ??
        schema['company']?.toString() ??
        schema['company_name']?.toString();

    // Derive avatar monogram initials if not directly supplied
    var avatarText =
        schema['avatar_text']?.toString() ?? schema['initials']?.toString();
    if ((avatarText == null || avatarText.isEmpty) && title.isNotEmpty) {
      final words = title.trim().split(RegExp(r'\s+'));
      if (words.length >= 2) {
        avatarText = '${words[0][0]}${words[1][0]}'.toUpperCase();
      } else if (words.isNotEmpty && words[0].isNotEmpty) {
        avatarText =
            words[0].substring(0, words[0].length >= 2 ? 2 : 1).toUpperCase();
      }
    }
    avatarText ??= 'L';

    final avatarBg = schema['avatar_bg'] != null
        ? SduiIconRegistry.parseColor(schema['avatar_bg'].toString())
        : (isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0));
    final avatarFg = schema['avatar_fg'] != null
        ? SduiIconRegistry.parseColor(schema['avatar_fg'].toString())
        : (isDark ? const Color(0xFF2DD4BF) : const Color(0xFF0F766E));

    // Priority stripe (Red = Urgent/High, Amber = Medium, Blue = Normal/Low)
    Color? stripeColor;
    final rawStripe = schema['stripe_color'] ??
        schema['priority_color'] ??
        schema['accent_color'];
    if (rawStripe != null && rawStripe.toString().isNotEmpty) {
      stripeColor = SduiIconRegistry.parseColor(rawStripe.toString());
    } else {
      final priority = schema['priority']?.toString().toLowerCase();
      if (priority == 'urgent' || priority == 'high') {
        stripeColor = const Color(0xFFEF4444);
      } else if (priority == 'medium') {
        stripeColor = const Color(0xFFF59E0B);
      } else if (priority == 'low' || priority == 'normal') {
        stripeColor = const Color(0xFF3B82F6);
      }
    }

    final badgeText = schema['badge_text']?.toString() ??
        schema['status_text']?.toString() ??
        schema['stage']?.toString() ??
        schema['status']?.toString();
    final badgeColor = schema['badge_color'] != null
        ? SduiIconRegistry.parseColor(schema['badge_color'].toString())
        : (schema['status_color'] != null
            ? SduiIconRegistry.parseColor(schema['status_color'].toString())
            : const Color(0xFF2DD4BF));

    final amountText = schema['amount_text']?.toString() ??
        schema['amount']?.toString() ??
        schema['value']?.toString();

    final phone = schema['phone']?.toString();
    final email = schema['email']?.toString();

    final rawMeta = schema['meta_items'] as List<dynamic>? ??
        schema['tags'] as List<dynamic>? ??
        const [];

    final note =
        schema['note']?.toString() ?? schema['description']?.toString();

    final rawActions = schema['actions'] as List<dynamic>? ??
        schema['buttons'] as List<dynamic>? ??
        schema['footer_actions'] as List<dynamic>? ??
        const [];

    final cardAction = schema['action'] as Map<String, dynamic>? ??
        schema['on_click'] as Map<String, dynamic>?;

    final cardRadius = _parseDouble(schema['border_radius']) ?? 16.0;
    final cardBg = schema['background_color'] != null
        ? SduiIconRegistry.parseColor(schema['background_color'].toString())
        : (isDark ? const Color(0xFF161F30) : theme.colorScheme.surface);
    final cardBorder = schema['border_color'] != null
        ? SduiIconRegistry.parseColor(schema['border_color'].toString())
        : (isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0));

    final mutedColor =
        isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B);

    return Container(
      margin: _parseEdgeInsets(schema['margin'],
          fallback: const EdgeInsets.symmetric(vertical: 6)),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(cardRadius),
        border: Border.all(color: cardBorder, width: 1.0),
      ),
      clipBehavior: Clip.antiAlias,
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (stripeColor != null)
              Container(
                width: 4.0,
                color: stripeColor,
              ),
            Expanded(
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: cardAction != null
                      ? () => sduiContext?.dispatchAction(cardAction)
                      : null,
                  child: Padding(
                    padding: _parseEdgeInsets(schema['padding'],
                        fallback: const EdgeInsets.all(14)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Header row: Avatar + Name/Company + Badge/Amount
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CircleAvatar(
                              radius: 18,
                              backgroundColor: avatarBg,
                              child: Text(
                                avatarText,
                                style: TextStyle(
                                  color: avatarFg,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    title,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 15,
                                      color: isDark
                                          ? const Color(0xFFF8FAFC)
                                          : const Color(0xFF0F172A),
                                    ),
                                  ),
                                  if (subtitle != null &&
                                      subtitle.isNotEmpty) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      context.tr(subtitle),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: mutedColor,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                if (badgeText != null && badgeText.isNotEmpty)
                                  SduiStatusBadge(
                                    label: context.tr(badgeText),
                                    color: badgeColor,
                                    isSolid: schema['badge_style'] == 'solid',
                                  ),
                                if (amountText != null &&
                                    amountText.isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text(
                                    amountText,
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: isDark
                                          ? const Color(0xFF2DD4BF)
                                          : const Color(0xFF0F766E),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ],
                        ),

                        // Contact items & Meta row
                        if ((phone != null && phone.isNotEmpty) ||
                            (email != null && email.isNotEmpty) ||
                            rawMeta.isNotEmpty) ...[
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 12,
                            runSpacing: 4,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              if (phone != null && phone.isNotEmpty)
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.phone_outlined,
                                        size: 13, color: mutedColor),
                                    const SizedBox(width: 4),
                                    Text(phone,
                                        style: TextStyle(
                                            fontSize: 12, color: mutedColor)),
                                  ],
                                ),
                              if (email != null && email.isNotEmpty)
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(Icons.email_outlined,
                                        size: 13, color: mutedColor),
                                    const SizedBox(width: 4),
                                    Text(email,
                                        style: TextStyle(
                                            fontSize: 12, color: mutedColor)),
                                  ],
                                ),
                              for (final m in rawMeta)
                                if (m is Map)
                                  Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      if (m['icon'] != null) ...[
                                        Icon(
                                          SduiIconRegistry.resolve(
                                              m['icon'].toString()),
                                          size: 13,
                                          color: mutedColor,
                                        ),
                                        const SizedBox(width: 4),
                                      ],
                                      Text(
                                        context.tr((m['text'] ??
                                                    m['label'] ??
                                                    m['value'])
                                                ?.toString() ??
                                            ''),
                                        style: TextStyle(
                                            fontSize: 12, color: mutedColor),
                                      ),
                                    ],
                                  )
                                else if (m != null)
                                  Text(
                                    context.tr(m.toString()),
                                    style: TextStyle(
                                        fontSize: 12, color: mutedColor),
                                  ),
                            ],
                          ),
                        ],

                        // Note / callout snippet
                        if (note != null && note.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 6),
                            decoration: BoxDecoration(
                              color: isDark
                                  ? const Color(0xFF0B0F19)
                                  : const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Icon(Icons.notes_outlined,
                                    size: 14, color: mutedColor),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    note,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                        fontSize: 12, color: mutedColor),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],

                        // Arbitrary nested children if defined
                        if (schema['children'] is List) ...[
                          const SizedBox(height: 8),
                          ...buildChildren(context, schema['children']),
                        ],

                        // Footer Action Buttons
                        if (rawActions.isNotEmpty) ...[
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              for (var i = 0; i < rawActions.length; i++) ...[
                                if (i > 0) const SizedBox(width: 8),
                                Expanded(
                                  child: _buildRecordCardAction(
                                    context,
                                    rawActions[i],
                                    isDark,
                                    sduiContext,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ],
                      ],
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

  static Widget _buildRecordCardAction(
    BuildContext context,
    dynamic rawAction,
    bool isDark,
    DynamicSchemaContext? sduiContext,
  ) {
    if (rawAction is! Map) {
      return const SizedBox.shrink();
    }
    final actMap = Map<String, dynamic>.from(rawAction);

    // If it is a full SDUI component schema with 'type'
    if (actMap.containsKey('type')) {
      return buildComponent(context, actMap);
    }

    final label = context.tr(actMap['label']?.toString() ?? 'Action');
    final iconName = actMap['icon']?.toString();
    final action = _componentAction(actMap) ?? const {};
    final rawStyle = actMap['style'];
    final actionStyle = rawStyle is Map
        ? Map<String, dynamic>.from(rawStyle)
        : const <String, dynamic>{};
    final isOutlined = actMap['variant'] == 'outlined' ||
        actMap['variant'] == 'outline' ||
        actMap['variant'] == 'outline_primary' ||
        actMap['outlined'] == true ||
        actMap['style'] == 'outlined';

    if (isOutlined) {
      final borderColor = _semanticColor(
        context,
        actionStyle['borderColor'] ?? actMap['border_color'],
        fallback: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1),
      );
      final textColor = _semanticColor(
        context,
        actionStyle['textColor'] ?? actMap['foreground_color'],
        fallback: isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A),
      );

      return OutlinedButton(
        style: OutlinedButton.styleFrom(
          visualDensity: VisualDensity.compact,
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          side: BorderSide(color: borderColor),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
          foregroundColor: textColor,
        ),
        onPressed: () => sduiContext?.dispatchAction(action),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (iconName != null) ...[
              Icon(SduiIconRegistry.resolve(iconName),
                  size: 14, color: textColor),
              const SizedBox(width: 4),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: textColor,
              ),
            ),
          ],
        ),
      );
    }

    final btnBg = _semanticColor(
      context,
      actionStyle['backgroundColor'] ??
          actMap['background_color'] ??
          actMap['color'],
      fallback: _primaryButtonGreen,
    );
    final btnFg = _semanticColor(
      context,
      actionStyle['textColor'] ?? actMap['foreground_color'],
      fallback: Colors.white,
    );

    return ElevatedButton(
      style: ElevatedButton.styleFrom(
        visualDensity: VisualDensity.compact,
        elevation: 0,
        backgroundColor: btnBg,
        foregroundColor: btnFg,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
        ),
      ),
      onPressed: () => sduiContext?.dispatchAction(action),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (iconName != null) ...[
            Icon(SduiIconRegistry.resolve(iconName), size: 14, color: btnFg),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: btnFg,
            ),
          ),
        ],
      ),
    );
  }

  static Widget _buildPipelineStageTracker(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final sduiContext = DynamicSchemaContext.of(context);

    final rawStages = schema['stages'] as List<dynamic>? ??
        const ['New', 'Contacted', 'Qualified', 'Proposal', 'Won'];
    if (rawStages.isEmpty) return const SizedBox.shrink();

    final stageItems = <Map<String, dynamic>>[];
    for (final s in rawStages) {
      if (s is Map<String, dynamic>) {
        stageItems.add(s);
      } else if (s is Map) {
        stageItems.add(Map<String, dynamic>.from(s));
      } else {
        stageItems
            .add({'label': s.toString(), 'key': s.toString().toLowerCase()});
      }
    }

    final currentStageRaw = schema['current_stage']?.toString().toLowerCase() ??
        schema['active_stage']?.toString().toLowerCase() ??
        '';
    int currentIndex = -1;
    if (schema['current_step'] is num) {
      currentIndex = (schema['current_step'] as num).toInt();
    } else {
      for (var i = 0; i < stageItems.length; i++) {
        final key = (stageItems[i]['key'] ?? stageItems[i]['label'])
            ?.toString()
            .toLowerCase();
        final label = stageItems[i]['label']?.toString().toLowerCase();
        if (key == currentStageRaw || label == currentStageRaw) {
          currentIndex = i;
          break;
        }
      }
    }
    if (currentIndex < 0) currentIndex = 0;

    final activeColor = schema['active_color'] != null
        ? SduiIconRegistry.parseColor(schema['active_color'].toString())
        : const Color(0xFF2DD4BF); // Mint teal
    final completedColor = schema['completed_color'] != null
        ? SduiIconRegistry.parseColor(schema['completed_color'].toString())
        : const Color(0xFF10B981); // Emerald
    final inactiveColor = schema['inactive_color'] != null
        ? SduiIconRegistry.parseColor(schema['inactive_color'].toString())
        : (isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1));

    final margin = _parseEdgeInsets(schema['margin'],
        fallback: const EdgeInsets.symmetric(vertical: 8));

    return Container(
      margin: margin,
      padding: _parseEdgeInsets(schema['padding'],
          fallback: const EdgeInsets.symmetric(vertical: 10, horizontal: 8)),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            for (var i = 0; i < stageItems.length; i++) ...[
              if (i > 0)
                Container(
                  width: 20,
                  height: 2,
                  color: i <= currentIndex ? completedColor : inactiveColor,
                ),
              InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: stageItems[i]['action'] != null
                    ? () => sduiContext?.dispatchAction(
                        Map<String, dynamic>.from(
                            stageItems[i]['action'] as Map))
                    : null,
                child: Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Node
                      if (i < currentIndex)
                        Container(
                          width: 20,
                          height: 20,
                          decoration: BoxDecoration(
                            color: completedColor,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.check,
                              size: 12, color: Colors.white),
                        )
                      else if (i == currentIndex)
                        Container(
                          width: 22,
                          height: 22,
                          decoration: BoxDecoration(
                            color: activeColor.withValues(alpha: 0.2),
                            shape: BoxShape.circle,
                            border: Border.all(color: activeColor, width: 2),
                          ),
                          child: Center(
                            child: Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                color: activeColor,
                                shape: BoxShape.circle,
                              ),
                            ),
                          ),
                        )
                      else
                        Container(
                          width: 18,
                          height: 18,
                          decoration: BoxDecoration(
                            color: Colors.transparent,
                            shape: BoxShape.circle,
                            border:
                                Border.all(color: inactiveColor, width: 1.5),
                          ),
                        ),
                      const SizedBox(height: 6),
                      // Label
                      Text(
                        context.tr(stageItems[i]['label']?.toString() ?? ''),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: i == currentIndex
                              ? FontWeight.w700
                              : (i < currentIndex
                                  ? FontWeight.w600
                                  : FontWeight.normal),
                          color: i == currentIndex
                              ? activeColor
                              : (i < currentIndex
                                  ? (isDark
                                      ? const Color(0xFFF8FAFC)
                                      : const Color(0xFF0F172A))
                                  : (isDark
                                      ? const Color(0xFF64748B)
                                      : const Color(0xFF94A3B8))),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static Widget _buildProgressBarStat(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final label = context.tr(schema['label']?.toString() ?? '');
    final count =
        schema['count']?.toString() ?? schema['count_text']?.toString();
    final value = schema['value']?.toString() ??
        schema['value_text']?.toString() ??
        schema['amount']?.toString();

    double progress = 0.0;
    if (schema['progress'] is num) {
      progress = (schema['progress'] as num).toDouble();
    } else if (schema['percentage'] is num) {
      progress = (schema['percentage'] as num).toDouble();
    }
    if (progress > 1.0) {
      progress = progress / 100.0;
    }
    progress = progress.clamp(0.0, 1.0);

    final barColor = schema['bar_color'] != null
        ? SduiIconRegistry.parseColor(schema['bar_color'].toString())
        : (schema['color'] != null
            ? SduiIconRegistry.parseColor(schema['color'].toString())
            : const Color(0xFF2DD4BF));

    final trackColor = schema['track_color'] != null
        ? SduiIconRegistry.parseColor(schema['track_color'].toString())
        : (isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0));

    final subtitle = schema['subtitle']?.toString();
    final margin = _parseEdgeInsets(schema['margin'],
        fallback: const EdgeInsets.symmetric(vertical: 6));

    return Container(
      margin: margin,
      padding: _parseEdgeInsets(schema['padding'],
          fallback: const EdgeInsets.symmetric(vertical: 4)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: isDark
                      ? const Color(0xFFF8FAFC)
                      : const Color(0xFF0F172A),
                ),
              ),
              if (count != null && count.isNotEmpty) ...[
                const SizedBox(width: 6),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(
                    color: isDark
                        ? const Color(0xFF1E293B)
                        : const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    count,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: isDark
                          ? const Color(0xFF94A3B8)
                          : const Color(0xFF64748B),
                    ),
                  ),
                ),
              ],
              const Spacer(),
              if (value != null && value.isNotEmpty)
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: isDark
                        ? const Color(0xFFF8FAFC)
                        : const Color(0xFF0F172A),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 7,
              backgroundColor: trackColor,
              valueColor: AlwaysStoppedAnimation<Color>(barColor),
            ),
          ),
          if (subtitle != null && subtitle.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              context.tr(subtitle),
              style: TextStyle(
                fontSize: 11,
                color:
                    isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
              ),
            ),
          ],
        ],
      ),
    );
  }

  static Widget _buildSegmentedFilterChips(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final sduiContext = DynamicSchemaContext.of(context);

    final rawChips = schema['chips'] as List<dynamic>? ??
        schema['options'] as List<dynamic>? ??
        schema['items'] as List<dynamic>? ??
        const [];
    if (rawChips.isEmpty) return const SizedBox.shrink();

    final selectedId = schema['selected_id']?.toString() ??
        schema['selected_value']?.toString() ??
        schema['active_id']?.toString();

    final activeColor = schema['active_color'] != null
        ? SduiIconRegistry.parseColor(schema['active_color'].toString())
        : const Color(0xFF2DD4BF);

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      padding: _parseEdgeInsets(schema['padding'],
          fallback: const EdgeInsets.symmetric(horizontal: 4, vertical: 8)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < rawChips.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            _buildSingleFilterChip(
              context: context,
              chip: rawChips[i] is Map
                  ? Map<String, dynamic>.from(rawChips[i] as Map)
                  : {'label': rawChips[i].toString()},
              selectedId: selectedId,
              activeColor: activeColor,
              isDark: isDark,
              sduiContext: sduiContext,
            ),
          ],
        ],
      ),
    );
  }

  static Widget _buildSingleFilterChip({
    required BuildContext context,
    required Map<String, dynamic> chip,
    required String? selectedId,
    required Color activeColor,
    required bool isDark,
    required DynamicSchemaContext? sduiContext,
  }) {
    final chipId = chip['id']?.toString() ?? chip['value']?.toString();
    final isSelected = chip['selected'] == true ||
        chip['is_selected'] == true ||
        (selectedId != null && chipId == selectedId);

    final rawLabel = context.tr(chip['label']?.toString() ?? '');
    final count = chip['count']?.toString();
    final text =
        (count != null && count.isNotEmpty) ? '$rawLabel · $count' : rawLabel;

    final action = chip['action'] as Map<String, dynamic>?;

    final bg = isSelected
        ? (isDark
            ? activeColor.withValues(alpha: 0.15)
            : activeColor.withValues(alpha: 0.1))
        : (isDark ? const Color(0xFF161F30) : Colors.white);

    final border = isSelected
        ? activeColor
        : (isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0));

    final textColor = isSelected
        ? (isDark ? activeColor : const Color(0xFF0F766E))
        : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B));

    return InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: action != null ? () => sduiContext?.dispatchAction(action) : null,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: border, width: isSelected ? 1.5 : 1.0),
        ),
        child: Text(
          text,
          style: TextStyle(
            fontSize: 13,
            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
            color: textColor,
          ),
        ),
      ),
    );
  }

  static Widget _buildSegmentedTabs(
      BuildContext context, Map<String, dynamic> schema) {
    final theme = Theme.of(context);
    final sduiContext = DynamicSchemaContext.of(context);
    final options = (schema['options'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((option) => Map<String, dynamic>.from(option))
        .toList();
    final active = schema['active_value']?.toString();
    final paramName = schema['param_name']?.toString() ?? 'value';
    final baseAction = schema['action'] is Map
        ? Map<String, dynamic>.from(schema['action'] as Map)
        : <String, dynamic>{};
    final selectedBackground = _semanticColor(
      context,
      schema['active_background_color'],
      fallback: theme.colorScheme.primaryContainer,
    );
    final selectedText = _semanticColor(
      context,
      schema['active_text_color'],
      fallback: theme.colorScheme.onPrimaryContainer,
    );
    final unselectedBackground = _semanticColor(
      context,
      schema['inactive_background_color'],
      fallback: theme.colorScheme.surface,
    );
    final unselectedText = _semanticColor(
      context,
      schema['inactive_text_color'],
      fallback: theme.colorScheme.onSurfaceVariant,
    );
    final border = _semanticColor(
      context,
      schema['border_color'],
      fallback: theme.dividerColor,
    );

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: [
          for (var index = 0; index < options.length; index++) ...[
            if (index > 0) const SizedBox(width: 8),
            Builder(builder: (context) {
              final option = options[index];
              final value = option['value']?.toString() ?? '';
              final selected = value == active;

              return ChoiceChip(
                selected: selected,
                showCheckmark: selected,
                label: Text(context.tr(option['label']?.toString() ?? value)),
                selectedColor: selectedBackground,
                backgroundColor: unselectedBackground,
                side: BorderSide(
                  color: selected ? selectedBackground : border,
                ),
                labelStyle: TextStyle(
                  color: selected ? selectedText : unselectedText,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                ),
                onSelected: selected || baseAction.isEmpty
                    ? null
                    : (_) {
                        final action = Map<String, dynamic>.from(baseAction);
                        final endpoint = action['endpoint']?.toString() ?? '';
                        if (endpoint.isNotEmpty) {
                          final uri = Uri.parse(endpoint);
                          action['endpoint'] = uri.replace(queryParameters: {
                            ...uri.queryParameters,
                            paramName: value,
                          }).toString();
                        }
                        action['type'] = 'refresh_sheet';
                        action['refresh_in_place'] = true;
                        sduiContext?.dispatchAction(action);
                      },
              );
            }),
          ],
        ],
      ),
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

  /// Accept the generic backend button schema and route it through the same
  /// themed renderers as the canonical button component types.
  static Widget _buildGenericButton(
      BuildContext context, Map<String, dynamic> schema) {
    final rawStyle = schema['style'];
    final style = rawStyle is Map
        ? Map<String, dynamic>.from(rawStyle)
        : const <String, dynamic>{};
    final normalized = Map<String, dynamic>.from(schema)
      ..['background_color'] =
          schema['background_color'] ?? style['backgroundColor']
      ..['foreground_color'] = schema['foreground_color'] ?? style['textColor']
      ..['border_color'] = schema['border_color'] ?? style['borderColor']
      ..['border_radius'] = schema['border_radius'] ?? style['borderRadius'];
    final variant = schema['variant']?.toString().toLowerCase().trim();

    if (variant == 'outline' ||
        variant == 'outlined' ||
        variant == 'outline_primary') {
      return _buildButtonOutlined(context, normalized);
    }
    if (variant == 'danger' || variant == 'destructive') {
      return _buildButtonDanger(context, normalized);
    }
    return _buildButtonPrimary(context, normalized);
  }

  static Widget _buildButtonPrimary(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? 'Submit');
    final iconName = schema['icon']?.toString();
    final action = _componentAction(schema) ?? const {};
    final isDense = schema['dense'] == true || schema['size'] == 'small';
    final isFullWidth = schema['full_width'] == true ||
        (schema['full_width'] == null &&
            schema['expanded'] != true &&
            !isDense);
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
    final iconSize =
        _parseDouble(schema['icon_size']) ?? (isDense ? 16.0 : 20.0);

    final btnPadding = _parseEdgeInsets(schema['padding'],
        fallback: isDense
            ? const EdgeInsets.symmetric(horizontal: 10, vertical: 8)
            : const EdgeInsets.symmetric(horizontal: 18, vertical: 12));

    final labelWidget = Text(
      label,
      maxLines: 1,
      softWrap: false,
      overflow: TextOverflow.ellipsis,
      textAlign: TextAlign.center,
      style: TextStyle(
        fontSize: isDense ? 13 : 14,
        fontWeight: FontWeight.w600,
      ),
    );

    final buttonStyle = ElevatedButton.styleFrom(
      backgroundColor: bgColor,
      foregroundColor: fgColor,
      disabledBackgroundColor: bgColor.withValues(alpha: 0.4),
      disabledForegroundColor: fgColor.withValues(alpha: 0.8),
      padding: btnPadding,
      visualDensity: isDense ? VisualDensity.compact : VisualDensity.standard,
      shape:
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius)),
    );

    Widget btn = (iconName != null && iconName.isNotEmpty)
        ? ElevatedButton.icon(
            icon: Icon(SduiIconRegistry.resolve(iconName), size: iconSize),
            label: labelWidget,
            style: buttonStyle,
            onPressed:
                enabled ? () => sduiContext?.dispatchAction(action) : null,
          )
        : ElevatedButton(
            style: buttonStyle,
            onPressed:
                enabled ? () => sduiContext?.dispatchAction(action) : null,
            child: labelWidget,
          );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    final verticalPadding =
        _parseDouble(schema['vertical_padding']) ?? (isDense ? 2.0 : 6.0);
    return Padding(
      padding: EdgeInsets.symmetric(vertical: verticalPadding),
      child: btn,
    );
  }

  static Widget _buildButtonOutlined(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? '');
    final iconName = schema['icon']?.toString();
    final action = _componentAction(schema) ?? const {};
    final isDense = schema['dense'] == true || schema['size'] == 'small';
    final isFullWidth = schema['full_width'] == true ||
        (schema['full_width'] == null &&
            schema['expanded'] != true &&
            !isDense);
    final enabled = schema['enabled'] != false;
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final defaultGreen =
        isDark ? const Color(0xFF10B981) : _outlinedButtonGreen;
    final accent = schema['color'] != null
        ? SduiIconRegistry.parseColor(schema['color'].toString(),
            fallback: defaultGreen)
        : (schema['border_color'] != null
            ? SduiIconRegistry.parseColor(schema['border_color'].toString(),
                fallback: defaultGreen)
            : defaultGreen);
    final radius = _parseDouble(schema['border_radius']) ?? 10.0;
    final iconSize =
        _parseDouble(schema['icon_size']) ?? (isDense ? 16.0 : 20.0);

    final btnPadding = _parseEdgeInsets(schema['padding'],
        fallback: isDense
            ? const EdgeInsets.symmetric(horizontal: 10, vertical: 8)
            : const EdgeInsets.symmetric(horizontal: 18, vertical: 12));

    final labelWidget = Text(
      label,
      maxLines: 1,
      softWrap: false,
      overflow: TextOverflow.ellipsis,
      textAlign: TextAlign.center,
      style: TextStyle(
        fontSize: isDense ? 13 : 14,
        fontWeight: FontWeight.w600,
      ),
    );

    final buttonStyle = OutlinedButton.styleFrom(
      foregroundColor: accent,
      side: BorderSide(
        color: isDark ? accent : accent.withValues(alpha: 0.6),
        width: 1.2,
      ),
      padding: btnPadding,
      visualDensity: isDense ? VisualDensity.compact : VisualDensity.standard,
      shape:
          RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius)),
    );

    Future<void> handlePress() async {
      final confirmMessage = action['confirm_message']?.toString() ??
          schema['confirm_message']?.toString();
      if (confirmMessage != null && confirmMessage.isNotEmpty) {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (dialogCtx) => AlertDialog(
            title: Text(context.tr(action['confirm_title']?.toString() ??
                schema['confirm_title']?.toString() ??
                'Confirm Action')),
            content: Text(context.tr(confirmMessage)),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogCtx).pop(false),
                child: Text(context.tr('Cancel')),
              ),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: accent,
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

    Widget btn = (iconName != null && iconName.isNotEmpty)
        ? OutlinedButton.icon(
            icon: Icon(SduiIconRegistry.resolve(iconName), size: iconSize),
            label: labelWidget,
            style: buttonStyle,
            onPressed: enabled ? handlePress : null,
          )
        : OutlinedButton(
            style: buttonStyle,
            onPressed: enabled ? handlePress : null,
            child: labelWidget,
          );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    final verticalPadding =
        _parseDouble(schema['vertical_padding']) ?? (isDense ? 2.0 : 4.0);
    return Padding(
      padding: EdgeInsets.symmetric(vertical: verticalPadding),
      child: btn,
    );
  }

  static Widget _buildButtonDanger(
      BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final label = context.tr(schema['label']?.toString() ?? 'Delete');
    final iconName = schema['icon']?.toString();
    final action = _componentAction(schema) ?? const {};
    final isDense = schema['dense'] == true || schema['size'] == 'small';
    final isFullWidth = schema['full_width'] == true ||
        (schema['full_width'] == null &&
            schema['expanded'] != true &&
            !isDense);
    final enabled = schema['enabled'] != false;
    final iconSize =
        _parseDouble(schema['icon_size']) ?? (isDense ? 16.0 : 20.0);

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

    final btnPadding = _parseEdgeInsets(schema['padding'],
        fallback: isDense
            ? const EdgeInsets.symmetric(horizontal: 10, vertical: 8)
            : const EdgeInsets.symmetric(horizontal: 18, vertical: 12));

    final labelWidget = Text(
      label,
      maxLines: 1,
      softWrap: false,
      overflow: TextOverflow.ellipsis,
      textAlign: TextAlign.center,
      style: TextStyle(
        fontSize: isDense ? 13 : 14,
        fontWeight: FontWeight.w600,
      ),
    );

    final buttonStyle = ElevatedButton.styleFrom(
      backgroundColor: Colors.red.shade600,
      foregroundColor: Colors.white,
      padding: btnPadding,
      visualDensity: isDense ? VisualDensity.compact : VisualDensity.standard,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    );

    Widget btn = (iconName != null && iconName.isNotEmpty)
        ? ElevatedButton.icon(
            icon: Icon(SduiIconRegistry.resolve(iconName), size: iconSize),
            label: labelWidget,
            style: buttonStyle,
            onPressed: enabled ? handlePress : null,
          )
        : ElevatedButton(
            style: buttonStyle,
            onPressed: enabled ? handlePress : null,
            child: labelWidget,
          );

    if (isFullWidth) {
      btn = SizedBox(width: double.infinity, child: btn);
    }

    final verticalPadding =
        _parseDouble(schema['vertical_padding']) ?? (isDense ? 2.0 : 6.0);
    return Padding(
      padding: EdgeInsets.symmetric(vertical: verticalPadding),
      child: btn,
    );
  }

  static Widget _buildFab(BuildContext context, Map<String, dynamic> schema) {
    final sduiContext = DynamicSchemaContext.of(context);
    final iconName = schema['icon']?.toString() ?? 'add';
    final label =
        schema['label'] == null ? null : context.tr(schema['label'].toString());
    final action = _componentAction(schema) ?? const {};
    final bgColor = schema['background_color'] != null
        ? SduiIconRegistry.parseColor(schema['background_color'].toString())
        : const Color(0xFF2DD4BF);
    final fgColor = schema['foreground_color'] != null
        ? SduiIconRegistry.parseColor(schema['foreground_color'].toString())
        : const Color(0xFF0F172A);

    if (label != null && label.isNotEmpty) {
      return FloatingActionButton.extended(
        backgroundColor: bgColor,
        foregroundColor: fgColor,
        onPressed: () => sduiContext?.dispatchAction(action),
        icon: Icon(SduiIconRegistry.resolve(iconName)),
        label: Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
      );
    }

    return FloatingActionButton(
      backgroundColor: bgColor,
      foregroundColor: fgColor,
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

  static Map<String, dynamic>? _componentAction(Map<String, dynamic> schema) {
    final raw = schema['action'] ?? schema['on_tap'] ?? schema['on_click'];
    if (raw is Map) {
      final action = Map<String, dynamic>.from(raw);
      final actionType =
          action['type'] ?? action['action_type'] ?? schema['action_type'];
      if (actionType != null && actionType.toString().isNotEmpty) {
        action['type'] ??= actionType;
        action['action_type'] ??= actionType;
      }
      for (final key in [
        'endpoint',
        'sheet_endpoint',
        'route',
        'url',
        'method',
        'data',
        'payload',
      ]) {
        if (action[key] == null && schema[key] != null) {
          action[key] = schema[key];
        }
      }
      return action;
    }

    final type = schema['action_type']?.toString();
    if (type == null || type.isEmpty) return null;

    return <String, dynamic>{
      'type': type,
      'action_type': type,
      if (schema['endpoint'] != null) 'endpoint': schema['endpoint'],
      if (schema['sheet_endpoint'] != null)
        'sheet_endpoint': schema['sheet_endpoint'],
      if (schema['route'] != null) 'route': schema['route'],
      if (schema['url'] != null) 'url': schema['url'],
      if (schema['method'] != null) 'method': schema['method'],
      if (schema['data'] != null) 'data': schema['data'],
      if (schema['payload'] != null) 'payload': schema['payload'],
    };
  }

  static Color _semanticColor(
    BuildContext context,
    dynamic value, {
    required Color fallback,
  }) {
    final theme = Theme.of(context);
    final token = value?.toString().trim().toLowerCase();

    return switch (token) {
      'theme.surface' || 'surface' || 'card' => theme.colorScheme.surface,
      'theme.canvas' ||
      'theme.background' ||
      'canvas' ||
      'background' =>
        theme.scaffoldBackgroundColor,
      'theme.divider' ||
      'theme.border' ||
      'theme.outlinevariant' ||
      'divider' ||
      'border' ||
      'outline' =>
        theme.dividerColor,
      'theme.textprimary' ||
      'theme.onsurface' ||
      'textprimary' =>
        theme.colorScheme.onSurface,
      'theme.textsecondary' ||
      'theme.onsurfacevariant' ||
      'textsecondary' =>
        theme.colorScheme.onSurfaceVariant,
      null || '' => fallback,
      _ => SduiIconRegistry.parseColor(value.toString(), fallback: fallback),
    };
  }

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

/// Renders a server `tabs` component with an owned [TabController] so an
/// `ADVANCE_TAB` directive from a `form_submit` response can animate to the
/// next tab (see [SduiTabAdvancer]). Behaviour otherwise matches the previous
/// inline `DefaultTabController` build.
class _SchemaTabsView extends StatefulWidget {
  const _SchemaTabsView({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SchemaTabsView> createState() => _SchemaTabsViewState();
}

class _SchemaTabsViewState extends State<_SchemaTabsView>
    with SingleTickerProviderStateMixin {
  late final List<Map<String, dynamic>> _tabItems;
  late final TabController _controller;

  @override
  void initState() {
    super.initState();
    _tabItems = (widget.schema['tabs'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((tab) => Map<String, dynamic>.from(tab))
        .toList();

    final maxIndex = _tabItems.isEmpty ? 0 : _tabItems.length - 1;
    final initialIndex = ((widget.schema['initial_index'] ??
                widget.schema['active_index']) as num?)
            ?.toInt()
            .clamp(0, maxIndex) ??
        0;

    _controller = TabController(
      length: _tabItems.length,
      initialIndex: initialIndex,
      vsync: this,
    );
    SduiTabAdvancer.bind(
      advance: _advanceTo,
      length: () => _tabItems.length,
      currentIndex: () => _controller.index,
    );
  }

  void _advanceTo(int index) {
    if (!mounted) return;
    if (index >= 0 && index < _controller.length) {
      _controller.animateTo(index);
    }
  }

  @override
  void dispose() {
    SduiTabAdvancer.unbind(_advanceTo);
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_tabItems.isEmpty) return const SizedBox.shrink();

    final scrollable = widget.schema['is_scrollable'] == true ||
        widget.schema['scrollable'] == true;
    final manyTabs = scrollable || _tabItems.length > 3;

    // The tab panel carries its own scroll, so it needs a bounded height.
    final mq = MediaQuery.of(context);
    final panelHeight = (mq.size.height * 0.78).clamp(420.0, 900.0).toDouble();
    // Clear the on-screen keyboard plus a floating-action margin so the last
    // field / submit button can always be scrolled fully into view.
    final bottomInset = mq.viewInsets.bottom + 96;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TabBar(
          controller: _controller,
          isScrollable: manyTabs,
          tabAlignment: manyTabs ? TabAlignment.start : null,
          labelPadding: const EdgeInsets.symmetric(horizontal: 14),
          tabs: [
            for (final t in _tabItems)
              Tab(
                text: context.tr((t['label'] ?? t['title'])?.toString() ?? ''),
                icon: t['icon'] != null
                    ? Icon(SduiIconRegistry.resolve(t['icon'].toString()),
                        size: 18)
                    : null,
              ),
          ],
        ),
        SizedBox(
          height: panelHeight,
          child: TabBarView(
            controller: _controller,
            children: [
              for (final t in _tabItems)
                SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: EdgeInsets.only(
                      left: 16, right: 16, top: 12, bottom: bottomInset),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: DynamicSchemaParser.buildChildren(
                        context, DynamicSchemaParser._extractChildren(t)),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
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

/// `file_picker` — native secure document / photo attachment.
///
/// Renders an upload card that offers camera, gallery and document sources
/// (gated by the schema's `allow_*` flags), enforces the schema's
/// `allowed_extensions` + `max_size_mb` on-device, uploads the picked file to
/// `upload_endpoint` as multipart `file`, then binds the returned storage URL
/// into the form value `name` and shows a removable preview card.
class _SduiFilePickerField extends StatefulWidget {
  const _SduiFilePickerField({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiFilePickerField> createState() => _SduiFilePickerFieldState();
}

class _SduiFilePickerFieldState extends State<_SduiFilePickerField> {
  static const _imageExtensions = {
    'jpg',
    'jpeg',
    'png',
    'webp',
    'heic',
    'heif',
    'gif',
    'bmp',
  };

  String? _url;
  String? _fileName;
  bool _busy = false;

  List<String> get _allowedExtensions {
    final raw = widget.schema['allowed_extensions'];
    final list = raw is List
        ? raw.map((e) => e.toString().toLowerCase().trim()).toList()
        : const ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'];
    return list.isEmpty
        ? const ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic']
        : list;
  }

  int get _maxBytes {
    final mb = (widget.schema['max_size_mb'] as num?)?.toInt() ?? 10;
    return mb * 1024 * 1024;
  }

  bool get _allowCamera => widget.schema['allow_camera'] != false;
  bool get _allowGallery => widget.schema['allow_gallery'] != false;
  bool get _allowDocument => widget.schema['allow_document'] != false;

  @override
  void initState() {
    super.initState();
    _url = widget.schema['current_url']?.toString();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final existing = _readContext()?.formValues[_name]?.toString();
      if (existing != null && existing.isNotEmpty && existing != _url) {
        setState(() => _url = existing);
      }
    });
  }

  String get _name => widget.schema['name']?.toString() ?? 'attachment';

  DynamicSchemaContext? _readContext() =>
      mounted ? DynamicSchemaContext.of(context) : null;

  void _snack(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? Colors.red.shade600 : null,
    ));
  }

  String _extensionOf(String name) {
    final dot = name.lastIndexOf('.');
    return dot >= 0 ? name.substring(dot + 1).toLowerCase().trim() : '';
  }

  /// Client-side gate mirroring the backend allow-list: only non-executable
  /// image / PDF types ever leave the device.
  bool _isPermitted(String name) =>
      _allowedExtensions.contains(_extensionOf(name));

  Future<void> _showSourceSheet() async {
    if (_busy) return;
    final sdui = _readContext();
    if (sdui?.apiClient == null) {
      _snack('Uploads are unavailable right now.', error: true);
      return;
    }

    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_allowCamera)
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined),
                title: Text(sheetContext.tr('Take Photo')),
                onTap: () => Navigator.pop(sheetContext, 'camera'),
              ),
            if (_allowGallery)
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: Text(sheetContext.tr('Upload from Gallery')),
                onTap: () => Navigator.pop(sheetContext, 'gallery'),
              ),
            if (_allowDocument)
              ListTile(
                leading: const Icon(Icons.description_outlined),
                title: Text(sheetContext.tr('Select PDF / Document')),
                onTap: () => Navigator.pop(sheetContext, 'document'),
              ),
            ListTile(
              leading: const Icon(Icons.close),
              title: Text(sheetContext.tr('Cancel')),
              onTap: () => Navigator.pop(sheetContext),
            ),
          ],
        ),
      ),
    );

    switch (choice) {
      case 'camera':
        await _pickImage(ImageSource.camera);
        break;
      case 'gallery':
        await _pickImage(ImageSource.gallery);
        break;
      case 'document':
        await _pickDocument();
        break;
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picked = await ImagePicker().pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 2400,
      );
      if (picked == null) return;
      final bytes = await picked.readAsBytes();
      var name = picked.name;
      if (_extensionOf(name).isEmpty) name = '$name.jpg';
      await _upload(name, bytes);
    } catch (error) {
      _snack('Could not open the camera or gallery: $error', error: true);
    }
  }

  Future<void> _pickDocument() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: _allowedExtensions,
        withData: true,
      );
      if (result == null || result.files.isEmpty) return;
      final file = result.files.first;
      final bytes = file.bytes;
      if (bytes == null) {
        _snack('That file could not be read.', error: true);
        return;
      }
      await _upload(file.name, bytes);
    } catch (error) {
      _snack('Could not open the document picker: $error', error: true);
    }
  }

  Future<void> _upload(String fileName, List<int> bytes) async {
    // Defense in depth — FileType.custom already filters, but a gallery item
    // or a platform quirk could still yield a disallowed type.
    if (!_isPermitted(fileName)) {
      _snack(
        'Only ${_allowedExtensions.join(', ').toUpperCase()} files are allowed. '
        'Executable and script files are blocked.',
        error: true,
      );
      return;
    }
    if (bytes.length > _maxBytes) {
      final mb = (widget.schema['max_size_mb'] as num?)?.toInt() ?? 10;
      _snack('That file is larger than the ${mb}MB limit.', error: true);
      return;
    }

    final sdui = _readContext();
    final client = sdui?.apiClient;
    final endpoint = widget.schema['upload_endpoint']?.toString() ?? '';
    if (client == null || endpoint.isEmpty) {
      _snack('Uploads are unavailable right now.', error: true);
      return;
    }

    setState(() => _busy = true);
    try {
      final response = await client.postMultipartAbsolute(
        endpoint,
        fieldName: widget.schema['field_name']?.toString() ?? 'file',
        bytes: bytes,
        filename: fileName,
      );
      final urlPath = widget.schema['response_url_path']?.toString() ?? 'url';
      final nextUrl = (_readPath(response, urlPath) ??
              response['url'] ??
              response['file_url'])
          ?.toString();
      if (nextUrl == null || nextUrl.isEmpty) {
        _snack('The server did not return a file link.', error: true);
        return;
      }
      if (!mounted) return;
      setState(() {
        _url = nextUrl;
        _fileName = fileName;
      });
      _readContext()?.setFormValue(_name, nextUrl);
    } on ApiException catch (error) {
      _snack(error.message, error: true);
    } catch (error) {
      _snack('Upload failed: $error', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _remove() {
    setState(() {
      _url = null;
      _fileName = null;
    });
    _readContext()?.setFormValue(_name, null);
  }

  static dynamic _readPath(Map<String, dynamic> source, String path) {
    dynamic value = source;
    for (final segment in path.split('.')) {
      if (value is! Map || !value.containsKey(segment)) return null;
      value = value[segment];
    }
    return value;
  }

  bool get _currentIsImage {
    final fromName = _fileName != null ? _extensionOf(_fileName!) : '';
    if (fromName.isNotEmpty) return _imageExtensions.contains(fromName);
    return _imageExtensions.contains(_extensionOf(_url ?? ''));
  }

  @override
  Widget build(BuildContext context) {
    final label =
        context.tr(widget.schema['label']?.toString() ?? 'Attachment');
    final hint = context.tr(widget.schema['hint']?.toString() ??
        'Upload a photo or PDF (non-executable files only)');
    final hasFile = _url?.isNotEmpty == true;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style:
                  const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
          const SizedBox(height: 6),
          if (hasFile)
            _PreviewCard(
              isImage: _currentIsImage,
              url: _url!,
              fileName: _fileName,
              onRemove: _busy ? null : _remove,
            )
          else
            InkWell(
              onTap: _busy ? null : _showSourceSheet,
              borderRadius: BorderRadius.circular(12),
              child: _DottedUploadArea(busy: _busy, hint: hint),
            ),
        ],
      ),
    );
  }
}

/// Compact preview shown after a successful upload.
class _PreviewCard extends StatelessWidget {
  const _PreviewCard({
    required this.isImage,
    required this.url,
    required this.onRemove,
    this.fileName,
  });

  final bool isImage;
  final String url;
  final String? fileName;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(10),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: isImage
                ? CachedNetworkImage(
                    imageUrl: url,
                    width: 56,
                    height: 56,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) =>
                        const Icon(Icons.broken_image_outlined),
                  )
                : Container(
                    width: 56,
                    height: 56,
                    color: Colors.red.shade50,
                    child: Icon(Icons.picture_as_pdf_outlined,
                        color: Colors.red.shade400),
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  fileName ?? context.tr('Attachment uploaded'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Text(
                  context.tr(isImage ? 'Photo attached' : 'Document attached'),
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: context.tr('Remove'),
            icon: const Icon(Icons.close),
            onPressed: onRemove,
          ),
        ],
      ),
    );
  }
}

/// Tap target shown before a file is chosen (also renders the busy state).
class _DottedUploadArea extends StatelessWidget {
  const _DottedUploadArea({required this.busy, required this.hint});

  final bool busy;
  final String hint;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        border: Border.all(color: Colors.grey.shade400),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          busy
              ? const SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                )
              : Icon(Icons.cloud_upload_outlined,
                  size: 32, color: Colors.grey.shade600),
          const SizedBox(height: 8),
          Text(
            busy ? context.tr('Uploading…') : context.tr('Tap to upload'),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          Text(
            hint,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
          ),
        ],
      ),
    );
  }
}

/// `search_bar` — full-width pill search field matching the core POS search
/// styling. Pressing the keyboard search key or tapping the inline clear
/// button fires the schema's `action` (a `filter_view`), re-opening the view
/// with the query as a param.
class _SduiSearchBar extends StatefulWidget {
  const _SduiSearchBar({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiSearchBar> createState() => _SduiSearchBarState();
}

class _SduiSearchBarState extends State<_SduiSearchBar> {
  final _controller = TextEditingController();

  String get _name => widget.schema['name']?.toString() ?? 'search';

  @override
  void initState() {
    super.initState();
    _controller.text = widget.schema['initial_value']?.toString() ?? '';
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final sdui = DynamicSchemaContext.of(context);
      final seeded = sdui?.formValues[_name]?.toString();
      if (seeded != null && seeded.isNotEmpty && _controller.text.isEmpty) {
        _controller.text = seeded;
        setState(() {});
      }
      sdui?.setFormValue(_name, _controller.text);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _dispatch() {
    final sdui = DynamicSchemaContext.of(context);
    sdui?.setFormValue(_name, _controller.text.trim());
    final action = widget.schema['action'];
    if (action is Map) {
      sdui?.dispatchAction(Map<String, dynamic>.from(action));
    }
  }

  void _clear() {
    _controller.clear();
    setState(() {});
    _dispatch();
  }

  @override
  Widget build(BuildContext context) {
    final hint = context.tr(widget.schema['placeholder']?.toString() ??
        widget.schema['hint']?.toString() ??
        'Search…');
    final prefixIcon = SduiIconRegistry.resolve(
        widget.schema['prefix_icon']?.toString() ?? 'search',
        fallback: Icons.search);
    final clearable = widget.schema['clearable'] != false;
    final autofocus = widget.schema['autofocus'] == true;

    return Container(
      height: 48,
      margin: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: TextField(
        controller: _controller,
        autofocus: autofocus,
        textInputAction: TextInputAction.search,
        onChanged: (val) {
          DynamicSchemaContext.of(context)?.setFormValue(_name, val);
          setState(() {}); // toggle the clear button
        },
        onSubmitted: (_) => _dispatch(),
        decoration: InputDecoration(
          isDense: true,
          hintText: hint,
          prefixIcon: Icon(prefixIcon, size: 22, color: Colors.grey.shade600),
          suffixIcon: (clearable && _controller.text.isNotEmpty)
              ? IconButton(
                  icon: const Icon(Icons.clear, size: 20),
                  color: Colors.grey.shade600,
                  onPressed: _clear,
                )
              : null,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
        ),
      ),
    );
  }
}

/// `creatable_select` — a dropdown of preset options plus a "+ Custom" entry
/// that reveals a free-text field. Whatever the user picks or types is bound
/// to `formValues[name]` as a plain string, so the backend keeps validating
/// it as `nullable|string` with no special handling.
class _SduiCreatableSelect extends StatefulWidget {
  const _SduiCreatableSelect({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiCreatableSelect> createState() => _SduiCreatableSelectState();
}

class _SduiCreatableSelectState extends State<_SduiCreatableSelect> {
  static const _customSentinel = '__custom__';

  final _customController = TextEditingController();
  late List<({String label, String value})> _presets;
  bool _custom = false;
  String? _selected;

  String get _name => widget.schema['name']?.toString() ?? '';
  String get _customValue =>
      widget.schema['custom_value']?.toString() ?? _customSentinel;

  @override
  void initState() {
    super.initState();
    _presets = ((widget.schema['options'] as List<dynamic>?) ?? const [])
        .map((o) {
          if (o is Map) {
            final label =
                (o['label'] ?? o['name'] ?? o['value'] ?? '').toString();
            final value = (o['value'] ?? o['code'] ?? label).toString();
            return (label: label, value: value);
          }
          return (label: o.toString(), value: o.toString());
        })
        .where((o) => o.value != _customValue && o.value != _customSentinel)
        .toList();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final sdui = DynamicSchemaContext.of(context);
      final current =
          (sdui?.formValues[_name] ?? widget.schema['initial_value'])
              ?.toString();

      if (current != null &&
          current.isNotEmpty &&
          _presets.any((p) => p.value == current)) {
        setState(() {
          _custom = false;
          _selected = current;
        });
        sdui?.setFormValue(_name, current);
      } else if (current != null &&
          current.isNotEmpty &&
          current != _customValue) {
        setState(() {
          _custom = true;
          _customController.text = current;
        });
        sdui?.setFormValue(_name, current);
      } else {
        setState(() {
          _custom = false;
          _selected = _presets.isNotEmpty ? _presets.first.value : null;
        });
        if (_selected != null) sdui?.setFormValue(_name, _selected);
      }
    });
  }

  @override
  void dispose() {
    _customController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final sdui = DynamicSchemaContext.of(context);
    final label = context.tr(widget.schema['label']?.toString() ?? '');
    final placeholder = context.tr(widget.schema['placeholder']?.toString() ??
        widget.schema['hint']?.toString() ??
        'Type a custom reason…');
    final customLabel = context.tr(
        widget.schema['custom_label']?.toString() ?? '+ Other / Custom Reason');
    final disabled = widget.schema['disabled'] == true;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _custom ? _customValue : _selected,
            dropdownColor: Theme.of(context).brightness == Brightness.dark
                ? const Color(0xFF131E29)
                : null,
            style: TextStyle(
              color: Theme.of(context).brightness == Brightness.dark
                  ? Colors.white
                  : null,
            ),
            autovalidateMode: AutovalidateMode.onUserInteraction,
            decoration: InputDecoration(
              labelText: label,
              border: const OutlineInputBorder(),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
            validator: (value) {
              if (DynamicSchemaParser._isRequired(widget.schema)) {
                final effective =
                    _custom ? _customController.text.trim() : (value ?? '');
                if (effective.isEmpty || effective == _customValue) {
                  return DynamicSchemaParser._requiredMessage(widget.schema);
                }
              }
              return null;
            },
            items: [
              for (final p in _presets)
                DropdownMenuItem(
                    value: p.value, child: Text(context.tr(p.label))),
              DropdownMenuItem(
                value: _customValue,
                child: Text(customLabel,
                    style: const TextStyle(fontStyle: FontStyle.italic)),
              ),
            ],
            onChanged: disabled
                ? null
                : (val) {
                    if (val == null) return;
                    if (val == _customValue) {
                      setState(() => _custom = true);
                      sdui?.setFormValue(_name, _customController.text.trim());
                    } else {
                      setState(() {
                        _custom = false;
                        _selected = val;
                      });
                      sdui?.setFormValue(_name, val);
                    }
                  },
          ),
          if (_custom) ...[
            const SizedBox(height: 8),
            TextFormField(
              controller: _customController,
              enabled: !disabled,
              autofocus: true,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              decoration: InputDecoration(
                labelText: context.tr('Custom reason'),
                hintText: placeholder,
                border: const OutlineInputBorder(),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              ),
              validator: (value) {
                if (DynamicSchemaParser._isRequired(widget.schema) &&
                    (value == null || value.trim().isEmpty)) {
                  return DynamicSchemaParser._requiredMessage(widget.schema);
                }
                return null;
              },
              onChanged: (val) => sdui?.setFormValue(_name, val.trim()),
            ),
          ],
        ],
      ),
    );
  }
}

typedef _Opt = ({String label, String value});

/// `dropdown_select` with `searchable: true` (or > 12 options). Renders a
/// tappable field that opens a searchable picker — a centred dialog on
/// tablet/desktop, a bottom sheet on phones — instead of an unfilterable
/// native menu. The pick binds to `formValues[name]` exactly like the plain
/// dropdown.
class _SduiSearchableSelect extends StatefulWidget {
  const _SduiSearchableSelect({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_SduiSearchableSelect> createState() => _SduiSearchableSelectState();
}

class _SduiSearchableSelectState extends State<_SduiSearchableSelect> {
  late final List<_Opt> _options;
  String? _value;

  String get _name => widget.schema['name']?.toString() ?? '';

  @override
  void initState() {
    super.initState();
    _options = ((widget.schema['options'] as List<dynamic>?) ?? const [])
        .map<_Opt>((o) {
      if (o is Map) {
        final label = (o['label'] ?? o['name'] ?? o['value'] ?? '').toString();
        final value = (o['value'] ?? o['code'] ?? label).toString();
        return (label: label, value: value);
      }
      return (label: o.toString(), value: o.toString());
    }).toList();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final sdui = DynamicSchemaContext.of(context);
      final current = (sdui?.formValues[_name] ??
              widget.schema['initial_value'] ??
              (_options.isNotEmpty ? _options.first.value : null))
          ?.toString();
      if (current != null && current.isNotEmpty) {
        setState(() => _value = current);
        sdui?.setFormValue(_name, current);
      }
    });
  }

  String get _currentLabel {
    for (final o in _options) {
      if (o.value == _value) return o.label;
    }
    return _value ?? '';
  }

  Future<void> _openPicker() async {
    final label = context.tr(widget.schema['label']?.toString() ?? 'Select');
    final sheet = _SearchableOptionSheet(
      title: label,
      options: _options,
      selected: _value,
      searchHint:
          context.tr(widget.schema['search_hint']?.toString() ?? 'Search…'),
    );
    final wide = MediaQuery.sizeOf(context).width >= 720;
    final picked = wide
        ? await showDialog<String>(
            context: context,
            builder: (_) => Dialog(
              clipBehavior: Clip.antiAlias,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16)),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: 480,
                  maxHeight: MediaQuery.sizeOf(context).height * 0.72,
                ),
                child: sheet,
              ),
            ),
          )
        : await showModalBottomSheet<String>(
            context: context,
            isScrollControlled: true,
            useSafeArea: true,
            showDragHandle: true,
            builder: (_) => SizedBox(
              height: MediaQuery.sizeOf(context).height * 0.78,
              child: sheet,
            ),
          );

    if (picked == null || !mounted) return;
    setState(() => _value = picked);
    DynamicSchemaContext.of(context)?.setFormValue(_name, picked);
  }

  @override
  Widget build(BuildContext context) {
    final label = context.tr(widget.schema['label']?.toString() ?? '');
    final disabled = widget.schema['disabled'] == true;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: InkWell(
        onTap: disabled ? null : _openPicker,
        borderRadius: BorderRadius.circular(4),
        child: InputDecorator(
          isEmpty: _currentLabel.isEmpty,
          decoration: InputDecoration(
            labelText: label,
            border: const OutlineInputBorder(),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
            suffixIcon: const Icon(Icons.arrow_drop_down),
          ),
          child:
              Text(_currentLabel, maxLines: 1, overflow: TextOverflow.ellipsis),
        ),
      ),
    );
  }
}

/// The searchable list body shared by the dialog and bottom-sheet
/// presentations. Pops the picked option's value.
class _SearchableOptionSheet extends StatefulWidget {
  const _SearchableOptionSheet({
    required this.title,
    required this.options,
    required this.selected,
    required this.searchHint,
  });

  final String title;
  final List<_Opt> options;
  final String? selected;
  final String searchHint;

  @override
  State<_SearchableOptionSheet> createState() => _SearchableOptionSheetState();
}

class _SearchableOptionSheetState extends State<_SearchableOptionSheet> {
  final _controller = TextEditingController();
  final _focus = FocusNode();
  late List<_Opt> _filtered;

  @override
  void initState() {
    super.initState();
    _filtered = widget.options;
    WidgetsBinding.instance.addPostFrameCallback((_) => _focus.requestFocus());
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _onQuery(String raw) {
    final q = raw.trim().toLowerCase();
    setState(() {
      _filtered = q.isEmpty
          ? widget.options
          : widget.options
              .where((o) =>
                  o.label.toLowerCase().contains(q) ||
                  o.value.toLowerCase().contains(q))
              .toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 12, 8),
          child: Row(
            children: [
              Expanded(
                child: Text(widget.title,
                    style: Theme.of(context).textTheme.titleMedium),
              ),
              IconButton(
                icon: const Icon(Icons.close),
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          child: TextField(
            controller: _controller,
            focusNode: _focus,
            autofocus: true,
            onChanged: _onQuery,
            decoration: InputDecoration(
              hintText: widget.searchHint,
              prefixIcon: const Icon(Icons.search),
              isDense: true,
              border: const OutlineInputBorder(),
              suffixIcon: _controller.text.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear),
                      onPressed: () {
                        _controller.clear();
                        _onQuery('');
                      },
                    ),
            ),
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: _filtered.isEmpty
              ? Center(
                  child: Text('No matches',
                      style: TextStyle(color: scheme.onSurfaceVariant)))
              : ListView.builder(
                  itemCount: _filtered.length,
                  itemBuilder: (context, i) {
                    final o = _filtered[i];
                    final selected = o.value == widget.selected;
                    return ListTile(
                      dense: true,
                      selected: selected,
                      selectedTileColor: scheme.primary.withValues(alpha: 0.10),
                      title: Text(o.label),
                      trailing: selected
                          ? Icon(Icons.check, color: scheme.primary, size: 20)
                          : null,
                      onTap: () => Navigator.of(context).pop(o.value),
                    );
                  },
                ),
        ),
      ],
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
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
            style: TextStyle(color: isDark ? const Color(0xFFF8FAFC) : null),
            decoration: InputDecoration(
              labelText: label,
              labelStyle:
                  TextStyle(color: isDark ? const Color(0xFF94A3B8) : null),
              prefixIcon: Icon(Icons.payments_outlined,
                  color: isDark ? const Color(0xFF94A3B8) : null),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(
                    color: isDark
                        ? const Color(0xFF334155)
                        : const Color(0xFFCBD5E1)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(
                    color: isDark
                        ? const Color(0xFF334155)
                        : const Color(0xFFCBD5E1)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
                borderSide:
                    const BorderSide(color: Color(0xFF10B981), width: 1.5),
              ),
              filled: isDark,
              fillColor: isDark ? const Color(0xFF0F172A) : null,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            ),
            onChanged: (val) => _commit(_num(val)),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: isDark
                ? const Color(0xFF10B981).withValues(alpha: 0.12)
                : const Color(0xFFECFDF5),
            border: Border.all(
              color: isDark
                  ? const Color(0xFF10B981).withValues(alpha: 0.3)
                  : const Color(0xFFA7F3D0),
            ),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'CHANGE DUE TO CUSTOMER',
                      style: TextStyle(
                          letterSpacing: 0.5,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                          color: isDark
                              ? const Color(0xFF6EE7B7)
                              : const Color(0xFF065F46)),
                    ),
                    Text(
                      'Change Due to Customer',
                      style: TextStyle(
                          fontSize: 12,
                          color: isDark
                              ? const Color(0xFF94A3B8)
                              : const Color(0xFF15803D)),
                    ),
                  ],
                ),
              ),
              Text(
                '$_currency${_changeDue.toStringAsFixed(2)}',
                style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 20,
                    color: isDark
                        ? const Color(0xFF34D399)
                        : const Color(0xFF059669)),
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

class _CustomerSelector extends StatefulWidget {
  const _CustomerSelector({required this.schema});

  final Map<String, dynamic> schema;

  @override
  State<_CustomerSelector> createState() => _CustomerSelectorState();
}

class _CustomerSelectorState extends State<_CustomerSelector> {
  final _searchController = TextEditingController();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();

  Timer? _debounce;
  bool _searching = false;
  List<Map<String, dynamic>> _searchResults = [];
  Map<String, dynamic>? _selectedCustomer;

  String get _idFieldName => widget.schema['name']?.toString() ?? 'customer_id';
  String get _nameFieldName {
    final fields = widget.schema['fields'] as Map<String, dynamic>?;
    return fields?['name_field']?.toString() ?? 'customer_name';
  }

  String get _phoneFieldName {
    final fields = widget.schema['fields'] as Map<String, dynamic>?;
    return fields?['phone_field']?.toString() ?? 'customer_phone';
  }

  String get _searchEndpoint =>
      widget.schema['search_endpoint']?.toString() ??
      '/api/tenant/customers/search';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final sdui = DynamicSchemaContext.of(context);
      if (sdui != null) {
        final existingName = sdui.formValues[_nameFieldName]?.toString() ??
            widget.schema['initial_name']?.toString() ??
            '';
        final existingPhone = sdui.formValues[_phoneFieldName]?.toString() ??
            widget.schema['initial_phone']?.toString() ??
            '';
        if (existingName.isNotEmpty) {
          _nameController.text = existingName;
          sdui.setFormValue(_nameFieldName, existingName);
        }
        if (existingPhone.isNotEmpty) {
          _phoneController.text = existingPhone;
          sdui.setFormValue(_phoneFieldName, existingPhone);
        }
        final existingId = sdui.formValues[_idFieldName]?.toString() ??
            widget.schema['initial_value']?.toString() ??
            '';
        if (existingId.isNotEmpty) {
          sdui.setFormValue(_idFieldName, existingId);
        }
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    _debounce?.cancel();
    final q = query.trim();
    if (q.isEmpty) {
      setState(() {
        _searching = false;
        _searchResults = [];
      });
      return;
    }

    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final sdui = DynamicSchemaContext.of(context);
      final client = sdui?.apiClient;
      if (client == null) return;

      setState(() => _searching = true);
      try {
        final res =
            await client.getAbsolute(_searchEndpoint, query: {'query': q});
        final rawList = res['customers'] as List<dynamic>? ?? const [];
        if (mounted) {
          setState(() {
            _searching = false;
            _searchResults = rawList.whereType<Map<String, dynamic>>().toList();
          });
        }
      } catch (_) {
        if (mounted) {
          setState(() {
            _searching = false;
            _searchResults = [];
          });
        }
      }
    });
  }

  void _selectCustomer(Map<String, dynamic> cust) {
    final sdui = DynamicSchemaContext.of(context);
    final id = cust['server_id']?.toString() ?? cust['id']?.toString() ?? '';
    final name = cust['name']?.toString() ?? '';
    final phone = cust['phone']?.toString() ?? '';

    setState(() {
      _selectedCustomer = cust;
      _searchResults = [];
      _searchController.clear();
      _nameController.text = name;
      _phoneController.text = phone;
    });

    sdui?.setFormValue(_idFieldName, id);
    sdui?.setFormValue(_nameFieldName, name);
    sdui?.setFormValue(_phoneFieldName, phone);
  }

  void _clearSelectedCustomer() {
    final sdui = DynamicSchemaContext.of(context);
    setState(() {
      _selectedCustomer = null;
    });
    sdui?.setFormValue(_idFieldName, '');
  }

  void _onNameChanged(String val) {
    final sdui = DynamicSchemaContext.of(context);
    sdui?.setFormValue(_nameFieldName, val);
  }

  void _onPhoneChanged(String val) {
    final sdui = DynamicSchemaContext.of(context);
    sdui?.setFormValue(_phoneFieldName, val);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final rawStyle = widget.schema['style'];
    final schemaStyle = rawStyle is Map
        ? Map<String, dynamic>.from(rawStyle)
        : const <String, dynamic>{};
    final dropdownBackground = DynamicSchemaParser._semanticColor(
      context,
      schemaStyle['dropdownBackgroundColor'] ?? schemaStyle['popup_background'],
      fallback: theme.cardColor,
    );
    final dropdownBorder = DynamicSchemaParser._semanticColor(
      context,
      schemaStyle['borderColor'] ?? schemaStyle['border_color'],
      fallback: theme.dividerColor,
    );
    final titleColor = DynamicSchemaParser._semanticColor(
      context,
      schemaStyle['titleColor'],
      fallback: theme.textTheme.bodyLarge?.color ?? theme.colorScheme.onSurface,
    );
    final subtitleColor = DynamicSchemaParser._semanticColor(
      context,
      schemaStyle['subtitleColor'],
      fallback: theme.textTheme.bodyMedium?.color ??
          theme.colorScheme.onSurfaceVariant,
    );
    final dueColor = DynamicSchemaParser._semanticColor(
      context,
      schemaStyle['dueColor'],
      fallback: const Color(0xFFEF4444),
    );
    final label = widget.schema['label']?.toString() ?? 'Client / Customer';
    final nameLabel =
        widget.schema['name_label']?.toString() ?? 'Client Full Name *';
    final phoneLabel =
        widget.schema['phone_label']?.toString() ?? 'Client Phone Number *';
    final isRequired = widget.schema['required'] == true;

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: theme.dividerColor.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.person_search_outlined,
                  size: 20, color: Color(0xFF15803D)),
              const SizedBox(width: 6),
              Text(
                label,
                style:
                    const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const Spacer(),
              if (_selectedCustomer != null)
                Chip(
                  avatar: const Icon(Icons.check_circle,
                      size: 16, color: Color(0xFF15803D)),
                  label: Text(
                    'CRM #${_selectedCustomer!['server_id'] ?? _selectedCustomer!['id']}',
                    style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF15803D),
                        fontWeight: FontWeight.bold),
                  ),
                  backgroundColor: const Color(0xFFDCFCE7),
                  deleteIcon: const Icon(Icons.close, size: 14),
                  onDeleted: _clearSelectedCustomer,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  visualDensity: VisualDensity.compact,
                ),
            ],
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _searchController,
            onChanged: _onSearchChanged,
            decoration: InputDecoration(
              hintText: 'Search CRM customer by name or phone...',
              prefixIcon: const Icon(Icons.search, size: 20),
              suffixIcon: _searching
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: Padding(
                        padding: EdgeInsets.all(12),
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : (_searchController.text.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () {
                            _searchController.clear();
                            _onSearchChanged('');
                          },
                        )
                      : null),
              border: const OutlineInputBorder(),
              isDense: true,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            ),
          ),
          if (_searchResults.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              key: const ValueKey('customer-search-results'),
              constraints: const BoxConstraints(maxHeight: 200),
              decoration: BoxDecoration(
                color: dropdownBackground,
                borderRadius: BorderRadius.circular(10),
                border:
                    Border.all(color: dropdownBorder.withValues(alpha: 0.15)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.3),
                    blurRadius: 8,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: _searchResults.length,
                separatorBuilder: (_, __) => Divider(
                  height: 1,
                  color: theme.dividerColor.withValues(alpha: 0.35),
                ),
                itemBuilder: (context, i) {
                  final cust = _searchResults[i];
                  final cName = cust['name']?.toString() ?? 'Unnamed';
                  final cPhone = cust['phone']?.toString() ?? '';
                  final due = (cust['balance_due'] as num?)?.toDouble() ?? 0.0;
                  final currency = cust['currency_symbol']?.toString() ??
                      widget.schema['currency_symbol']?.toString() ??
                      '\$';

                  return Material(
                    color: Colors.transparent,
                    child: ListTile(
                      dense: true,
                      leading: const CircleAvatar(
                        radius: 14,
                        backgroundColor: Color(0xFFDCFCE7),
                        child: Icon(Icons.person,
                            size: 16, color: Color(0xFF15803D)),
                      ),
                      title: Text(cName,
                          style: TextStyle(
                              color: titleColor,
                              fontWeight: FontWeight.w600,
                              fontSize: 13)),
                      subtitle: cPhone.isNotEmpty
                          ? Text(cPhone,
                              style:
                                  TextStyle(color: subtitleColor, fontSize: 12))
                          : null,
                      trailing: due > 0
                          ? Text('Due: $currency${due.toStringAsFixed(2)}',
                              style: TextStyle(
                                  color: dueColor,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold))
                          : Icon(Icons.arrow_forward_ios,
                              size: 12, color: subtitleColor),
                      onTap: () => _selectCustomer(cust),
                    ),
                  );
                },
              ),
            ),
          ],
          const SizedBox(height: 10),
          TextFormField(
            controller: _nameController,
            onChanged: _onNameChanged,
            validator: (v) {
              if (isRequired && (v == null || v.trim().isEmpty)) {
                return 'Client name is required';
              }
              return null;
            },
            decoration: InputDecoration(
              labelText: nameLabel,
              border: const OutlineInputBorder(),
              isDense: true,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              prefixIcon: const Icon(Icons.badge_outlined, size: 20),
            ),
          ),
          const SizedBox(height: 8),
          TextFormField(
            controller: _phoneController,
            onChanged: _onPhoneChanged,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(
              labelText: phoneLabel,
              border: const OutlineInputBorder(),
              isDense: true,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              prefixIcon: const Icon(Icons.phone_outlined, size: 20),
            ),
          ),
        ],
      ),
    );
  }
}
