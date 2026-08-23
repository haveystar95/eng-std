import 'dart:convert';

import '../../data/local/app_database.dart';

/// One line of «Вы искали» — a word the learner has already looked up once.
///
/// It carries the translation and the level ALONGSIDE the word because the empty search screen has
/// to be readable, not just a log: three bare spellings say «you typed these», three dictionary
/// lines say «here they are again». Both are null when the query never resolved to anything — a
/// misspelling, or a word the model was never asked about — and the row then degrades to the word
/// alone rather than disappearing.
class RecentSearch {
  const RecentSearch({required this.word, this.translation, this.cefr});

  final String word;
  final String? translation;
  final String? cefr;

  Map<String, dynamic> toJson() => {
    'w': word,
    if ((translation ?? '').isNotEmpty) 't': translation,
    if ((cefr ?? '').isNotEmpty) 'c': cefr,
  };

  static RecentSearch? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final word = (raw['w'] as String?)?.trim() ?? '';
    if (word.isEmpty) return null;

    return RecentSearch(word: word, translation: raw['t'] as String?, cefr: raw['c'] as String?);
  }

  /// Is this line worth showing at all?
  ///
  /// «случай — случай» is not a search result, it is a hint that failed: the instant translator was
  /// asked to turn a Russian word into Russian and returned it unchanged. Lines like that were
  /// written by builds before the field knew which way round it was translating, and there is
  /// nothing to salvage in them — the word alone would be a log entry, and this section is a way
  /// back in, not a log.
  bool get isUseful {
    final meaning = (translation ?? '').trim();

    return meaning.isEmpty || meaning.toLowerCase() != word.toLowerCase();
  }
}

/// The last few searches, kept on the device and nowhere else.
///
/// LOCAL on purpose: what somebody typed into a search field is not progress, it is not worth a
/// column on the server, and the one screen that reads it is the one screen that wrote it. It lives
/// in the sync-meta key/value table the local mirror already has, so it needs no migration and it
/// leaves with the account when [AppDatabase] is cleared on sign-out.
///
/// Every read is defensive: a key that was hand-edited, truncated or written by an older build must
/// degrade to «no history», never to a crash on the empty search screen.
///
/// ## Two rules about what gets in
///
/// WHEN: only a search that ENDED somewhere — a card opened, or a card built. Remembering every
/// submitted string filled the section with words the learner glanced at and abandoned, which is a
/// log of keystrokes rather than a way back to something.
///
/// WHAT: never a line whose translation is its own word. See [RecentSearch.isUseful] — those were
/// written before the field knew which way it was translating, and they are filtered on READ rather
/// than migrated away. A filter is the honest fix here: the store is three lines of local cache
/// with no history worth preserving, a migration would need its own version key and its own
/// failure mode, and the filter also catches a junk line the next bad answer writes tomorrow.
class SearchHistory {
  const SearchHistory(this._db);

  final AppDatabase _db;

  /// The mockup's empty state shows three lines and the screen is built around three. More would
  /// turn a reminder into a log; fewer would leave the section looking broken.
  static const limit = 3;

  static const metaKey = 'search.recent';

  Future<List<RecentSearch>> load() async {
    final raw = await _db.getMeta(metaKey);
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];

      return [
        for (final entry in decoded)
          if (RecentSearch.fromJson(entry) case final row? when row.isUseful) row,
      ].take(limit).toList(growable: false);
    } catch (_) {
      return const [];
    }
  }

  /// Put [entry] at the top, deduplicated case-insensitively, and drop what falls past [limit].
  ///
  /// Re-searching a word MOVES it rather than adding a second line — the list is «where you have
  /// been», and the same word twice says nothing the one line did not.
  Future<List<RecentSearch>> remember(RecentSearch entry) async {
    final word = entry.word.trim();
    // A line that says a word means itself is not worth a row — same rule on the way in as on the
    // way out, so a bad answer cannot become tomorrow's junk.
    if (word.isEmpty || !entry.isUseful) return load();

    final existing = await load();
    final kept = existing.where((r) => r.word.toLowerCase() != word.toLowerCase());
    final next = [
      RecentSearch(word: word, translation: entry.translation, cefr: entry.cefr),
      ...kept,
    ].take(limit).toList(growable: false);

    await _db.setMeta(metaKey, jsonEncode([for (final r in next) r.toJson()]));

    return next;
  }
}
