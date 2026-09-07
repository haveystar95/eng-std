import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/data/speech/speech_turn.dart';

/// Плагин, которым управляет тест: каждый `listenOnce` — открытая попытка, в которую тест кладёт
/// частичные результаты ([say]) и закрывает её тем исходом, каким закрыл бы iOS ([close]).
class _DrivenRecognizer implements SpeechRecognizer {
  int opened = 0;
  int cancels = 0;
  int stops = 0;
  Completer<SpeechAttempt>? _pending;
  ValueChanged<String>? _onPartial;
  final List<Duration> pauseFors = [];

  @override
  bool get isReady => true;

  @override
  Future<bool> get hasPermission async => true;

  @override
  Future<bool> prepare() async => true;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) {
    opened++;
    pauseFors.add(pauseFor);
    _onPartial = onPartial;
    final completer = Completer<SpeechAttempt>();
    _pending = completer;

    return completer.future;
  }

  bool get isOpen => _pending != null && !_pending!.isCompleted;

  void say(String text) => _onPartial?.call(text);

  void close(SpeechAttempt attempt) {
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(attempt);
  }

  @override
  Future<void> stop() async {
    stops++;
    // Как плагин: `stop` отдаёт последний кусок — здесь тест закрывает попытку сам.
  }

  @override
  Future<void> cancel() async {
    cancels++;
    close(const SpeechAttempt.silent());
  }
}

