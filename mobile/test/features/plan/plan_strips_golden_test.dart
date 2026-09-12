import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/entry/entry_goal_step.dart';
import 'package:eng_std/features/plan/entry/entry_state.dart';
import 'package:eng_std/features/plan/plan_day_plate_view.dart';
import 'package:eng_std/features/plan/plan_route.dart';
import 'package:eng_std/features/plan/plan_tab_parts.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';

/// ПОЛОСКИ СОСТОЯНИЙ — три снимка, на которых состояния одного элемента стоят РЯДОМ.
///
/// Канва рисует их полосками не для красоты: узел, плита и микрофон отличаются между состояниями
/// одной деталью каждый (обводка, заливка, подпись), и поймать такую разницу можно только глядя на
/// состояния одновременно. По отдельным кадрам «латунь только у сегодня» не проверяется — на
/// каждом кадре латунь на месте.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget strip(List<Widget> rows, {Color background = AppColors.ground}) => planGoldenApp(
    Scaffold(
      backgroundColor: background,
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

  Widget label(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: PlanSectionLabel(text),
  );

  // ── ШЕСТЬ СОСТОЯНИЙ УЗЛА (кадр 21-2b) ────────────────────────────────────────────────────
  testWidgets('узел маршрута — шесть состояний подряд', (tester) async {
    // Один снятый план и один маршрут: каждая полоска — тот же `PlanRoute` с одним днём в нужном
    // состоянии, а не шесть нарисованных вручную кружков.
    Widget one(String caption, Map<String, dynamic> Function(Map<String, dynamic>) edit) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [label(caption), PlanRoute(plan: planFrom('current_closed', edit))],
    );

    await expectPlanGolden(
      tester,
      strip([
        one('пройден', _onlyDay(1)),
        one('сегодня', _onlyDay(2)),
        one('первый запертый — с причиной', _onlyDays(const [2, 3])),
        one('запертый — только номер и дата', _onlyDay(3)),
        one('системный день — своя иллюстрация', _onlyDay(3)),
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            label('событие — мишень с датой'),
            PlanRoute(plan: planFrom('current_ready', _eventOnly)),
          ],
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            label('событие без даты — пунктир и «указать дату»'),
            PlanRoute(plan: planFrom('current_ready', _eventNoDate)),
          ],
        ),
      ]),
      'plan/strip-route-node',
      size: const Size(390, 1500),
    );
  });

  // ── ПЯТЬ СОСТОЯНИЙ ПЛИТЫ (кадры 21-2, 21-3, 21-4, 22-5a, 22-5c) ──────────────────────────
  testWidgets('плита дня — пять состояний подряд', (tester) async {
    Widget plate(String caption, Plan plan, PlanDayRoom? room, PlanDayRoute day) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        label(caption),
        PlanDayPlateView(plan: plan, day: day, room: room, onOpen: () {}),
      ],
    );

    final ready = planFrom('current_ready');
    final progress = planFrom('current_progress');
    final closed = planFrom('current_closed');
    final building = planFrom('current_ready', _lesson('building'));
    final failed = planFrom('current_ready', _lesson('failed'));

    await expectPlanGolden(
      tester,
      strip([
        plate('не начат', ready, roomFrom('room_unopened'), ready.days.first),
        plate('идёт', progress, roomFrom('room_progress'), progress.days.first),
        plate('закрыт', closed, roomFrom('room_closed'), closed.days.first),
        plate('собирается', building, null, building.days.first),
        plate('не собрался', failed, null, failed.days.first),
      ]),
      'plan/strip-day-plate',
      size: const Size(390, 2200),
    );
  });

  // ── ЧЕТЫРЕ СОСТОЯНИЯ МИКРОФОНА (кадр 22-1) ───────────────────────────────────────────────
  testWidgets('микрофон в поле цели — четыре состояния подряд', (tester) async {
    Widget mic(String caption, EntryMicState state, String text) {
      final controller = TextEditingController(text: text);
      addTearDown(controller.dispose);
      final focus = FocusNode();
      addTearDown(focus.dispose);

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          label(caption),
          // Снимается САМ шаг цели: поле, подпись у микрофона и волна — его собственные части, и
          // собранная заново копия их бы не проверила.
          SizedBox(
            height: 300,
            child: EntryGoalStep(
              controller: controller,
              focus: focus,
              mic: state,
              micSeconds: 7,
              onMic: () {},
              onStory: (_) {},
            ),
          ),
        ],
      );
    }

    await expectPlanGolden(
      tester,
      strip([
        mic('покой', EntryMicState.idle, ''),
        mic('слушаю', EntryMicState.listening, ''),
        mic('распознаю', EntryMicState.recognising, ''),
        mic('текст в поле', EntryMicState.done,
            'Иду к врачу, болит спина уже неделю, боюсь не понять назначения'),
      ]),
      'plan/strip-mic',
      size: const Size(390, 1400),
    );
  });
}

/// Маршрут из ОДНОГО дня — чтобы в полоске стояло ровно одно состояние узла.
Map<String, dynamic> Function(Map<String, dynamic>) _onlyDay(int number) =>
    _onlyDays([number]);

Map<String, dynamic> Function(Map<String, dynamic>) _onlyDays(List<int> numbers) => (json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  json['days'] = days.where((d) => numbers.contains(d['number'])).toList();
  // Событие рисуется своим узлом и в полоске узлов дней мешает.
  json['event_native'] = null;
  json['event_date'] = null;

  return json;
};

/// Только мишень события — узлов дней в полоске нет.
Map<String, dynamic> _eventOnly(Map<String, dynamic> json) {
  json['days'] = <Map<String, dynamic>>[];

  return json;
}

/// Мишень без даты: канва рисует пунктирный круг и «указать дату» вместо дня недели.
Map<String, dynamic> _eventNoDate(Map<String, dynamic> json) {
  _eventOnly(json);
  json['event_date'] = null;

  return json;
}

Map<String, dynamic> Function(Map<String, dynamic>) _lesson(String status) => (json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  days.first['lesson_status'] = status;
  json['current_day'] = days.first;

  return json;
};
