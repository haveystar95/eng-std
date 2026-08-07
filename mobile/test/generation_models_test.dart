import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';

void main() {
  group('GenerationQuota', () {
    test('parses the /me generation block and flags exhaustion', () {
      final q = GenerationQuota.fromJson({
        'limit': 50,
        'used': 50,
        'remaining': 0,
        'resets_at': '2026-08-05T00:00:00Z',
      });
      expect(q.remaining, 0);
      expect(q.exhausted, isTrue);
      // resets_at is rendered in device-local time — parsed to a local DateTime.
      expect(q.resetsAt.isUtc, isFalse);
    });

    test('is not exhausted with remaining > 0', () {
      final q = GenerationQuota.fromJson(
          {'limit': 50, 'used': 3, 'remaining': 47, 'resets_at': '2026-08-05T00:00:00Z'});
      expect(q.exhausted, isFalse);
    });
  });

  group('GenerationStatusView', () {
    test('recognises terminal states', () {
      expect(const GenerationStatusView(status: 'succeeded').isSucceeded, isTrue);
      expect(const GenerationStatusView(status: 'failed').isFailed, isTrue);
      expect(const GenerationStatusView(status: 'running').isTerminal, isFalse);
      expect(const GenerationStatusView(status: 'pending').isTerminal, isFalse);
    });
  });

  group('AppUser', () {
    test('parses the generation quota but never persists it (avoids staleness)', () {
      final user = AppUser.fromJson({
        'id': 'u1',
        'name': 'Denis',
        'generation': {'limit': 50, 'used': 1, 'remaining': 49, 'resets_at': '2026-08-05T00:00:00Z'},
      });
      expect(user.quota, isNotNull);
      expect(user.quota!.remaining, 49);
      // toJson (the keychain cache) omits the volatile quota.
      expect(user.toJson().containsKey('generation'), isFalse);
    });
  });
}
