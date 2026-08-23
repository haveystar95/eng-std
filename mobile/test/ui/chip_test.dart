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
    await tester.pumpWidget(
      _host(const ChipScrollRow(children: [AppChip(label: 'сводить концы с концами')])),
    );
    expect(tester.takeException(), isNull); // horizontal scroll, no overflow throw

    final text = tester.widget<Text>(find.text('сводить концы с концами'));
    expect(text.overflow, TextOverflow.visible);
    expect(text.maxLines, 1);
  });

  testWidgets('ChipWrap wraps to a second row when it does not fit', (tester) async {
    // Six short chips in a narrow row → guaranteed to wrap; each fits alone.
    await tester.pumpWidget(
      _host(
        const ChipWrap(
          children: [
            AppChip(label: 'А'),
            AppChip(label: 'Б'),
            AppChip(label: 'В'),
            AppChip(label: 'Г'),
            AppChip(label: 'Д'),
            AppChip(label: 'Е'),
          ],
        ),
        width: 120,
      ),
    );
    expect(tester.takeException(), isNull); // no clip/overflow

    final first = tester.getTopLeft(find.text('А')).dy;
    final last = tester.getTopLeft(find.text('Е')).dy;
    expect(last, greaterThan(first), reason: 'chips must wrap, not clip (rule 16)');
  });

  /// QA-OBS-15 — the chip is drawn at ~29pt, which is a fine chip and an unfair target. The tap
  /// zone around it is 44 while the paint is untouched.
  group('the tap zone is 44pt, the chip is not', () {
    testWidgets('the box is 44 tall and the painted chip stays its own size', (tester) async {
      await tester.pumpWidget(_host(AppChip(label: 'B1', onTap: () {}), width: 300));

      expect(tester.getSize(find.byType(MinTapHeight)).height, AppSpacing.minTap);
      final painted = tester.getSize(
        find.descendant(of: find.byType(AppChip), matching: find.byType(Material)).first,
      );
      expect(painted.height, lessThan(AppSpacing.minTap), reason: 'the chip itself must not grow');
    });

    testWidgets('a tap in the transparent margin counts — and counts once', (tester) async {
      var taps = 0;
      await tester.pumpWidget(_host(AppChip(label: 'B1', onTap: () => taps++), width: 300));

      final box = tester.getRect(find.byType(MinTapHeight));
      await tester.tapAt(Offset(box.center.dx, box.top + 2)); // above the painted chip
      await tester.pump();
      expect(taps, 1, reason: 'the margin is part of the target');

      await tester.tap(find.byType(AppChip));
      await tester.pump();
      expect(taps, 2, reason: 'and the chip itself still fires exactly one tap, not two');
    });
  });

  testWidgets('selected chip is ink-filled (system selection idiom)', (tester) async {
    await tester.pumpWidget(
      _host(AppChip(label: 'большая', selected: true, onTap: () {}), width: 300),
    );
    final m = tester.widget<Material>(
      find.descendant(of: find.byType(AppChip), matching: find.byType(Material)).first,
    );
    expect(m.color, AppColors.ink);
  });
}