/// ДВИЖОК ОДНОГО ХОДА — замки правил Ч.1.2–Ч.1.5 наряда DAY-FIX-3.
void main() {
  const config = SpeechTurnConfig(
    silenceAfterSpeech: Duration(seconds: 2),
    maxSpeech: Duration(seconds: 15),
    silenceBeforeSkip: Duration(seconds: 15),
    reopenGap: Duration(milliseconds: 100),
  );

  test('finalResult плагина не закрывает попытку: результаты копятся в склейку до тишины', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Запинка: iOS закрыл попытку на «my back» через секунду.
      fake.elapse(const Duration(seconds: 1));
      mic.say('my back');
      mic.close(const SpeechAttempt.heard('my back'));
      fake.elapse(const Duration(milliseconds: 200));

      // Микрофон переоткрыт, попытка жива, ход не отдан.
      expect(mic.opened, 2);
      expect(result, isNull);

      // …человек договаривает, и через две секунды ТИШИНЫ попытка закрывается склейкой.
      fake.elapse(const Duration(milliseconds: 800));
      mic.say('hurts');
      fake.elapse(const Duration(seconds: 1));
      expect(result, isNull);
      fake.elapse(const Duration(seconds: 1));

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'my back hurts');
      expect(mic.cancels, greaterThan(0));
    });
  });

  test('мёртвый канал: три мгновенные пустые попытки подряд — unavailable, а не сторож через 15 с', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Симулятор / отозванное разрешение: плагин закрывается пустым сразу после открытия.
      for (var i = 0; i < 3; i++) {
        fake.elapse(const Duration(milliseconds: 50));
        mic.close(const SpeechAttempt.silent());
        fake.elapse(const Duration(milliseconds: 150));
      }

      expect(result?.outcome, SpeechTurnOutcome.unavailable);
      expect(mic.opened, 3);
    });
  });

  test('одна мгновенная пустота — ещё не мёртвый канал: микрофон переоткрывается, сторож ждёт', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      fake.elapse(const Duration(milliseconds: 50));
      mic.close(const SpeechAttempt.silent());
      fake.elapse(const Duration(milliseconds: 150));
      // Второй заход живёт нормально — человек просто молчит; сторож придёт в свой срок.
      fake.elapse(const Duration(seconds: 3));
      mic.close(const SpeechAttempt.silent());
      fake.elapse(const Duration(milliseconds: 150));

      expect(result, isNull);
      expect(mic.opened, 3);
      fake.elapse(const Duration(seconds: 15));
      expect(result?.outcome, SpeechTurnOutcome.silent);
    });
  });

  test('потолок речи — 15 с от ПЕРВОГО слова, не от открытия микрофона', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Человек думает 10 секунд, потом говорит без пауз по слову в секунду.
      fake.elapse(const Duration(seconds: 10));
      final words = <String>[];
      for (var i = 0; i < 16; i++) {
        words.add('w$i');
        mic.say(words.join(' '));
        fake.elapse(const Duration(seconds: 1));
        if (i < 14) expect(result, isNull, reason: 'секунда ${i + 1} речи — попытка ещё открыта');
      }

      expect(result?.outcome, SpeechTurnOutcome.heard);
      // Ровно то, что успело прозвучать за 15 секунд речи.
      expect(result?.transcript.split(' ').length, 15);
    });
  });

  test('сторож: 15 с тишины от открытия без единого слова — silent; слово в последнюю секунду снимает его', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Плагин сам закрывает пустые попытки каждые 5 секунд — они переоткрываются молча.
      fake.elapse(const Duration(seconds: 5));
      mic.close(const SpeechAttempt.silent());
      fake.elapse(const Duration(seconds: 5));
      mic.close(const SpeechAttempt.silent());
      fake.elapse(const Duration(milliseconds: 200));
      expect(result, isNull);
      expect(mic.opened, 3);

      fake.elapse(const Duration(milliseconds: 4800));
      expect(result?.outcome, SpeechTurnOutcome.silent);
      expect(result?.transcript, '');
    });

    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      fake.elapse(const Duration(seconds: 14));
      mic.say('a fever');
      fake.elapse(const Duration(seconds: 1));
      // Сторож не сработал: речь началась.
      expect(result, isNull);
      fake.elapse(const Duration(seconds: 1));
      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'a fever');
    });
  });

  test('обрыв канала после начала речи — incomplete, а не ответ; до речи — unavailable', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      mic.say('my ba');
      mic.close(const SpeechAttempt.unavailable());
      fake.flushMicrotasks();

      expect(result?.outcome, SpeechTurnOutcome.incomplete);
      expect(result?.transcript, 'my ba');
    });

    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      mic.close(const SpeechAttempt.unavailable());
      fake.flushMicrotasks();

      expect(result?.outcome, SpeechTurnOutcome.unavailable);
    });
  });

  test('эхо-замок: узнанная реплика роли без ключа выбрасывается, микрофон переоткрывается', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      final partials = <String>[];
      SpeechTurnResult? result;
      turn
          .listen(
            expected: const ['a fever'],
            localeId: 'en_US',
            isAnswer: (t) => t.contains('fever'),
            echoOf: (t) => t.contains('what seems to be the problem'),
            onPartial: partials.add,
          )
          .then((r) => result = r);
      fake.flushMicrotasks();

      // Динамик: реплика собеседника попала в микрофон.
      mic.say('what seems to be');
      mic.say('what seems to be the problem');
      mic.close(const SpeechAttempt.heard('what seems to be the problem'));
      fake.elapse(const Duration(milliseconds: 200));

      expect(result, isNull);
      expect(mic.opened, 2, reason: 'микрофон переоткрыт');
      expect(turn.transcript, '', reason: 'склейка выброшена');
      // Живой «Услышали: …» на экране тоже очищен.
      expect(partials.last, '');

      // Теперь человек: ключ узнан по дороге — договорил.
      mic.say('I have a fever');
      fake.flushMicrotasks();

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'I have a fever');
      expect(result?.echoes, 1);
    });
  });

  test('склейка, в которой ключ УЗНАН, не считается эхом, даже если реплика роли в ней тоже есть', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn
          .listen(
            expected: const ['insurance'],
            localeId: 'en_US',
            isAnswer: (t) => t.contains('insurance'),
            echoOf: (t) => t.contains('do you have insurance'),
          )
          .then((r) => result = r);
      fake.flushMicrotasks();

      mic.say('do you have insurance yes I have insurance');
      fake.flushMicrotasks();

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.echoes, 0);
    });
  });

  test('«Готово» закрывает попытку тем, что есть; «сразу» меряется от первого слова', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      // Часы теста: идут вместе с `fake.elapse`, чтобы «сразу» было измеримо.
      final opened = DateTime(2026, 9, 7, 12);
      var elapsed = Duration.zero;
      final turn = SpeechTurn(mic, config: config, now: () => opened.add(elapsed));
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      fake.elapse(const Duration(seconds: 4));
      elapsed = const Duration(seconds: 4);
      mic.say('my back');
      fake.elapse(const Duration(milliseconds: 500));
      turn.stop();
      fake.flushMicrotasks();

      expect(mic.stops, 1);
      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'my back');
      expect(result?.speechStartedAt, opened.add(const Duration(seconds: 4)));
    });
  });

  test('cancel бросает ход: ничего не хранится, плагин закрыт', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();
      mic.say('my back');
      turn.cancel();
      fake.flushMicrotasks();

      expect(result?.outcome, SpeechTurnOutcome.silent);
      expect(turn.isListening, isFalse);
      expect(mic.cancels, greaterThan(0));
    });
  });

  test('плагину отдаётся пауза после речи из конфига — окно, а не правило', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config.copyWith(silenceAfterSpeech: const Duration(seconds: 3)));
      turn.listen(expected: const [], localeId: 'en_US');
      fake.flushMicrotasks();

      expect(mic.pauseFors.single, const Duration(seconds: 3));
      turn.cancel();
      fake.flushMicrotasks();
    });
  });
}
