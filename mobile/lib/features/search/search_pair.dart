import 'dart:convert';

import '../../data/local/app_database.dart';

/// Which way the search field is pointing: what the learner types, what they get back.
///
/// A PAIR AND A DIRECTION, carried together, because both halves are asked of the server on every
/// call and because the direction is the thing the pill flips. «EN → RU» and «RU → EN» are the same
/// two languages and two different questions.
class SearchPair {
  const SearchPair({required this.source, required this.target});

  /// What the learner types in.
  final String source;

  /// What comes back.
  final String target;

  /// The same two languages, asked the other way. This is the whole of a tap on the pill.
  SearchPair get swapped => SearchPair(source: target, target: source);

  /// The learner's own side of the pair, given which language the app teaches.
  String otherThan(String taught) => source == taught ? target : source;

  /// True when the query is typed in the learner's own language and the ANSWER is the word being
  /// taught — the direction the small card sets its headline from.
  bool reversedFor(String taught) => target == taught;

  SearchPair withOther(String taught, String other) => reversedFor(taught)
      ? SearchPair(source: other, target: taught)
      : SearchPair(source: taught, target: other);

  Map<String, dynamic> toJson() => {'s': source, 't': target};

  static SearchPair? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final source = (raw['s'] as String?)?.trim() ?? '';
    final target = (raw['t'] as String?)?.trim() ?? '';
    if (source.isEmpty || target.isEmpty || source == target) return null;

    return SearchPair(source: source, target: target);
  }

  @override
  bool operator ==(Object other) =>
      other is SearchPair && other.source == source && other.target == target;

  @override
  int get hashCode => Object.hash(source, target);

  @override
  String toString() => '$source→$target';
}

/// A pair with its ROLES named, which is the shape everything downstream of the search field wants.
///
/// [SearchPair] is a direction — «what I typed» → «what came back» — and it flips twice a minute.
/// A COLLECTION's pair does not flip: it is «изучаемый → язык поддержки» (`target_lang` /
/// `source_lang`), one per collection, forever (DECISIONS п. 81). This is the translation between
/// the two, made once at the top of the save path so no screen further down has to work out which
/// half of the direction was the taught one.
class LearningPair {
  const LearningPair({required this.learned, required this.support});

  /// The language BEING LEARNED — a collection's `target_lang`, and the language of every term in
  /// it. The one half the server's gate actually checks.
  final String learned;

  /// The language of SUPPORT — a collection's `source_lang`: which translation is shown.
  final String support;

  /// Which way round a [SearchPair] is, given the language this deployment teaches.
  factory LearningPair.of(SearchPair pair, String taught) =>
      LearningPair(learned: taught, support: pair.otherThan(taught));

  @override
  bool operator ==(Object other) =>
      other is LearningPair && other.learned == learned && other.support == support;

  @override
  int get hashCode => Object.hash(learned, support);

  @override
  String toString() => '$learned←$support';
}

/// What the server says the pickers may offer, AND which role each language plays.
///
/// ROLES COME FROM HERE AND NOWHERE ELSE. `lib/l10n/language_endonyms.dart` is a table of NAMES —
/// it says what a language is called and what flag it flies, not whether this deployment can teach
/// it or read it. Those two lists are a capability of the server (`GET /search/languages`), they
/// change by deployment rather than by release, and a client that held its own copy would offer a
/// pair the server then refuses. So the «На какой» picker lists [learned] and the «С какого» picker
/// lists [support], and neither is written out in the app.
///
/// [learned] HOLDS EXACTLY ONE ENTRY TODAY, because the server names exactly one: `SupportedLanguages`
/// still requires the taught language on one side of every pair (`config/languages.php`), which
/// DECISIONS п. 134 records as a v1 product limit due to be lifted (RS-3), not as the rule. The
/// picker is written against a list so that lifting it on the server is the whole change.
class SearchLanguages {
  const SearchLanguages({required this.taught, required this.natives, required this.defaultNative});

  /// The language the app teaches — the fixed half of every pair in v1.
  final String taught;

  /// The languages a learner may read, in the order to offer them.
  final List<String> natives;

  /// Where the pill starts on a device that has never been set: the learner's own language.
  final String defaultNative;

  /// What may occupy the LEARNED side of the pair — «На какой».
  List<String> get learned => [taught];

  /// What may occupy the SUPPORT side of the pair — «С какого».
  List<String> get support => natives;

  /// Which language may stand in the slot [code] currently occupies. The two pills are the two
  /// SIDES of the direction, so the swap moves the roles between them and each pill has to ask
  /// again which list it is offering.
  List<String> optionsAt(String code) => code == taught ? learned : support;

  /// The pill's opening position — taught language into their own. Looking up a word they have
  /// just met is the commoner half of the job, so that is the way round it opens.
  SearchPair get initialPair => SearchPair(source: taught, target: defaultNative);

  factory SearchLanguages.fromJson(Map<String, dynamic> j) {
    final taught = (j['target'] as String?)?.trim();
    final natives = [for (final n in (j['natives'] as List? ?? const [])) (n as String).trim()]
      ..removeWhere((n) => n.isEmpty);

    return SearchLanguages(
      taught: (taught ?? '').isNotEmpty ? taught! : 'en',
      natives: natives.isNotEmpty ? natives : const ['ru'],
      defaultNative: (j['default_native'] as String?)?.trim().isNotEmpty == true
          ? (j['default_native'] as String).trim()
          : (natives.isNotEmpty ? natives.first : 'ru'),
    );
  }
}

/// The chosen pair, kept on the device and nowhere else.
///
/// LOCAL because it is a preference about a screen, not progress: which way somebody last pointed
/// their search field is not worth a column on the server, and it should survive a restart of the
/// app rather than a re-login. It rides the same sync-meta key/value table as the search history,
/// so it needs no migration and it leaves with the account when the database is cleared.
///
/// Every read is defensive. A key written by an older build, hand-edited, or naming a language the
/// server has since stopped serving must degrade to «the default pair», never to a search the
/// server will refuse — which is exactly what a remembered `ro` would become if `ro` were dropped
/// from the deployment.
class SearchPairStore {
  const SearchPairStore(this._db);

  final AppDatabase _db;

  static const metaKey = 'search.pair';

  /// The stored pair if it is still one the server serves, otherwise [languages]' opening position.
  Future<SearchPair> load(SearchLanguages languages) async {
    final raw = await _db.getMeta(metaKey);
    if (raw == null || raw.isEmpty) return languages.initialPair;
    try {
      final stored = SearchPair.fromJson(jsonDecode(raw));
      if (stored == null || !_serves(languages, stored)) return languages.initialPair;

      return stored;
    } catch (_) {
      return languages.initialPair;
    }
  }

  Future<void> save(SearchPair pair) => _db.setMeta(metaKey, jsonEncode(pair.toJson()));

  /// Exactly one side taught, the other a language still on offer — the same rule the server
  /// enforces, applied here so a stale preference never becomes a 422 the learner has to read.
  static bool _serves(SearchLanguages languages, SearchPair pair) {
    final taught = languages.taught;
    final sides = {pair.source, pair.target};

    return sides.contains(taught) &&
        sides.length == 2 &&
        languages.natives.contains(pair.otherThan(taught));
  }
}
