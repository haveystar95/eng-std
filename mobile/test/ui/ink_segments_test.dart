import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ink_segments.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _host(Widget child, {double width = 206}) => MaterialApp(
  home: Scaffold(
    body: Center(
      child: SizedBox(width: width, child: child),
    ),
  ),
);

void main() {
  testWidgets('three densities render proportionally (2:1:1)', (tester) async {
    // width 206, gap 3 ×2 = 6 → avail 200; shares 100 / 50 / 50
    await tester.pumpWidget(
      _host(InkSegments.fromCounts(confirmed: 2, familiar: 1, inProgress: 1)),
    );

    double w(InkDensity d) => tester.getSize(find.byKey(ValueKey(d))).width;
    expect(w(InkDensity.filled), closeTo(100, 0.5));
    expect(w(InkDensity.halftone), closeTo(50, 0.5));
    expect(w(InkDensity.outline), closeTo(50, 0.5));
  });

  testWidgets('zero-count segment is omitted (rule 12 numbers must add up)', (tester) async {
    await tester.pumpWidget(
      _host(InkSegments.fromCounts(confirmed: 0, familiar: 3, inProgress: 0)),
    );
    expect(find.byKey(const ValueKey(InkDensity.filled)), findsNothing);
    expect(find.byKey(const ValueKey(InkDensity.outline)), findsNothing);
    expect(find.byKey(const ValueKey(InkDensity.halftone)), findsOneWidget);
  });
}
