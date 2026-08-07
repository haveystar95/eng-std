import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// One weighted piece of an [InkSegments] bar.
class InkSegment {
  const InkSegment(this.value, this.density);
  final double value;
  final InkDensity density;
}

/// Полоса плотности чернил (§4) — единственный способ показать «степень» в
/// бесцветной системе (rule 03). Три сегмента экрана коллекции:
/// подтверждено (залито) · знакомое (полутон) · в работе (контур). Ширины
/// пропорциональны значениям; сегменты с нулём не рисуются. Сумма значений
/// должна сходиться с «всего» (rule 12) на стороне вызова.
class InkSegments extends StatelessWidget {
  const InkSegments({
    super.key,
    required this.segments,
    this.height = AppProgress.heightScreen,
    this.gap = 3,
  });

  final List<InkSegment> segments;
  final double height;
  final double gap;

  /// Удобный конструктор из трёх счётчиков (порядок = порядок плотностей).
  factory InkSegments.fromCounts({
    Key? key,
    required num confirmed,
    required num familiar,
    required num inProgress,
    double height = AppProgress.heightScreen,
    double gap = 3,
  }) {
    return InkSegments(
      key: key,
      height: height,
      gap: gap,
      segments: [
        InkSegment(confirmed.toDouble(), InkDensity.filled),
        InkSegment(familiar.toDouble(), InkDensity.halftone),
        InkSegment(inProgress.toDouble(), InkDensity.outline),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final visible = segments.where((s) => s.value > 0).toList();
    final total = visible.fold<double>(0, (a, s) => a + s.value);
    if (visible.isEmpty || total <= 0) {
      return SizedBox(
        height: height,
        child: DecoratedBox(
          decoration: BoxDecoration(
            border: Border.all(color: AppInkDensity.outlineColor, width: AppInkDensity.outlineWidth),
          ),
        ),
      );
    }

    return SizedBox(
      height: height,
      child: LayoutBuilder(
        builder: (context, c) {
          final gaps = gap * (visible.length - 1);
          final avail = (c.maxWidth - gaps).clamp(0.0, double.infinity);
          return Row(
            children: [
              for (var i = 0; i < visible.length; i++) ...[
                if (i > 0) SizedBox(width: gap),
                SizedBox(
                  key: ValueKey(visible[i].density),
                  width: avail * (visible[i].value / total),
                  height: height,
                  child: _SegmentBox(visible[i].density),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _SegmentBox extends StatelessWidget {
  const _SegmentBox(this.density);
  final InkDensity density;

  @override
  Widget build(BuildContext context) {
    switch (density) {
      case InkDensity.filled:
      case InkDensity.halftone:
        return ColoredBox(color: AppInkDensity.solid(density));
      case InkDensity.outline:
        return DecoratedBox(
          decoration: BoxDecoration(
            border: Border.all(color: AppInkDensity.outlineColor, width: AppInkDensity.outlineWidth),
          ),
        );
    }
  }
}
