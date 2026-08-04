import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// One triage swipe waiting to be uploaded. Carries its own client ULID (the
/// idempotency key `POST /triage/batch` dedupes on) and a per-user monotonic
/// `clientSeq`: the server picks the current verdict for a (user, term) by the
/// greatest clientSeq, NOT by `decidedAt` (a device clock that can lag and roll
/// back a genuinely-later verdict). `decidedAt` rides along for analytics only.
class PendingTriage {
  final String id; // client ULID — the idempotency key
  final String termId;
  final String verdict; // known | unknown | unsure
  final String? collectionId; // where the swipe came from (analytics)
  final String decidedAt; // ISO-8601 UTC, captured at swipe time (reference-only)
  final int clientSeq; // per-user monotonic order key — decides the current verdict
  final int? latencyMs; // measured by the client; null when it couldn't be measured
  final bool? revealed; // flipped the card before deciding — a risk signal for «known»

  const PendingTriage({
    required this.id,
    required this.termId,
    required this.verdict,
    required this.decidedAt,
    required this.clientSeq,
    this.collectionId,
    this.latencyMs,
    this.revealed,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'term_id': termId,
        'verdict': verdict,
        'collection_id': collectionId,
        'decided_at': decidedAt,
        'client_seq': clientSeq,
        'latency_ms': latencyMs,
        'revealed': revealed,
      };

  /// The exact shape `/triage/batch` expects. latency_ms is sent as null (not
  /// omitted) when unmeasured — the server treats null neutrally; a 0 would once
  /// have read as "too fast to have read it", so the client must never send 0.
  Map<String, dynamic> toBatchJson() => {
        'id': id,
        'term_id': termId,
        'verdict': verdict,
        if (collectionId != null) 'collection_id': collectionId,
        'decided_at': decidedAt,
        'client_seq': clientSeq,
        'latency_ms': latencyMs, // null-safe: null stays null
        if (revealed != null) 'revealed': revealed,
      };

  factory PendingTriage.fromJson(Map<String, dynamic> j) => PendingTriage(
        id: j['id'] as String,
        termId: j['term_id'] as String,
        verdict: j['verdict'] as String,
        collectionId: j['collection_id'] as String?,
        decidedAt: j['decided_at'] as String,
        clientSeq: (j['client_seq'] as int?) ?? 0, // legacy queued items → 0 (server treats as oldest)
        latencyMs: j['latency_ms'] as int?,
        revealed: j['revealed'] as bool?,
      );
}

/// Durable FIFO of un-uploaded triage swipes, persisted as one JSON blob in the
/// keychain — so an app kill mid-triage loses nothing.
class TriageQueue {
  static const _key = 'pending_triages';
  final _storage = const FlutterSecureStorage();

  Future<List<PendingTriage>> load() async {
    final raw = await _storage.read(key: _key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list.map((e) => PendingTriage.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      await _storage.delete(key: _key); // corrupt payload — drop rather than wedge
      return [];
    }
  }

  Future<void> save(List<PendingTriage> triages) async {
    if (triages.isEmpty) {
      await _storage.delete(key: _key);
      return;
    }
    await _storage.write(key: _key, value: jsonEncode(triages.map((e) => e.toJson()).toList()));
  }
}
