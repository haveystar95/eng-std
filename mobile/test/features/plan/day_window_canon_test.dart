import 'dart:math' show max, min;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/image_loader.dart';
import 'package:eng_std/data/plan/day_providers.dart';
import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/features/plan/day/day_window_screen.dart';
import 'package:eng_std/features/plan/day/window/window_action_bar.dart';
import 'package:eng_std/features/plan/day/window/window_bits.dart';
import 'package:eng_std/features/plan/day/window/window_dialogue.dart';
import 'package:eng_std/features/plan/day/window/window_phrases.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_plate.dart';
import 'package:eng_std/features/plan/day/window/window_scroll.dart';
import 'package:eng_std/features/plan/day/window/window_word_sheet.dart';
import 'package:eng_std/features/plan/day/window/window_words.dart';
import 'package:eng_std/features/plan/plan_tab_parts.dart' show PlanLoadFailedCard;
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../support/day_window_harness.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';

/// КАНОН ОКНА ДНЯ (наряды DAY-UI-2 §5, DAY-UI-3 §6) — правила наряда и канвы серии 23, а не снимок кода.
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
  List<Map<String, dynamic>> itemsOf(Map<String, dynamic> json, String tab) =>
      (tabOf(json, tab)['items'] as List).cast<Map<String, dynamic>>();

  ScrollController outerOf(WidgetTester tester) =>
      tester.state<NestedScrollViewState>(find.byType(NestedScrollView)).outerController;

  // ── 1 · В РЯДАХ ЭТАПОВ ЦИФР НЕТ ───────────────────────────────────────────────────────────
  group('в рядах этапов цифр нет', () {
    // ПРАВИЛО (кадры 23-0a…0c, 30-1 серии 38; наряд FIX-3 §5): ряд этапа говорит СЛОВО состояния и полосу — «N / M»
    // снято отовсюду, и у идущего этапа тоже: сколько сделано, говорит полоса, а числа этапа — его итог 30-6.
    // ЛОВИТ: кабинет со счётом в ряду («Слова · 32 из 32») и счёт, вернувшийся к текущему ряду.
    testWidgets('идущий день — ни одной цифры в рядах', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'));
      expect(counts, findsNothing);
    });

    // ЛОВИТ: клиента, который рисует цифру всякому ряду, где она пришла с сервера.
    testWidgets('счёт сервера в ряду не рисуется', (tester) async {
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
      expect(counts, findsNothing);
    });

    // ЛОВИТ: «0 / 16» у не начатого дня и «16 / 16» у пройденного.
    testWidgets('не начатый и пройденный дни — цифр нет совсем', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'));
      expect(counts, findsNothing);

      await pumpDayWindow(tester, windowRoom('passed'));
      expect(counts, findsNothing);
    });
  });

  // ── 2 · КНОПКА ОДНА И ПО ALLOWED_ACTION ───────────────────────────────────────────────────
  group('кнопка одна и по allowed_action', () {
    // ПРАВИЛО (наряд DAY-UI-2 §3, FIX-3 §5): одно главное действие, прижатое к низу поверх ленты; его слово —
    // `allowed_action` сервера: start → «Начать», continue → «Продолжить». Дневного «Ещё раз» на проводе больше нет —
    // повтор живёт у ряда этапа.
    // ЛОВИТ: вторую кнопку на плите (в старом кабинете «Начать» стояла и в плите, и в подвале) и
    // клиента, который выбирает слово по своему чтению статуса дня: статус здесь один и тот же
    // («идёт»), меняется только действие — и слово обязано идти за ним.
    for (final (action, label) in [('start', 'Начать'), ('continue', 'Продолжить')]) {
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
    // ПРАВИЛО (канва 23-0d, таблица golden 23-0b «счёт в брови, не в пилюле»): счёт вкладки —
    // `program.<вкладка>.summary` сервера, словами («6 пройдено · 2 вернутся завтра»), и только в брови.
    // ЛОВИТ: бровь, посчитанную телефоном по маркерам карточек, — она разойдётся с сервером на первом
    // возврате из вчерашнего дня (здесь summary нарочно не совпадает с маркерами), — и счётчик,
    // вернувшийся в сегмент пилюли, как было во вкладках DAY-UI-2.
    testWidgets('summary расходится с маркерами — бровь говорит числами summary, пилюля — без чисел', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('in_progress', (j) {
          tabOf(j, 'words')['summary'] = {'total': 8, 'done': 3, 'returns': 1};
          // «Вернётся завтра» — СОСТОЯНИЕ единицы, а не `summary.returns`: с наряда FIX-3 §4 `returns` значит, сколько
          // ВЕРНУЛОСЬ из прошлых дней, и бровь его не печатает. Здесь вернётся ровно одно слово.
          for (final item in itemsOf(j, 'words')) {
            item['state'] = 'done';
          }
          itemsOf(j, 'words').first['state'] = 'returns_tomorrow';
          return j;
        }),
      );

      expect(find.text(nb('СЛОВА · 8 · 3 ПРОЙДЕНО · 1 ВЕРНЁТСЯ ЗАВТРА')), findsOneWidget);
      expect(find.descendant(of: find.byType(WindowPill), matching: find.textContaining(RegExp(r'\d'))), findsNothing);
    });

    // ЛОВИТ: «вернутся 0» и «0 пройдено» — части брови с нулём, которых в кадре нет.
    testWidgets('нулевые части не пишутся', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'));

      expect(find.text(nb('СЛОВА · 8')), findsOneWidget);
    });

    // ПРАВИЛО (наряд DAY-UI-2 §1): «нет поля — честная ошибка».
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

  // ── 4 · ЦЕЛИ — ПРЕДЛОЖЕНИЕ, НЕ СПИСОК ─────────────────────────────────────────────────────
  group('цели — предложение, не список', () {
    const sentence = 'описать, где болит, сказать, как долго болит, ответить на вопросы врача и понять назначения врача';

    // ПРАВИЛО (наряд DAY-UI-3 §1, канва 23-0a «Вычтено»): цели — ОДНО предложение «Научишься …» 15 без
    // маркеров; части — цели сервера через запятую, последняя через «и».
    // ЛОВИТ: список целей с кружками и лейблом «НАУЧИШЬСЯ» (плита DAY-UI-2) — четыре строки вместо
    // одной, и плита выше потолка в 60 % экрана.
    testWidgets('не пройденный день — «Научишься …» одной строкой, ни одной цели отдельно', (tester) async {
      final json = planFixture('room_window_not_started');
      await pumpDayWindow(tester, windowRoom('not_started'));
      final plate = find.byType(WindowPlate);

      expect(find.descendant(of: plate, matching: find.text('Научишься $sentence')), findsOneWidget);
      for (final goal in (windowOf(json)['day']['goals'] as List).cast<Map<String, dynamic>>()) {
        expect(find.descendant(of: plate, matching: find.text(goal['text'] as String)), findsNothing, reason: '${goal['text']}');
      }
      expect(find.descendant(of: plate, matching: find.textContaining('НАУЧИШЬСЯ')), findsNothing);
    });

    // ПРАВИЛО (канва 23-0c): у пройденного дня — «Научился:» шалфеем с галкой 14 и те же цели тем же
    // предложением.
    // ЛОВИТ: галки у каждой цели (снова список) и «Научишься» у дня, который уже пройден.
    testWidgets('пройденный день — «Научился: …» с одной галкой в том же предложении', (tester) async {
      await pumpDayWindow(tester, windowRoom('passed'));
      final plate = find.byType(WindowPlate);
      final line = find.descendant(
        of: plate,
        matching: find.byWidgetPredicate((w) => w is RichText && w.text.toPlainText().contains('Научился: $sentence')),
      );

      expect(line, findsOneWidget);
      expect(find.descendant(of: line, matching: find.byType(WindowCheck)), findsOneWidget);
      expect(find.descendant(of: plate, matching: find.textContaining('Научишься')), findsNothing);
    });
  });

  // ── 5 · ПЛИТА — ФУНКЦИЯ ПРОКРУТКИ ─────────────────────────────────────────────────────────
  group('плита — функция прокрутки', () {
    // ПРАВИЛО (таблица «Тайминг · серия 23»): плита → компактная шапка 56 — «функция прокрутки 0…160 px,
    // linear по позиции, не анимация»; ничего не анимируется двумя правилами сразу.
    // ЛОВИТ: сжатие, запускаемое порогом и доигрываемое контроллером 240 мс (DAY-UI-2), — палец стоит,
    // а шапка ещё проявляется, и при медленной тяге строка 56 уже встала, когда плита видна наполовину.
    test('проявление шапки — линейно по позиции на последних 160 px пути', () {
      const range = 400.0;
      expect(WindowHeaderMath.progress(0, range), 0);
      expect(WindowHeaderMath.progress(range - AppMotion.windowPlateToHeaderSpan, range), 0);
      expect(WindowHeaderMath.progress(range - AppMotion.windowPlateToHeaderSpan / 2, range), .5);
      expect(WindowHeaderMath.progress(range, range), 1);
      expect(WindowHeaderMath.progress(range + 50, range), 1);
      expect(WindowHeaderMath.progress(60, 80), .75, reason: 'путь короче 160 — весь путь и есть переход');
    });

    testWidgets('палец ведёт ленту — шапка и плита стоят там, где позиция, и не движутся, пока палец стоит', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
      final range = pillTravel(tester);
      final outer = outerOf(tester);
      final plate = find.byType(WindowPlate);
      expect(range, greaterThan(AppMotion.windowPlateToHeaderSpan));

      final gesture = await tester.startGesture(tester.getCenter(find.byType(NestedScrollView)));
      await gesture.moveBy(const Offset(0, -30));
      await tester.pump();
      final middles = <double>[];
      // Точки пути: до перехода, середина перехода, три четверти — и в каждой палец стоит полсекунды.
      for (final target in [range - AppMotion.windowPlateToHeaderSpan - 40, range - 80, range - 40]) {
        await gesture.moveBy(Offset(0, -(target - outer.offset)));
        await tester.pump();
        final offset = outer.offset;
        final expected = WindowHeaderMath.progress(offset, range);
        expect(compactOpacity(tester), moreOrLessEquals(expected, epsilon: 1e-9), reason: 'на позиции $offset');
        expect(tester.getRect(plate).top, moreOrLessEquals(-offset, epsilon: .01), reason: 'плита едет вместе с лентой');

        await tester.pump(const Duration(milliseconds: 500));
        expect(outer.offset, offset);
        expect(compactOpacity(tester), moreOrLessEquals(expected, epsilon: 1e-9), reason: 'палец стоит — шапка стоит');
        if (expected > 0 && expected < 1) middles.add(expected);
      }
      expect(middles, isNotEmpty, reason: 'тест прошёл через середину перехода');
      await gesture.up();
      await tester.pumpAndSettle();
    });

    // ПРАВИЛО (таблица «Тайминг · серия 23»): «примагничивание в точке отпускания — 260 мс,
    // ease-out-cubic»; шапка во время доводки — та же функция позиции.
    // ЛОВИТ: доводку второй анимацией поверх сжатия (две кривые одной вещи спорили в DAY-UI-2), доводку
    // линейную или другой длины и шапку, которая за позицией не успевает.
    testWidgets('отпущенная на полпути — один animateTo 260 мс ease-out-cubic, шапка следует за позицией', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
      final range = pillTravel(tester);
      final outer = outerOf(tester);

      final gesture = await tester.startGesture(tester.getCenter(find.byType(NestedScrollView)));
      for (var i = 1; i <= 10; i++) {
        await gesture.moveBy(Offset(0, -range * .07), timeStamp: Duration(milliseconds: 16 * i));
        await tester.pump(const Duration(milliseconds: 16));
      }
      await tester.pump(const Duration(milliseconds: 300));
      await gesture.up(timeStamp: const Duration(milliseconds: 600));
      await tester.pump();
      await tester.pump();
      final from = outer.offset;
      expect(from, lessThan(range));

      var elapsed = Duration.zero;
      double? atHalf;
      while (elapsed < AppMotion.windowSnap) {
        expect(compactOpacity(tester), moreOrLessEquals(WindowHeaderMath.progress(outer.offset, range), epsilon: 1e-9));
        await tester.pump(const Duration(milliseconds: 10));
        elapsed += const Duration(milliseconds: 10);
        if (elapsed == AppMotion.windowSnap ~/ 2) atHalf = (outer.offset - from) / (range - from);
      }
      await tester.pump(const Duration(milliseconds: 16));

      expect(outer.offset, range, reason: 'за 260 мс лента доехала до шапки');
      expect(compactOpacity(tester), 1);
      // ease-out-cubic на половине времени прошёл ~87 % пути; линейная доводка — 50 %.
      expect(atHalf, greaterThan(.75));
      expect(atHalf, lessThan(1));
    });

    // ПРАВИЛО: лента не останавливается между плитой и шапкой — отпущенная на полпути, она сама
    // доезжает в ту сторону, куда её тянули.
    // ЛОВИТ (живой прогон DAY-UI-2, 14.09): доводку, отложенную до «следующего кадра» — после медленно
    // отпущенной ленты кадров больше нет, и на симуляторе плита торчала из-под шапки полосой. Поэтому
    // жест здесь — как у человека: движения с кадрами между ними, отпускание без кадра после.
    testWidgets('отпущенная на полпути лента сама доезжает до шапки и обратно', (tester) async {
      await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
      final travel = pillTravel(tester);
      final pill = find.byType(WindowPill);

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
      expect(tester.getRect(pill).top, pillPinnedTop, reason: 'пилюля встала под шапку');
      expect(compactOpacity(tester), 1);

      await dragBy(travel * .3);
      expect(tester.binding.hasScheduledFrame, isTrue);
      await tester.pumpAndSettle();
      expect(tester.getRect(pill).top, pillPinnedTop + travel, reason: 'плита вернулась целиком');
      expect(compactOpacity(tester), 0);
      // Семантика выключена, как на телефоне без VoiceOver: включённая сама заказывает кадр на
      // отпускании пальца и прячет дефект.
    }, semanticsEnabled: false);
  });

  // ── 6 · ПИЛЮЛЯ НА ШВЕ И ПРИЛИПАЕТ ─────────────────────────────────────────────────────────
  // ПРАВИЛО (наряд DAY-UI-3 §1, канва 23-0a…0d): плита во всю ширину от самого верха; пилюля 48 стоит
  // на шве — верхняя половина на плите, нижняя на бумаге (перекрытие 24); едет вместе с плитой и
  // прилипает под компактной шапкой, тень остаётся; дальше лента уходит под неё.
  // ЛОВИТ: карточку плиты с отступами (DAY-UI-1), вкладки строкой под плитой с зазором (DAY-UI-2),
  // пилюлю, которая уезжает с лентой (не видно, на какой ты вкладке), и пилюлю без тени под шапкой.
  testWidgets('пилюля — на шве плиты во всю ширину; прокрученная — под шапкой, и лента уходит под неё', (tester) async {
    await pumpDayWindow(tester, windowRoom('not_started'));
    final plate = tester.getRect(find.byType(WindowPlate));
    Rect pill() => tester.getRect(find.byType(WindowPill));
    List<BoxShadow>? shadow() => (tester
                .widget<Container>(find.descendant(of: find.byType(WindowPill), matching: find.byType(Container)).first)
                .decoration as BoxDecoration?)
            ?.boxShadow;

    expect(plate.topLeft, Offset.zero, reason: 'плита от самого верха');
    expect(plate.width, kFrameSize.width, reason: 'плита во всю ширину');
    expect(pill().height, WindowPill.height);
    expect(pill().center.dy, plate.bottom, reason: 'половина пилюли на плите, половина на бумаге');
    expect(pill().left, WindowPill.inset);
    expect(pill().right, kFrameSize.width - WindowPill.inset);
    expect(shadow(), [const BoxShadow(color: AppColors.windowPillShadow, offset: Offset(0, 2), blurRadius: 8)]);

    await scrollToHeader(tester);
    expect(pill().top, pillPinnedTop, reason: 'прилипла под строкой 56');
    expect(compactOpacity(tester), 1);

    final firstWord = find.byType(WindowWordCard).first;
    final wordTop = tester.getRect(firstWord).top;
    await tester.drag(find.byType(NestedScrollView), const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(pill().top, pillPinnedTop, reason: 'содержимое едет дальше, пилюля стоит');
    expect(tester.getRect(firstWord).top, lessThan(wordTop - 250));
    expect(shadow(), isNotEmpty, reason: 'тень под шапкой остаётся');
  });

  // ── 7 · ВКЛАДКА — ЧИП СКОЛЬЗИТ, СОДЕРЖИМОЕ СДВИГАЕТСЯ ─────────────────────────────────────
  // ПРАВИЛО (таблица «Тайминг · серия 23»): смена вкладки — чип скользит к сегменту 220 мс
  // ease-out-cubic, содержимое сдвигается по горизонтали той же длительностью; ничего двумя правилами.
  // ЛОВИТ: мгновенный прыжок чипа, чип и ленту разными анимациями (разойдутся на кадр) и `om-cab-in`
  // (подъём с прозрачностью) поверх сдвига — снятый DAY-UI-3.
  testWidgets('тап по «Фразы» — чип и страницы едут одним движением 220 мс', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'), reduceMotion: false);
    final chip = find.byKey(const ValueKey('window-pill-chip'));
    final from = tester.getRect(chip).left;
    final pages = find.byType(TabBarView);

    await tester.tap(find.descendant(of: find.byType(WindowPill), matching: find.text('Фразы')));
    await tester.pump();
    await tester.pump(AppMotion.windowTabChip ~/ 2);
    final halfway = tester.getRect(chip).left;
    final phrasesLeft = tester.getRect(find.byType(WindowPhrases)).left;
    expect(halfway, greaterThan(from), reason: 'на середине чип в пути');
    expect(phrasesLeft, greaterThan(tester.getRect(pages).left), reason: 'страница фраз ещё въезжает');
    expect(find.descendant(of: pages, matching: find.byType(FadeTransition)), findsNothing, reason: 'сдвиг, без проявления');

    await tester.pump(AppMotion.windowTabChip ~/ 2);
    await tester.pump(const Duration(milliseconds: 16));
    final to = tester.getRect(chip).left;
    expect(to, greaterThan(halfway));
    expect(tester.getRect(find.byType(WindowPhrases)).left, tester.getRect(pages).left + 24);
    // Чип и страницы — один контроллер: на середине пройдены одинаковые доли пути.
    expect((halfway - from) / (to - from), greaterThan(.75), reason: 'ease-out-cubic, а не линейно');
  });

  // ── 8 · У КАЖДОЙ СТРОКИ ГОЛОС ─────────────────────────────────────────────────────────────
  group('у каждой строки «прослушать»', () {
    // ПРАВИЛО (наряд DAY-UI-3 §1, §3; канва 23-0d): у слова, у фразы и у ОБЕИХ реплик каждого обмена —
    // «прослушать» 28; маркер состояния в диалоге — у реплики ученика; тап говорит ИМЕННО эту строку.
    // ЛОВИТ: правило «фразы голосом телефона» и «прослушать» только у собеседника (DAY-UI-2), маркер у
    // пузыря собеседника и кнопку, которая говорит соседнюю строку.
    testWidgets('диалог — «прослушать» у обеих реплик, маркер у реплики ученика', (tester) async {
      final json = planFixture('room_window_passed');
      final server = WindowServer(windowRoom('passed'));
      await pumpDayWindowServer(tester, server);
      await scrollToHeader(tester);
      await openWindowTab(tester, 'Диалог');

      final pairs = itemsOf(json, 'dialogue');
      Finder rowOf(String text) => find.ancestor(
        of: find.descendant(of: find.byType(WindowDialogue), matching: find.text(text)).first,
        matching: find.byType(Row),
      ).first;
      var lines = 0;
      for (final pair in pairs) {
        if (pair['partner'] case {'text': final String partner}) {
          lines++;
          expect(find.descendant(of: rowOf(partner), matching: find.byType(WindowUnitMarker)), findsNothing, reason: partner);
          expect(find.descendant(of: rowOf(partner), matching: find.byType(WindowListenButton)), findsOneWidget, reason: partner);
        }
        if (pair['learner'] case {'text': final String learner}) {
          lines++;
          expect(find.descendant(of: rowOf(learner), matching: find.byType(WindowUnitMarker)), findsOneWidget, reason: learner);
          expect(find.descendant(of: rowOf(learner), matching: find.byType(WindowListenButton)), findsOneWidget, reason: learner);
        }
      }
      expect(lines, 16);
      expect(find.descendant(of: find.byType(WindowDialogue), matching: find.byType(WindowListenButton)), findsNWidgets(lines));

      final learner = (pairs.last['learner'] as Map<String, dynamic>)['text'] as String;
      final button = find.descendant(of: rowOf(learner), matching: find.byType(WindowListenButton));
      await revealInWindow(tester, button);
      await tester.tap(button);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));
      expect(server.spoken, [learner]);
    });

    testWidgets('слова и фразы — у каждой «прослушать», и тап говорит свою строку', (tester) async {
      final json = planFixture('room_window_in_progress');
      final server = WindowServer(windowRoom('in_progress'));
      await pumpDayWindowServer(tester, server);

      final words = itemsOf(json, 'words');
      expect(find.descendant(of: find.byType(WindowWords), matching: find.byType(WindowListenButton)), findsNWidgets(words.length));
      final term = words[1]['term'] as String;
      final wordButton = find.descendant(
        of: find.ancestor(of: find.text(term), matching: find.byType(WindowWordCard)),
        matching: find.byType(WindowListenButton),
      );
      await revealInWindow(tester, wordButton);
      await tester.tap(wordButton);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));
      expect(server.spoken, [term]);
      expect(find.byType(WindowWordSheet), findsNothing, reason: '«прослушать» не открывает шит');

      await scrollToHeader(tester);
      await openWindowTab(tester, 'Фразы');
      final phrases = itemsOf(json, 'phrases');
      expect(find.descendant(of: find.byType(WindowPhrases), matching: find.byType(WindowListenButton)), findsNWidgets(phrases.length));
      final phrase = phrases.last['text'] as String;
      final phraseButton = find.descendant(
        of: find.ancestor(of: find.text(phrase), matching: find.byType(Row)).at(1),
        matching: find.byType(WindowListenButton),
      );
      await revealInWindow(tester, phraseButton);
      await tester.tap(phraseButton);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));
      expect(server.spoken, [term, phrase]);
      // Чтение кириллицей — второй строкой под фразой (23-0d «у фраз чтение второй строкой»).
      final reading = phrases.last['pronunciation'] as String;
      expect(tester.getRect(find.text(reading)).top, greaterThan(tester.getRect(find.text(phrase)).bottom - 1));
      expect(tester.getRect(find.text(phrases.last['translation'] as String)).top, greaterThan(tester.getRect(find.text(reading)).bottom - 1));
    });
  });

  // ── 9 · КАРТОЧКА СЛОВА — ШИТ 23-0e ────────────────────────────────────────────────────────
  group('карточка слова — шит 23-0e', () {
    // ПРАВИЛО (канва 23-0e, таблица golden): тап по слову — шит снизу на 86 %, фон под ним затемнён до
    // 40 % (окно не скрыто), одна текстовая кнопка «Закрыть», слово 30 с «прослушать» 44, «В разговоре» —
    // реплика дня со словом латунью и своим «прослушать»; состояние словами, цифр нет.
    // ЛОВИТ: вторую кнопку («Учить», «Тренировать») на шите, шит на весь экран, скрытое окно, слово в
    // реплике, найденное телефоном поиском подстроки (сервер назвал место), и «0 / 1» вместо слов.
    testWidgets('тап по слову — шит 86 %, фон 40 %, одна «Закрыть», слово латунью по месту сервера', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'));
      await openWordSheet(tester, 'lower back');
      final sheet = find.byType(WindowWordSheet);

      expect(tester.getRect(sheet).height, moreOrLessEquals(kFrameSize.height * .86, epsilon: .01));
      expect(tester.getRect(sheet).bottom, kFrameSize.height);
      expect(tester.widget<ModalBarrier>(find.byType(ModalBarrier).last).color, AppColors.windowSheetScrim);
      expect(AppColors.windowSheetScrim.a, moreOrLessEquals(.4, epsilon: .005));
      expect(find.byType(WindowPlate), findsOneWidget, reason: 'окно под шитом не скрыто');

      expect(find.descendant(of: sheet, matching: find.text('Закрыть')), findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.byType(ButtonStyleButton)), findsNothing);
      expect(find.descendant(of: sheet, matching: find.byType(WindowActionBar)), findsNothing);
      final listens = find.descendant(of: sheet, matching: find.byType(WindowListenButton));
      expect(listens, findsNWidgets(2));
      expect(tester.getSize(listens.first).width, 44);
      expect(tester.getSize(listens.last).width, 28);

      final usage = find.descendant(
        of: sheet,
        matching: find.byWidgetPredicate((w) => w is RichText && w.text.toPlainText() == 'My lower back hurts.'),
      );
      expect(usage, findsOneWidget);
      final brass = <String>[];
      tester.widget<RichText>(usage).text.visitChildren((span) {
        if (span is TextSpan && span.text != null && span.style?.color == AppColors.brassInk) brass.add(span.text!);
        return true;
      });
      expect(brass, ['lower back']);
      expect(find.descendant(of: sheet, matching: find.text('не начато')), findsOneWidget);
      expect(find.descendant(of: sheet, matching: counts), findsNothing);
    });

    // ПРАВИЛО (канва 23-0e «пройдено · вернётся в день 3»): день возврата — `returns_day` сервера.
    // ЛОВИТ: «день + 1», посчитанный телефоном: сервер переносит возврат на день, который есть в плане
    // (выходные, событие), и здесь он нарочно не следующий.
    testWidgets('вернувшееся слово — «пройдено · вернётся в день N» из returns_day сервера', (tester) async {
      await pumpDayWindow(
        tester,
        windowRoom('passed', (j) {
          itemsOf(j, 'words').firstWhere((w) => w['term'] == 'worse')['returns_day'] = 7;
          return j;
        }),
      );
      await revealInWindow(tester, find.text('worse'));
      await openWordSheet(tester, 'worse');

      expect(find.descendant(of: find.byType(WindowWordSheet), matching: find.text('пройдено · вернётся в день 7')), findsOneWidget);
    });

    // ПРАВИЛО (таблица «Тайминг · серия 23»): шит поднимается 320 мс ease-out-cubic, фон затемняется
    // вместе с ним; закрытие — «Закрыть» или тяга вниз — 260 мс.
    // ЛОВИТ: шит, выпрыгивающий без подъёма, чужие длительности системного шита (250/200) и шит,
    // который тягой не закрывается.
    testWidgets('подъём 320 мс вместе с фоном; «Закрыть» и тяга вниз закрывают за 260 мс', (tester) async {
      await pumpDayWindow(tester, windowRoom('not_started'), reduceMotion: false);
      final card = find.ancestor(of: find.text('lower back').first, matching: find.byType(WindowWordCard));
      await tester.tap(card);
      await tester.pump();
      final sheet = find.byType(WindowWordSheet);
      final route = ModalRoute.of(tester.element(sheet))!;
      expect(route.transitionDuration, AppMotion.windowSheetRise);
      expect(route.reverseTransitionDuration, AppMotion.windowSheetClose);

      final restTop = kFrameSize.height * (1 - WindowWordSheet.share);
      await tester.pump(AppMotion.windowSheetRise ~/ 2);
      final risen = (kFrameSize.height - tester.getRect(sheet).top) / (kFrameSize.height - restTop);
      expect(risen, greaterThan(.75), reason: 'ease-out-cubic: к половине времени пройдено ~87 %');
      expect(risen, lessThan(1));
      final scrim = tester.widget<ModalBarrier>(find.byType(ModalBarrier).last).color!;
      expect(scrim.a, inExclusiveRange(0.0, AppColors.windowSheetScrim.a), reason: 'фон темнеет вместе с подъёмом');
      await tester.pump(AppMotion.windowSheetRise ~/ 2);
      await tester.pump(const Duration(milliseconds: 16));
      expect(tester.getRect(sheet).top, moreOrLessEquals(restTop, epsilon: .01));

      await tester.tap(find.text('Закрыть'));
      await tester.pump();
      await tester.pump(AppMotion.windowSheetClose - const Duration(milliseconds: 40));
      expect(sheet, findsOneWidget, reason: 'ещё закрывается');
      await tester.pump(const Duration(milliseconds: 60));
      expect(sheet, findsNothing);

      await tester.tap(card);
      await tester.pumpAndSettle();
      await tester.drag(find.text('Закрыть'), const Offset(0, 500));
      await tester.pumpAndSettle();
      expect(sheet, findsNothing, reason: 'тяга вниз закрывает');
    });

    // ПРАВИЛО: место слова в реплике — в символах (кодовых точках), как его считает сервер.
    // ЛОВИТ: подсветку по кодовым единицам UTF-16 — эмодзи или символ вне BMP до слова сдвигает латунь на
    // полбуквы; и падение шита на месте за концом строки.
    test('место слова — в символах, место вне строки — реплика без подсветки', () {
      String? brassOf(TextSpan span) {
        String? found;
        span.visitChildren((s) {
          if (s is TextSpan && s.style?.color == AppColors.brassInk) found = s.text;
          return true;
        });
        return found;
      }

      const text = 'Ой 😀 my lower back hurts';
      expect(brassOf(WindowUsageLine.span(const WindowUsage(text: text, translation: '', offset: 8, length: 10))), 'lower back');
      expect(brassOf(WindowUsageLine.span(const WindowUsage(text: text, translation: '', offset: 20, length: 10))), isNull);
      expect(WindowUsageLine.span(const WindowUsage(text: text, translation: '', offset: 20, length: 10)).toPlainText(), text);
    });
  });

  // ── 10 · ВЕСЬ ДЕНЬ КАЧАЕТСЯ ПРИ ОТКРЫТИИ ──────────────────────────────────────────────────
  // ПРАВИЛО (наряд DAY-UI-3 §5): при открытии окна дня качаются все картинки и всё аудио дня сразу — к
  // «Начать» и к «прослушать» файлы уже на диске.
  // ЛОВИТ: докачку «по тапу» (первое «прослушать» — системным голосом, пока файл едет) и докачку только
  // реплик собеседника (DAY-UI-2): голос ученика, фраз и слов в сессии звучал бы телефоном. Фото сетка
  // слов и так просит все при открытии, поэтому мутацию ловит голосовая половина.
  testWidgets('окно при открытии просит весь голос дня и все его фото', (tester) async {
    String audio(String ref) => 'https://api.test/api/v1/plans/audio/$ref';
    final room = windowRoom('in_progress', (j) {
      for (final w in itemsOf(j, 'words')) {
        w['audio_url'] = audio('w-${w['ref']}');
        (w['usage'] as Map<String, dynamic>)['audio_url'] = audio('u-${w['ref']}');
      }
      for (final p in itemsOf(j, 'phrases')) {
        p['audio_url'] = audio('p-${p['ref']}');
      }
      for (final pair in itemsOf(j, 'dialogue')) {
        (pair['partner'] as Map<String, dynamic>)['audio_url'] = audio('a-${pair['step']}');
        (pair['learner'] as Map<String, dynamic>)['audio_url'] = audio('b-${pair['step']}');
      }
      return j;
    });
    final photos = <String>{};
    final loader = ImageLoader.instance;
    final network = loader.fetcher;
    loader.fetcher = (uri, headers) {
      photos.add('$uri');
      return network(uri, headers);
    };
    addTearDown(() => loader.fetcher = network);
    final server = WindowServer(room);
    await pumpDayWindowServer(tester, server);

    final window = DayWindow.fromJson(room.windowJson);
    final program = window.program;
    final expected = {
      for (final w in program.words) ...{w.term: w.audioUrl, w.usage!.text: w.usage!.audioUrl},
      for (final p in program.phrases) p.text: p.audioUrl,
      for (final pair in program.dialogue) ...{pair.partner!.text: pair.partner!.audioUrl, pair.learner!.text: pair.learner!.audioUrl},
    };
    final asked = {for (final line in server.lines.asked) line.text: line.url};
    for (final line in expected.entries) {
      expect(asked.keys, contains(line.key), reason: 'голос строки «${line.key}» не попросили');
    }
    expect({for (final line in server.lines.asked) line.url}, containsAll(expected.values.whereType<String>().toSet()));
    expect(photos, containsAll({window.day.image!.url, for (final w in program.words) w.image!.url}));
  });

  // ── 11 · ГАЛКА ЭТАПА ПРИ ВОЗВРАТЕ ─────────────────────────────────────────────────────────
  // ПРАВИЛО (таблица «Тайминг · серия 23», `om-check-pop`): этап, закрытый в сессии, получает галку при
  // возврате в окно через 300 мс — масштаб 0 → 1 за 180 мс; уже пройденные этапы стоят с галкой сразу.
  // ЛОВИТ: галку, которая стоит сразу (возврат ничего не показал), и галку, «выпрыгивающую» у всех
  // пройденных этапов на каждом входе.
  testWidgets('возврат из сессии — галка закрытого этапа встаёт через 300 мс, остальные на месте', (tester) async {
    final server = WindowServer(windowRoom('not_started'));
    await pumpDayWindowServer(tester, server, reduceMotion: false);
    expect(find.byType(CheckPop), findsNothing);

    server.room = windowRoom('in_progress');
    ProviderScope.containerOf(tester.element(find.byType(DayWindowScreen))).invalidate(dayRoomProvider);
    await tester.pump();
    await tester.pump();

    final pops = find.descendant(of: find.byType(WindowPlate), matching: find.byType(CheckPop));
    expect(pops, findsNWidgets(3), reason: 'слова, фразы и диалог закрыты с прошлого ответа');
    double scale() => tester
        .widget<Transform>(find.descendant(of: pops.first, matching: find.byType(Transform)).first)
        .transform
        .storage[0];
    expect(scale(), 0);
    await tester.pump(AppMotion.windowStageCheckDelay - const Duration(milliseconds: 20));
    expect(scale(), 0, reason: 'до 300 мс галки нет');
    await tester.pump(AppMotion.windowStageCheck + const Duration(milliseconds: 40));
    expect(scale(), 1);
  });

  // ── 12 · EXCHANGE ORDER BY KIND ───────────────────────────────────────────────────────────
  // RULE (SESSION-1b′, item 9): inside an exchange the bubbles follow its kind — `answer`: the partner, then
  // the learner; `ask` and `rescue`: the learner first, then the partner. An exchange without a kind (the
  // scene's lesson was not at hand) keeps the partner first.
  // CATCHES: a feed that always opens with the partner, so the learner's question reads as a reply to the
  // line that answers it.
  testWidgets('dialogue feed — answer opens with the partner, ask and rescue with the learner', (tester) async {
    const kinds = ['answer', 'ask', 'rescue'];
    final room = windowRoom('passed', (j) {
      for (final (i, pair) in itemsOf(j, 'dialogue').take(kinds.length).indexed) {
        pair['kind'] = kinds[i];
      }
      return j;
    });
    await pumpDayWindowServer(tester, WindowServer(room));
    await scrollToHeader(tester);
    await openWindowTab(tester, 'Диалог');

    double top(String text) {
      final line = find.descendant(of: find.byType(WindowDialogue), matching: find.text(text));
      expect(line, findsOneWidget, reason: 'the fixture line «$text» is unique in the feed');
      return tester.getTopLeft(line).dy;
    }

    final pairs = DayWindow.fromJson(room.windowJson).program.dialogue;
    expect([for (final pair in pairs.take(4)) pair.kind],
        [WindowExchangeKind.answer, WindowExchangeKind.ask, WindowExchangeKind.rescue, null]);
    final bottoms = <double>[];
    for (final pair in pairs.take(4)) {
      final partner = top(pair.partner!.text);
      final learner = top(pair.learner!.text);
      final learnerFirst = pair.kind == WindowExchangeKind.ask || pair.kind == WindowExchangeKind.rescue;
      expect(learnerFirst ? learner < partner : partner < learner, isTrue,
          reason: 'step ${pair.step} (${pair.kind?.name ?? 'no kind'}): partner at $partner, learner at $learner');
      if (bottoms.isNotEmpty) expect(min(partner, learner), greaterThan(bottoms.last), reason: 'exchanges keep their order');
      bottoms.add(max(partner, learner));
    }
  });

  // ── ОКНО ПО СОСТОЯНИЮ СЕРВЕРА ─────────────────────────────────────────────────────────────
  // ПРАВИЛО (наряд DAY-UI-2 §3: окно рисует состояние дня сервера; mobile/CLAUDE.md: экран плана читает
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
    expect(find.text('Итог дня'), findsOneWidget, reason: 'у пройденного дня внизу — итог, а не повтор дня');
    expect(find.text('Продолжить'), findsNothing);
  });

  // ── ЗАПЕРТЫЙ ДЕНЬ ─────────────────────────────────────────────────────────────────────────
  // ПРАВИЛО (наряд DAY-UI-2 §3; с ACC-1 — наряд CLIENT-START §6, кадр 23-0a «по подписке»): день, запертый ДАТОЙ, в
  // окно не попадает (таб отвечает листом «откроется …»); `locked` в окне — только день по подписке: бесплатный видит
  // его целиком, а внизу вместо «Начать» — «Подписка» и «Откроется с подпиской».
  // ЛОВИТ: окно дня по подписке с «Начать», которое упрётся в 409, и ошибку загрузки вместо кадра 23-0a.
  testWidgets('запертый день в окне — «по подписке»: кадр целиком, «Подписка» вместо «Начать»', (tester) async {
    await pumpDayWindow(tester, windowRoom('not_started', (j) => j..['window']['day']['status'] = 'locked'));

    expect(find.byType(PlanLoadFailedCard), findsNothing);
    expect(find.byType(WindowPlate), findsOneWidget);
    expect(find.byType(WindowActionBar), findsOneWidget);
    expect(find.text('Подписка'), findsOneWidget);
    expect(find.text('Откроется с подпиской'), findsOneWidget);
    expect(find.text('Начать'), findsNothing);
  });
}
