import 'dart:convert';

import 'package:drift/drift.dart' show Value;
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'local/app_database.dart';

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

  /// The acquisition rung the card was dealt at (1–5), echoed back with the answer; null off the
  /// ladder. It is what tells the server this answer was a rung-1 TAP, which is graded by identity:
  /// [response] then carries the tapped option's TERM ID, not its text. Without it the server falls
  /// back to text grading, compares a translation against the term's own forms, and folds a correct
  /// tap as a lapse. 0 is never sent — that is the intro, which is an exposure, not an answer.
  final int? ladderStep;

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
    this.ladderStep,
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
        'ladder_step': ladderStep,
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
        // The contract accepts 1–5 only; 0 (intro) is an exposure, not an answer, and a queue row
        // written before this field existed has none — both are simply omitted.
        if (ladderStep != null && ladderStep! >= 1 && ladderStep! <= 5) 'ladder_step': ladderStep,
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
        ladderStep: (j['ladder_step'] as num?)?.toInt(),
      );
}

/// Durable FIFO of un-uploaded reviews, held in the local drift DB.
///
/// It used to be one JSON blob in the Keychain, which meant every answer re-serialised and rewrote
/// the WHOLE queue on the UI isolate. That was fine while the queue never exceeded one entry, but
/// two changes made it bite: practice now batches a whole session before uploading, and a server
/// 500 (transient by definition) can hold answers for a day. Measured: one `record()` at 650 ms.
/// drift runs in a background isolate, so an append costs the UI isolate nothing (F20-r2).
///
/// The queue is application data, not a secret — the Keychain was simply the wrong store. The
/// per-user monotonic [SeqCounter] deliberately STAYS in the Keychain: it exists to survive this
/// queue being cleared, so the two must not share a lifetime.
class ReviewQueue {
  ReviewQueue(this._db, [FlutterSecureStorage? storage])
      : _storage = storage ?? const FlutterSecureStorage();

  /// The Keychain key the queue used to live under. Read once, then retired.
  static const legacyKey = 'pending_reviews';

  /// Set in sync_meta once the legacy blob has been imported, so a failed delete can't cause a
  /// re-import loop. (The import is idempotent anyway — same client ULIDs — but the marker makes
  /// the intent explicit and skips the Keychain read entirely on later launches.)
  static const migratedMetaKey = 'review_queue_migrated';

  final AppDatabase _db;
  final FlutterSecureStorage _storage;

  Future<List<PendingReview>> load() async {
    final rows = await _db.reviewQueue();
    return [for (final r in rows) _fromRow(r)];
  }

  /// Append one answer. Single insert — no whole-queue rewrite.
  Future<void> add(PendingReview review) => _db.enqueueReview(_toRow(review));

  Future<void> removeIds(Iterable<String> ids) => _db.dequeueReviews(ids);

  Future<int> length() => _db.reviewQueueLength();

  Future<List<String>> oldestPracticeIds(int limit) => _db.oldestPracticeReviewIds(limit);

  /// One-time import of the legacy Keychain blob. Order of operations is deliberate: rows are
  /// written to drift FIRST, the marker is set only after that write returns, and the blob is
  /// deleted last. A crash anywhere leaves the blob in place and the import simply repeats — and
  /// repeating is safe because the client ULID is the primary key.
  ///
  /// A corrupt blob is dropped rather than left to wedge the queue forever, exactly as before.
  Future<void> migrateFromKeychain() async {
    if (await _db.getMeta(migratedMetaKey) != null) return;

    String? raw;
    try {
      raw = await _storage.read(key: legacyKey);
    } catch (e) {
      debugPrint('ReviewQueue: legacy read failed, leaving the blob for the next launch: $e');
      return; // no marker → we try again next start
    }

    if (raw != null && raw.isNotEmpty) {
      List<PendingReview> legacy;
      try {
        legacy = (jsonDecode(raw) as List)
            .map((e) => PendingReview.fromJson(e as Map<String, dynamic>))
            .toList();
      } catch (_) {
        legacy = const []; // corrupt → drop it, same as the old behaviour
      }
      await _db.importReviewQueue([for (final r in legacy) _toRow(r)]);
    }

    await _db.setMeta(migratedMetaKey, '1');
    try {
      await _storage.delete(key: legacyKey);
    } catch (e) {
      // The marker is already set, so the blob is inert — it just lingers.
      debugPrint('ReviewQueue: legacy blob delete failed (harmless, already imported): $e');
    }
  }

  static ReviewQueueRowsCompanion _toRow(PendingReview r) => ReviewQueueRowsCompanion.insert(
        id: r.id,
        termId: r.termId,
        exerciseMode: r.exerciseMode,
        response: r.response,
        clientSeq: r.clientSeq,
        answeredAt: r.answeredAt,
        usedHint: Value(r.usedHint),
        isPractice: Value(r.isPractice),
        latencyMs: Value(r.latencyMs),
        sessionId: Value(r.sessionId),
        ladderStep: Value(r.ladderStep),
      );

  static PendingReview _fromRow(ReviewQueueRow r) => PendingReview(
        id: r.id,
        termId: r.termId,
        exerciseMode: r.exerciseMode,
        response: r.response,
        clientSeq: r.clientSeq,
        answeredAt: r.answeredAt,
        usedHint: r.usedHint,
        isPractice: r.isPractice,
        latencyMs: r.latencyMs,
        sessionId: r.sessionId,
        ladderStep: r.ladderStep,
      );
}
