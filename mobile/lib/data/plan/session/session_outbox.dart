/// DEFERRED SESSION ANSWERS — the learner's answer is not lost when the network drops (work order SESSION-1b,
/// section 1).
///
/// An answer goes into the queue and is sent one at a time, in order. The network dropped — the answer stays in
/// the queue, [offline] raises the «no connection» banner, the retry goes with a growing pause and immediately as
/// soon as the network is back ([retryNow]). The session does not open the next card until the queue is empty
/// ([drained]).
///
/// The queue lives while the screen is open: it is not written to disk. The server is the source of truth, and a
/// card whose answer did not get through before the app was closed simply arrives unanswered on the next entry.
library;

import 'dart:async';
import 'dart:collection';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../api_client.dart';
import 'session_outcomes.dart';

/// Sending one answer.
typedef AnswerTransport = Future<SessionAnswerOutcome> Function(String cardId, SessionAnswer answer);

/// How a send attempt ended.
enum OutboxFailure {
  /// No network, timeout, 5xx, 429 — retry later.
  transient,

  /// 409 `plan_card_answered` — the server already knows the answer: treat it as delivered.
  alreadyAnswered,

  /// A 4xx that a retry will not fix (422 — the kind does not accept such a result): drop it.
  permanent,
}

/// The delivery result: the server's response or null (the card is already answered / the answer was rejected).
typedef OutboxDelivered = void Function(SessionAnswerOutcome? outcome, OutboxFailure? failure);

class _Pending {
  _Pending(this.cardId, this.answer, this.onDelivered);

  final String cardId;
  final SessionAnswer answer;
  final OutboxDelivered onDelivered;
}

class AnswerOutbox extends ChangeNotifier {
  AnswerOutbox({required this._send, Duration Function(int failures)? backoff})
    : _backoff = backoff ?? defaultBackoff;

  final AnswerTransport _send;
  final Duration Function(int failures) _backoff;
  final Queue<_Pending> _queue = Queue();

  bool _sending = false;
  bool _offline = false;
  bool _disposed = false;
  int _failures = 0;
  Timer? _retry;
  Completer<void>? _drained;

  /// 1, 2, 4, 8 s and after that no less often than once every 15 s.
  static Duration defaultBackoff(int failures) {
    final seconds = failures <= 1 ? 1 : (1 << (failures - 1));
    return Duration(seconds: seconds > 15 ? 15 : seconds);
  }

  /// The last attempt failed on the network — the «no connection» banner.
  bool get offline => _offline;

  /// Nothing is waiting to be sent.
  bool get isEmpty => _queue.isEmpty && !_sending;

  int get pending => _queue.length + (_sending ? 1 : 0);

  /// Completes when the queue is empty (immediately if it is already empty).
  Future<void> get drained {
    if (isEmpty) return Future<void>.value();
    return (_drained ??= Completer<void>()).future;
  }

  /// Put an answer into the queue; [onDelivered] will be called exactly once.
  void enqueue(String cardId, SessionAnswer answer, OutboxDelivered onDelivered) {
    _queue.add(_Pending(cardId, answer, onDelivered));
    _notify();
    unawaited(_pump());
  }

  /// The network is back — do not wait for the pause.
  void retryNow() {
    if (_queue.isEmpty || _sending) return;
    _retry?.cancel();
    _retry = null;
    unawaited(_pump());
  }

  /// How to read a send error.
  static OutboxFailure classify(Object error) {
    if (error is! DioException) return OutboxFailure.transient;
    if (isOffline(error)) return OutboxFailure.transient;
    final status = error.response?.statusCode;
    if (status == null) return OutboxFailure.transient;
    if (status == 409 && problemCode(error) == 'plan_card_answered') return OutboxFailure.alreadyAnswered;
    if (status == 429 || status == 408 || status >= 500) return OutboxFailure.transient;
    return OutboxFailure.permanent;
  }

  Future<void> _pump() async {
    if (_sending || _disposed) return;
    while (_queue.isNotEmpty && !_disposed) {
      _sending = true;
      final head = _queue.first;
      try {
        final outcome = await _send(head.cardId, head.answer);
        _queue.removeFirst();
        _failures = 0;
        _setOffline(false);
        head.onDelivered(outcome, null);
      } catch (error) {
        final failure = classify(error);
        if (failure == OutboxFailure.transient) {
          _failures++;
          _setOffline(true);
          _sending = false;
          debugPrint('[session] answer ${head.cardId} not sent ($_failures): $error');
          _retry?.cancel();
          _retry = Timer(_backoff(_failures), () {
            _retry = null;
            unawaited(_pump());
          });
          _notify();
          return;
        }
        _queue.removeFirst();
        _failures = 0;
        _setOffline(false);
        if (failure == OutboxFailure.permanent) debugPrint('[session] answer ${head.cardId} refused: $error');
        head.onDelivered(null, failure);
      } finally {
        _sending = false;
      }
    }
    _notify();
    if (_queue.isEmpty) {
      final waiting = _drained;
      _drained = null;
      if (waiting != null && !waiting.isCompleted) waiting.complete();
    }
  }

  void _setOffline(bool value) {
    if (_offline == value) return;
    _offline = value;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _retry?.cancel();
    final waiting = _drained;
    _drained = null;
    if (waiting != null && !waiting.isCompleted) waiting.complete();
    super.dispose();
  }
}
