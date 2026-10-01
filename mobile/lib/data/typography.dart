/// THE TYPOGRAPHY OF A TEXT IN THE LEARNER'S OWN LANGUAGE — one rule, applied when the text is SHOWN (наряд
/// CLIENT-22-1 §2).
///
/// A short word does not hang at the end of a line, and a line does not start with a dash. A no-break space (U+00A0)
/// replaces the space:
/// - after every word of one or two letters in ru, uk and be, and after the longer words of the language's list below
///   (prepositions of three letters and the compound «из-за»);
/// - after every word of one letter in every other language;
/// - before «—» in all of them.
///
/// Where it applies: the interface's own strings (the `.arb` files carry it — `tool/typeset_arb.dart` writes it,
/// `test/l10n/nbsp_typography_test.dart` holds every string of both files to it, since gen-l10n has no hook to run
/// code on a string) and the server's texts in the learner's NATIVE language, at display ([typesetNative]).
/// NEVER a text in the language being learned: a frame, a line, a word, an option — whatever the learner reads aloud
/// or the recognizer compares — keeps its plain spaces, or the comparison breaks. Which side a string is on is its
/// field in the contract (`*_native` and its kin), not the screen it stands on.
///
/// The lists — [kBindingWords] — live beside the endonyms in `lib/l10n/typography_words.dart`: facts about a language
/// written in its own letters, next to the client's other language settings.
library;

import '../l10n/typography_words.dart';

export '../l10n/typography_words.dart' show kBindingWords;

/// U+00A0 — the space a line does not break at.
const String kNoBreakSpace = ' ';

/// [text] as the learner's [language] sets it — see the library comment. Idempotent: a space already made no-break is
/// not touched again, so a string that carries the rule passes through unchanged.
String typeset(String text, String language) {
  if (text.isEmpty) return text;
  final code = _code(language);
  final listed = kBindingWords[code];
  var out = text.replaceAllMapped(listed == null ? _oneLetter : _oneOrTwoLetters, (m) => '${m[1]}$kNoBreakSpace');
  if (listed != null) {
    out = out.replaceAllMapped(_listed.putIfAbsent(code, () => _longerOf(listed)), (m) => '${m[1]}$kNoBreakSpace');
  }

  return out.replaceAll(' —', '$kNoBreakSpace—');
}

/// The language the SERVER composes its own strings in for a learner of [native] (plan-api, LANG-1: `summary`,
/// `route_summary`, the day count of `until_phrase`, `highlights`, the slot labels, the judge's refusals by code): ru,
/// uk and en have their packs, every other native reads them in English.
String composedLanguage(String native) {
  final code = _code(native);

  return const {'ru', 'uk', 'en'}.contains(code) ? code : 'en';
}

// A word starts where the character before it is no letter, mark, digit, apostrophe or hyphen («кто-то» holds no word
// «то», «об'єм» no word «єм»), and the rule binds it only when a plain space follows.
const _before = r"(?<![\p{L}\p{M}\p{N}'’ʼ-])";
final _oneLetter = RegExp('$_before(\\p{L}\\p{M}*) ', unicode: true);
final _oneOrTwoLetters = RegExp('$_before((?:\\p{L}\\p{M}*){1,2}) ', unicode: true);
final _listed = <String, RegExp>{};

// The lists hold letters and hyphens only — nothing to escape (and `\-` outside a class is no escape in unicode mode).
RegExp _longerOf(List<String> words) => RegExp(
  '$_before(${[for (final w in words) if (w.runes.length > 2) w].join('|')}) ',
  unicode: true,
  caseSensitive: false,
);

String _code(String language) => language.trim().toLowerCase().split(RegExp('[-_]')).first;
