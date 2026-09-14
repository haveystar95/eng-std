import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/day/window/window_action_bar.dart';
import 'package:eng_std/features/plan/day/window/window_stage_row.dart';

import '../../support/day_window_harness.dart';
import '../../support/plan_goldens.dart';

/// ОКНО ДНЯ — кадры 23-0a…0d канвы `plan-canvas.dc.html`: ответ сервера → экран → снимок (DAY-UI-2).
///
/// Снимок сверяет композицию с кадром. То, что таблица канвы «Изменения · серия 23 · что проверяет
/// golden» называет словами, проверяется здесь же утверждениями, а не глазом по PNG:
/// - 23-0a — нет цифр «N / M» у не начатого дня; бровь «Слова · 8»;
/// - 23-0b — «6 / 16» на «Слушаю и отвечаю»; легенды маркеров нет;
/// - 23-0c — действие «Ещё раз»; возвраты названы в брови словами;
/// - 23-0d — кнопка одна и прижата к низу; ни одной обрезанной строки (ellipsis 0).
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

  // ── 23-0a · не начат ──────────────────────────────────────────────────────────────────────
  testWidgets('23-0a · не начат: пять рядов «впереди» без цифр, бровь «Слова · 8», «Начать» внизу', (tester) async {
    await pumpDayWindow(tester, windowRoom('not_started'));

    expect(counts, findsNothing, reason: 'у не начатого дня цифр «N / M» нет');
    expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('впереди')), findsNWidgets(5));
    expect(find.text('СЛОВА · 8'), findsOneWidget);
    expectOneButton(tester, 'Начать');
    expectNothingCut(tester);
    await shoot('23-0a-not-started');
  });

  // ── 23-0b · идёт ──────────────────────────────────────────────────────────────────────────
  testWidgets('23-0b · идёт: цифра одна — у «Слушаю и отвечаю», легенды маркеров нет', (tester) async {
    await pumpDayWindow(tester, windowRoom('in_progress'));

    expect(counts, findsOneWidget, reason: 'цифра только у текущего ряда');
    expect(
      find.descendant(of: find.widgetWithText(WindowStageRow, 'Слушаю и отвечаю'), matching: find.text('6 / 16')),
      findsOneWidget,
    );
    // Легенда «галка — пройдено · латунь — вернётся завтра» вычтена: о возвратах говорит только бровь.
    expect(find.textContaining(RegExp('вернётся|вернутся|ВЕРНЁТСЯ|ВЕРНУТСЯ')), findsOneWidget);
    expect(find.text('СЛОВА · 8 · 6 ПРОЙДЕНО · 2 ВЕРНУТСЯ ЗАВТРА'), findsOneWidget);
    expectOneButton(tester, 'Продолжить');
    expectNothingCut(tester);
    await shoot('23-0b-in-progress');
  });

  // ── 23-0c · пройден ───────────────────────────────────────────────────────────────────────
  testWidgets('23-0c · пройден: строка итога, цифр нет, «Ещё раз», возвраты в брови словами', (tester) async {
    await pumpDayWindow(tester, windowRoom('passed'));

    expect(counts, findsNothing, reason: 'текущего ряда нет — цифр нет совсем');
    expect(find.descendant(of: find.byType(WindowStageRow), matching: find.text('пройдено')), findsNWidgets(5));
    expect(find.textContaining('День пройден · '), findsOneWidget);
    expect(find.text('СЛОВА · 8 · 6 ПРОЙДЕНО · 2 ВЕРНУТСЯ ЗАВТРА'), findsOneWidget);
    expectOneButton(tester, 'Ещё раз');
    expectNothingCut(tester);
    await shoot('23-0c-passed');
  });

  // ── 23-0d · три вкладки в трёх состояниях (прокручено) ─────────────────────────────────────
  const states = [
    (state: 'not_started', slug: 'not-started', action: 'Начать'),
    (state: 'in_progress', slug: 'in-progress', action: 'Продолжить'),
    (state: 'passed', slug: 'passed', action: 'Ещё раз'),
  ];
  for (final s in states) {
    testWidgets('23-0d · ${s.slug}: компактная шапка, вкладки Слова · Фразы · Диалог', (tester) async {
      await pumpDayWindow(tester, windowRoom(s.state));
      await scrollToHeader(tester);
      expect(compactOpacity(tester), 1, reason: 'плита сжата в строку 56');

      for (final (tab, file) in [('Слова', 'words'), ('Фразы', 'phrases'), ('Диалог', 'dialogue')]) {
        if (tab != 'Слова') await openWindowTab(tester, tab);
        expectOneButton(tester, s.action);
        expectNothingCut(tester);
        await shoot('23-0d-${s.slug}-$file');
      }
    });
  }
}
