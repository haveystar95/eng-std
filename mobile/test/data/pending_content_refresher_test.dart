import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/pending_content_refresher.dart';

/// A SyncService that only counts. The refresher's whole job is deciding WHEN to ask, so what a
/// sync actually does is irrelevant here — how many times it is asked is the entire behaviour.
class _CountingSync implements SyncService {
  int calls = 0;

  @override
  Future<void> sync() async => calls++;

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  test('arms nothing while nothing is pending', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      refresher.nudge(pending: false);
      async.elapse(const Duration(minutes: 5));

      expect(sync.calls, 0);
      expect(refresher.isWatching, isFalse);
      refresher.dispose();
    });
  });

  test('looks again on a widening backoff while content is missing', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      // The screen rebuilds after every sync; each rebuild still says «pending», so the next round
      // arms. This mirrors the real loop rather than calling nudge() in a tight burst.
      for (var i = 0; i < PendingContentRefresher.delays.length; i++) {
        refresher.nudge(pending: true);
        async.elapse(PendingContentRefresher.delays[i]);
      }

      expect(sync.calls, PendingContentRefresher.delays.length);
      refresher.dispose();
    });
  });

  test('STOPS when the budget is spent — an un-illustratable word is not «not yet»', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      // Twice the budget's worth of rebuilds, all still pending: the photo is never coming, because
      // the model refused to illustrate this word. The refresher must not keep polling a phone's
      // battery to rediscover a settled fact.
      for (var i = 0; i < PendingContentRefresher.delays.length * 2; i++) {
        refresher.nudge(pending: true);
        async.elapse(const Duration(minutes: 1));
      }

      expect(sync.calls, PendingContentRefresher.delays.length);
      expect(refresher.roundsLeft, 0);
      refresher.dispose();
    });
  });

  test('a rebuild while a timer is already armed does not stack a second one', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      // Flutter rebuilds far more often than this fires. Every one of those must be free.
      for (var i = 0; i < 50; i++) {
        refresher.nudge(pending: true);
      }
      async.elapse(PendingContentRefresher.delays.first);

      expect(sync.calls, 1);
      refresher.dispose();
    });
  });

  test('content arriving resets the budget, so the NEXT word gets a full window', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      refresher.nudge(pending: true);
      async.elapse(PendingContentRefresher.delays.first);
      expect(sync.calls, 1);

      // The photo landed.
      refresher.nudge(pending: false);
      expect(refresher.roundsLeft, PendingContentRefresher.delays.length);

      // …and a second word is saved. It must get the short first delay again, not the long tail of
      // the previous window — otherwise the learner who uses search most waits longest.
      refresher.nudge(pending: true);
      async.elapse(PendingContentRefresher.delays.first);

      expect(sync.calls, 2);
      refresher.dispose();
    });
  });

  test('disposing cancels a pending look', () {
    fakeAsync((async) {
      final sync = _CountingSync();
      final refresher = PendingContentRefresher(sync);

      refresher.nudge(pending: true);
      refresher.dispose();
      async.elapse(const Duration(minutes: 5));

      expect(sync.calls, 0, reason: 'a closed screen must not keep syncing');
    });
  });
}
