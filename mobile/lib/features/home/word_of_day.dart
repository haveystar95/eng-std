import '../../data/models.dart';

/// «Слово дня» is client-side and deterministic: the same term for a given day,
/// stable across launches and fully offline (no endpoint). Given [daySeed] (a
/// day number) it picks from [terms] by a stable order so the choice doesn't
/// depend on query/sync order. Empty input → null (the block hides).
///
/// Level-proximity («ближе к уровню пользователя») is deferred: local terms
/// carry no CEFR yet, so there is nothing to bias on. Recorded as a limitation.
Word? pickWordOfDay(List<Word> terms, int daySeed) {
  if (terms.isEmpty) return null;
  final ordered = [...terms]..sort((a, b) => a.termId.compareTo(b.termId));
  final idx = ((daySeed % ordered.length) + ordered.length) % ordered.length;
  return ordered[idx];
}

/// Day number since the Unix epoch in UTC — the [pickWordOfDay] seed. UTC so the
/// pick flips at a single well-defined instant, not per-device-timezone drift.
int dayNumber(DateTime now) =>
    DateTime.utc(now.year, now.month, now.day).millisecondsSinceEpoch ~/ Duration.millisecondsPerDay;
