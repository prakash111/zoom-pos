/// One-shot mailbox that carries a prescription's resolved line items,
/// patient and prescribing doctor from the SDUI "Load Prescription into POS"
/// action across to the natively-built [PosScreen] / [PosProvider].
///
/// [PosProvider] is created locally inside [PosScreen] (it is not a global
/// provider), so the SDUI action dispatcher cannot reach it directly. It
/// instead [stage]s the payload here and pushes the POS screen; [PosScreen]
/// [take]s it exactly once while constructing its provider.
class RxCartHandoff {
  RxCartHandoff._();

  static final RxCartHandoff instance = RxCartHandoff._();

  Map<String, dynamic>? _pending;

  bool get hasPending => _pending != null;

  void stage(Map<String, dynamic> payload) {
    _pending = payload;
  }

  /// Returns the staged payload (if any) and clears it, so a later plain
  /// visit to the POS screen never re-loads a stale prescription.
  Map<String, dynamic>? take() {
    final pending = _pending;
    _pending = null;
    return pending;
  }

  void clear() {
    _pending = null;
  }
}
