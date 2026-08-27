import '../local/app_database.dart';

/// Wrong options for a multiple-choice practice card, picked from the collection the card came
/// from. Port of the server's `EloquentDistractorReader`, with one deliberate difference and one
/// deliberate restriction — both because the local mirror is smaller than the database.
///
/// **Near-duplicates.** The server compares the full set of a term's translations (it has a
/// `term_translations` table) and drops any candidate whose translations overlap the target's — or
/// any option ALREADY TAKEN — because such an option would read as correct for the same prompt. The
/// mirror stores ONE translation per term, so this compares that one. It is the same rule on less
/// data: it still removes the case that matters (two words in a collection sharing a translation),
/// it just can't see synonyms the server would. A distractor is cosmetic — practice schedules
/// nothing — so the gap costs an occasionally easier card, never a wrong grade.
///
/// Comparing against the taken options and not only against the target is what stops «check-in
/// desk» and «front desk» — both «стойка регистрации» — standing beside each other as each other's
/// wrong answer (QA-17). Whichever is the answer, the other is equally right.
///
/// **Where candidates come from.** The server tops up from same-language terms of a similar level
/// when the pool is thin. The mirror has no `cefr` column and no level to sort by, so there is no
/// top-up here: candidates are the list the caller handed over, and a thin one simply yields fewer
/// options. Better a card with three options than one with a nonsense fourth.
///
/// **The PAIR gate**, and it is a filter with no fallback. An option is a term TEXT in the language
/// being studied, so a candidate of another pair is not a weaker wrong answer — it is a word from a
/// different card. Shown the English `hello`, the owner's phone offered the Polish `Cześć` beside it
/// (BUGFIX-2 Ч.1): every option a correct word in its own language, and only the one the answer key
/// names counted. Knowing the word did not help; it could not.
///
/// The pair comes from [AppDatabase.pairByTerms] — the one place that answers «which pair is this
/// term being studied in» — because `terms` has no `lang` column and is not getting one: a word's
/// language is a fact about the folder it is being learned through, and that resolver is where the
/// server's `CardLanguageResolver` is mirrored. A term whose folders have all left the mirror has no
/// pair, and an unverifiable pair is treated as a failed one — the same direction the server's
/// recognition card takes.
abstract final class PracticeDistractors {
  /// The terms of [pool] that share [target]'s pair — the material any practice card may draw on.
  ///
  /// [bothHalves] says how much of the pair has to match. The STUDIED side always does: it is the
  /// language the option text is written in, and it is the whole gate for an ordinary
  /// multiple_choice, exactly as the server's distractor reader compares `terms.lang` and nothing
  /// else. The far-option (recognition) card matches the SUPPORT side too, mirroring the server's
  /// `recognitionCard()`: that card may show the translations, and a pair is DIRECTED — ru→en and
  /// en→ru hold the same two languages and are not the same pair.
  ///
  /// When the TARGET's own pair is unknown there is nothing to compare against, so the list is
  /// returned untouched: an orphan word — every folder it came from deleted, which the pool
  /// deliberately survives (п. 102) — must not lose all of its options and with them its card.
  static List<Term> samePairAs({
    required Term target,
    required List<Term> pool,
    required Map<String, ({String learned, String support})> pairs,
    bool bothHalves = false,
  }) {
    final own = pairs[target.id];
    if (own == null) return pool;

    return [
      for (final candidate in pool)
        if (_matches(pairs[candidate.id], own, bothHalves: bothHalves)) candidate,
    ];
  }

  static bool _matches(
    ({String learned, String support})? candidate,
    ({String learned, String support}) own, {
    required bool bothHalves,
  }) {
    if (candidate == null) return false; // unverifiable — treated as a failed match, as on the server
    if (candidate.learned != own.learned) return false;

    return !bothHalves || candidate.support == own.support;
  }

  /// Up to [count] wrong answers for [target], drawn from [pool] (the session's other terms).
  /// [pool] is expected pre-shuffled — the caller owns randomness so a session is reproducible
  /// under test.
  ///
  /// [pairs] is the pair each term is being studied in; the gate above runs HERE rather than only at
  /// the call site, so a new caller cannot get options past it by forgetting to filter first.
  static List<String> forTarget({
    required Term target,
    required List<Term> pool,
    required int count,
    Map<String, ({String learned, String support})> pairs = const {},
  }) {
    if (count < 1) return const [];
    pool = samePairAs(target: target, pool: pool, pairs: pairs);

    final targetText = _normalize(target.termText ?? '');
    final targetTranslation = _normalize(target.translation ?? '');

    final picked = <String>[];
    final usedTexts = <String>{targetText};
    // The MEANINGS already on the card, the prompt's own taken first — so the prompt and the
    // options are one rule rather than two.
    final usedTranslations = <String>{if (targetTranslation.isNotEmpty) targetTranslation};

    for (final candidate in pool) {
      if (picked.length >= count) break;
      if (candidate.id == target.id) continue;

      final text = candidate.termText;
      if (text == null || text.trim().isEmpty) continue;

      final key = _normalize(text);
      if (!usedTexts.add(key)) continue; // no duplicate option texts

      // A candidate that means the same as the prompt — or as an option already taken — would read
      // as correct for this very prompt.
      final translation = _normalize(candidate.translation ?? '');
      if (translation.isNotEmpty && !usedTranslations.add(translation)) continue;

      picked.add(text);
    }

    return picked;
  }

  static String _normalize(String value) => value.trim().toLowerCase();
}
