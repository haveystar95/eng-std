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
