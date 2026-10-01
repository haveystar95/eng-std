import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_window.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/day/window/window_action_bar.dart';
import 'package:eng_std/features/plan/day/window/window_bits.dart';
import 'package:eng_std/features/plan/day/window/window_pill.dart';
import 'package:eng_std/features/plan/day/window/window_plate.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';
import 'package:eng_std/features/plan/day/window/window_word_sheet.dart';
import 'package:eng_std/features/plan/day/window/window_words.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/day_window_harness.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';
import '../../support/server_fixtures.dart';

/// ОКНО ДНЯ — кадры 23-0a…0e канвы `plan-canvas.dc.html`: ответ сервера → экран → снимок (DAY-UI-3).
///
/// Снимок сверяет композицию с кадром. То, что таблица канвы «Изменения · серия 23 · что проверяет
/// golden» называет словами, проверяется здесь же утверждениями, а не глазом по PNG:
/// - 23-0a — тёмное от y = 0 до низа плиты без светлой полосы; плита ≤ 60 %; цифр «N / M» нет;
/// - 23-0b — «6 / 16» только на «Слушаю и отвечаю»; счёт в брови, не в пилюле;
/// - 23-0c — возвраты словами в брови; латунная точка на карточке; плита ≤ 60 %;
/// - 23-0d — пилюля прилипает под шапкой с тенью; ellipsis 0; второе состояние «прокручено»;
/// - 23-0e — одна кнопка «Закрыть»; фон 40 %; два состояния словами.
///
/// «Плита ≤ 60 %» меряется без статус-бара: он внутри тёмного по кадру («статус-бар и стрелка внутри
/// тёмного»), и сам кадр 23-0a — плита 536 из 844 с ним, 484 (57 %) без него.
///
/// Фикстуры — один план, пройденный по живому API (`support/day_window_harness.dart`). Снимки —
/// `test/goldens/plan/23-0*.png`; обновлять:
/// `flutter test --update-goldens test/features/plan/day_window_golden_test.dart`.
void main() {
  setUpAll(setUpPlanGoldens);

  Future<void> shoot(String name) =>
      expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/$name.png'));

  final counts = find.textContaining(RegExp(r'\d+ / \d+'));

  /// «Кнопка одна и прижата к низу» — с тем словом, которое прислал сервер.
  void expectOneButton(WidgetTester tester, String label) {
    expect(find.byType(WindowActionBar), findsOneWidget);
    expect(find.descendant(of: find.byType(WindowActionBar), matching: find.text(label)), findsOneWidget);
    expect(tester.getRect(find.byType(WindowActionBar)).bottom, kFrameSize.height);
  }

  /// «Ellipsis 0»: ни одна строка на экране не упёрлась в свой предел.
  void expectNothingCut(WidgetTester tester) {
    final cut = [
      for (final p in tester.allRenderObjects.whereType<RenderParagraph>())
        if (p.didExceedMaxLines) p.text.toPlainText(),
    ];
    expect(cut, isEmpty, reason: 'обрезанные строки: $cut');
  }

  /// «Плита ≤ 60 %» — без статус-бара (см. шапку файла).
  void expectPlateCeiling(WidgetTester tester) {
    final plate = tester.getRect(find.byType(WindowPlate));
    expect(plate.bottom - kWindowInsets.top, lessThanOrEqualTo(kFrameSize.height * .6), reason: 'плита ${plate.height}');
  }

  /// «Тёмное от y = 0 до низа плиты без светлой полосы»: столбцы у левого и правого края (вне полей 24, где
  /// нет текста), от верхней строки кадра до начала скругления над пилюлей, — ни одного светлого пикселя.
  Future<void> expectDarkFromTop(WidgetTester tester) async {
    final plate = tester.getRect(find.byType(WindowPlate));
    final pill = tester.getRect(find.byType(WindowPill));
    final image = await captureImage(tester.element(find.byType(MaterialApp)));
    final bytes = (await tester.runAsync(() => image.toByteData(format: ui.ImageByteFormat.rawRgba)))!;
    final width = image.width;
    double luma(int x, int y) {
      final i = (y * width + x) * 4;
      return (.2126 * bytes.getUint8(i) + .7152 * bytes.getUint8(i + 1) + .0722 * bytes.getUint8(i + 2)) / 255;
    }

    final light = <String>[];
    // Столбцы вне скругления низа (28) и вне текста: у левого края внутри поля 24 и у правого.
    for (final x in [8.0, kFrameSize.width - 8]) {
      for (var y = 0.0; y < pill.top - WindowPlate.radius; y += 2) {
        if (luma((x * kGoldenDpr).round(), (y * kGoldenDpr).round()) > .35) light.add('($x, $y)');
      }
    }
    image.dispose();
    expect(plate.top, 0);
    expect(light, isEmpty, reason: 'светлые точки на плите: ${light.take(8)}');
  }

  // ── 23-0a · не начат ──────────────────────────────────────────────────────────────────────
  testWidgets('23-0a · не начат: тёмное от верха, плита ≤ 60 %, цифр нет, «Начать» внизу', (tester) async {
    await pumpDayWindow(tester, windowRoom('not_started'));

    expect(counts, findsNothing, reason: 'у не начатого дня цифр «N / M» нет');
    expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('впереди')), findsNWidgets(5));
    expect(find.text(nbTypo('СЛОВА · 8')), findsOneWidget);
    expectPlateCeiling(tester);
    await expectDarkFromTop(tester);
    expectOneButton(tester, 'Начать');
    expectNothingCut(tester);
    await shoot('23-0a-not-started');
  });

  // ── 23-0b · идёт ──────────────────────────────────────────────────────────────────────────
  testWidgets('23-0b · идёт: цифр в рядах нет, счёт в брови, не в пилюле', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'));

    // Наряд FIX-3 §5, кадры серии 38: «N / M» снято из рядов — состояние говорит словом и полосой.
    expect(counts, findsNothing, reason: 'цифр в рядах нет');
    expect(find.text(nbTypo('СЛОВА · 8 · 6 ПРОЙДЕНО · 2 ВЕРНУТСЯ ЗАВТРА')), findsOneWidget);
    expect(find.descendant(of: find.byType(WindowPill), matching: find.textContaining(RegExp(r'\d'))), findsNothing);
    expectPlateCeiling(tester);
    expectOneButton(tester, 'Продолжить');
    expectNothingCut(tester);
    await shoot('23-0b-in-progress');
  });

  // ── 23-0c · пройден ───────────────────────────────────────────────────────────────────────
  testWidgets('23-0c · пройден: строка итога, «Научился:», возвраты в брови, латунная точка, «ещё раз» у рядов', (tester) async {
    await pumpDayWindow(tester, windowRoom('passed'));

    expect(counts, findsNothing, reason: 'текущего ряда нет — цифр нет совсем');
    // Наряд FIX-3 §5: пройденный ряд, который можно пройти ещё раз (`stages[].again`), говорит «ещё раз» и ведёт туда.
    expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('ещё раз')), findsNWidgets(5));
    expect(find.textContaining('День пройден ·'), findsOneWidget);
    expect(find.text(nbTypo('СЛОВА · 8 · 6 ПРОЙДЕНО · 2 ВЕРНУТСЯ ЗАВТРА')), findsOneWidget);
    final brass = find.descendant(
      of: find.byType(WindowWordCard),
      matching: find.byWidgetPredicate((w) => w is WindowUnitMarker && w.state == WindowUnitState.returnsTomorrow),
    );
    expect(brass, findsNWidgets(2), reason: 'латунная точка у каждого возврата');
    expectPlateCeiling(tester);
    expectOneButton(tester, 'Итог дня');
    expectNothingCut(tester);
    await shoot('23-0c-passed');
  });

  // ── 23-0d · три вкладки в трёх состояниях (прокручено) ─────────────────────────────────────
  const states = [
    (state: 'not_started', slug: 'not-started', action: 'Начать'),
    (state: 'in_progress', slug: 'in-progress', action: 'Продолжить'),
    (state: 'passed', slug: 'passed', action: 'Итог дня'),
  ];
  for (final s in states) {
    testWidgets('23-0d · ${s.slug}: пилюля под шапкой с тенью, вкладки Слова · Фразы · Диалог', (tester) async {
      await pumpDayWindow(tester, windowRoom(s.state));
      await scrollToHeader(tester);
      expect(compactOpacity(tester), 1, reason: 'плита сжата в строку 56');

      for (final (tab, file) in [('Слова', 'words'), ('Фразы', 'phrases'), ('Диалог', 'dialogue')]) {
        if (tab != 'Слова') await openWindowTab(tester, tab);
        expect(tester.getRect(find.byType(WindowPill)).top, pillPinnedTop, reason: 'пилюля прилипла под шапкой');
        final pill = tester.widget<Container>(find.descendant(of: find.byType(WindowPill), matching: find.byType(Container)).first);
        expect((pill.decoration! as BoxDecoration).boxShadow, isNotEmpty, reason: 'тень под шапкой остаётся');
        expectOneButton(tester, s.action);
        expectNothingCut(tester);
        await shoot('23-0d-${s.slug}-$file');
      }
    });
  }

  // ── 23-0d · второе состояние «прокручено»: фразы и диалог, не влезающие в экран, — до конца ────────
  testWidgets('23-0d · идёт · прокручено до конца: фразы и диалог целиком, ellipsis 0', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'));
    await scrollToHeader(tester);
    for (final (tab, file) in [('Фразы', 'phrases'), ('Диалог', 'dialogue')]) {
      await openWindowTab(tester, tab);
      await tester.drag(find.byType(NestedScrollView), const Offset(0, -2400));
      await tester.pumpAndSettle();
      expect(tester.getRect(find.byType(WindowPill)).top, pillPinnedTop);
      expectNothingCut(tester);
      await shoot('23-0d-in-progress-$file-end');
    }
  });

  // ── 23-0e · карточка слова · шит ──────────────────────────────────────────────────────────
  const sheets = [
    (state: 'not_started', term: 'lower back', slug: 'not-started', line: 'не начато'),
    (state: 'passed', term: 'worse', slug: 'returns', line: 'пройдено · вернётся в день 2'),
  ];
  for (final s in sheets) {
    testWidgets('23-0e · ${s.slug}: шит 86 %, одна «Закрыть», фон 40 %, «${s.line}»', (tester) async {
      final room = windowRoom(s.state);
      await pumpDayWindow(tester, room);
      // Кадр 23-0e снят над окном в верхнем положении — плита на месте, шит поверх; слово с возвратом
      // в сетке ниже сгиба, поэтому шит открывается его же функцией, а тап по карточке — в каноне.
      final word = DayWindow.fromJson(room.windowJson).program.words.firstWhere((w) => w.term == s.term);
      unawaited(showWindowWordSheet(tester.element(find.byType(WindowPlate)), word: word, onListen: (_, _) {}));
      await tester.pump();
      await tester.pump(AppMotion.windowSheetRise);
      final sheet = find.byType(WindowWordSheet);

      expect(tester.getRect(sheet).top, moreOrLessEquals(kFrameSize.height * (1 - WindowWordSheet.share), epsilon: .01));
      expect(find.descendant(of: sheet, matching: find.text('Закрыть')), findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.byType(ButtonStyleButton)), findsNothing);
      expect(tester.widget<ModalBarrier>(find.byType(ModalBarrier).last).color, AppColors.windowSheetScrim);
      expect(find.descendant(of: sheet, matching: find.text(nbTypo(s.line))), findsOneWidget);
      expect(find.descendant(of: sheet, matching: counts), findsNothing);
      expectNothingCut(tester);
      await shoot('23-0e-${s.slug}');
    });
  }

  // ── 37-1 · 37-2 · окно репетиции и повторения — ответы сервера BACK-TAILS-2 ─────────────────
  // Кадры 37-1, 37-2 (CLIENT-CONV-1c): ряды — ровно `window.stages` сервера с минутами («около N минут»), под плитой —
  // `window.sources[]`; пилюли и вкладок нет. Фикстуры — `server_fixtures.dart`, план — `plan_rehearsal`.
  const system = [
    (fixture: 'day-review', golden: '37-2-review-in-progress', brow: 'ИЗ КАКИХ ДНЕЙ'),
    (fixture: 'day-rehearsal', golden: '37-1-rehearsal-in-progress', brow: 'ИЗ КАКИХ СЦЕН'),
  ];
  for (final s in system) {
    testWidgets('${s.golden}: ряды и минуты сервера, «${s.brow}», одна кнопка', (tester) async {
      await pumpDayWindow(tester, PlanDayRoom.fromJson(serverFixtureJson(s.fixture)), plan: planFrom('plan_rehearsal'));
      expect(find.byType(WindowPill), findsNothing);
      expect(find.text(nbTypo(s.brow)), findsOneWidget);
      expect(counts, findsNothing, reason: 'цифр в рядах нет (наряд FIX-3 §5)');
      expectOneButton(tester, 'Продолжить');
      expectNothingCut(tester);
      await shoot(s.golden);
    });
  }
}
