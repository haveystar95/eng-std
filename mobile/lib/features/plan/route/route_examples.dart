import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'route_line.dart';
import 'route_view.dart';

/// ЛЕНТА ТРЁХ ПРИМЕРОВ ВИТРИНЫ (кадр 21-1, `@keyframes om-carousel` / `om-dot1…3`).
///
/// Три плана разного масштаба — «Приём у врача» 5 дней, «Собеседование» 10, «Звонок арендодателю»
/// 3 — чтобы было видно, что план бывает и на три дня, и на десять. У каждого три дня тем же
/// маршрутом, что в табе, и под днём не этапы, а то, что человек сможет сказать после него.
///
/// Лента сама меняет пример каждые 6 с (кадр держится 26 % цикла 18 с, сдвиг — 7 % = 1.26 с кривой
/// `cubic(.4, 0, .2, 1)`), листается свайпом и после первого касания сама больше не едет. Точки:
/// активная 18 × 6 ink, остальные 6 × 6 ink .25; смена занимает 4 % цикла — 720 мс, линейно.
/// Под «уменьшением движения» лента стоит на первом примере.
///
/// Примеры — статический конфиг клиента: это образ продукта, а не чей-то план с сервера.
class PlanExamplesStrip extends StatefulWidget {
  const PlanExamplesStrip({super.key});

  static const Duration hold = Duration(seconds: 6);
  static const Duration slide = Duration(milliseconds: 1260);
  static const Duration dot = Duration(milliseconds: 720);
  static const Curve curve = Cubic(.4, 0, .2, 1);

  @override
  State<PlanExamplesStrip> createState() => _PlanExamplesStripState();
}

class _PlanExamplesStripState extends State<PlanExamplesStrip> with SingleTickerProviderStateMixin {
  final _scroll = ScrollController();
  late final AnimationController _move;

  @override
  void initState() {
    super.initState();
    _move = AnimationController(vsync: this, duration: PlanExamplesStrip.slide);
  }
  Timer? _auto;
  int _page = 0;
  double _width = 0;
  bool _touched = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final still = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    _auto?.cancel();
    if (!still && !_touched) {
      _auto = Timer.periodic(PlanExamplesStrip.hold, (_) => _go((_page + 1) % 3));
    }
  }

  @override
  void dispose() {
    _auto?.cancel();
    _move.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _go(int page) {
    if (!mounted || _width == 0 || !_scroll.hasClients) return;
    final from = _scroll.offset;
    final to = page * _width;
    setState(() => _page = page);
    final tween = Tween(begin: from, end: to).chain(CurveTween(curve: PlanExamplesStrip.curve));
    void tick() {
      if (_scroll.hasClients) _scroll.jumpTo(tween.evaluate(_move));
    }

    _move
      ..stop()
      ..reset();
    _move.addListener(tick);
    _move.forward().whenCompleteOrCancel(() => _move.removeListener(tick));
  }

  void _stopAuto() {
    _touched = true;
    _auto?.cancel();
    _move.stop();
  }

  void _drag(DragUpdateDetails d) {
    if (!_scroll.hasClients) return;
    _scroll.jumpTo((_scroll.offset - d.delta.dx).clamp(0, 2 * _width));
  }

  void _release(DragEndDetails d) {
    final velocity = d.primaryVelocity ?? 0;
    final exact = _width == 0 ? 0.0 : _scroll.offset / _width;
    var page = exact.round();
    if (velocity < -300) page = exact.floor() + 1;
    if (velocity > 300) page = exact.ceil() - 1;
    _go(page.clamp(0, 2));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final examples = planExamples(l);

    return LayoutBuilder(
      builder: (context, box) {
        _width = box.maxWidth;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            GestureDetector(
              onHorizontalDragDown: (_) => _stopAuto(),
              onHorizontalDragUpdate: _drag,
              onHorizontalDragEnd: _release,
              child: SingleChildScrollView(
                controller: _scroll,
                scrollDirection: Axis.horizontal,
                physics: const NeverScrollableScrollPhysics(),
                // Кадр: `overflow:hidden` — соседний пример за краем не выглядывает.
                clipBehavior: Clip.hardEdge,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    for (final e in examples)
                      SizedBox(
                        width: _width,
                        child: Padding(padding: const EdgeInsets.only(right: 12), child: _ExampleCard(example: e)),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < examples.length; i++) ...[
                  if (i > 0) const SizedBox(width: 6),
                  AnimatedContainer(
                    duration: PlanExamplesStrip.dot,
                    width: i == _page ? 18 : 6,
                    height: 6,
                    decoration: BoxDecoration(
                      color: i == _page ? AppColors.ink : AppColors.ink.withValues(alpha: .25),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ],
              ],
            ),
          ],
        );
      },
    );
  }
}

