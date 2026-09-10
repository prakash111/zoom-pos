/// Bridges a server-driven `tabs` component to [SduiActionDispatcher].
///
/// A `form_submit` response can carry
/// `next_action: { type: "ADVANCE_TAB", target_index: N, ... }`. The dispatcher
/// runs from the schema page's own context, which is an *ancestor* of the
/// [TabController], so it can't reach the controller through the tree. The
/// mounted `tabs` view registers a tiny advance hook here instead; only one
/// schema `tabs` view is on screen at a time.
class SduiTabAdvancer {
  SduiTabAdvancer._();

  static void Function(int index)? _advance;
  static int Function()? _length;
  static int Function()? _index;

  /// Called by the mounted tabs view in `initState`.
  static void bind({
    required void Function(int index) advance,
    required int Function() length,
    required int Function() currentIndex,
  }) {
    _advance = advance;
    _length = length;
    _index = currentIndex;
  }

  /// Called by that same view in `dispose` (identity-checked so a newer view
  /// that mounted first is never cleared by an older one tearing down).
  static void unbind(void Function(int index) advance) {
    if (identical(_advance, advance)) {
      _advance = null;
      _length = null;
      _index = null;
    }
  }

  static int? get currentIndex => _index?.call();
  static int? get length => _length?.call();

  /// Animates the visible schema tabs to [index]. Returns false when there is
  /// no mounted tabs view or the index is out of range, so the caller can fall
  /// back to another navigation strategy.
  static bool advanceTo(int index) {
    final advance = _advance;
    final length = _length;
    if (advance == null || length == null) return false;
    if (index < 0 || index >= length()) return false;
    advance(index);
    return true;
  }
}
