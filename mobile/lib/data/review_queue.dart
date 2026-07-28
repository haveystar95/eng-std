import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One graded answer waiting to be uploaded. Carries its own client ULID (the
/// idempotency key backend2 dedupes on) and the moment it was actually answered,
/// so a replayed offline batch folds into progress exactly like the online path.
class PendingReview {
  final String id; // client ULID — the /reviews/batch idempotency key
  final String termId;
  final String grade; // again | hard | good | easy
  final String answeredAt; // ISO-8601 UTC, captured at answer time
  final int? latencyMs;

  const PendingReview({
    required this.id,
    required this.termId,
    required this.grade,
    required this.answeredAt,
    this.latencyMs,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'term_id': termId,
        'grade': grade,
        'answered_at': answeredAt,
        'latency_ms': latencyMs,
      };

  /// The exact shape `/reviews/batch` expects (latency_ms omitted when null).
  Map<String, dynamic> toBatchJson() => {
        'id': id,
        'term_id': termId,
        'grade': grade,
        'answered_at': answeredAt,
        if (latencyMs != null) 'latency_ms': latencyMs,
      };

  factory PendingReview.fromJson(Map<String, dynamic> j) => PendingReview(
        id: j['id'] as String,
        termId: j['term_id'] as String,
        grade: j['grade'] as String,
        answeredAt: j['answered_at'] as String,
        latencyMs: j['latency_ms'] as int?,
      );
}

/// Durable FIFO of un-uploaded reviews, persisted as one JSON blob in the
/// keychain. Small by design — a session is ~20 answers, each a few dozen bytes.
class ReviewQueue {
  static const _key = 'pending_reviews';
  final _storage = const FlutterSecureStorage();

  Future<List<PendingReview>> load() async {
    final raw = await _storage.read(key: _key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list.map((e) => PendingReview.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      // Corrupt payload — drop it rather than wedge the queue forever.
      await _storage.delete(key: _key);
      return [];
    }
  }

  Future<void> save(List<PendingReview> reviews) async {
    if (reviews.isEmpty) {
      await _storage.delete(key: _key);
      return;
    }
    await _storage.write(key: _key, value: jsonEncode(reviews.map((e) => e.toJson()).toList()));
  }
}
