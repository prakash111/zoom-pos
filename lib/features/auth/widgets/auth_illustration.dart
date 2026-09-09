import 'package:flutter/material.dart';

/// Right-hand brand panel for [AuthScaffold] on desktop widths — a flat,
/// on-brand illustration of a shopkeeper at a POS counter, echoing the
/// reference design. Drawn entirely with a [CustomPainter] so it needs no
/// image asset and scales crisply to any window size.
class AuthIllustration extends StatelessWidget {
  const AuthIllustration({super.key});

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return DecoratedBox(
      decoration: const BoxDecoration(color: Colors.white),
      child: Padding(
        padding: const EdgeInsets.all(44),
        child: LayoutBuilder(
          builder: (context, c) => CustomPaint(
            size: Size(c.maxWidth, c.maxHeight),
            painter: _PosScenePainter(primary),
          ),
        ),
      ),
    );
  }
}

class _PosScenePainter extends CustomPainter {
  _PosScenePainter(this.primary);

  final Color primary;

  static const _blob = Color(0xFFCFE1FF);
  static const _blobSoft = Color(0xFFE9F1FF);
  static const _skin = Color(0xFFF3C9A8);
  static const _counter = Color(0xFFF0C9A6);
  static const _device = Color(0xFF334155);
  static const _deviceDark = Color(0xFF1E293B);

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final p = Paint()..isAntiAlias = true;

    // Soft background blobs.
    p.color = _blobSoft;
    canvas.drawCircle(Offset(w * 0.54, h * 0.44), w * 0.60, p);
    p.color = _blob;
    canvas.drawCircle(Offset(w * 0.64, h * 0.52), w * 0.42, p);

    // Counter surface.
    final counterTop = h * 0.74;
    p.color = _counter;
    canvas.drawRRect(
      RRect.fromRectAndCorners(
        Rect.fromLTWH(w * 0.03, counterTop, w * 0.97, h * 0.30),
        topLeft: const Radius.circular(12),
        topRight: const Radius.circular(12),
      ),
      p,
    );

    // Shopkeeper — torso.
    p.color = primary;
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(w * 0.58, h * 0.33, w * 0.34, h * 0.44),
        const Radius.circular(44),
      ),
      p,
    );
    // Collar.
    p.color = Colors.white;
    final collar = Path()
      ..moveTo(w * 0.69, h * 0.34)
      ..lineTo(w * 0.75, h * 0.42)
      ..lineTo(w * 0.81, h * 0.34)
      ..close();
    canvas.drawPath(collar, p);

    // Arm reaching toward the terminal.
    p.color = primary;
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(w * 0.44, h * 0.50, w * 0.24, h * 0.11),
        const Radius.circular(26),
      ),
      p,
    );
    p.color = _skin;
    canvas.drawCircle(Offset(w * 0.45, h * 0.55), h * 0.045, p);

    // Head + hair.
    p.color = _skin;
    canvas.drawCircle(Offset(w * 0.75, h * 0.23), h * 0.085, p);
    p.color = _deviceDark;
    canvas.drawArc(
      Rect.fromCircle(center: Offset(w * 0.75, h * 0.225), radius: h * 0.092),
      3.5,
      2.4,
      true,
      p,
    );

    // POS terminal: screen, stand, base.
    final screen = RRect.fromRectAndRadius(
      Rect.fromLTWH(w * 0.26, h * 0.42, w * 0.26, h * 0.19),
      const Radius.circular(14),
    );
    p.color = _device;
    canvas.drawRRect(screen.inflate(6), p);
    p.color = Colors.white;
    canvas.drawRRect(screen.deflate(6), p);
    p.color = primary.withValues(alpha: 0.16);
    canvas.drawRRect(screen.deflate(16), p);
    p.color = _deviceDark;
    canvas.drawRect(Rect.fromLTWH(w * 0.37, h * 0.61, w * 0.04, h * 0.08), p);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(w * 0.29, h * 0.68, w * 0.20, h * 0.055),
        const Radius.circular(10),
      ),
      p,
    );

    // Floating accent chips — a payment card and a barcode ticket.
    _card(canvas, Rect.fromLTWH(w * 0.06, h * 0.16, w * 0.17, h * 0.11));
    _barcode(canvas, Rect.fromLTWH(w * 0.08, h * 0.44, w * 0.13, h * 0.10));
  }

  void _card(Canvas canvas, Rect r) {
    final p = Paint()..isAntiAlias = true;
    p.color = primary;
    canvas.drawRRect(RRect.fromRectAndRadius(r, const Radius.circular(10)), p);
    p.color = Colors.white.withValues(alpha: 0.9);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(r.left + r.width * 0.12, r.top + r.height * 0.24,
            r.width * 0.30, r.height * 0.22),
        const Radius.circular(4),
      ),
      p,
    );
    p.color = Colors.white.withValues(alpha: 0.55);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(r.left + r.width * 0.12, r.top + r.height * 0.62,
            r.width * 0.66, r.height * 0.12),
        const Radius.circular(4),
      ),
      p,
    );
  }

  void _barcode(Canvas canvas, Rect r) {
    final p = Paint()..isAntiAlias = true;
    p.color = Colors.white;
    canvas.drawRRect(RRect.fromRectAndRadius(r, const Radius.circular(8)), p);
    p.color = _deviceDark;
    var x = r.left + r.width * 0.14;
    var i = 0;
    while (x < r.right - r.width * 0.12) {
      final bw = i.isEven ? 3.0 : 5.0;
      canvas.drawRect(
          Rect.fromLTWH(x, r.top + r.height * 0.22, bw, r.height * 0.56), p);
      x += bw + 4;
      i++;
    }
  }

  @override
  bool shouldRepaint(covariant _PosScenePainter old) => old.primary != primary;
}
