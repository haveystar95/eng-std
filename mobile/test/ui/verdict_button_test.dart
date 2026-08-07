import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/verdict_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _host(Widget child) => MaterialApp(home: Scaffold(body: Center(child: child)));

Material _fillOf(WidgetTester tester) => tester.widget<Material>(
      find.descendant(of: find.byType(VerdictButton), matching: find.byType(Material)).first,
    );

void main() {
  testWidgets('«Не знаю» (unknown) is the only filled verdict — rule 20', (tester) async {
    await tester.pumpWidget(_host(
      const VerdictButton(kind: VerdictKind.unknown, label: 'Не знаю'),
    ));
    expect(_fillOf(tester).color, AppColors.verdictUnknown);
  });

  testWidgets('«Не уверен» (unsure) has no fill', (tester) async {
    await tester.pumpWidget(_host(
      const VerdictButton(kind: VerdictKind.unsure, label: 'Не уверен'),
    ));
    expect(_fillOf(tester).color, Colors.transparent);
  });

  testWidgets('«Знаю» (known) has no fill', (tester) async {
    await tester.pumpWidget(_host(
      const VerdictButton(kind: VerdictKind.known, label: 'Знаю'),
    ));
    expect(_fillOf(tester).color, Colors.transparent);
  });

  testWidgets('tap fires onPressed', (tester) async {
    var tapped = false;
    await tester.pumpWidget(_host(
      VerdictButton(kind: VerdictKind.known, label: 'Знаю', onPressed: () => tapped = true),
    ));
    await tester.tap(find.byType(VerdictButton));
    expect(tapped, isTrue);
  });
}
