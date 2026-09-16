/// ОТЛОЖЕННЫЕ ОТВЕТЫ СЕССИИ — ответ ученика не теряется, когда падает сеть (наряд SESSION-1b, разд. 1).
///
/// Ответ уходит в очередь и отправляется по одному, по порядку. Сеть упала — ответ остаётся в очереди,
/// [offline] поднимает баннер «нет связи», повтор идёт с растущей паузой и сразу, как только сеть
/// вернулась ([retryNow]). Сессия не открывает следующую карточку, пока очередь не опустела ([drained]).
///
/// Очередь живёт, пока открыт экран: на диск она не пишется. Сервер — источник правды, и карточка, чей
/// ответ не дошёл до закрытия приложения, при следующем входе просто придёт неотвеченной.
library;

import 'dart:async';
import 'dart:collection';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../api_client.dart';
import 'session_outcomes.dart';

/// Отправка одного ответа.
typedef AnswerTransport = Future<SessionAnswerOutcome> Function(String cardId, SessionAnswer answer);

/// Чем кончилась попытка отправки.
enum OutboxFailure {
  /// Нет сети, таймаут, 5xx, 429 — повторить позже.
  transient,

  /// 409 `plan_card_answered` — сервер ответ уже знает: считать доставленным.
  alreadyAnswered,

  /// 4xx, который повтор не исправит (422 — вид такого итога не принимает): выбросить.
  permanent,
}

/// Результат доставки: ответ сервера или null (карточка уже отвечена / ответ отбит).
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

  /// 1, 2, 4, 8 с и дальше не реже раза в 15 с.
  static Duration defaultBackoff(int failures) {
    final seconds = failures <= 1 ? 1 : (1 << (failures - 1));
    return Duration(seconds: seconds > 15 ? 15 : seconds);
  }

  /// Последняя попытка упала на сети — баннер «нет связи».
  bool get offline => _offline;

  /// Ничего не ждёт отправки.
  bool get isEmpty => _queue.isEmpty && !_sending;

  int get pending => _queue.length + (_sending ? 1 : 0);

  /// Завершается, когда очередь пуста (сразу, если уже пуста).
  Future<void> get drained {
    if (isEmpty) return Future<void>.value();
    return (_drained ??= Completer<void>()).future;
  }

  /// Поставить ответ в очередь; [onDelivered] позовётся ровно один раз.
  void enqueue(String cardId, SessionAnswer answer, OutboxDelivered onDelivered) {
    _queue.add(_Pending(cardId, answer, onDelivered));
    _notify();
    unawaited(_pump());
  }

  /// Сеть вернулась — не ждать паузы.
  void retryNow() {
    if (_queue.isEmpty || _sending) return;
    _retry?.cancel();
    _retry = null;
    unawaited(_pump());
  }

  /// Как читать ошибку отправки.
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
