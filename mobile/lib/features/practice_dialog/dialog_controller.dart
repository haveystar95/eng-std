import 'dart:async';

import 'package:flutter/foundation.dart';

import 'dialog_models.dart';
import 'dialog_repository.dart';
import 'realtime_channel.dart';

/// Lifecycle of one practice dialog. A plain [ChangeNotifier] (no Riverpod), so it drives the
/// screen and is trivial to test with the [FakeDialogRepository] + [FakeRealtimeChannel].
///
/// It owns: starting the dialog (token + words), the transport subscription, the coverage bar
/// (server-authoritative `used` flags), the transcript feed, batched transcript upload (every
/// [flushEvery] or [flushBatchSize] events), the remaining-time countdown (from the token TTL),
/// and the wrap-up. It NEVER records a review or touches progress — a dialog is practice.
enum DialogStatus { idle, starting, active, finishing, finished, error }

class DialogController extends ChangeNotifier {
  DialogController({
    required this.repository,
    required this.channel,
    required this.collectionId,
    required this.clientId,
    this.flushEvery = const Duration(seconds: 5),
    this.flushBatchSize = 10,
  });

  final DialogRepository repository;
  final RealtimeChannel channel;
  final String collectionId;
  final String clientId;
  final Duration flushEvery;
  final int flushBatchSize;

  // ── Observable state ──
  DialogStatus status = DialogStatus.idle;
  DialogPhase phase = DialogPhase.connecting;
  List<TargetWord> targetWords = const [];
  List<TranscriptEvent> feed = const [];
  int remainingSeconds = 0;
  DialogSummary? summary;
  DialogErrorKind? errorKind;
  DateTime? rateResetsAt;

  int get usedCount => targetWords.where((w) => w.used).length;

  // ── Internals ──
  StreamSubscription<TranscriptEvent>? _eventsSub;
  StreamSubscription<DialogPhase>? _phaseSub;
  Timer? _countdown;
  Timer? _flushTimer;
  final List<TranscriptEvent> _pending = [];
  String? _dialogId;
  bool _finishing = false;
  bool _disposed = false;

  void _emit() {
    if (!_disposed) notifyListeners();
  }

  /// Begin: request the dialog, then connect the transport. On a start error, lands in
  /// [DialogStatus.error] with [errorKind] (and [rateResetsAt] for a rate-limit) — the screen shows
  /// the matching message and stays out of the conversation UI.
  Future<void> start() async {
    if (status != DialogStatus.idle) return;
    status = DialogStatus.starting;
    _emit();

    final DialogStart started;
    try {
      started = await repository.start(collectionId: collectionId, clientId: clientId);
    } on DialogException catch (e) {
      errorKind = e.kind;
      rateResetsAt = e.resetsAt;
      status = DialogStatus.error;
      _emit();
      return;
    } catch (_) {
      errorKind = DialogErrorKind.unknown;
      status = DialogStatus.error;
      _emit();
      return;
    }
    if (_disposed) return;

    _dialogId = started.dialogId;
    targetWords = started.targetWords;
    remainingSeconds = started.durationSeconds;
    status = DialogStatus.active;
    _emit();

    _eventsSub = channel.events.listen(_onEvent);
    _phaseSub = channel.phase.listen(_onPhase);
    try {
      await channel.connect(started);
    } catch (_) {
      errorKind = DialogErrorKind.network;
      status = DialogStatus.error;
      await _teardownChannel();
      _emit();
      return;
    }
    if (_disposed) return;

    _countdown = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _flushTimer = Timer.periodic(flushEvery, (_) => _flush());
  }

  void _onEvent(TranscriptEvent e) {
    feed = [...feed, e];
    _pending.add(e);
    _emit();
    if (_pending.length >= flushBatchSize) _flush();
  }

  void _onPhase(DialogPhase p) {
    phase = p;
    _emit();
    if (p == DialogPhase.closed) finish(); // the bot wrapped up
  }

  void _tick() {
    if (status != DialogStatus.active) return;
    remainingSeconds = (remainingSeconds - 1).clamp(0, 1 << 30);
    if (remainingSeconds <= 0) {
      _emit();
      finish(); // token TTL reached
    } else {
      _emit();
    }
  }

  Future<void> _flush() async {
    if (_dialogId == null || _pending.isEmpty) return;
    final batch = List<TranscriptEvent>.from(_pending);
    _pending.clear();
    try {
      final updated = await repository.sendTranscripts(_dialogId!, batch);
      _applyCoverage(updated);
      _emit();
    } catch (_) {
      // Transient (offline/5xx): keep the batch for a later retry — the upload is idempotent by ts.
      _pending.insertAll(0, batch);
    }
  }

  void _applyCoverage(List<TargetWord> updated) {
    final byId = {for (final w in updated) w.termId: w.used};
    targetWords = [
      for (final w in targetWords) w.copyWith(used: byId[w.termId] ?? w.used),
    ];
  }

  /// End the dialog — user tapped «Завершить», the bot wrapped up, or the TTL hit. Flushes the last
  /// transcripts, fetches the summary (falling back to a local count if the network is gone), and
  /// lands in [DialogStatus.finished] with a [summary].
  Future<void> finish() async {
    if (_finishing || status == DialogStatus.finished) return;
    _finishing = true;
    status = DialogStatus.finishing;
    _countdown?.cancel();
    _flushTimer?.cancel();
    _emit();

    await _flush();

    DialogSummary result;
    if (_dialogId != null) {
      try {
        result = await repository.finish(_dialogId!);
      } catch (_) {
        result = _localSummary();
      }
    } else {
      result = _localSummary();
    }
    if (_disposed) return;
    summary = result;
    status = DialogStatus.finished;
    _emit();
    await _teardownChannel();
  }

  DialogSummary _localSummary() =>
      DialogSummary(summary: '', wordsUsed: usedCount, wordsTotal: targetWords.length);

  Future<void> _teardownChannel() async {
    await _eventsSub?.cancel();
    await _phaseSub?.cancel();
    _eventsSub = null;
    _phaseSub = null;
    await channel.close();
  }

  @override
  void dispose() {
    _disposed = true;
    _countdown?.cancel();
    _flushTimer?.cancel();
    _eventsSub?.cancel();
    _phaseSub?.cancel();
    channel.close();
    super.dispose();
  }
}
