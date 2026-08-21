import 'dart:async';

import 'local/sync_service.dart';

/// Keeps pulling `/sync` while a screen is still waiting for content that the SERVER is in the
/// middle of producing — a term's photo, a cover, a translation the enrichment job is filling in.
///
/// ## Why this exists
///
/// Every screen reads from the local mirror and rebuilds the moment a sync writes to it, so the
/// photo appears on its own — IF a sync happens. And that was the hole: syncs fired on tab entry,
/// on app resume, on reconnect and on pull-to-refresh, and none of those happens while the learner
/// is sitting on the collection they just added a word to, watching the placeholder. The content
/// was landing on the server within seconds and reaching the phone only when the learner gave up
/// and swiped down. Asking a person to poll on the app's behalf is not a refresh policy.
///
/// ## Why a backoff, and why it ends
///
/// The work being waited on is a queue job with retries, so the honest window is «seconds to a
/// minute or two», not «forever». The delays widen as they go, and then the refresher STOPS.
///
/// Stopping matters more than starting: a word can be legitimately un-illustratable — the model is
/// asked for an empty image query when a word has no honest picture, and that word will never get
/// one. A refresher that treated «no photo» as «not yet» would poll that screen for as long as it
/// was open, forever, on a phone battery, to discover a fact that was already settled. So the
/// budget is fixed, and a screen that has spent it simply stops asking.
///
/// The caller says on every build whether anything is still missing; this decides whether that is
/// worth another look. Nothing is timed while nothing is pending, so an ordinary collection of
/// fully-illustrated words arms no timer at all.
class PendingContentRefresher {
  PendingContentRefresher(this._sync);

  final SyncService _sync;

  /// Widening delays, then done. ~100 seconds in total across six looks — long enough for a queue
  /// job with backoff to land, short enough to be over before anyone reads the screen twice.
  static const List<Duration> delays = [
    Duration(seconds: 3),
    Duration(seconds: 6),
    Duration(seconds: 10),
    Duration(seconds: 15),
    Duration(seconds: 25),
    Duration(seconds: 40),
  ];

  Timer? _timer;
  int _round = 0;
  bool _disposed = false;

  /// How many looks are left in the budget. Exposed for tests and for a screen that wants to say
  /// «still loading» rather than «nothing here».
  int get roundsLeft => delays.length - _round;

  bool get isWatching => _timer != null;

  /// Call on every build with whether the screen is still missing something.
  ///
  /// `pending: false` RESETS the budget, so a second word added later gets its own full window —
  /// the alternative (a budget spent once per screen) would silently stop refreshing exactly for
  /// the learner who uses the feature most.
  void nudge({required bool pending}) {
    if (_disposed) return;

    if (!pending) {
      _timer?.cancel();
      _timer = null;
      _round = 0;

      return;
    }

    if (_timer != null || _round >= delays.length) return;

    final delay = delays[_round++];
    _timer = Timer(delay, () {
      _timer = null;
      if (_disposed) return;
      // Fire and forget: a failed sync (offline) is not an error here, it is simply a look that
      // found nothing. The next round tries again, and the budget still ends.
      unawaited(_sync.sync());
    });
  }

  void dispose() {
    _disposed = true;
    _timer?.cancel();
    _timer = null;
  }
}