/// Один пример.
class PlanExample {
  const PlanExample({required this.title, required this.meta, required this.days, required this.footer});

  final String title;
  final String meta;
  final List<RouteDayView> days;
  final String footer;
}

/// Три примера кадра 21-1 — тексты из кадра, в ARB.
List<PlanExample> planExamples(AppLocalizations l) {
  RouteDayView day(int n, String name, List<String> goals) => RouteDayView(
    title: l.planRouteDayTitle(n, name),
    circle: const RoutePhoto(),
    tone: RouteDayTone.plain,
    children: [for (final g in goals) RouteChildView(label: g, mark: RouteChildMark.goal)],
  );
  String meta(int days, String level) => l.planExampleMeta(l.planDaysCount(days), level.toLowerCase());

  return [
    PlanExample(
      title: l.planExampleDoctorTitle,
      meta: meta(5, l.planEntryLevelBeginner),
      footer: l.planExampleMore(2),
      days: [
        day(1, l.planExampleDoctorDay1, [l.planExampleDoctorGoal11, l.planExampleDoctorGoal12, l.planExampleDoctorGoal13]),
        day(2, l.planExampleDoctorDay2, [l.planExampleDoctorGoal21, l.planExampleDoctorGoal22, l.planExampleDoctorGoal23]),
        day(3, l.planExampleDoctorDay3, [l.planExampleDoctorGoal31, l.planExampleDoctorGoal32, l.planExampleDoctorGoal33]),
      ],
    ),
    PlanExample(
      title: l.planExampleInterviewTitle,
      meta: meta(10, l.planEntryLevelIntermediate),
      footer: l.planExampleMore(7),
      days: [
        day(1, l.planExampleInterviewDay1, [l.planExampleInterviewGoal11, l.planExampleInterviewGoal12, l.planExampleInterviewGoal13]),
        day(2, l.planExampleInterviewDay2, [l.planExampleInterviewGoal21, l.planExampleInterviewGoal22, l.planExampleInterviewGoal23]),
        day(3, l.planExampleInterviewDay3, [l.planExampleInterviewGoal31, l.planExampleInterviewGoal32, l.planExampleInterviewGoal33]),
      ],
    ),
    PlanExample(
      title: l.planExampleLandlordTitle,
      meta: meta(3, l.planEntryLevelBeginner),
      footer: l.planExampleWhole,
      days: [
        day(1, l.planExampleLandlordDay1, [l.planExampleLandlordGoal11, l.planExampleLandlordGoal12, l.planExampleLandlordGoal13]),
        day(2, l.planExampleLandlordDay2, [l.planExampleLandlordGoal21, l.planExampleLandlordGoal22, l.planExampleLandlordGoal23]),
        day(3, l.planExampleLandlordDay3, [l.planExampleLandlordGoal31, l.planExampleLandlordGoal32, l.planExampleLandlordGoal33]),
      ],
    ),
  ];
}

/// Карточка примера — бумага r22, заголовок Literata 20, мета 13, маршрут, подвал за волосяной.
class _ExampleCard extends StatelessWidget {
  const _ExampleCard({required this.example});

  final PlanExample example;

  @override
  Widget build(BuildContext context) => PaperCard(
    radius: 22,
    padding: const EdgeInsets.all(16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          example.title,
          style: const TextStyle(fontFamily: AppFonts.literata, fontSize: 20, fontWeight: FontWeight.w500, height: 1.2, color: AppColors.ink),
        ),
        const SizedBox(height: 5),
        Text(
          example.meta,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 13,
            color: AppColors.tertiary,
            fontFeatures: [FontFeature.tabularFigures()],
          ),
        ),
        const SizedBox(height: 14),
        RouteLine(days: example.days, progress: false, ground: AppColors.paper),
        Container(
          margin: const EdgeInsets.only(top: 12),
          padding: const EdgeInsets.only(top: 12),
          decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.dividerFaint))),
          child: Text(example.footer, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary)),
        ),
      ],
    ),
  );
}
