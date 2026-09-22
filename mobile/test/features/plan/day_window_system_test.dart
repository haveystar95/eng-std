import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_plate.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/day_window_harness.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';
import '../../support/server_fixtures.dart';

/// THE WINDOW OF A REVIEW AND OF THE REHEARSAL (кадры 37-1, 37-2; наряды CLIENT-CONV-1b, CLIENT-CONV-1c) — «второй
/// компонент окна: то же окно дня 23-0a, сменились ряды и список под плитой». Over the server's own replies
/// (`day-review.json`, `day-rehearsal.json`, `day-doctor.json` of BACK-TAILS-2 — `server_fixtures.dart`; the plan —
/// `test/fixtures/plan/plan_rehearsal.json`).
///
/// Rows, minutes and scenes come from the server's `stages[]` and `window` — every row's `minutes`, `window.sources[]`,
/// the stage `repetition`; where the reply has no number, none is printed. A reply without those fields is the window of
/// a server before BACK-TAILS-2 ([beforeTails2]) — the one case the fixtures no longer carry.
void main() {
  setUpAll(setUpPlanGoldens);

  final plan = planFrom('plan_rehearsal');
  final booking = plan.scenes.firstWhere((s) => s.titleNative == 'Запись к врачу');
  final visit = plan.scenes.firstWhere((s) => s.titleNative == 'Приём у врача');

  Map<String, dynamic> dayJson(String name) => serverFixtureJson(name);
  Map<String, dynamic> windowOf(Map<String, dynamic> json) => json['window'] as Map<String, dynamic>;
  Map<String, dynamic> windowDay(Map<String, dynamic> json) => windowOf(json)['day'] as Map<String, dynamic>;
  List<Map<String, dynamic>> rowsOf(Map<String, dynamic> json) => (windowOf(json)['stages'] as List).cast<Map<String, dynamic>>();

  /// [json] as a server before BACK-TAILS-2 wrote it: no `minutes`, `targets`, `sources`, `talk_again`, and a review's
  /// cards under `speak`.
  Map<String, dynamic> beforeTails2(Map<String, dynamic> json) {
    for (final row in rowsOf(json)) {
      row
        ..remove('minutes')
        ..remove('targets');
      if (row['stage'] == 'repetition') row['stage'] = 'speak';
    }
    windowOf(json)
      ..remove('sources')
      ..remove('talk_again');
    return json;
  }

  WindowStageRow rowOf(WidgetTester tester, PlanStage stage) =>
      tester.widgetList<WindowStageRow>(find.byType(WindowStageRow)).firstWhere((r) => r.stage.stage == stage);
  Finder inRow(PlanStage stage, String text) => find.descendant(
    of: find.byWidgetPredicate((w) => w is WindowStageRow && w.stage.stage == stage),
    matching: find.text(nb(text)),
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
      await pumpDayWindow(tester, PlanDayRoom.fromJson(dayJson('day-review')), plan: plan);

      expect(find.text('ПОВТОРЕНИЕ'), findsOneWidget);
      expect(find.text('Что уже было'), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'идёт');
      expect(textOf(tester, 'window-system-lead'), 'Вернёшь фразы прошлых дней и поговоришь с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.repetition, PlanStage.conversation], reason: 'ряды — ровно `window.stages` сервера');
      expect(inRow(PlanStage.repetition, 'Повторение'), findsOneWidget);
      // The frames of 37-1 / 37-2 say the minutes in words, as the status line does (BACK-TAILS-2's contract quotes them).
      expect(inRow(PlanStage.repetition, 'идёт · около 3 минут'), findsOneWidget, reason: 'у текущего — остаток `minutes_left`');
      expect(inRow(PlanStage.conversation, 'около 4 минут'), findsOneWidget, reason: 'у ряда впереди — его `minutes`');
      expect(rowOf(tester, PlanStage.conversation).stage.minutes, 4);
      expect(find.byType(WindowPill), findsNothing, reason: 'вкладок программы у повторения нет');

      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ ДНЕЙ');
      expect(find.text('День 1 · Приём у врача'), findsOneWidget);
      final card = tester.getRect(find.byKey(ValueKey('window-source-${visit.id}')));
      expect(find.byKey(ValueKey('window-source-lines-${visit.id}')), findsNothing, reason: '«N карточек» сервер не шлёт — числа нет');
      expect(card.height, greaterThanOrEqualTo(72));
      expect(card.left, 24);
      expect(card.right, 390 - 24);
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9б; handoff BACK-TAILS-2 §9 п. 4): `day_number` бывает null — у сцены без своего дня
    // (стенд e2e 1b: «Запись к врачу»); такая сцена печатается одним именем, и порядок — сервера.
    // ЛОВИТ: «День null · …» и список, пересортированный телефоном.
    testWidgets('сцена без своего дня — одним именем, порядок сервера', (tester) async {
      final json = dayJson('day-review');
      (windowOf(json)['sources'] as List).add({'scene_id': booking.id, 'title_native': booking.titleNative, 'day_number': null});
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(find.text('Запись к врачу'), findsOneWidget);
      final visitCard = tester.getRect(find.byKey(ValueKey('window-source-${visit.id}')));
      expect(visitCard.top, lessThan(tester.getRect(find.byKey(ValueKey('window-source-${booking.id}'))).top));
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9а, §9б): нет `minutes` — у ряда впереди «впереди», минут, которых сервер не дал, нет;
    // нет `sources` или список пуст — блока «Из каких дней» нет вовсе. Ответ до BACK-TAILS-2 — это `speak`, и ряд — «Говорю
    // сам».
    // ЛОВИТ: «≈ 0 мин», список дней, придуманный на телефоне, и бровь над пустым списком.
    testWidgets('ответ до BACK-TAILS-2: «впереди», ряд «Говорю сам», списка нет', (tester) async {
      await pumpDayWindow(tester, PlanDayRoom.fromJson(beforeTails2(dayJson('day-review'))), plan: plan);
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.speak, PlanStage.conversation]);
      expect(inRow(PlanStage.conversation, 'впереди'), findsOneWidget);
      expect(find.byKey(const ValueKey('window-sources-brow')), findsNothing);
      expect(find.text('День 1 · Приём у врача'), findsNothing);

      final empty = dayJson('day-review');
      windowOf(empty)['sources'] = <dynamic>[];
      await tester.pumpWidget(const SizedBox());
      await pumpDayWindow(tester, PlanDayRoom.fromJson(empty), plan: plan);
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
          [PlanStage.repetition, PlanStage.conversation]);
      expect(logs.where((m) => m.contains('warmup')), isNotEmpty);
    });

    // ПРАВИЛО: минуты — те, что прислал сервер: до старта оценка («около 11 минут»), у пройденного — потраченные; список
    // дней до раздачи — тот, что прислал сервер.
    // ЛОВИТ: минуты, посчитанные на телефоне, и «≈ 0 минут» у дня, которому сервер минут не дал.
    testWidgets('не начат — «не начат · около N минут», дни — из ответа сервера', (tester) async {
      final json = notDealt(dayJson('day-review'));
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), nb('не начат · около $estimate минут'));
      expect(find.text('День 1 · Приём у врача'), findsOneWidget);
    });

    testWidgets('пройден — «пройден · N минут» потраченных', (tester) async {
      final passed = dayJson('day-review');
      windowDay(passed)
        ..['status'] = 'passed'
        ..['minutes_spent'] = 9;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(passed), plan: plan);
      expect(textOf(tester, 'window-system-status'), nb('пройден · 9 минут'));
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
      await pumpDayWindow(tester, PlanDayRoom.fromJson(dayJson('day-review')), plan: plan);
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
      await pumpDayWindow(tester, PlanDayRoom.fromJson(dayJson('day-rehearsal')), plan: plan);
      expect(find.text('РЕПЕТИЦИЯ'), findsOneWidget);
      expect(find.descendant(of: find.byType(WindowPlate), matching: find.text(plan.shortTitle ?? plan.displayTitle)), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'перед событием · сегодня · идёт');
      expect(textOf(tester, 'window-system-lead'), 'Проговоришь весь разговор с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.recall, PlanStage.conversation]);
      expect(inRow(PlanStage.conversation, 'около 6 минут'), findsOneWidget);
      expect(inRow(PlanStage.recall, 'идёт · около 4 минут'), findsOneWidget, reason: 'кадр 37-1b: остаток `minutes_left`');
      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ СЦЕН');
      final tops = <double>[];
      for (final s in [booking, visit]) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsOneWidget);
        expect(find.descendant(of: find.byKey(ValueKey('window-source-${s.id}')), matching: find.text(s.titleNative)), findsOneWidget);
        expect(find.byKey(ValueKey('window-source-lines-${s.id}')), findsNothing);
        tops.add(tester.getRect(find.byKey(ValueKey('window-source-${s.id}'))).top);
      }
      expect(tops.first, lessThan(tops.last), reason: 'порядок сервера: сцены плана по порядку');
      expect(find.text('День 1 · Приём у врача'), findsNothing, reason: 'у репетиции сцены — без дня');
    });

    // ПРАВИЛО: до раздачи — тот же список сервера; день недели — из даты слота («в четверг»), «сегодня» и «завтра» — как
    // их прислал сервер. Без `sources` (сервер до BACK-TAILS-2) — блока нет.
    // ЛОВИТ: дату цифрами там, где кадр говорит днём недели; сцены плана, вписанные телефоном в пустой ответ.
    testWidgets('не начат: «перед событием · в четверг · не начат · около N минут», сцены сервера; без `sources` — блока нет',
        (tester) async {
      Map<String, dynamic> notStarted(Map<String, dynamic> json) {
        (notDealt(json)['day'] as Map<String, dynamic>)['slot'] = {'code': 'date', 'date': '2026-09-24', 'label_native': null};
        return json;
      }

      final json = notStarted(dayJson('day-rehearsal'));
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), nb('перед событием · в четверг · не начат · около $estimate минут'));
      for (final s in [booking, visit]) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsOneWidget);
      }

      await tester.pumpWidget(const SizedBox());
      await pumpDayWindow(tester, PlanDayRoom.fromJson(beforeTails2(notStarted(dayJson('day-rehearsal')))), plan: plan);
      expect(find.byKey(const ValueKey('window-sources-brow')), findsNothing);
      for (final s in plan.scenes) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsNothing);
      }
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §9а, кадр 23-0a): у дня-сцены ряд впереди говорит свои плановые минуты коротко —
    // «≈ 12 мин», как компактная шапка; текущий — остаток «идёт · ≈ 5 мин»; слово «впереди» остаётся ряду, минут которого
    // сервер не прислал. День — `day-doctor.json` BACK-TAILS-2: минуты у каждого ряда.
    // ЛОВИТ: «около N минут» дня повторения, протёкшее на день-сцену, и «≈ 0 мин».
    testWidgets('день-сцена: у рядов впереди «≈ N мин», без минут — «впереди»', (tester) async {
      final json = dayJson('day-doctor');
      rowsOf(json).firstWhere((r) => r['stage'] == 'speak').remove('minutes');
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json));
      expect(inRow(PlanStage.words, 'идёт · ≈ 3 мин'), findsOneWidget);
      expect(inRow(PlanStage.phrases, '≈ 14 мин'), findsOneWidget);
      expect(inRow(PlanStage.dialogue, '≈ 4 мин'), findsOneWidget);
      expect(inRow(PlanStage.listen, '≈ 3 мин'), findsOneWidget);
      expect(inRow(PlanStage.conversation, '≈ 5 мин'), findsOneWidget);
      expect(inRow(PlanStage.speak, 'впереди'), findsOneWidget, reason: 'ряд без `minutes`');
    });

    testWidgets('пройден: «перед событием · завтра · пройден · N минут»', (tester) async {
      final json = dayJson('day-rehearsal');
      windowDay(json)
        ..['status'] = 'passed'
        ..['minutes_spent'] = 21;
      (json['day'] as Map<String, dynamic>)['slot'] = {'code': 'tomorrow', 'date': '2026-09-22', 'label_native': 'завтра'};
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), nb('перед событием · завтра · пройден · 21 минута'));
    });
  });
}
