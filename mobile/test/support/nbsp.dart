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

/// A LINE OF CLIENT-START'S AS THE APP DRAWS IT (доработка п. 1): [nb], and a no-break space after a one-letter word
/// («в», «и», «с», «к», «о», «у», «а», «я»; «a», «I») and after «не», and before «—» — a line neither ends on a one-letter
/// word nor starts with a dash (the rule `test/l10n/nbsp_typography_test.dart` holds the work order's strings to).
String nbTypo(String text) => nb(text)
    .replaceAllMapped(RegExp(r'(?<![\p{L}\p{N}-])(\p{L}|[Нн]е) ', unicode: true), (m) => '${m[1]}$nbsp')
    .replaceAll(' —', '$nbsp—');

/// A STAGE SUMMARY'S TITLE (30-6) as the app draws it: [nb], and the dash kept with the stage's name, and the dot kept
/// with the word before it too — «Говорю сам — / пройдено · 6 минут» breaks only after the dash (приёмка
/// CLIENT-CONV-1c 22.09, третий заход; `planSessionPassedMinutes` and the `planSessionPassed*` titles).
String nbPassed(String text) => nb(text).replaceAll(' —', '$nbsp—').replaceAll(' ·$nbsp', '$nbsp·$nbsp');
