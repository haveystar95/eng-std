import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/day_window_harness.dart';
import '../../support/plan_goldens.dart';

/// THE WINDOW OF A REVIEW AND OF THE REHEARSAL (кадры 37-1, 37-2; наряд CLIENT-CONV-1b) — «второй компонент окна: то
/// же окно дня 23-0a, сменились ряды и список под плитой». Over live replies of the e2e plan the stand walked
/// (`backend2/docs/fixtures/day-review.json`, `day-rehearsal.json`, the plan — `test/fixtures/plan/plan_rehearsal.json`).
///
/// Rows, minutes and scenes come from the server's `stages[]` and `window`; where the reply has no number, none is
/// printed.
void main() {
  setUpAll(setUpPlanGoldens);

  final plan = planFrom('plan_rehearsal');

  Map<String, dynamic> dayJson(String name) =>
      jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;
  Map<String, dynamic> windowDay(Map<String, dynamic> json) => (json['window'] as Map<String, dynamic>)['day'] as Map<String, dynamic>;

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
    // ПРАВИЛО (кадр 37-2): бровь — вид дня, заголовок «Что уже было», строка состояния, одна фраза о том, что в дне;
    // ряды — этапы, которые прислал сервер; под плитой «Из каких дней» — дни маршрута, откуда карточки дня. Ни пилюли,
    // ни вкладок программы: список дней и есть программа повторения.
    // ЛОВИТ: окно сценного дня на повторении (пустая плита «День 2», вкладки «Слова · Фразы · Диалог» с чужим
    // диалогом), список, собранный из маршрута мимо карточек дня.
    testWidgets('идёт: «Что уже было», ряды сервера, «Из каких дней» — день карточек', (tester) async {
      final room = PlanDayRoom.fromJson(dayJson('day-review'));
      await pumpDayWindow(tester, room, plan: plan);

      expect(find.text('ПОВТОРЕНИЕ'), findsOneWidget);
      expect(find.text('Что уже было'), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'идёт');
      expect(textOf(tester, 'window-system-lead'), 'Вернёшь фразы прошлых дней и поговоришь с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.speak, PlanStage.conversation], reason: 'ряды — ровно `window.stages` сервера');
      expect(find.byType(WindowPill), findsNothing, reason: 'вкладок программы у повторения нет');

      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ ДНЕЙ');
      final day1 = plan.days.firstWhere((d) => d.number == 1);
      expect(find.byKey(ValueKey('window-source-${day1.id}')), findsOneWidget);
      expect(find.text('День 1 · Приём у врача'), findsOneWidget);
      expect(find.byKey(ValueKey('window-source-lines-${day1.id}')), findsNothing, reason: '«N карточек» сервер не шлёт — числа нет');
      final card = tester.getRect(find.byKey(ValueKey('window-source-${day1.id}')));
      expect(card.height, greaterThanOrEqualTo(72));
      expect(card.left, 24);
      expect(card.right, 390 - 24);
    });

    // ПРАВИЛО: минуты — те, что прислал сервер: до старта оценка («около 11 минут»), у пройденного — потраченные.
    // ЛОВИТ: минуты, посчитанные на телефоне, и «≈ 0 минут» у дня, которому сервер минут не дал.
    testWidgets('не начат — «не начат · около N минут», дни — по маршруту до раздачи', (tester) async {
      final json = notDealt(dayJson('day-review'));
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'не начат · около $estimate минут');
      expect(find.text('День 1 · Приём у врача'), findsOneWidget, reason: 'до раздачи — сцены дней маршрута перед повторением');
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
      await pumpDayWindow(tester, PlanDayRoom.fromJson(dayJson('day-review')), plan: plan);
      final brow = find.byKey(const ValueKey('window-sources-brow'));
      expect(tester.widget<Text>(brow).style, AppTextWindow.tabBrow);
      final lastRow = tester.getRect(find.byType(WindowStageRow).last);
      expect(tester.getRect(brow).top, greaterThan(lastRow.bottom));
    });
  });

  group('37-1 · репетиция', () {
    // ПРАВИЛО (кадр 37-1): бровь «РЕПЕТИЦИЯ», заголовок — имя плана, строка «перед событием · когда · состояние»,
    // ряды «Вспомнить» и «Разговор» от сервера; под плитой «Из каких сцен» — сцены обзора дня с числом своих реплик
    // (длина списка сервера, не посчитанная карточка).
    // ЛОВИТ: «День 3» вместо имени плана, числа, посчитанные на телефоне, список сцен мимо обзора, который сдан.
    testWidgets('идёт: «перед событием · сегодня · идёт», сцены обзора с «N реплик»', (tester) async {
      final room = PlanDayRoom.fromJson(dayJson('day-rehearsal'));
      await pumpDayWindow(tester, room, plan: plan);
      expect(find.text('РЕПЕТИЦИЯ'), findsOneWidget);
      expect(find.text(plan.shortTitle ?? plan.displayTitle), findsOneWidget);
      expect(textOf(tester, 'window-system-status'), 'перед событием · сегодня · идёт');
      expect(textOf(tester, 'window-system-lead'), 'Проговоришь весь разговор с собеседником');
      expect([for (final r in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow))) r.stage.stage],
          [PlanStage.recall, PlanStage.conversation]);
      expect(textOf(tester, 'window-sources-brow'), 'ИЗ КАКИХ СЦЕН');
      for (final s in room.recallScenes) {
        expect(find.byKey(ValueKey('window-source-${s.sceneId}')), findsOneWidget);
        expect(find.text(s.titleNative), findsOneWidget);
        expect(textOf(tester, 'window-source-lines-${s.sceneId}'), '7 реплик');
      }
    });

    // ПРАВИЛО: до раздачи обзора нет — сцены плана по порядку, без числа; день недели — из даты слота («в четверг»),
    // «сегодня» и «завтра» — как их прислал сервер.
    // ЛОВИТ: число реплик, придуманное до раздачи; дату цифрами там, где кадр говорит днём недели.
    testWidgets('не начат: «перед событием · в четверг · не начат · около N минут», сцены плана без чисел', (tester) async {
      final json = notDealt(dayJson('day-rehearsal'));
      (json['day'] as Map<String, dynamic>)['slot'] = {'code': 'date', 'date': '2026-09-24', 'label_native': null};
      final estimate = windowDay(json)['minutes_estimate'] as int;
      await pumpDayWindow(tester, PlanDayRoom.fromJson(json), plan: plan);
      expect(textOf(tester, 'window-system-status'), 'перед событием · в четверг · не начат · около $estimate минут');
      for (final s in plan.scenes) {
        expect(find.byKey(ValueKey('window-source-${s.id}')), findsOneWidget);
        expect(find.byKey(ValueKey('window-source-lines-${s.id}')), findsNothing);
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
