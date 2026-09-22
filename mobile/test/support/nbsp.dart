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
