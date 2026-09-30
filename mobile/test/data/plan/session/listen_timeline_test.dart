import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/listen_timeline.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';

import '../../../support/server_fixtures.dart';

/// THE VISIT PLAYER'S TIMELINE (work order SESSION-1c §3, canvas 34-1): the files in the order of `lines[]`, the
/// exchange marks at the end of every `exchange_step`, the length from `total_ms` or the lines' `duration_ms`.
void main() {
  Map<String, dynamic> raw() => serverFixtureJson('day-doctor');
  ListenDialoguePayload visit(Map<String, dynamic> json) =>
      SessionDay.fromJson(json).stageOf(PlanStage.listen)!.cards.map((c) => c.payload).whereType<ListenDialoguePayload>().single;

  // CATCHES: marks placed by line instead of by exchange, a rescue exchange (the learner first) split in two, the
  // length ignoring `total_ms`.
  // The numbers are the fixture's own (наряд CLIENT-22-1 §4): the lesson's audio is re-voiced with every generation, so
  // the lengths are read off the raw payload here — `total_ms` and each line's `duration_ms` — and never written out.
  test('the fixture visit: 16 lines, 8 exchanges, marks at the end of every exchange, the length from total_ms', () {
    final json = raw();
    final t = ListenTimeline.of(visit(json));
    final payload = _visitJson(json);
    final durations = [for (final l in (payload['lines'] as List).cast<Map<String, dynamic>>()) (l['audio'] as Map)['duration_ms'] as int];
    final total = payload['total_ms'] as int;
    final starts = [for (var i = 0; i < durations.length; i++) durations.take(i).fold(0, (a, b) => a + b)];

    expect(t.lines, hasLength(16));
    expect([for (final l in t.lines) l.ref].take(4), ['x1', 'x1b', 'x2', 'x2b']);
    expect(t.exchanges, 8);
    expect([for (final m in t.marks) m.step], [1, 2, 3, 4, 5, 6, 7, 8]);
    expect([for (final m in t.marks) m.lastLine], [1, 3, 5, 7, 9, 11, 13, 15]);
    expect(t.totalMs, total);
    expect(t.starts, starts, reason: 'each line starts where the lines before it end');
    expect(t.marks.first.at, closeTo((durations[0] + durations[1]) / total, 1e-9));
    expect(t.marks.last.at, closeTo(1, 1e-9));
    expect(t.endsExchange(1), isTrue);
    expect(t.endsExchange(2), isFalse);
    expect(t.exchangeOrdinal(0), 1);
    expect(t.exchangeOrdinal(11), 6);
    expect(t.progress(1, Duration(milliseconds: durations[1] ~/ 2)), closeTo((starts[1] + durations[1] ~/ 2) / total, 1e-9));
    expect(t.elapsed(2, Duration.zero), Duration(milliseconds: starts[2]));
  });

  test('lengths unknown — the marks stand evenly, the bar moves by lines, no clock', () {
    final json = raw();
    final listen = (json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'listen');
    final card = (listen['cards'] as List).cast<Map<String, dynamic>>().firstWhere((c) => c['kind'] == 'listen_dialogue');
    final payload = card['payload'] as Map<String, dynamic>;
    payload['total_ms'] = null;
    for (final line in (payload['lines'] as List).cast<Map<String, dynamic>>()) {
      (line['audio'] as Map<String, dynamic>)['duration_ms'] = null;
    }
    final t = ListenTimeline.of(visit(json));
    expect(t.totalMs, isNull);
    expect(t.starts, isNull);
    expect([for (final m in t.marks) m.at], [for (var k = 1; k <= 8; k++) k / 8]);
    expect(t.progress(0, const Duration(seconds: 5)), closeTo(0.5 / 16, 1e-9));
    expect(t.elapsed(3, Duration.zero), isNull);
  });
}

/// The raw `listen_dialogue` payload of [json] — the visit the timeline reads.
Map<String, dynamic> _visitJson(Map<String, dynamic> json) {
  final listen = (json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'listen');
  final card = (listen['cards'] as List).cast<Map<String, dynamic>>().firstWhere((c) => c['kind'] == 'listen_dialogue');
  return card['payload'] as Map<String, dynamic>;
}
