import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/progress_line.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ProgressLine.fillWidth — min-width 8 (§4б)', () {
    test('zero value → no fill', () {
      expect(ProgressLine.fillWidth(0, 200), 0);
    });

    test('tiny value still fills at least 8px', () {
      expect(ProgressLine.fillWidth(0.01, 200), 8);
    });

    test('proportional above the floor', () {
      expect(ProgressLine.fillWidth(0.5, 200), 100);
      expect(ProgressLine.fillWidth(1, 200), 200);
    });

    test('value is clamped to 0..1', () {
      expect(ProgressLine.fillWidth(2, 200), 200);
      expect(ProgressLine.fillWidth(-1, 200), 0);
    });
  });

  // QA-BUG-3: the pure function above was right the whole time — the WIDGET drew nothing. A
  // childless DecoratedBox sizes to the smallest its constraints allow, and vertically they were
  // loose, so the fill was N×0 and every bar in the app looked empty. Measured, not eyeballed:
  // the paper/ink palette is close enough that «the bar looks empty» is not a finding until
  // it is a number.
  group('ProgressLine — the fill is actually PAINTED', () {
    Finder inkFill() => find.byWidgetPredicate(
      (w) => w is DecoratedBox && (w.decoration as BoxDecoration).color == AppColors.ink,
    );

    Future<Size> fillSize(WidgetTester tester, double value, {double width = 200}) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Center(
              child: SizedBox(
                width: width,
                child: ProgressLine(value: value, height: 6),
              ),
            ),
          ),
        ),
      );
      expect(inkFill(), findsOneWidget);
      return tester.getSize(inkFill());
    }

    testWidgets('40% of 200 is 80 wide and the full 6 tall', (tester) async {
      expect(await fillSize(tester, 0.4), const Size(80, 6));
    });

    testWidgets('15% likewise — the value QA measured as a flat, empty bar', (tester) async {
      expect(await fillSize(tester, 0.15), const Size(30, 6));
    });

    testWidgets('a tiny value still paints the 8px stub, at full height', (tester) async {
      expect(await fillSize(tester, 0.01), const Size(AppProgress.fillMinWidth, 6));
    });

    testWidgets('zero paints no fill at all', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: Center(child: SizedBox(width: 200, child: ProgressLine(value: 0))),
          ),
        ),
      );
      expect(inkFill(), findsNothing);
    });
  });
}
