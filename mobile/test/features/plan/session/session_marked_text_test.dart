import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;

/// A LINE DRAWN IN PLATES (35-3, 35-4, 34-3, 34-7) — every place a line of the day is drawn with its words on plates goes
/// through [SessionMarkedText].
void main() {
  setUpAll(setUpPlanGoldens);

  List<TextMark> everyWord(String text) => [
    for (final m in RegExp(r"[A-Za-z][A-Za-z'-]*").allMatches(text)) (start: m.start, end: m.end, look: MarkLook.sage),
  ];

  Future<void> pumpLine(WidgetTester tester, String text, double width, {List<TextMark>? marks}) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: width,
              child: SessionMarkedText(text: text, marks: marks ?? everyWord(text), style: AppTextSession.question),
            ),
          ),
        ),
      ),
    );
  }

  // ПРАВИЛО (приёмка CLIENT-CONV-1c 22.09, харнесс 24, 25): знак препинания крепится к плашке предыдущего слова без
  // зазора и никогда не остаётся один на строке — ни на какой ширине.
  // ЛОВИТ: «appointment / ?» — знак отдельной строкой после плашки (перенос на краю вставки).
  testWidgets('35-3 · 35-4: «?» стоит вплотную к плашке и не уходит на строку один', (tester) async {
    const line = 'Do we need a follow-up appointment?';
    final word = line.indexOf('appointment');
    for (var width = 200.0; width <= 360; width += 2) {
      await pumpLine(tester, line, width);
      final plate = tester.getRect(find.byKey(ValueKey('mark-sage-$word')));
      final mark = tester.getRect(find.text('?'));
      expect(mark.left, moreOrLessEquals(plate.right, epsilon: 0.5), reason: 'ширина $width: знак у края плашки');
      expect(mark.center.dy, moreOrLessEquals(plate.center.dy, epsilon: 4), reason: 'ширина $width: на той же строке');
    }
  });

  // ПРАВИЛО: открывающий знак перед плашкой едет вместе с ней — строка не кончается на «(».
  // ЛОВИТ: «(» в конце строки, слово в начале следующей.
  testWidgets('открывающий знак перед плашкой — вплотную к ней', (tester) async {
    const line = 'He said it hurts (here) and here.';
    final word = line.indexOf('here');
    for (var width = 160.0; width <= 320; width += 4) {
      await pumpLine(tester, line, width);
      final plate = tester.getRect(find.byKey(ValueKey('mark-sage-$word')));
      final open = tester.getRect(find.text('('));
      expect(open.right, moreOrLessEquals(plate.left, epsilon: 0.5), reason: 'ширина $width: «(» у плашки');
      expect(open.center.dy, moreOrLessEquals(plate.center.dy, epsilon: 4), reason: 'ширина $width: на той же строке');
    }
  });

  // ПРАВИЛО: плашка длиннее строки переносится внутри своей рамки, знак стоит у её последней строки; плашка со знаком
  // не шире строки.
  // ЛОВИТ: переполнение ряда «плашка + знак» — 34-3 в тестовом шрифте: «lower back» и «.» шире пузыря на 7,8.
  testWidgets('плашка длиннее строки — переносится внутри, знак у её последней строки', (tester) async {
    const line = 'It hurts in his lower back.';
    final start = line.indexOf('hurts');
    await pumpLine(tester, line, 140, marks: [(start: start, end: line.length - 1, look: MarkLook.brass)]);
    expect(tester.takeException(), isNull);
    final plate = tester.getRect(find.byKey(ValueKey('mark-brass-$start')));
    final unit = tester.getRect(find.byKey(ValueKey('mark-unit-$start')));
    final mark = tester.getRect(find.text('.'));
    expect(unit.width, lessThanOrEqualTo(140.5), reason: 'не шире строки');
    expect(plate.height, greaterThan(mark.height * 1.5), reason: 'плашка перенеслась внутри себя');
    expect(mark.left, moreOrLessEquals(plate.right, epsilon: 0.5), reason: 'знак у края плашки');
    expect(mark.bottom, moreOrLessEquals(plate.bottom - 1.5, epsilon: 2), reason: 'знак — на последней строке плашки');
  });

  // ПРАВИЛО: без отмеченных слов строка — обычный текст, знаки на местах.
  testWidgets('без отметок — строка как есть', (tester) async {
    await pumpLine(tester, 'Do we need a follow-up appointment?', 300, marks: const []);
    expect(find.text('Do we need a follow-up appointment?', findRichText: true), findsOneWidget);
  });
}
