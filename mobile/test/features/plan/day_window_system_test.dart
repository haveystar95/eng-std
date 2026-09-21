import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_window.dart' show WindowStageState;
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_plate.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/day_window_harness.dart';
import '../../support/plan_goldens.dart';

/// THE WINDOW OF A REVIEW AND OF THE REHEARSAL (кадры 37-1, 37-2; наряды CLIENT-CONV-1b, CLIENT-CONV-1c) — «второй
/// компонент окна: то же окно дня 23-0a, сменились ряды и список под плитой». Over live replies of the e2e plan the stand
/// walked (`backend2/docs/fixtures/day-review.json`, `day-rehearsal.json`, the plan — `test/fixtures/plan/plan_rehearsal.json`).
///
/// Rows, minutes and scenes come from the server's `stages[]` and `window`; where the reply has no number, none is
/// printed. BACK-TAILS-2's fields — every row's `minutes`, `window.sources[]`, the stage `repetition` — are not in the
/// server's fixtures yet: [tails2] writes them onto a reply the way that branch serializes them (`PlanJson`), and a
/// reply without them is the window of a server before it.
void main() {
  setUpAll(setUpPlanGoldens);

  final plan = planFrom('plan_rehearsal');
  final booking = plan.scenes.firstWhere((s) => s.titleNative == 'Запись к врачу');
  final visit = plan.scenes.firstWhere((s) => s.titleNative == 'Приём у врача');

  Map<String, dynamic> dayJson(String name) =>
      jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;
  Map<String, dynamic> windowDay(Map<String, dynamic> json) => (json['window'] as Map<String, dynamic>)['day'] as Map<String, dynamic>;

  /// BACK-TAILS-2 on [json]: [minutes] — a row's planned minutes by stage id; [sources] — the list the day is made of;
  /// [repetition] — a review's cards under the stage id `repetition` instead of `speak`.
  Map<String, dynamic> tails2(
    Map<String, dynamic> json, {
    Map<String, int> minutes = const {},
    List<Map<String, dynamic>>? sources,
    bool repetition = false,
  }) {
    final window = json['window'] as Map<String, dynamic>;
    for (final s in (window['stages'] as List).cast<Map<String, dynamic>>()) {
      if (repetition && s['stage'] == 'speak') s['stage'] = 'repetition';
      if (minutes[s['stage']] case final m?) s['minutes'] = m;
    }
    if (sources != null) window['sources'] = sources;
    return json;
  }

  Map<String, dynamic> source(PlanScene scene, int? day) => {'scene_id': scene.id, 'title_native': scene.titleNative, 'day_number': day};

  WindowStageRow rowOf(WidgetTester tester, PlanStage stage) =>
      tester.widgetList<WindowStageRow>(find.byType(WindowStageRow)).firstWhere((r) => r.stage.stage == stage);
  Finder inRow(PlanStage stage, String text) => find.descendant(
    of: find.byWidgetPredicate((w) => w is WindowStageRow && w.stage.stage == stage),
    matching: find.text(text),
  );

  /// The reply as the window of a day NOT STARTED gets it: no card dealt yet.
  Map<String, dynamic> notDealt(Map<String, dynamic> json) {
    for (final s in (json['stages'] as List).cast<Map<String, dynamic>>()) {
      s['cards'] = <dynamic>[];
    }
    windowDay(json)['status'] = 'not_started';
    (json['window'] as Map<String, dynamic>)['allowed_action'] = 'start';
    return json;
  }

  String textOf(WidgetTester tester, String key) => tester.widget<Text>(find.byKey(ValueKey(key))).data!;

  group('37-2 · повторение', () {
    // ПРАВИЛО (кадр 37-2; наряд CLIENT-CONV-1c §9а–в): бровь — вид дня, заголовок «Что уже было», строка состояния,
    // одна фраза о том, что в дне; ряды — этапы, которые прислал сервер, и этап `repetition` — ряд «Повторение»; у ряда
    // впереди — его плановые минуты «≈ N мин», у текущего — остаток; под плитой «Из каких дней» — СПИСОК СЕРВЕРА
    // (`window.sources[]`) в его порядке: «День 1 · Приём у врача», а сцена без своего дня — одним именем. Ни пилюли, ни
    // вкладок программы: список дней и есть программа повторения. Чисел на карточках нет — список их не несёт.
    // ЛОВИТ: окно сценного дня на повторении (пустая плита «День 2», вкладки «Слова · Фразы · Диалог» с чужим диалогом);
    // «Говорю сам» на месте «Повторения»; список, собранный на телефоне из маршрута или карточек мимо ответа сервера.
    testWidgets('идёт: «Что уже было», ряд «Повторение», минуты рядов, «Из каких дней» — список сервера', (tester) async {
      final json = tails2(
        dayJson('day-review'),
        repetition: true,
        minutes: {'repetition': 6, 'conversation': 5},
        sources: [source(visit, 1), source(booking, null)],
      );
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);

      expect(find.text('ПОВТОРЕНИЕ'), findsOneWidget);
      expect(find.text('Что уже было'), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'идёт');
      expect(textOf(tester, 'window-system-lead'), 'Вернёшь фразы прошлых дней и поговоришь с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.repetition, PlanStage.conversation], reason: 'ряды — ровно `window.stages` сервера');
      expect(inRow(PlanStage.repetition, 'Повторение'), findsOneWidget);
      // The frames of 37-1 / 37-2 say the minutes in words, as the status line does (BACK-TAILS-2's contract quotes them).
      expect(inRow(PlanStage.repetition, 'идёт · около 5 минут'), findsOneWidget, reason: 'у текущего — остаток `minutes_left`, не план');
      expect(inRow(PlanStage.conversation, 'около 5 минут'), findsOneWidget, reason: 'у ряда впереди — его `minutes`');
      expect(rowOf(tester, PlanStage.conversation).stage.minutes, 5);
      expect(find.byType(WindowPill), findsNothing, reason: 'вкладок программы у повторения нет');

      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ ДНЕЙ');
      expect(find.text('День 1 · Приём у врача'), findsOneWidget);
      expect(find.text('Запись к врачу'), findsOneWidget, reason: 'сцена без своего дня — одним именем');
      final first = tester.getRect(find.byKey(ValueKey('window-source-${visit.id}')));
      expect(first.top, lessThan(tester.getRect(find.byKey(ValueKey('window-source-${booking.id}'))).top), reason: 'порядок сервера');
      expect(find.byKey(ValueKey('window-source-lines-${visit.id}')), findsNothing, reason: '«N карточек» сервер не шлёт — числа нет');
      expect(first.height, greaterThanOrEqualTo(72));
      expect(first.left, 24);
      expect(first.right, 390 - 24);
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9а, §9б): нет `minutes` — у ряда впереди «впереди», минут, которых сервер не дал, нет;
    // нет `sources` или список пуст — блока «Из каких дней» нет вовсе. Ответ до BACK-TAILS-2 — это `speak`, и ряд — «Говорю
    // сам».
    // ЛОВИТ: «≈ 0 мин», список дней, придуманный на телефоне, и бровь над пустым списком.
    testWidgets('без полей BACK-TAILS-2: «впереди», ряд «Говорю сам», списка нет', (tester) async {
      await pumpDayWindow(tester, PlanDayRoom.fromJson(dayJson('day-review')), plan: plan);
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.speak, PlanStage.conversation]);
      expect(inRow(PlanStage.conversation, 'впереди'), findsOneWidget);
      expect(find.byKey(const ValueKey('window-sources-brow')), findsNothing);
      expect(find.text('День 1 · Приём у врача'), findsNothing);

      await tester.pumpWidget(const SizedBox());
      await pumpDayWindow(tester, PlanDayRoom.fromJson(tails2(dayJson('day-review'), sources: [])), plan: plan);
      expect(find.byKey(const ValueKey('window-sources-brow')), findsNothing, reason: 'пустой список — блока нет');
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9в): этап, которого сборка не знает, — ряд не рисуется, в отладочный лог — его имя;
    // остальные ряды на месте, окно грузится.
    // ЛОВИТ: окно «не загрузилось» из-за одного нового ряда, и пропуск без следа в логе.
    testWidgets('незнакомый этап — ряда нет, в логе его имя, остальные на месте', (tester) async {
      final json = dayJson('day-review');
      ((json['window'] as Map<String, dynamic>)['stages'] as List).insert(1, {
        'stage': 'warmup',
        'state': 'locked',
        'done_count': null,
        'total': null,
        'minutes_left': null,
        'minutes': 3,
        'share': 0.0,
      });
      final logs = <String>[];
      final print = debugPrint;
      debugPrint = (message, {wrapWidth}) => logs.add(message ?? '');
      addTearDown(() => debugPrint = print);
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      debugPrint = print;
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.speak, PlanStage.conversation]);
      expect(logs.where((m) => m.contains('warmup')), isNotEmpty);
    });

    // ПРАВИЛО: минуты — те, что прислал сервер: до старта оценка («около 11 минут»), у пройденного — потраченные; список
    // дней до раздачи — тот, что прислал сервер.
    // ЛОВИТ: минуты, посчитанные на телефоне, и «≈ 0 минут» у дня, которому сервер минут не дал.
    testWidgets('не начат — «не начат · около N минут», дни — из ответа сервера', (tester) async {
      final json = tails2(notDealt(dayJson('day-review')), sources: [source(visit, 1)]);
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'не начат · около $estimate минут');
      expect(find.text('День 1 · Приём у врача'), findsOneWidget);
    });

    testWidgets('пройден — «пройден · N минут» потраченных', (tester) async {
      final passed = dayJson('day-review');
      windowDay(passed)
        ..['status'] = 'passed'
        ..['minutes_spent'] = 9;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(passed), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'пройден · 9 минут');
    });

    testWidgets('пройден без минут от сервера — одно слово', (tester) async {
      final passed = dayJson('day-review');
      windowDay(passed)
        ..['status'] = 'passed'
        ..remove('minutes_spent');
      await pumpDayWindow(tester, PlanDayRoom.fromJson(passed), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'пройден');
    });

    // ПРАВИЛО (кадр 37-2): плита без пилюли — под последним рядом 28 воздуха, и лента начинается под ней через 24.
    // ЛОВИТ: низ плиты сценного дня (48 под пилюлю), который оставлял пустую полосу над списком.
    testWidgets('плита без пилюли: список дней под плитой, бровь списка — стиль бровей окна', (tester) async {
      await pumpDayWindow(tester, PlanDayRoom.fromJson(tails2(dayJson('day-review'), sources: [source(visit, 1)])), plan: plan);
      final brow = find.byKey(const ValueKey('window-sources-brow'));
      expect(tester.widget<Text>(brow).style, AppTextWindow.tabBrow);
      final lastRow = tester.getRect(find.byType(WindowStageRow).last);
      expect(tester.getRect(brow).top, greaterThan(lastRow.bottom));
    });
  });

  group('37-1 · репетиция', () {
    // ПРАВИЛО (кадр 37-1; наряд CLIENT-CONV-1c §9а, §9б): бровь «РЕПЕТИЦИЯ», заголовок — имя плана, строка «перед
    // событием · когда · состояние», ряды «Вспомнить» и «Разговор» от сервера с минутами ряда впереди; под плитой «Из
    // каких сцен» — сцены из `window.sources[]` по порядку сервера, именами сцен (дня у репетиции нет). Числа реплик на
    // карточках нет: список его не несёт, а телефон не считает.
    // ЛОВИТ: «День 3» вместо имени плана, числа, посчитанные на телефоне по карточке обзора, список сцен мимо сервера.
    testWidgets('идёт: «перед событием · сегодня · идёт», «Из каких сцен» — список сервера без чисел', (tester) async {
      final json = tails2(dayJson('day-rehearsal'), minutes: {'recall': 6, 'conversation': 6}, sources: [source(booking, null), source(visit, 1)]);
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(find.text('РЕПЕТИЦИЯ'), findsOneWidget);
      expect(find.descendant(of: find.byType(WindowPlate), matching: find.text(plan.shortTitle ?? plan.displayTitle)), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'перед событием · сегодня · идёт');
      expect(textOf(tester, 'window-system-lead'), 'Проговоришь весь разговор с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.recall, PlanStage.conversation]);
      expect(inRow(PlanStage.conversation, 'около 6 минут'), findsOneWidget);
      expect(inRow(PlanStage.recall, 'идёт · около 6 минут'), findsOneWidget, reason: 'кадр 37-1b');
      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ СЦЕН');
      for (final s in [booking, visit]) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsOneWidget);
        expect(find.descendant(of: find.byKey(ValueKey('window-source-${s.id}')), matching: find.text(s.titleNative)), findsOneWidget);
        expect(find.byKey(ValueKey('window-source-lines-${s.id}')), findsNothing);
      }
      expect(find.text('День 1 · Приём у врача'), findsNothing, reason: 'у репетиции сцены — без дня');
    });

    // ПРАВИЛО: до раздачи — тот же список сервера; день недели — из даты слота («в четверг»), «сегодня» и «завтра» — как
    // их прислал сервер. Без `sources` — блока нет.
    // ЛОВИТ: дату цифрами там, где кадр говорит днём недели; сцены плана, вписанные телефоном в пустой ответ.
    testWidgets('не начат: «перед событием · в четверг · не начат · около N минут»; без `sources` — блока нет', (tester) async {
      final json = notDealt(dayJson('day-rehearsal'));
      (json['day'] as Map<String, dynamic>)['slot'] = {'code': 'date', 'date': '2026-09-24', 'label_native': null};
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'перед событием · в четверг · не начат · около $estimate минут');
      expect(find.byKey(const ValueKey('window-sources-brow')), findsNothing);
      for (final s in plan.scenes) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsNothing);
      }
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9а, кадр 23-0a): у дня-сцены ряд впереди говорит свои плановые минуты коротко —
    // «≈ 5 мин», как компактная шапка; слово «впереди» остаётся ряду, минут которого сервер не прислал.
    // ЛОВИТ: «около N минут» дня повторения, протёкшее на день-сцену, и «≈ 0 мин».
    testWidgets('день-сцена: у рядов впереди «≈ N мин», без минут — «впереди»', (tester) async {
      final room = windowRoom('in_progress', (json) {
        final data = (json['data'] as Map<String, dynamic>?) ?? json;
        for (final row in ((data['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>()) {
          if (row['stage'] != 'speak') row['minutes'] = 4;
        }
        return json;
      });
      await pumpDayWindow(tester, room);
      final locked = [for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) if (r.stage.state == WindowStageState.locked) r.stage.stage];
      expect(locked, contains(PlanStage.speak));
      for (final stage in locked) {
        expect(inRow(stage, stage == PlanStage.speak ? 'впереди' : '≈ 4 мин'), findsOneWidget, reason: stage.name);
      }
    });

    testWidgets('пройден: «перед событием · завтра · пройден · N минут»', (tester) async {
      final json = dayJson('day-rehearsal');
      windowDay(json)
        ..['status'] = 'passed'
        ..['minutes_spent'] = 21;
      (json['day'] as Map<String, dynamic>)['slot'] = {'code': 'tomorrow', 'date': '2026-09-22', 'label_native': 'завтра'};
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'перед событием · завтра · пройден · 21 минута');
    });
  });
}
