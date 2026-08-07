import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// A container with a dashed hairline border instead of a solid one — used for an action that is
/// present but temporarily unavailable (кадр 9c: the offline generation card). No fill, no shadow;
/// the dash reads as "not now" without alarm.
class DottedBorderBox extends StatelessWidget {
  const DottedBorderBox({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.radius = AppRadii.field,
    this.color = AppColors.dashed,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _DashedBorderPainter(color: color, radius: radius),
      child: Padding(padding: padding, child: child),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  _DashedBorderPainter({required this.color, required this.radius});

  final Color color;
  final double radius;

  static const double _dash = 5, _gap = 4, _stroke = 1;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final rrect = RRect.fromRectAndRadius(rect.deflate(_stroke / 2), Radius.circular(radius));
    final path = Path()..addRRect(rrect);
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = _stroke;

    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = distance + _dash;
        canvas.drawPath(metric.extractPath(distance, next.clamp(0.0, metric.length)), paint);
        distance = next + _gap;
      }
    }
  }

  @override
  bool shouldRepaint(_DashedBorderPainter old) => old.color != color || old.radius != radius;
}
