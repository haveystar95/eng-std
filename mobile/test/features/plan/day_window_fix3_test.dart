import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/plan_models.dart' show PlanDayRoom;
import 'package:eng_std/features/plan/day/window/window_returns.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/features/plan/day/window/window_words.dart';

import '../../support/day_window_harness.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';

/// КАНОН НАРЯДА FIX-3 НА ОКНЕ ДНЯ (§§4, 5; кадры 23-0a…0d, 30-1 серии 38).
///
/// Два правила наряда: ряд пройденного этапа, который можно пройти ещё раз, говорит «ещё раз» — И ЭТО ГОВОРИТ СЕРВЕР
/// (`stages[].again`), а не вывод телефона из статуса дня; и то, что ВЕРНУЛОСЬ из прошлого дня, стоит на вкладке
/// группой со своим заголовком и полосой сцены-источника (`items[].source`, `items[].scene`), а своё дня — выше и без
/// заголовка.
void main() {
  setUpAll(setUpPlanGoldens);

  Map<String, dynamic> windowOf(Map<String, dynamic> json) => json['window'] as Map<String, dynamic>;
  List<Map<String, dynamic>> stagesOf(Map<String, dynamic> json) =>
      (windowOf(json)['stages'] as List).cast<Map<String, dynamic>>();
  List<Map<String, dynamic>> itemsOf(Map<String, dynamic> json, String tab) =>
      (((windowOf(json)['program'] as Map<String, dynamic>)[tab] as Map<String, dynamic>)['items'] as List)
          .cast<Map<String, dynamic>>();

  group('§5 · «ещё раз» у ряда — по контракту', () {
    // ПРАВИЛО (наряд FIX-3 §5, кадры 23-0c, 30-1): пройденный ряд с `again: true` говорит «ещё раз»; без `again` —
    // «пройдено». Сервер решает, что можно пройти снова, телефон — только печатает.
    // ЛОВИТ: «ещё раз» у каждого пройденного ряда (телефон решил сам) и ряд, у которого сервер разрешил повтор, а
    // телефон его не предложил.
    testWidgets('пройденный ряд с `again` говорит «ещё раз»', (tester) async {
      await pumpDayWindow(tester, windowRoom('passed'));
      expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('ещё раз')), findsNWidgets(5));
    });

    testWidgets('пройденный ряд без `again` говорит «пройдено»', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('passed', (j) {
          for (final row in stagesOf(j)) {
            row['again'] = false;
          }
          return j;
        }),
      );
      expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('ещё раз')), findsNothing);
      expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('пройдено')), findsNWidgets(5));
    });

    // ПРАВИЛО (наряд FIX-3 §5): у ПРОЙДЕННОГО разговора «ещё раз» стоит, пока сервер не упёрся в лимит повторов дня;
    // упёрся (`again: false`) — ряд говорит «лимит на сегодня», а не «пройдено»: ученик видит, почему двери нет.
    // ЛОВИТ: «ещё раз» у разговора после трёх повторов — тап в 409.
    PlanDayRoom withTalk({required bool again}) => windowRoom('passed', (j) {
      stagesOf(j).add({
        ...stagesOf(j).first,
        'stage': 'conversation',
        'state': 'done',
        'again': again,
        'summary': null,
      });
      return j;
    });

    testWidgets('пройденный разговор с повторами — «ещё раз»', (tester) async {
      await pumpDayWindow(tester, withTalk(again: true));
      expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('ещё раз')), findsNWidgets(6));
      expect(find.text('лимит на сегодня'), findsNothing);
    });

    testWidgets('разговор упёрся в лимит повторов — «лимит на сегодня»', (tester) async {
      await pumpDayWindow(tester, withTalk(again: false));
      expect(find.text('лимит на сегодня'), findsOneWidget);
    });

    // ПРАВИЛО: у ИДУЩЕГО ряда «ещё раз» не предлагается — он ещё не пройден, и «ещё раз» там нечему.
    // ЛОВИТ: «ещё раз» у текущего этапа, если сервер прислал `again` всем рядам разом.
    testWidgets('идущий ряд «ещё раз» не говорит', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('in_progress', (j) {
          for (final row in stagesOf(j)) {
            row['again'] = true;
          }
          return j;
        }),
      );
      final current = tester
          .widgetList<WindowStageRow>(find.byType(WindowStageRow))
          .where((r) => r.stage.state == WindowStageState.current);
      expect(current, isNotEmpty);
      for (final row in current) {
        expect(find.descendant(of: find.byWidget(row), matching: find.text('ещё раз')), findsNothing);
      }
    });
  });

  group('§4 · «Вернулось» — группой со своей сценой', () {
    /// The day whose words came back from day 1: the server marks them `source: returned` and names their scene.
    PlanDayRoom returned({String tab = 'words'}) => windowRoom('in_progress', (j) {
      final items = itemsOf(j, tab);
      for (final item in items.take(2)) {
        item
          ..['source'] = 'returned'
          ..['scene'] = {'id': 'ulid-0001', 'title_native': 'Ресепшен зала', 'day_number': 1};
      }
      for (final item in items.skip(2)) {
        item['source'] = 'own';
      }
      return j;
    });

    // ПРАВИЛО (наряд FIX-3 §4, кадр 23-0d «вернулось»): вернувшиеся единицы стоят ГРУППОЙ ниже своих: заголовок
    // «Вернулось из дня 1», под ним полоса сцены-источника «День 1 · Ресепшен зала», и уже под ней — карточки. Своё
    // дня идёт выше и без заголовка.
    // ЛОВИТ: вернувшееся, вперемешку со своим (в зале день 2 читался одной кучей из 15 реплик), и группу без имени
    // дня, из которого единица вернулась.
    testWidgets('вернувшиеся слова — под заголовком дня и полосой сцены, своё выше', (tester) async {
      await pumpDayWindow(tester, returned());

      final heading = find.byType(WindowReturnHeading);
      expect(heading, findsOneWidget, reason: 'одна сцена-источник — одна группа');
      expect(find.descendant(of: heading, matching: find.text('ВЕРНУЛОСЬ ИЗ ДНЯ 1')), findsOneWidget);
      expect(find.descendant(of: heading, matching: find.text(nb('День 1 · Ресепшен зала'))), findsOneWidget);

      final cards = tester.widgetList<WindowWordCard>(find.byType(WindowWordCard)).toList();
      final own = cards.where((c) => c.word.source == WindowUnitSource.own);
      final back = cards.where((c) => c.word.source == WindowUnitSource.returned);
      expect(own, isNotEmpty);
      expect(back, isNotEmpty);
      final headingTop = tester.getRect(heading).top;
      for (final card in own) {
        expect(tester.getRect(find.byWidget(card)).top, lessThan(headingTop), reason: 'своё — выше заголовка');
      }
      for (final card in back) {
        expect(tester.getRect(find.byWidget(card)).top, greaterThan(headingTop), reason: 'вернувшееся — под заголовком');
      }
    });

    // ПРАВИЛО: день без возвратов заголовка не рисует — группа появляется только тогда, когда сервер сказал
    // `source: returned`.
    // ЛОВИТ: пустой заголовок «Вернулось» над своими же словами дня.
    testWidgets('возвратов нет — заголовка нет', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'));
      expect(find.byType(WindowReturnHeading), findsNothing);
    });
  });
}
