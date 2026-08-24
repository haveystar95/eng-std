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

  /// The learner's own side of the pair, given which of the two sides is the taught one.
  ///
  /// WHICH side that is is no longer a property of the pair: since RS-3 both halves may be
  /// languages this deployment teaches, so the answer comes from [SearchLanguages.taughtSideOf] and
  /// is passed in here.
  String otherThan(String taught) => source == taught ? target : source;

  /// True when the query is typed in the learner's own language and the ANSWER is the word being
  /// taught — the direction the small card sets its headline from.
  bool reversedFor(String taught) => target == taught;

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

  /// Which way round a [SearchPair] is, given which languages this deployment teaches.
  ///
  /// The taught side used to be a constant — one language, always on one half of the pair — so this
  /// took it as a string. Since RS-3 it is a question about the pair, and [SearchLanguages] is the
  /// only thing that can answer it.
  factory LearningPair.of(SearchPair pair, SearchLanguages languages) {
    final learned = languages.taughtSideOf(pair);

    return LearningPair(learned: learned, support: pair.otherThan(learned));
  }

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
/// pair the server then refuses. So each picker lists what [optionsAgainst] says may stand beside
/// the other pill, and no language, list or role is written out in the app.
///
/// NEITHER LIST HAS A FIXED MEMBER any more. Until RS-3 the server named ONE taught language and
/// required it on one side of every pair (DECISIONS п. 134 recorded that as a v1 product limit, not
/// as the rule); `GET /search/languages` now answers with [targets] and [natives] as two roles, a
/// pair being any [targets] entry with any [natives] entry as long as the two differ — English is
/// required on neither side (пп. 85, 145). The pickers were written against lists from the start so
/// that lifting it on the server would be the whole change; this is the client half of that change.
class SearchLanguages {
  const SearchLanguages({
    required this.targets,
    required this.natives,
    required this.defaultTaught,
    required this.defaultNative,
  });

  /// What may be LEARNED — the term side of a pair — in the order to offer them.
  final List<String> targets;

  /// The languages a learner may READ, in the order to offer them. Every language the catalogue
  /// names, which since RS-3 makes it a superset of [targets].
  final List<String> natives;

  /// The singular, legacy `target` field: the ONE taught language the shipped app knew.
  ///
  /// It survives for exactly one job — breaking the tie in [taughtSideOf] when both halves of a
  /// pair are teachable. It is a deployment constant (`SupportedLanguages::LEGACY_TARGET`), which
  /// for this deployment is also the profile's taught language, and the server breaks the same tie
  /// with the profile. When the field finally goes, the fallback below (the first taught language)
  /// takes over and the tie-break degrades to «the direction's source wins», which is what the
  /// server does anyway when the profile matches neither side.
  final String defaultTaught;

  /// Where the pill starts on a device that has never been set: the learner's own language.
  final String defaultNative;

  /// Can this deployment TEACH [code] — may it stand on the term side of a pair?
  bool teaches(String code) => targets.contains(code);

  /// Can a learner READ [code] — may it stand on the support side?
  bool reads(String code) => natives.contains(code);

  /// The server's own rule for a pair it will answer (`SupportedLanguages::supports`): one side
  /// taught, the other one we can name, and the two different.
  ///
  /// Mirrored here rather than discovered by asking, so that a pair the pills can build is never a
  /// 422 the learner has to read on a screen that worked a moment ago.
  bool serves(SearchPair pair) =>
      pair.source != pair.target &&
      ((teaches(pair.source) && reads(pair.target)) ||
          (teaches(pair.target) && reads(pair.source)));

  /// What may stand in one slot of the pair, given the language in the OTHER one.
  ///
  /// The question a pill asks is not «what am I» but «what would still make a pair with my
  /// neighbour», and that is the only question with a stable answer now that a language can play
  /// either role. A taught neighbour covers the pair's taught half by itself, so this slot may hold
  /// anything readable; a support-only neighbour leaves the taught half to this slot, so only
  /// [targets] will do. The pool is picked by role rather than filtered out of one flat list so
  /// that each sheet keeps the ORDER the server sent for that role.
  ///
  /// [opposite] IS NOT IN THE RESULT — «en → en» is not a pair, and the way to say «the other way
  /// round» is the arrow between the pills, not the same language twice.
  List<String> optionsAgainst(String opposite) {
    final pool = teaches(opposite) ? natives : targets;

    return [
      for (final code in pool)
        if (code != opposite && serves(SearchPair(source: code, target: opposite))) code,
    ];
  }

