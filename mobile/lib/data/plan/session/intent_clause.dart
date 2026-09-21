/// THE INTENTION AS A CLAUSE — a mirror of the server's `IntentClause` (наряд CONV-2, п. 11), for the ONE place the server
/// does not apply it yet: the `task_native` of a `speak_answer` card (кадр 35-2), which arrives as the learner's line —
/// a sentence, «У него болит поясница.» — and goes inside the client's own «Скажи, что …».
///
/// The talk needs none of this: its `hints.native` comes as a clause from the server, and the talk prints it as it came
/// (наряд CLIENT-CONV-1c §2а — the talk's own correction, `TalkTexts.clause`, is gone). When the card's `task_native`
/// comes as a clause too, this file goes (отчёт client-conv-1c §5).
///
/// The rule is the server's, word for word: one closing full stop dropped; the first letter lowered only when the second
/// is lower-case already, so an abbreviation keeps its capitals («США …» stays); a question mark, an exclamation mark, an
/// ellipsis and a closing quote stay — they are part of what is to be said. Idempotent: a clause stays itself.
library;

abstract final class IntentClause {
  static String of(String sentence) {
    var text = sentence.trim();
    if (text.endsWith('.') && !text.endsWith('..')) text = text.substring(0, text.length - 1).trimRight();
    final runes = text.runes.toList();
    if (runes.length < 2) return text;
    final first = String.fromCharCode(runes[0]);
    final second = String.fromCharCode(runes[1]);
    final capital = first.toUpperCase() == first && first.toLowerCase() != first;
    if (capital && second.toLowerCase() == second) {
      text = first.toLowerCase() + String.fromCharCodes(runes.skip(1));
    }
    return text;
  }
}
