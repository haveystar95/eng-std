import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/language_endonyms.dart';
import 'package:eng_std/ui/mini_flag.dart';

/// The catalogue's half of HYG-1: the app, backend2 and the admin console each hold one copy of the
/// same language table, and this holds THIS copy to the list in
/// `docs/research/language-capability-matrix.md`. Written out rather than derived from
/// [kLanguages]: a test that reads its expectation out of the thing under test proves nothing.
void main() {
  const taught = ['en', 'ro', 'pl', 'de', 'es', 'it', 'fr'];
  const referenceOnly = ['zh', 'ja'];
  const support = ['ru', 'uk', 'en'];

  test('covers every language the capability matrix names', () {
    final codes = kLanguages.map((l) => l.code).toSet();

    for (final code in [...taught, ...referenceOnly, ...support]) {
      expect(codes, contains(code), reason: '$code is in the matrix but not in the catalogue');
    }
  });

  test('has no duplicate codes', () {
    final codes = kLanguages.map((l) => l.code).toList();

    expect(codes.toSet().length, codes.length);
  });

  test('fills every column of every row', () {
    for (final lang in kLanguages) {
      expect(lang.code, matches(RegExp(r'^[a-z]{2}$')));
      expect(lang.endonym.trim(), isNotEmpty);
      expect(lang.nameRu.trim(), isNotEmpty);
      expect(lang.nameEn.trim(), isNotEmpty);
      expect(lang.flag.trim(), isNotEmpty);
    }
  });

  test('names Romanian as the LANGUAGE, not as the country', () {
    // `România` is the country; the endonym of the language is `Română` (QA-OBS-16). The picker
    // showed the country to the user for months.
    final ro = languageByCode('ro');

    expect(ro.endonym, 'Română');
    expect(ro.flag, '🇷🇴');
  });

  testWidgets('MiniFlag draws the Romanian flag instead of the neutral code circle', (
    tester,
  ) async {
    // The fallback face is a grey circle with the uppercase code written in it. Romanian was
    // sitting on that fallback while the picker offered it (HC-M5). pl/it still are — see ROADMAP.
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: MiniFlag(languageCode: 'ro')),
      ),
    );

    expect(find.text('RO'), findsNothing);
  });
}
