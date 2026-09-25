import 'package:flutter/material.dart';

/// A navigation branch whose destination and disclosure control are two
/// independent tap targets. Tapping [title]/[leading] invokes [onTap], while
/// only the chevron changes the visibility of [children].
class SplitNavigationTile extends StatefulWidget {
  const SplitNavigationTile({
    super.key,
    required this.title,
    this.onTap,
    this.mainTileKey,
    this.expandButtonKey,
    this.leading,
    this.children = const [],
    this.contentPadding = const EdgeInsets.symmetric(horizontal: 16),
    this.dense = false,
    this.selected = false,
    this.selectedColor,
    this.iconColor,
    this.initiallyExpanded = false,
    this.expandTooltip = 'Expand submenu',
    this.collapseTooltip = 'Collapse submenu',
  });

  final Key? mainTileKey;
  final Key? expandButtonKey;
  final Widget title;
  final Widget? leading;
  final List<Widget> children;
  final EdgeInsetsGeometry contentPadding;
  final bool dense;
  final bool selected;
  final Color? selectedColor;
  final Color? iconColor;
  final bool initiallyExpanded;
  final String expandTooltip;
  final String collapseTooltip;
  final VoidCallback? onTap;

  @override
  State<SplitNavigationTile> createState() => _SplitNavigationTileState();
}

class _SplitNavigationTileState extends State<SplitNavigationTile> {
  late bool _expanded = widget.initiallyExpanded && widget.children.isNotEmpty;

  @override
  void didUpdateWidget(covariant SplitNavigationTile oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.children.isEmpty && _expanded) {
      _expanded = false;
    }
  }

  void _toggleExpanded() {
    setState(() => _expanded = !_expanded);
  }

  void _handleTileTap() {
    if (widget.children.isNotEmpty) {
      _toggleExpanded();
    } else if (widget.onTap != null) {
      widget.onTap!();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: ListTile(
                key: widget.mainTileKey,
                contentPadding: widget.contentPadding,
                dense: widget.dense,
                visualDensity: widget.dense
                    ? VisualDensity.compact
                    : VisualDensity.standard,
                leading: widget.leading,
                title: widget.title,
                selected: widget.selected,
                selectedColor: widget.selectedColor,
                onTap: _handleTileTap,
              ),
            ),
            if (widget.children.isNotEmpty)
              IconButton(
                key: widget.expandButtonKey,
                tooltip:
                    _expanded ? widget.collapseTooltip : widget.expandTooltip,
                onPressed: _toggleExpanded,
                icon: AnimatedRotation(
                  turns: _expanded ? 0.5 : 0,
                  duration: const Duration(milliseconds: 200),
                  child: Icon(
                    Icons.keyboard_arrow_down,
                    size: 20,
                    color: widget.iconColor ??
                        Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
          ],
        ),
        ClipRect(
          child: AnimatedSize(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeInOut,
            alignment: Alignment.topCenter,
            child: _expanded
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: widget.children,
                  )
                : const SizedBox.shrink(),
          ),
        ),
      ],
    );
  }
}
