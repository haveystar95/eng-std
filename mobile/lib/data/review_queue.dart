import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One RAW answer waiting to be uploaded. The client sends what the user actually did —
/// [exerciseMode], the raw [response] text, whether a hint was used, latency — and the SERVER
/// grades it, so the grading rule lives in one place (invariant: only the server grades). Carries
/// its own client ULID (the `/reviews/batch` idempotency key) and a per-user monotonic [clientSeq]
/// (from `seq_review`, surviving queue clears) so the server folds answers into progress in
/// sequence order, never by the device clock. A replayed offline batch folds identically.
class PendingReview {
  final String id; // client ULID — the /reviews/batch idempotency key
  final String termId;
  final String exerciseMode; // multiple_choice | word_bank | typing | listening | cloze
  final String response; // the user's raw answer text
  final int clientSeq; // per-user monotonic order (seq_review)
  final String answeredAt; // ISO-8601 UTC, reference-only (device clock)
  final bool usedHint;
  final bool isPractice;
  final int? latencyMs;
  final String? sessionId;

  const PendingReview({
    required this.id,
    required this.termId,
    required this.exerciseMode,
    required this.response,
    required this.clientSeq,
    required this.answeredAt,
    this.usedHint = false,
    this.isPractice = false,
    this.latencyMs,
    this.sessionId,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'term_id': termId,
        'exercise_mode': exerciseMode,
        'response': response,
        'client_seq': clientSeq,
        'answered_at': answeredAt,
        'used_hint': usedHint,
        'is_practice': isPractice,
        'latency_ms': latencyMs,
        'session_id': sessionId,
      };

  /// The exact shape `/reviews/batch` expects (optional keys omitted when null/default).
  Map<String, dynamic> toBatchJson() => {
        'id': id,
        'term_id': termId,
        'exercise_mode': exerciseMode,
        'response': response,
        'client_seq': clientSeq,
        'answered_at': answeredAt,
        if (usedHint) 'used_hint': usedHint,
        if (isPractice) 'is_practice': isPractice,
        if (latencyMs != null) 'latency_ms': latencyMs,
        if (sessionId != null) 'session_id': sessionId,
      };

  factory PendingReview.fromJson(Map<String, dynamic> j) => PendingReview(
        id: j['id'] as String,
        termId: j['term_id'] as String,
        exerciseMode: (j['exercise_mode'] as String?) ?? 'typing',
        response: (j['response'] as String?) ?? '',
        clientSeq: (j['client_seq'] as int?) ?? 0,
        answeredAt: j['answered_at'] as String,
        usedHint: (j['used_hint'] as bool?) ?? false,
        isPractice: (j['is_practice'] as bool?) ?? false,
        latencyMs: j['latency_ms'] as int?,
        sessionId: j['session_id'] as String?,
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
