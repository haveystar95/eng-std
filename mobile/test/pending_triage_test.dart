import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/triage_queue.dart';

PendingTriage _t({bool? revealed}) => PendingTriage(
  id: '01HTRIAGE0000000000000001',
  termId: '01HTERM00000000000000000001',
  verdict: 'known',
  decidedAt: '2026-08-04T10:00:00.000Z',
  clientSeq: 7,
  revealed: revealed,
);

void main() {
  group('PendingTriage.revealed', () {
    test('round-trips through the persisted queue JSON', () {
      final back = PendingTriage.fromJson(_t(revealed: true).toJson());
      expect(back.revealed, isTrue);
      expect(back.clientSeq, 7);
    });

    test('batch JSON includes revealed when set', () {
      expect(_t(revealed: true).toBatchJson()['revealed'], isTrue);
      expect(_t(revealed: false).toBatchJson()['revealed'], isFalse);
    });

    test('batch JSON omits revealed when null (server treats absent as neutral)', () {
      expect(_t().toBatchJson().containsKey('revealed'), isFalse);
    });

    test('legacy queued item without revealed decodes to null', () {
      final legacy = PendingTriage.fromJson({
        'id': '01HTRIAGE0000000000000001',
        'term_id': '01HTERM00000000000000000001',
        'verdict': 'known',
        'decided_at': '2026-08-04T10:00:00.000Z',
        'client_seq': 1,
      });
      expect(legacy.revealed, isNull);
    });
  });
}
