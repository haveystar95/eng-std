import 'package:eng_std/data/typography.dart';

/// The no-break space the app keeps a number with (приёмка CLIENT-CONV-1c 22.09), spelled by its code: a literal would
/// hide an invisible character in the test.
final String nbsp = String.fromCharCode(0xA0);

/// AN EXPECTATION AS THE APP DRAWS IT — written with plain spaces, it gets the no-break spaces the `.arb` has: a number
/// keeps the word after it, and «·» / «≈» keep the number after them (the rule `test/l10n/nbsp_numbers_test.dart`
/// holds the strings to, and `planDot` holds a join of two strings to). Only for the app's own words: a server's line
/// («His temperature is 39 degrees.») or a date keeps its plain spaces and is not passed through here.
String nb(String text) => text
    .replaceAllMapped(RegExp(r'(\d) (?=\p{L})', unicode: true), (m) => '${m[1]}$nbsp')
    .replaceAllMapped(RegExp(r'([·≈]) (?=\d)'), (m) => '${m[1]}$nbsp');

/// [nb] over a list of lines.
List<String> nbAll(List<String> texts) => [for (final t in texts) nb(t)];

/// A LINE AS THE APP DRAWS IT UNDER THE TYPOGRAPHY RULE (наряд CLIENT-22-1 §2): [nb], then the app's own rule —
/// `typeset` of `lib/data/typography.dart`, the same function that sets the `.arb` (`test/l10n/nbsp_typography_test.dart`)
/// and the server's native texts at display. The language is [language], or read off the text: Cyrillic — ru, anything
/// else — en. Never for a text in the language being learned: a frame or a line the learner says keeps its plain
/// spaces on screen.
String nbTypo(String text, [String? language]) => typeset(nb(text), language ?? (_cyrillic.hasMatch(text) ? 'ru' : 'en'));

/// [nbTypo] over a list of lines.
List<String> nbTypoAll(List<String> texts, [String? language]) => [for (final t in texts) nbTypo(t, language)];

/// A SERVER'S NATIVE TEXT AS THE APP DRAWS IT (наряд CLIENT-22-1 §2): the rule alone, no [nb] — the server's own numbers
/// keep the spaces it sent (наряд FIX-3 §8).
String nt(String text, [String language = 'ru']) => typeset(text, language);

final _cyrillic = RegExp('[Ѐ-ӿ]');

/// A STAGE SUMMARY'S TITLE (30-6) as the app draws it: [nb], and the dash kept with the stage's name, and the dot kept
/// with the word before it too — «Говорю сам — / пройдено · 6 минут» breaks only after the dash (приёмка
/// CLIENT-CONV-1c 22.09, третий заход; `planSessionPassedMinutes` and the `planSessionPassed*` titles).
String nbPassed(String text) => nbTypo(text).replaceAll(' ·$nbsp', '$nbsp·$nbsp');
