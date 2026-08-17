import '../local/app_database.dart';

/// Wrong options for a multiple-choice practice card, picked from the collection the card came
/// from. Port of the server's `EloquentDistractorReader`, with one deliberate difference and one
/// deliberate restriction — both because the local mirror is smaller than the database.
///
/// **Near-duplicates.** The server compares the full set of a term's translations (it has a
/// `term_translations` table) and drops any candidate whose translations overlap the target's,
/// because such an option would read as correct for the same prompt. The mirror stores ONE
/// translation per term, so this compares that one. It is the same rule on less data: it still
/// removes the case that matters (two words in a collection sharing a translation), it just can't
/// see synonyms the server would. A distractor is cosmetic — practice schedules nothing — so the
/// gap costs an occasionally easier card, never a wrong grade.
///
/// **Where candidates come from.** The server tops up from same-language terms of a similar level
/// when the pool is thin. The mirror has no `lang` or `cefr` column, so topping up globally could
/// put a German word among English options for someone with several collections. Better a card
/// with three options than one with a nonsense fourth: candidates stay inside the collection, and
/// a thin collection simply yields fewer options.
abstract final class PracticeDistractors {
  /// Up to [count] wrong answers for [target], drawn from [pool] (the collection's other terms).
  /// [pool] is expected pre-shuffled — the caller owns randomness so a session is reproducible
  /// under test.
  static List<String> forTarget({
    required Term target,
    required List<Term> pool,
    required int count,
  }) {
    if (count < 1) return const [];

    final targetText = _normalize(target.termText ?? '');
    final targetTranslation = _normalize(target.translation ?? '');

    final picked = <String>[];
    final usedTexts = <String>{targetText};

    for (final candidate in pool) {
      if (picked.length >= count) break;
      if (candidate.id == target.id) continue;

      final text = candidate.termText;
      if (text == null || text.trim().isEmpty) continue;

      final key = _normalize(text);
      if (!usedTexts.add(key)) continue; // no duplicate option texts

      // A candidate that means the same thing would read as correct for the same prompt.
      final translation = _normalize(candidate.translation ?? '');
      if (translation.isNotEmpty && translation == targetTranslation) continue;

      picked.add(text);
    }

    return picked;
  }

  static String _normalize(String value) => value.trim().toLowerCase();
}
