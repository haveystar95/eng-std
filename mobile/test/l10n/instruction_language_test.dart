import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/language_endonyms.dart';

/// THE INSTRUCTION UNDER THE PROMPT NAMES THE CARD'S OWN LANGUAGE — never a language in general.
///
/// «Выбери английский эквивалент» was printed under an Italian word with Italian options, and
/// «прослушай и напиши по-английски» under an Italian recording. The copy was written when the app
/// taught one language; the pool has mixed pairs by design since DECISIONS п. 128, so a hardcoded
/// language in these three lines is an instruction to do something other than what the card asks.
///
/// Two guards, and they need each other. The first reads the ARB files, because the templates are
/// where the word would come back — a future edit that "simplifies" `{lang}` back to «английский»
/// looks harmless in a diff and breaks every non-English card. The second checks the derivation,
/// because a parameter that produces the wrong Russian form is a template that reads as broken.
void main() {
  /// The three instructions that name the language being written or recognised. The other ten name
  /// an ACT («собери из слов», «скажи слово вслух») and have no language in them to get wrong.
  const parameterised = ['sessionInstrChoose', 'sessionInstrType', 'sessionInstrListenType'];

  Map<String, dynamic> arb(String path) =>
      jsonDecode(File(path).readAsStringSync()) as Map<String, dynamic>;

  group('the templates carry a parameter, not a language', () {
    for (final file in ['lib/l10n/app_ru.arb', 'lib/l10n/app_en.arb']) {
      test('$file names no language of its own', () {
        final strings = arb(file);

        for (final key in parameterised) {
          final template = strings[key] as String?;
          expect(template, isNotNull, reason: '$key is missing from $file');
          expect(
            template!.contains('{lang}'),
            isTrue,
            reason: '$key must take the card\'s language as a parameter, not assume one',
          );
          // The hardcoded forms this test exists to keep out — the Russian stem covers
          // «английский» and «по-английски» at once.
          expect(template.toLowerCase(), isNot(contains('английск')));
          expect(template.toLowerCase(), isNot(contains('english')));
        }
      });
    }
  });

  group('the parameter is the right word in the right locale', () {
    test('Russian gets an adjective for the noun and an adverb for the verb', () {
      // The two shapes the two sentences want: «выбери итальянский эквивалент» and «напиши
      // по-итальянски». One placeholder cannot serve both, which is why there are two functions.
      expect(languageAdjectiveFor('it', 'ru'), 'итальянский');
      expect(languageAdverbFor('it', 'ru'), 'по-итальянски');

      // The adverb rule is `по-` + stem + `-и`, and it has to hold for every row of the catalogue —
      // «по-немецки» and «по-турецки» are the `-кий` endings that would fall out of a naive rule.
      expect(languageAdverbFor('en', 'ru'), 'по-английски');
      expect(languageAdverbFor('de', 'ru'), 'по-немецки');
      expect(languageAdverbFor('tr', 'ru'), 'по-турецки');
      expect(languageAdverbFor('ja', 'ru'), 'по-японски');
    });

    test('English has one form, and the preposition stays in the template', () {
      // «write it in {lang}» — so the value must be the bare name, or the sentence reads
      // «write it in in Italian».
      expect(languageAdjectiveFor('it', 'en'), 'Italian');
      expect(languageAdverbFor('it', 'en'), 'Italian');
    });

    test('every taught language produces a word in both locales', () {
      for (final language in kLanguages) {
        for (final ui in ['ru', 'en']) {
          expect(languageAdjectiveFor(language.code, ui), isNotEmpty);
          expect(languageAdverbFor(language.code, ui), isNotEmpty);
        }
      }
    });
  });
}
