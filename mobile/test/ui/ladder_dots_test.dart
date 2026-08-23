import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ladder_dots.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The ladder as it appears in a word row (кадр 16d) and in the expanded card (кадр 16e).
///
/// Five dots stand for six rungs, because rungs 1 and 2 are both «узнавание» and share one: the list
/// says how far the word has come, and the direction a recognition asked in is not that.
void main() {
  Widget wrap(Widget child) => MaterialApp(
    theme: buildAppTheme(),
    home: Scaffold(body: Center(child: child)),
  );

  /// The colour of each dot, left to right — which is the whole visual contract: transparent means
  /// a passed rung (drawn as an outline), ink means the current one, track means still ahead.
  List<Color?> dotColours(WidgetTester tester) {
    final containers = tester
        .widgetList<Container>(
          find.descendant(of: find.byType(LadderDots), matching: find.byType(Container)),
        )
        .toList();
    return [for (final c in containers) (c.decoration as BoxDecoration?)?.color];
  }

  testWidgets('a never-shown word lights the first dot and nothing else', (tester) async {
    await tester.pumpWidget(wrap(const LadderDots(step: 0)));

    expect(dotColours(tester), [
      AppColors.ink, // rung 0 — where the word is
      AppColors.track, AppColors.track, AppColors.track, AppColors.track,
    ]);
  });

  testWidgets('both recognition rungs share one dot — the direction is not progress', (
    tester,
  ) async {
    await tester.pumpWidget(wrap(const LadderDots(step: 1)));
    final forward = dotColours(tester);

    await tester.pumpWidget(wrap(const LadderDots(step: 2)));
    expect(dotColours(tester), forward, reason: 'rung 2 lights the same dot as rung 1');
    expect(forward[1], AppColors.ink);
  });

  testWidgets('a passed rung is an outline, not a fill', (tester) async {
    await tester.pumpWidget(wrap(const LadderDots(step: 4)));

    final colours = dotColours(tester);
    // Rungs behind the current one are spent: they hold their place without asking for attention.
    expect(colours[0], Colors.transparent);
    expect(colours[1], Colors.transparent);
    expect(colours[2], Colors.transparent);
    expect(colours[3], AppColors.ink); // current
    expect(colours[4], AppColors.track); // ahead
  });

  testWidgets('the top rung leaves nothing ahead', (tester) async {
    await tester.pumpWidget(wrap(const LadderDots(step: 5)));

    expect(dotColours(tester).last, AppColors.ink);
    expect(dotColours(tester).where((c) => c == AppColors.track), isEmpty);
  });

  testWidgets('the current dot is the larger one — findable at a glance in a long list', (
    tester,
  ) async {
    await tester.pumpWidget(wrap(const LadderDots(step: 3)));

    final sizes = tester
        .widgetList<Container>(
          find.descendant(of: find.byType(LadderDots), matching: find.byType(Container)),
        )
        .map((c) => (c.constraints?.maxWidth ?? 0))
        .toList();

    expect(sizes[2], greaterThan(sizes[0]), reason: 'the current dot is a touch larger');
    expect(sizes[2], greaterThan(sizes[4]));
  });

  testWidgets('a known word gets a dash, not five pale dots', (tester) async {
    // Five pale dots would say «at the very beginning», which is the opposite of what «знаю» means.
    await tester.pumpWidget(wrap(const LadderKnownDash(label: 'знаю')));

    expect(find.text('знаю'), findsOneWidget);
    expect(find.byType(LadderDots), findsNothing);
  });

  testWidgets('the expanded card captions the same five dots, current one semibold', (
    tester,
  ) async {
    await tester.pumpWidget(
      wrap(
        const LadderTrack(
          step: 1,
          labels: ['знакомство', 'узнавание', 'сборка', 'написание', 'диктант'],
        ),
      ),
    );

    for (final label in ['знакомство', 'узнавание', 'сборка', 'написание', 'диктант']) {
      expect(find.text(label), findsOneWidget);
    }

    final current = tester.widget<Text>(find.text('узнавание'));
    final other = tester.widget<Text>(find.text('диктант'));
    expect(current.style?.fontWeight, FontWeight.w600);
    expect(other.style?.fontWeight, FontWeight.w400);
  });
}
