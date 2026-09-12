import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/local/cached_image_provider.dart';
import 'route_stop.dart';
import 'route_view.dart';

/// УЗЛЫ МАРШРУТА — круг дня 56, точка этапа / цели 14, мишень события (канва PLAN-DES-3).
///
/// Цвета и тени — из кода кадров 21-2 / 21-3 / 21-4: кольцо 2 цвета фона вокруг круга (оно и
/// «разрывает» линию у картинки), латунная обводка 2 и тёплая тень у текущего дня, вуаль ground .6
/// у запертого; этап — шалфей с галкой 10, латунь с кольцом 3, контур 1.5.

/// Точка этапа или цели — 14.
class RouteChildDot extends StatelessWidget {
  const RouteChildDot({super.key, required this.mark, this.ground = AppColors.ground});

  final RouteChildMark mark;

  /// Чем залит контур «впереди» — фон, на котором лежит маршрут.
  final Color ground;

  @override
  Widget build(BuildContext context) {
    const size = RouteStop.smallNode;

    return switch (mark) {
      RouteChildMark.done => Container(
        width: size,
        height: size,
        decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
        child: const Center(child: RouteCheck(size: 10, color: AppColors.paper, stroke: 4.5)),
      ),
      RouteChildMark.current => Container(
        width: size,
        height: size,
        decoration: const BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.brassInk,
          boxShadow: [BoxShadow(color: AppColors.routeCurrentRing, spreadRadius: 3)],
        ),
      ),
      RouteChildMark.goal => Container(
        width: size,
        height: size,
        decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk),
      ),
      RouteChildMark.locked => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: ground,
          border: Border.all(color: AppColors.routeStageOutline, width: 1.5),
        ),
      ),
    };
  }
}

/// Галка канвы — `m5 12.5 4.5 4.5L19 7` в квадрате 24, круглые концы.
///
/// Рисуется, а не берётся из шрифта значков: у Lucide другая галка (другой угол и концы), и на 10 px
/// разница видна глазом.
class RouteCheck extends StatelessWidget {
  const RouteCheck({super.key, required this.size, required this.color, required this.stroke});

  final double size;
  final Color color;

  /// Толщина в единицах квадрата 24 (3 у галки 16, 4.5 у галки 10).
  final double stroke;

  @override
  Widget build(BuildContext context) =>
      CustomPaint(size: Size.square(size), painter: _CheckPainter(color: color, stroke: stroke));
}

class _CheckPainter extends CustomPainter {
  const _CheckPainter({required this.color, required this.stroke});

  final Color color;
  final double stroke;

  @override
  void paint(Canvas canvas, Size size) {
    final k = size.width / 24;
    final path = Path()
      ..moveTo(5 * k, 12.5 * k)
      ..lineTo(9.5 * k, 17 * k)
      ..lineTo(19 * k, 7 * k);
    canvas.drawPath(
      path,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = stroke * k
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
  }

  @override
  bool shouldRepaint(_CheckPainter old) => old.color != color || old.stroke != stroke;
}

/// Круг дня 56.
class RouteDayCircle extends StatelessWidget {
  const RouteDayCircle({super.key, required this.circle, required this.tone, this.ground = AppColors.ground});

  final RouteCircle circle;
  final RouteDayTone tone;

  /// Цвет кольца 2 вокруг круга — фон под маршрутом (ground на табе, paper в карточке витрины).
  final Color ground;

  @override
  Widget build(BuildContext context) {
    final locked = tone == RouteDayTone.locked;
    final current = tone == RouteDayTone.current;
    final shadows = <BoxShadow>[
      BoxShadow(color: ground, spreadRadius: 2),
      if (current)
        const BoxShadow(color: AppColors.routeCurrentGlow, blurRadius: 10, offset: Offset(0, 2))
      else if (!locked)
        BoxShadow(color: AppColors.ink.withValues(alpha: .10), blurRadius: 8, offset: const Offset(0, 2)),
    ];
    final border = current ? Border.all(color: AppColors.brass, width: 2) : null;

    return switch (circle) {
      RoutePhoto(:final url, :final tone) => SceneCircle(
        image: url == null ? null : CachedNetworkImage(url),
        tone: tone,
        size: RouteStop.bigNode,
        veil: locked,
        border: border,
        shadows: shadows,
      ),
      RouteSystemMark(:final day) => Container(
        width: RouteStop.bigNode,
        height: RouteStop.bigNode,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: locked ? ground : AppColors.photoPlaceholder,
          border: border,
          boxShadow: shadows,
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            Center(child: PlanSystemDayMark(day: day, color: AppColors.ink, size: 32)),
            if (locked) const ColoredBox(color: AppColors.routeVeil),
          ],
        ),
      ),
    };
  }
}

/// Мишень события: бумага #E3DCCF с латунным кантом, или ink с галкой, когда событие прошло.
class RouteEventCircle extends StatelessWidget {
  const RouteEventCircle({super.key, required this.event, this.ground = AppColors.ground});

  final RouteEventView event;
  final Color ground;

  @override
  Widget build(BuildContext context) {
    final circle = Container(
      width: RouteStop.bigNode,
      height: RouteStop.bigNode,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: event.passed ? AppColors.ink : AppColors.photoPlaceholder,
        border: event.passed ? null : Border.all(color: AppColors.brassHairline),
        boxShadow: [BoxShadow(color: ground, spreadRadius: 2)],
      ),
      child: event.passed
          ? const RouteCheck(size: 22, color: AppColors.paper, stroke: 3)
          : const PlanSystemDayMark(day: PlanSystemDay.event, color: AppColors.brassInk, size: 32),
    );
    if (event.dated) return circle;

    // Событие без даты — пунктир вокруг круга: «дату ещё предстоит назвать».
    return SizedBox(
      width: RouteStop.bigNode,
      height: RouteStop.bigNode,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          const Positioned(
            left: -3,
            top: -3,
            width: RouteStop.bigNode + 6,
            height: RouteStop.bigNode + 6,
            child: DottedBorderBox(
              padding: EdgeInsets.zero,
              radius: RouteStop.bigNode / 2 + 3,
              color: AppColors.brassHairline,
              child: SizedBox.expand(),
            ),
          ),
          circle,
        ],
      ),
    );
  }
}
