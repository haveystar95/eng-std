import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'route_fill.dart';
import 'route_marks.dart';
import 'route_stop.dart';
import 'route_view.dart';

/// ОДИН ВИДЖЕТ МАРШРУТА для всех экранов (наряд PLAN-UI-3 §1): узлы дней и их этапов (или целей) на
/// одной линии через 44, мета-строка под заголовком дня, мишень события последней.
///
/// [progress] — таб: линия заливается по правилу `route_fill.dart`. Без него (превью 22-4b,
/// витрина 21-1) линия ровная и тихая: прогресса у ещё не начатого плана нет.
class RouteLine extends StatelessWidget {
  const RouteLine({
    super.key,
    required this.days,
    this.event,
    this.progress = true,
    this.ground = AppColors.ground,
    this.entrance = false,
  });

  final List<RouteDayView> days;
  final RouteEventView? event;
  final bool progress;

  /// Превью 22-4b: узлы появляются шагом 60 мс, fade + 8 px вверх, после плиты «Как это будет».
  final bool entrance;

  /// Фон под маршрутом: ground на табе и в превью, paper в карточке витрины.
  final Color ground;

  @override
  Widget build(BuildContext context) {
    final nodes = <_Node>[
      for (final day in days) ...[
        _Node.day(day),
        for (final child in day.children) _Node.child(day, child),
      ],
      if (event != null) _Node.event(event!),
    ];

    final segments = progress
        ? routeSegments([
            for (final n in nodes)
              RouteFillNode(
                passed: n.child == null ? (n.day?.passedLine ?? false) : n.child!.mark == RouteChildMark.done,
                current: n.child?.mark == RouteChildMark.current,
              ),
          ])
        : List.filled(nodes.isEmpty ? 0 : nodes.length - 1, RouteSegment.ahead);
    final quiet = progress ? AppColors.routeAhead : AppColors.routeQuiet;
    Color colorOf(RouteSegment s) => switch (s) {
      RouteSegment.walked => AppColors.verdictKnown,
      RouteSegment.current => AppColors.brassInk,
      RouteSegment.ahead => quiet,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < nodes.length; i++)
          _entering(
            i,
            _anchored(nodes[i], RouteStop(
              big: nodes[i].big,
              previousBig: i == 0 ? null : nodes[i - 1].big,
              nextBig: i == nodes.length - 1 ? null : nodes[i + 1].big,
              incoming: i == 0 ? null : colorOf(segments[i - 1]),
              outgoing: i == nodes.length - 1 ? null : colorOf(segments[i]),
              onTap: nodes[i].day?.onTap,
              semanticsLabel: nodes[i].child == null ? nodes[i].day?.title : null,
              node: _nodeWidget(nodes[i]),
              child: _textOf(nodes[i]),
            )),
          ),
      ],
    );
  }

  Widget _entering(int i, Widget stop) => entrance ? RouteEntrance(index: i + 1, child: stop) : stop;

  static Widget _anchored(_Node n, Widget stop) =>
      n.child == null && n.day?.anchor != null ? KeyedSubtree(key: n.day!.anchor, child: stop) : stop;

  Widget _nodeWidget(_Node n) {
    if (n.eventView != null) return RouteEventCircle(event: n.eventView!, ground: ground);
    if (n.child != null) return RouteChildDot(mark: n.child!.mark, ground: ground);

    return RouteDayCircle(circle: n.day!.circle, tone: n.day!.tone, ground: ground);
  }

  Widget _textOf(_Node n) {
    if (n.eventView != null) return _EventText(event: n.eventView!);
    if (n.child != null) return _ChildText(day: n.day!, child: n.child!);

    return _DayText(day: n.day!);
  }
}

class _Node {
  const _Node._({this.day, this.child, this.eventView});

  factory _Node.day(RouteDayView day) => _Node._(day: day);
  factory _Node.child(RouteDayView day, RouteChildView child) => _Node._(day: day, child: child);
  factory _Node.event(RouteEventView event) => _Node._(eventView: event);

  final RouteDayView? day;
  final RouteChildView? child;
  final RouteEventView? eventView;

  bool get big => child == null;
}

/// Заголовок дня 17 и мета 13 под ним; справа — «сегодня» латунью или галка пройденного.
class _DayText extends StatelessWidget {
  const _DayText({required this.day});