  /// Which half of [pair] is the language being LEARNED.
  ///
  /// Usually the pair names it: in `ru → ro` only `ro` is one this deployment teaches, so it is the
  /// term side whichever way round the learner typed it. `es → en` names TWO, and what the pill
  /// holds is a DIRECTION rather than a pair of roles — it is either Spanish studied with English
  /// support or the other way about, and the two are indistinguishable from the pair alone.
  ///
  /// The server breaks that tie with the learner's profile and falls back to the direction's source
  /// (DECISIONS п. 147); this mirrors it with [defaultTaught] standing in for the profile, which is
  /// the same value for this deployment and the only one the client is told. WHERE THE TWO COULD
  /// DISAGREE, THE SERVER'S ANSWER IS THE ONE THAT COUNTS — it decides what a search returns. This
  /// one decides something narrower: which collections the save sheet may offer, and in which pair
  /// a collection made from that sheet is born.
  String taughtSideOf(SearchPair pair) {
    final source = teaches(pair.source);
    final target = teaches(pair.target);
    if (source != target) return source ? pair.source : pair.target;
    // Neither side teachable: not a pair this deployment serves at all, so the store has already
    // fallen back and nothing downstream will use the answer. Source, for the sake of an answer.
    if (!source) return pair.source;

    return pair.target == defaultTaught ? pair.target : pair.source;
  }

  /// The pill's opening position — a taught language into the learner's own. Looking up a word they
  /// have just met is the commoner half of the job, so that is the way round it opens.
  ///
  /// GUARDED ON BOTH SIDES, because the learner's own language may now be one this deployment
  /// teaches: an English speaker's `default_native` is `en`, and the old formula would have opened
  /// the screen on «en → en», the one pair the server refuses.
  SearchPair get initialPair {
    final learned = _openingTaught;

    return SearchPair(source: learned, target: _openingSupport(learned));
  }

  String get _openingTaught {
    if (defaultTaught != defaultNative && teaches(defaultTaught)) return defaultTaught;
    for (final code in targets) {
      if (code != defaultNative) return code;
    }

    return defaultTaught; // every taught language is their own; the support side sorts it out
  }

  String _openingSupport(String learned) {
    if (defaultNative != learned) return defaultNative;
    for (final code in natives) {
      if (code != learned) return code;
    }

    return learned == 'en' ? 'ru' : 'en'; // a one-language catalogue: still two sides, still a pair
  }

  factory SearchLanguages.fromJson(Map<String, dynamic> j) {
    // ADDITIVE, so the parse never depends on which of the two contracts answered: `targets` is the
    // roles version, the singular `target` is what a server from before RS-3 sends, and a body with
    // neither still has to leave the pill with a pair it can draw.
    final targets = _codes(j['targets']);
    final natives = _codes(j['natives']);
    final legacy = (j['target'] as String?)?.trim() ?? '';
    final defaultTaught = legacy.isNotEmpty
        ? legacy
        : (targets.isNotEmpty ? targets.first : 'en');
    final defaultNative = (j['default_native'] as String?)?.trim() ?? '';

    return SearchLanguages(
      targets: targets.isNotEmpty ? targets : [defaultTaught],
      natives: natives.isNotEmpty ? natives : const ['ru'],
      defaultTaught: defaultTaught,
      defaultNative: defaultNative.isNotEmpty
          ? defaultNative
          : (natives.isNotEmpty ? natives.first : 'ru'),
    );
  }

  /// A list of language codes from a body that may hold anything at all.
  static List<String> _codes(Object? raw) => [
    for (final code in (raw is List ? raw : const []))
      if (code is String && code.trim().isNotEmpty) code.trim(),
  ];
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
      // One side taught, the other readable, the two different — the SERVER's rule, so a stale
      // preference degrades to the default pair instead of to a 422. It is checked on every read
      // rather than at write time because the lists move under the stored value: a language may
      // stop being taught, or start being taught, between one session and the next.
      if (stored == null || !languages.serves(stored)) return languages.initialPair;

      return stored;
    } catch (_) {
      return languages.initialPair;
    }
  }

  Future<void> save(SearchPair pair) => _db.setMeta(metaKey, jsonEncode(pair.toJson()));
}
