import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/features/plan/day/window/window_action_bar.dart';
import 'package:eng_std/features/plan/day/window/window_bits.dart';
import 'package:eng_std/features/plan/day/window/window_compact_header.dart';
import 'package:eng_std/features/plan/day/window/window_plate.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/features/plan/day/window/window_tabs.dart';
import 'package:eng_std/features/plan/plan_tab_parts.dart' show PlanLoadFailedCard;
import 'package:eng_std/ui/ui.dart';

import '../../support/day_window_harness.dart';
import '../../support/plan_goldens.dart';

/// КАНОН ОКНА ДНЯ (наряд DAY-UI-2, §5) — правила наряда и канвы серии 23, а не снимок кода.
///
/// Каждый тест назван правилом и в комментарии говорит, какой дефект ловит. Снимков поведения здесь
/// нет: всё проверяется утверждениями о дереве виджетов над живыми ответами сервера
/// (`support/day_window_harness.dart`); правка фикстуры — только там, где проверяется КЛИЕНТ.
void main() {
  setUpAll(setUpPlanGoldens);

  final counts = find.textContaining(RegExp(r'\d+ / \d+'));

  Map<String, dynamic> windowOf(Map<String, dynamic> json) => json['window'] as Map<String, dynamic>;
  List<Map<String, dynamic>> stagesOf(Map<String, dynamic> json) =>
      (windowOf(json)['stages'] as List).cast<Map<String, dynamic>>();
  Map<String, dynamic> tabOf(Map<String, dynamic> json, String tab) =>
      (windowOf(json)['program'] as Map<String, dynamic>)[tab] as Map<String, dynamic>;

  // ── 1 · ЦИФРА ТОЛЬКО У ТЕКУЩЕГО ЭТАПА ─────────────────────────────────────────────────────
  group('цифра только у текущего этапа', () {
    // ПРАВИЛО (канва 23-0b): «N / M» стоит в одном ряду — у этапа, который идёт; остальные ряды —
    // слово состояния и полоса.
    // ЛОВИТ: старый кабинет со счётом в каждом ряду («Слова · 32 из 32») — цифра у пройденного ряда.
    testWidgets('идущий день — одна цифра, и она в ряду текущего этапа', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'));

      final withCount = [
        for (final row in tester.widgetList<WindowStageRow>(find.byType(WindowStageRow)))
          if (find.descendant(of: find.byWidget(row), matching: counts).evaluate().isNotEmpty) row.stage.state,
      ];
      expect(withCount, [WindowStageState.current]);
    });

    // ЛОВИТ: клиента, который рисует цифру всякому ряду, где она пришла, — сервер ошибся одним полем,
    // и у пройденного этапа снова «32 / 32».
    testWidgets('счёт, присланный пройденному ряду, не рисуется', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('in_progress', (j) {
          stagesOf(j).first
            ..['done_count'] = 32
            ..['total'] = 32;
          return j;
        }),
      );

      expect(find.text('32 / 32'), findsNothing);
      expect(counts, findsOneWidget);
    });

    // ЛОВИТ: «0 / 16» у не начатого дня и «16 / 16» у пройденного — цифру там, где текущего ряда нет.
    testWidgets('не начатый и пройденный дни — цифр нет совсем', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'));
      expect(counts, findsNothing);

      await pumpDayWindow(tester, windowRoom('passed'));
      expect(counts, findsNothing);
    });
  });

  // ── 2 · КНОПКА ОДНА И ПО ALLOWED_ACTION ───────────────────────────────────────────────────
  group('кнопка одна и по allowed_action', () {
    // ПРАВИЛО (наряд §3): одно главное действие, прижатое к низу поверх ленты; его слово —
    // `allowed_action` сервера: start → «Начать», continue → «Продолжить», again → «Ещё раз».
    // ЛОВИТ: вторую кнопку на плите (в старом кабинете «Начать» стояла и в плите, и в подвале) и
    // клиента, который выбирает слово по своему чтению статуса дня: статус здесь один и тот же
    // («идёт»), меняется только действие — и слово обязано идти за ним.
    for (final (action, label) in [('start', 'Начать'), ('continue', 'Продолжить'), ('again', 'Ещё раз')]) {
      testWidgets('$action → «$label», одна на экране', (tester) async {
        await pumpDayWindow(tester, windowRoom('in_progress', (j) => j..['window']['allowed_action'] = action));

        expect(find.byType(WindowActionBar), findsOneWidget);
        expect(find.descendant(of: find.byType(WindowActionBar), matching: find.text(label)), findsOneWidget);
        expect(find.descendant(of: find.byType(WindowPlate), matching: find.text(label)), findsNothing);
        expect(tester.getRect(find.byType(WindowActionBar)).bottom, kFrameSize.height);
      });
    }

    // ЛОВИТ: кнопку-заглушку, нарисованную клиентом, когда сервер действия не дал.
    testWidgets('действия нет — кнопки нет', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress', (j) => j..['window']['allowed_action'] = null));

      expect(find.byType(WindowActionBar), findsNothing);
    });
  });

  // ── 3 · БРОВЬ СЛОВАМИ ИЗ SUMMARY ──────────────────────────────────────────────────────────
  group('бровь словами из summary', () {
    // ПРАВИЛО (канва 23-0d, наряд §1): счёт в брови вкладки — `program.<вкладка>.summary` сервера,
    // словами («6 пройдено · 2 вернутся завтра»); клиент ничего не выводит сам.
    // ЛОВИТ: бровь, посчитанную телефоном по маркерам карточек, — она разойдётся с сервером на первом
    // возврате из вчерашнего дня. Здесь summary нарочно не совпадает с маркерами.
    testWidgets('summary расходится с маркерами — бровь говорит числами summary', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('in_progress', (j) {
          tabOf(j, 'words')['summary'] = {'total': 8, 'done': 3, 'returns': 1};
          return j;
        }),
      );

      expect(find.text('СЛОВА · 8 · 3 ПРОЙДЕНО · 1 ВЕРНЁТСЯ ЗАВТРА'), findsOneWidget);
    });

    // ЛОВИТ: «вернутся 0» и «0 пройдено» — части брови с нулём, которых в кадре нет.
    testWidgets('нулевые части не пишутся', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'));

      expect(find.text('СЛОВА · 8'), findsOneWidget);
    });

    // ПРАВИЛО (наряд §1): «нет поля — честная ошибка».
    // ЛОВИТ: окно, которое без summary пересчитало бы бровь само или нарисовало «0».
    testWidgets('нет summary — окно не рисуется, а говорит, что не загрузилось', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('in_progress', (j) {
          tabOf(j, 'words').remove('summary');
          return j;
        }),
      );

      expect(find.byType(PlanLoadFailedCard), findsOneWidget);
      expect(find.byType(WindowPlate), findsNothing);
    });
  });

  // ── 4 · МАРКЕР У РЕПЛИКИ УЧЕНИКА, НЕ СОБЕСЕДНИКА ──────────────────────────────────────────
  // ПРАВИЛО (канва 23-0d, «Вычтено»): в диалоге маркер состояния — у твоих реплик, у тёмных пузырей
  // справа; у реплики собеседника вместо маркера «прослушать» 28.
  // ЛОВИТ: маркер у пузыря собеседника (старая раскладка с легендой) и «прослушать» у своей реплики.
  testWidgets('маркер у реплики ученика, «прослушать» у собеседника', (tester) async {
    final json = planFixture('room_window_passed');
    await pumpDayWindow(tester, windowRoom('passed'));
    await scrollToHeader(tester);
    await openWindowTab(tester, 'Диалог');

    final pairs = (tabOf(json, 'dialogue')['items'] as List).cast<Map<String, dynamic>>();
    expect(pairs, isNotEmpty);
    Finder rowOf(String text) => find.ancestor(of: find.text(text).first, matching: find.byType(Row)).first;
    var learners = 0;
    for (final pair in pairs) {
      if (pair['partner'] case {'text': final String partner}) {
        expect(find.descendant(of: rowOf(partner), matching: find.byType(WindowUnitMarker)), findsNothing, reason: partner);
        expect(find.descendant(of: rowOf(partner), matching: find.byType(PlayCircle)), findsOneWidget, reason: partner);
      }
      if (pair['learner'] case {'text': final String learner}) {
        learners++;
        expect(find.descendant(of: rowOf(learner), matching: find.byType(WindowUnitMarker)), findsOneWidget, reason: learner);
        expect(find.descendant(of: rowOf(learner), matching: find.byType(PlayCircle)), findsNothing, reason: learner);
      }
    }
    expect(learners, greaterThan(0));
  });

  // ── 5 · ПЛИТА СЖИМАЕТСЯ В ШАПКУ ПРИ ПРОКРУТКЕ ─────────────────────────────────────────────
  // ПРАВИЛО (наряд §3, таблица «Тайминг · серия 23»): по прокрутке плита сжимается в строку 56 за
  // 240 мс ease-out; тяга вниз с верха вкладки возвращает плиту.
  // ЛОВИТ: плиту, которая просто уезжает вместе с лентой (шапки нет — не видно, какой это день и
  // сколько осталось), строку, встающую без перехода, и ленту, из которой плиту не вернуть.
  testWidgets('прокрутка — строка 56 встаёт за 240 мс; тяга вниз — плита возвращается', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
    final title = find.descendant(of: find.byType(WindowPlate), matching: find.text('Приём у врача'));
    final plateTop = tester.getRect(title).top;
    expect(compactOpacity(tester), 0);

    await tester.drag(find.byType(NestedScrollView), const Offset(0, -700));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 120));
    expect(compactOpacity(tester), inExclusiveRange(0.0, 1.0), reason: 'на середине перехода');
    await tester.pump(const Duration(milliseconds: 140));
    expect(compactOpacity(tester), 1, reason: 'за 240 мс строка встала');
    await tester.pumpAndSettle();
    expect(tester.getRect(title).bottom, lessThanOrEqualTo(54 + WindowCompactHeader.height), reason: 'плита ушла под шапку');

    await tester.drag(find.byType(NestedScrollView), const Offset(0, 900));
    await tester.pumpAndSettle();
    expect(compactOpacity(tester), 0);
    expect(tester.getRect(title).top, plateTop, reason: 'плита вернулась на место');
  });

  // ПРАВИЛО (наряд §3): лента не останавливается между плитой и шапкой — отпущенная на полпути, она
  // сама доезжает в ту сторону, куда её тянули.
  // ЛОВИТ (живой прогон 14.09): доводку, отложенную до «следующего кадра» — после медленно
  // отпущенной ленты кадров больше нет, и на симуляторе плита торчала из-под шапки полосой. Поэтому
  // жест здесь — как у человека: движения с кадрами между ними, отпускание без кадра после.
  testWidgets('отпущенная на полпути лента сама доезжает до шапки и обратно', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
    final tabs = find.byType(WindowTabBar);
    final travel = tester.getRect(tabs).top - 54 - WindowCompactHeader.height;

    // Палец ведёт ленту, останавливается и отпускает без броска: кадров после этого ничто не заказывает.
    Future<void> dragBy(double dy) async {
      await tester.pumpAndSettle();
      final gesture = await tester.startGesture(tester.getCenter(find.byType(NestedScrollView)));
      for (var i = 1; i <= 10; i++) {
        await gesture.moveBy(Offset(0, dy / 10), timeStamp: Duration(milliseconds: 16 * i));
        await tester.pump(const Duration(milliseconds: 16));
      }
      await tester.pump(const Duration(milliseconds: 300));
      expect(tester.binding.hasScheduledFrame, isFalse, reason: 'лента стоит под пальцем');
      await gesture.up(timeStamp: const Duration(milliseconds: 600));
    }

    await dragBy(-travel * .7);
    expect(tester.binding.hasScheduledFrame, isTrue, reason: 'после отпускания лента движется сама');
    await tester.pumpAndSettle();
    expect(tester.getRect(tabs).top, 54 + WindowCompactHeader.height, reason: 'вкладки встали под шапку');
    expect(compactOpacity(tester), 1);

    await dragBy(travel * .3);
    expect(tester.binding.hasScheduledFrame, isTrue);
    await tester.pumpAndSettle();
    expect(tester.getRect(tabs).top, 54 + travel + WindowCompactHeader.height, reason: 'плита вернулась целиком');
    expect(compactOpacity(tester), 0);
    // Семантика выключена, как на телефоне без VoiceOver: включённая сама заказывает кадр на
    // отпускании пальца и прячет дефект.
  }, semanticsEnabled: false);

  // ── ОКНО ПО СОСТОЯНИЮ СЕРВЕРА ─────────────────────────────────────────────────────────────
  // ПРАВИЛО (наряд §3: окно рисует состояние дня сервера; mobile/CLAUDE.md: экран плана читает
  // сеть на каждом входе): каждый вход в окно читает день заново.
  // ЛОВИТ (живой прогон 14.09): окно, запомнившее ответ первого входа, — «Слова» прошли, плита таба
  // это показала, а окно на втором входе рисовало «Слова · идёт · 0 / 32» и «Продолжить».
  testWidgets('второй вход в окно — свежий день, а не ответ первого входа', (tester) async {
    final server = WindowServer(windowRoom('in_progress'));
    await pumpDayWindowServer(tester, server, launcher: true);
    Future<void> settleRoute() async {
      for (var i = 0; i < 8; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
    }

    await tester.tap(find.text('open'));
    await settleRoute();
    expect(find.text('Продолжить'), findsOneWidget);

    tester.state<NavigatorState>(find.byType(Navigator)).pop();
    await settleRoute();
    server.room = windowRoom('passed');
    await tester.tap(find.text('open'));
    await settleRoute();

    expect(server.reads, 2);
    expect(find.text('Ещё раз'), findsOneWidget);
    expect(find.text('Продолжить'), findsNothing);
  });

  // ── ЗАПЕРТЫЙ ДЕНЬ ─────────────────────────────────────────────────────────────────────────
  // ПРАВИЛО (наряд §3): запертый день в окно не попадает (таб отвечает 409); в окне запертого
  // состояния нет — пришло — честная ошибка.
  // ЛОВИТ: окно, нарисовавшее «запертое» состояние, которого нет ни в одном кадре серии 23.
  testWidgets('запертый день — ошибка загрузки, а не нарисованное состояние', (tester) async {
    await pumpDayWindow(tester, windowRoom('not_started', (j) => j..['window']['day']['status'] = 'locked'));

    expect(find.byType(PlanLoadFailedCard), findsOneWidget);
    expect(find.byType(WindowPlate), findsNothing);
    expect(find.byType(WindowActionBar), findsNothing);
  });
}
