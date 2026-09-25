import 'dart:ui' show PictureRecorder;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/language_endonyms.dart';
import 'package:eng_std/ui/mini_flag.dart';

/// EVERY language the catalogue offers must have a flag drawn for it.
///
/// This has been fixed twice after the fact — `ro` in HYG-1, then `pl`/`it`/`ru` in A-4 — and both
/// times the symptom was the same: the picker offered a language the way it offers all the others,
/// and that one row came up a grey circle with a code in it, which on screen reads as «this
/// language is second-class» rather than as «nobody drew it yet».
///
/// The neutral circle is not being removed: it is the right answer for a code that is NOT in the
/// catalogue — a typo, or a language added to one runtime and forgotten in another. What this test
/// forbids is a catalogue row falling into it.
void main() {
  testWidgets('every catalogue language draws a flag, not the neutral fallback', (tester) async {
    final missing = <String>[];

    for (final language in kLanguages) {
      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: Center(child: MiniFlag(languageCode: language.code)))),
      );
      await tester.pump();

      // The fallback is the ONE shape that prints the code as text inside the circle.
      if (find.text(language.code.toUpperCase()).evaluate().isNotEmpty) {
        missing.add(language.code);
      }
    }

    expect(
      missing,
      isEmpty,
      reason: 'these catalogue languages fall back to the neutral coded circle: $missing',
    );
  });

  // LANG-1: Belarusian is offered as a native (onboarding, the profile row) — it is drawn, not coded,
  // and its face is not a plain red-green bicolour: the white hoist strip is there.
  testWidgets('be draws the Belarusian flag with the white hoist strip', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: Center(child: MiniFlag(languageCode: 'be', size: 44)))),
    );

    expect(find.text('BE'), findsNothing);
    final painter = tester
        .widgetList<CustomPaint>(find.descendant(of: find.byType(MiniFlag), matching: find.byType(CustomPaint)))
        .map((p) => p.painter)
        .whereType<CustomPainter>()
        .single;
    final recorder = PictureRecorder();
    painter.paint(Canvas(recorder), const Size.square(44));
    final image = await tester.runAsync(() => recorder.endRecording().toImage(44, 44));
    final bytes = (await tester.runAsync(() => image!.toByteData()))!;
    // rawRgba: one byte per channel.
    ({int r, int g, int b}) pixel(int x, int y) {
      final i = (y * 44 + x) * 4;

      return (r: bytes.getUint8(i), g: bytes.getUint8(i + 1), b: bytes.getUint8(i + 2));
    }

    expect(pixel(1, 22), (r: 255, g: 255, b: 255), reason: 'the hoist strip is white beside the ornament');
    expect(pixel(5, 22).g, lessThan(pixel(5, 22).r), reason: 'the ornament is red');
    expect(pixel(30, 10).r, greaterThan(pixel(30, 10).g), reason: 'red above');
    expect(pixel(30, 40).g, greaterThan(pixel(30, 40).r), reason: 'green below, a third of the height');
  });

  testWidgets('a code outside the catalogue still gets the neutral circle', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: Center(child: MiniFlag(languageCode: 'xx')))),
    );

    expect(find.text('XX'), findsOneWidget);
  });

  testWidgets('an empty code degrades to a question mark rather than throwing', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: Center(child: MiniFlag(languageCode: '')))),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('?'), findsOneWidget);
  });
}
