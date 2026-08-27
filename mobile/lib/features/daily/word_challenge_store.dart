import 'dart:convert';

import 'package:flutter/foundation.dart' show debugPrint;

import '../../data/local/app_database.dart';
import 'word_challenge.dart';

/// WHAT THE CHALLENGE REMEMBERS between launches: today's word, the answer, and the run.
///
/// One `sync_meta` row rather than a table of its own. This is four scalars about one day; a table
/// would buy queryability nobody needs and one more migration to get wrong. It lives beside the
/// cached day for the same reason that one does — the screen reads the local DB, always.
///
/// [streak] outlives the day and is the only field that does: it is the run the counter shows, and
/// resetting it at midnight would make «угадано 6 подряд» impossible to reach.
class WordChallengeState {
  const WordChallengeState({
    this.date,
    this.termId,
    this.chosen,
    this.collapsed = false,
    this.streak = 0,
  });

  /// The learner's own calendar day, `YYYY-MM-DD`. Null before the first card was ever drawn.
  final String? date;
  final String? termId;
  final String? chosen;
  final bool collapsed;
  final int streak;

  /// The state a NEW day starts from: the run survives, everything about the word does not.
  WordChallengeState tomorrow(String date) => WordChallengeState(date: date, streak: streak);

  factory WordChallengeState.fromJson(Map<String, dynamic> j) => WordChallengeState(
    date: j['date'] as String?,
    termId: j['term_id'] as String?,
    chosen: j['chosen'] as String?,
    collapsed: (j['collapsed'] as bool?) ?? false,
    streak: (j['streak'] as int?) ?? 0,
  );

  Map<String, dynamic> toJson() => {
    'date': date,
    'term_id': termId,
    'chosen': chosen,
    'collapsed': collapsed,
    'streak': streak,
  };
}

/// The stub's DATA SOURCE — and the seam DAILY-1 replaces.
///
/// Everything the server will one day decide is decided here: which word, which options, what the
/// run is. The widget is handed a [WordChallenge] and knows none of it, so the day the server picks
/// by level and sends the social line, this class changes and the card does not.
class WordChallengeStore {
  WordChallengeStore(this._db);

  static const String metaKey = 'word_challenge';

  final AppDatabase _db;

  /// Today's card, or null when the mirror cannot honestly produce one.
  ///
  /// [now] and [userId] are arguments rather than reads: the word must be stable until midnight in
  /// the LEARNER's day and different for different people, and a function that fetches its own clock
  /// is a function no test can ask about tomorrow.
  Future<WordChallenge?> today({required DateTime now, required String userId}) async {
    final date = _dateOf(now);
    var state = await _read();
    if (state.date != date) {
      state = state.tomorrow(date);
    }

    final challenge = pickWordChallenge(
      mirror: await _db.challengeMirror(),
      seed: '$date:$userId',
      streak: state.streak,
      pinnedTermId: state.termId,
      chosen: state.chosen,
      collapsed: state.collapsed,
    );

    if (challenge == null) {
      // Nothing to pin and nothing to draw. The state is still advanced to today, so the run is not
      // re-read from a stale date the moment the mirror fills up.
      if (state.date != (await _read()).date) await _write(state);

      return null;
    }

    // PIN IT. The word is chosen once per day and then held by id: «Учить» moves it into the pool,
    // which would take it out of the candidate set, and the day's word must not change under the
    // learner's hand the moment they take it.
    if (state.termId != challenge.termId || state.date != (await _read()).date) {
      await _write(
        WordChallengeState(
          date: date,
          termId: challenge.termId,
          chosen: state.chosen,
          collapsed: state.collapsed,
          streak: state.streak,
        ),
      );
    }

    return challenge;
  }

  /// The learner tapped an option. The run moves here and only here: it grows on a hit and drops to
  /// zero on a miss, which is what makes «угадано 6 подряд» worth anything.
  Future<void> answer({
    required DateTime now,
    required WordChallenge challenge,
    required String option,
  }) async {
    final state = await _read();
    if (state.chosen != null) return; // one answer per day; a second tap is a mis-tap

    await _write(
      WordChallengeState(
        date: _dateOf(now),
        termId: challenge.termId,
        chosen: option,
        collapsed: false,
        streak: option == challenge.translation ? state.streak + 1 : 0,
      ),
    );
  }

  /// «Завтра новое» — fold the card into one line until the date changes.
  Future<void> collapse({required DateTime now}) async {
    final state = await _read();
    await _write(
      WordChallengeState(
        date: _dateOf(now),
        termId: state.termId,
        chosen: state.chosen,
        collapsed: true,
        streak: state.streak,
      ),
    );
  }

  Future<WordChallengeState> _read() async {
    final raw = await _db.getMeta(metaKey);
    if (raw == null || raw.isEmpty) return const WordChallengeState();
    try {
      return WordChallengeState.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (e) {
      // A row this build cannot read is a row an older or newer build wrote. Starting the run over
      // is a small loss; refusing to draw the card is a bigger one.
      debugPrint('[challenge] unreadable state, starting over: $e');

      return const WordChallengeState();
    }
  }

  Future<void> _write(WordChallengeState state) => _db.setMeta(metaKey, jsonEncode(state.toJson()));

  /// The DEVICE's calendar day. The word turns over at the learner's midnight, not at UTC's.
  static String _dateOf(DateTime now) {
    final local = now.toLocal();

    return '${local.year.toString().padLeft(4, '0')}-'
        '${local.month.toString().padLeft(2, '0')}-'
        '${local.day.toString().padLeft(2, '0')}';
  }
}
