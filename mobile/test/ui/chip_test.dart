import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/chip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _host(Widget child, {double width = 120}) => MaterialApp(
      home: Scaffold(
        body: Center(
          child: SizedBox(width: width, child: child),
        ),
      ),
    );

void main() {
  testWidgets('AppChip never ellipsizes its label (rule 16)', (tester) async {
    await tester.pumpWidget(_host(
      const ChipScrollRow(children: [AppChip(label: 'сводить концы с концами')]),
    ));
    expect(tester.takeException(), isNull); // horizontal scroll, no overflow throw

    final text = tester.widget<Text>(find.text('сводить концы с концами'));
    expect(text.overflow, TextOverflow.visible);
    expect(text.maxLines, 1);
  });

  testWidgets('ChipWrap wraps to a second row when it does not fit', (tester) async {
    // Six short chips in a narrow row → guaranteed to wrap; each fits alone.
    await tester.pumpWidget(_host(
      const ChipWrap(children: [
        AppChip(label: 'А'),
        AppChip(label: 'Б'),
        AppChip(label: 'В'),
        AppChip(label: 'Г'),
        AppChip(label: 'Д'),
        AppChip(label: 'Е'),
      ]),
      width: 120,
    ));
    expect(tester.takeException(), isNull); // no clip/overflow

    final first = tester.getTopLeft(find.text('А')).dy;
    final last = tester.getTopLeft(find.text('Е')).dy;
    expect(last, greaterThan(first), reason: 'chips must wrap, not clip (rule 16)');
  });

  testWidgets('selected chip is ink-filled (system selection idiom)', (tester) async {
    await tester.pumpWidget(_host(
      AppChip(label: 'большая', selected: true, onTap: () {}),
      width: 300,
    ));
    final m = tester.widget<Material>(
      find.descendant(of: find.byType(AppChip), matching: find.byType(Material)).first,
    );
    expect(m.color, AppColors.ink);
  });
}
