import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/languages.dart';
import 'package:eng_std/ui/mini_flag.dart';

/// The catalogue's half of HYG-1: the app, backend2 and the admin console each hold one copy of the
/// same language table, and this holds THIS copy to the list in
/// `docs/research/language-capability-matrix.md`. Written out rather than derived from
/// [kLanguages]: a test that reads its expectation out of the thing under test proves nothing.
void main() {
  const taught = ['en', 'ro', 'pl', 'de', 'es', 'it', 'fr'];
  const referenceOnly = ['zh', 'ja'];
  // `be` joined with LANG-1 as a plan native (the matrix's `be` row).
  const support = ['ru', 'uk', 'be', 'en'];

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

  test('names Belarusian by its endonym, with its flag, and in both interface languages', () {
    final be = findLanguage('be');

    expect(be, isNotNull, reason: 'a Belarusian native would be drawn as the first row, «Русский»');
    expect(be!.endonym, 'Беларуская');
    expect(be.nameRu, 'Белорусский');
    expect(be.nameEn, 'Belarusian');
    expect(be.flag, '🇧🇾');
    expect(languageAdverbFor('be', 'ru'), 'по-белорусски');
  });

  test('Belarusian is spoken and heard through the Russian voice, and written in Cyrillic', () {
    // iOS has no Belarusian voice or recognizer: the nearest one the phone has, not the English
    // default an unmapped code would get.
    expect(ttsLocaleFor('be'), 'ru-RU');
    expect(sttLocaleFor('be'), 'ru_RU');
    expect(looksLikeWrongKeyboard('be', 'dobry dzien'), isTrue);
    expect(looksLikeWrongKeyboard('be', 'добры дзень'), isFalse);
  });

  test('findLanguage knows only the catalogue; resolveLanguage lets the server name the rest', () {
    expect(findLanguage(' DE ')?.endonym, 'Deutsch');
    expect(findLanguage('nl'), isNull);
    expect(resolveLanguage('ro', endonym: 'România').endonym, 'Română');
    expect(resolveLanguage('nl', endonym: 'Nederlands', flag: '🇳🇱').flag, '🇳🇱');
    expect(resolveLanguage('nl').endonym, 'nl');
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
