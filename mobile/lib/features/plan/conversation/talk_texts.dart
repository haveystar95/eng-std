import 'package:eng_std/l10n/app_localizations.dart';

/// THE TALK'S WORDING — choosing an ARB string and joining it with the server's words. Nothing here
/// counts and nothing here declines.
abstract final class TalkTexts {
  /// THE HINT CHIP (кадр 37-7): the client's «Скажи, что …» around the server's intention.
  ///
  /// The contract promises the intention «без префикса», and the live run showed what the server
  /// actually sends: a whole sentence — «У моего сына температура.» — which, glued into the prefix
  /// as it stands, read «Скажи, что У моего сына температура.» on the phone. So the sentence is
  /// turned into a clause when it goes INTO the client's own sentence: the first letter lowered and
  /// the closing full stop dropped. The words themselves are the server's, untouched (отчёт §5).
  static String hint(AppLocalizations l, String intent) => l.planTalkHintChip(clause(intent));

  /// A sentence as a clause of someone else's: «У моего сына температура.» → «у моего сына
  /// температура». The first letter is lowered only when the second one is lower-case already, so
  /// an abbreviation keeps its capitals («США …» stays «США …») — the rule `WindowTexts` applies to
  /// the goals of a day. One closing full stop goes; a question or an exclamation mark, an ellipsis
  /// and a closing quote stay — they are part of what is to be said.
  static String clause(String sentence) {
    var s = sentence.trim();
    if (s.endsWith('.') && !s.endsWith('..')) s = s.substring(0, s.length - 1).trimRight();
    final runes = s.runes.toList();
    if (runes.length > 1) {
      final first = String.fromCharCode(runes[0]);
      final second = String.fromCharCode(runes[1]);
      if (first.toUpperCase() == first && first.toLowerCase() != first && second.toLowerCase() == second) {
        s = first.toLowerCase() + String.fromCharCodes(runes.skip(1));
      }
    }
    return s;
  }
}
