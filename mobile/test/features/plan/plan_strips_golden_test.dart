import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/plan_day_plate_view.dart';
import 'package:eng_std/features/plan/plan_tab_parts.dart';
import 'package:eng_std/features/plan/route/plan_route.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';

/// ПОЛОСКИ СОСТОЯНИЙ — снимки, на которых состояния одного элемента стоят РЯДОМ.
///
/// Наряд PLAN-UI-3: golden у таймлайна — ровно по трём состояниям дня (пройден · сегодня ·
/// заперт), и все три — ОДИН живой маршрут, снятый с сервера (день 1 закрыт, день 2 сегодня,
/// дальше заперто), а не три нарисованных вручную узла: так в одном кадре видно и цвета дней, и
/// заливку линии между ними. Снимков поведения (микрофон по шагам и т. п.) здесь больше нет —
/// поведение держат тесты канона.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget strip(List<Widget> rows) => planGoldenApp(
    Scaffold(
      backgroundColor: AppColors.ground,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            for (final row in rows) ...[row, const SizedBox(height: 20)],
          ],
        ),
      ),
    ),
  );

  Widget label(String text) => Padding(padding: const EdgeInsets.only(bottom: 8), child: PlanSectionLabel(text));

  // ── ТРИ СОСТОЯНИЯ ДНЯ НА ТАЙМЛАЙНЕ ───────────────────────────────────────────────────────
  testWidgets('таймлайн — пройден · сегодня · заперт (день 1 закрыт, день 2 сегодня)', (tester) async {
    await expectPlanGolden(
      tester,
      strip([
        label('пройден · сегодня · заперт'),
        PlanRoute(plan: planFrom('current_day2', _firstDays(3)), onOpenDay: (_) {}),
      ]),
      'plan/strip-route-day-states',
      size: const Size(390, 900),
    );
  });

  // ── СОСТОЯНИЯ ПЛИТЫ (кадры 21-2, 21-3, 21-4, 22-5a, 22-5c) ───────────────────────────────
  testWidgets('плита дня — пять состояний подряд', (tester) async {
    Widget plate(String caption, Plan plan, PlanDayRoom? room, PlanDayRoute day) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [label(caption), PlanDayPlateView(plan: plan, day: day, room: room, onOpen: () {})],
    );

    final started = planFrom('current_started');
    final progress = planFrom('current_progress');
    final closed = planFrom('current_closed');
    final building = planFrom('current_started', _lesson('building'));
    final failed = planFrom('current_started', _lesson('failed'));

    await expectPlanGolden(
      tester,
      strip([
        plate('не начат', started, roomFrom('room_unopened'), started.days.first),
        plate('идёт', progress, roomFrom('room_progress'), progress.days.first),
        plate('закрыт', closed, roomFrom('room_closed'), closed.days.first),
        plate('собирается', building, null, building.days.first),
        plate('не собрался', failed, null, failed.days.first),
      ]),
      'plan/strip-day-plate',
      size: const Size(390, 2300),
    );
  });
}

/// Первые [n] дней маршрута и без мишени — в полоске только три дня.
Map<String, dynamic> Function(Map<String, dynamic>) _firstDays(int n) => (json) {
  json['days'] = (json['days'] as List).take(n).toList();
  json['event_native'] = null;
  json['event_date'] = null;

  return json;
};

Map<String, dynamic> Function(Map<String, dynamic>) _lesson(String status) => (json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  days.first['lesson_status'] = status;
  json['current_day'] = days.first;

  return json;
};