  final RouteDayView day;

  @override
  Widget build(BuildContext context) {
    final locked = day.tone == RouteDayTone.locked;
    final passed = day.tone == RouteDayTone.passed;
    final title = Text(
      day.title,
      style: TextStyle(
        fontFamily: AppFonts.inter,
        fontSize: 17,
        fontWeight: passed ? FontWeight.w500 : FontWeight.w600,
        height: 1.25,
        color: locked ? AppColors.routeLockedText : AppColors.ink,
      ),
    );
    final meta = day.meta;

    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              title,
              if (meta != null && meta.isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(
                  meta,
                  style: TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 13,
                    height: 1.35,
                    color: locked ? AppColors.routeLockedMeta : AppColors.tertiary,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ],
            ],
          ),
        ),
        if (day.trailingToday != null) ...[
          const SizedBox(width: 10),
          Text(
            day.trailingToday!,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.brassInk,
            ),
          ),
        ] else if (passed) ...[
          const SizedBox(width: 10),
          const RouteCheck(size: 16, color: AppColors.verdictKnown, stroke: 3),
        ],
      ],
    );
  }
}

/// Метка этапа / цели — 15 / 1.3.
class _ChildText extends StatelessWidget {
  const _ChildText({required this.day, required this.child});

  final RouteDayView day;
  final RouteChildView child;

  @override
  Widget build(BuildContext context) {
    final (color, weight) = switch ((day.tone, child.mark)) {
      (RouteDayTone.plain, _) => (AppColors.inkBody, FontWeight.w400),
      (RouteDayTone.passed, _) => (AppColors.routeWalkedLabel, FontWeight.w400),
      (RouteDayTone.locked, _) => (AppColors.routeLockedText, FontWeight.w400),
      (RouteDayTone.current, RouteChildMark.current) => (AppColors.ink, FontWeight.w600),
      (RouteDayTone.current, _) => (AppColors.ink, FontWeight.w400),
    };

    return Text(
      child.label,
      style: TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.3, color: color, fontWeight: weight),
    );
  }
}

class _EventText extends StatelessWidget {
  const _EventText({required this.event});

  final RouteEventView event;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(
        event.title,
        style: const TextStyle(
          fontFamily: AppFonts.inter,
          fontSize: 17,
          fontWeight: FontWeight.w700,
          height: 1.25,
          color: AppColors.ink,
        ),
      ),
      const SizedBox(height: 3),
      Text(
        event.meta,
        style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 1.35, color: AppColors.tertiary),
      ),
    ],
  );
}

/// ПОЯВЛЕНИЕ ПРЕВЬЮ (22-4b): элемент [index] проявляется через `220 + 60 × (index − 1)` мс — плита
/// «Как это будет» (index 0) первой за 220 мс, узлы маршрута следом шагом 60 мс, fade + 8 px вверх.
/// Под «уменьшением движения» всё стоит сразу.
class RouteEntrance extends StatefulWidget {
  const RouteEntrance({super.key, required this.index, required this.child});

  final int index;
  final Widget child;

  static const Duration plate = Duration(milliseconds: 220);
  static const Duration step = Duration(milliseconds: 60);

  @override
  State<RouteEntrance> createState() => _RouteEntranceState();
}

class _RouteEntranceState extends State<RouteEntrance> with SingleTickerProviderStateMixin {
  // Задержка — часть длительности (Interval), а не таймер: ни одного висящего Timer в тестах.
  late final Duration _delay =
      widget.index == 0 ? Duration.zero : RouteEntrance.plate + RouteEntrance.step * (widget.index - 1);
  late final AnimationController _in = AnimationController(vsync: this, duration: _delay + RouteEntrance.plate);
  late final double _startAt = _delay.inMicroseconds / (_delay + RouteEntrance.plate).inMicroseconds;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _in.value = 1;
    } else {
      _in.forward();
    }
  }

  @override
  void dispose() {
    _in.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _in,
    builder: (context, child) {
      final t = Interval(_startAt, 1, curve: Curves.easeOut).transform(_in.value);

      return Opacity(opacity: t, child: Transform.translate(offset: Offset(0, 8 * (1 - t)), child: child));
    },
    child: widget.child,
  );
}
