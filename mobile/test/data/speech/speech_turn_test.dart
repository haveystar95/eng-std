import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart' show MethodChannel;
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/speech/speech_diagnostics.dart';
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
  final List<Duration> timeouts = [];

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
    ValueChanged<double>? onLevel,
  }) {
    opened++;
    pauseFors.add(pauseFor);
    timeouts.add(timeout);
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

/// ДВИЖОК ОДНОЙ ЗАПИСИ — замки правил Ч.2 наряда SPEECH-2 (и того, что от DAY-FIX-3 уцелело).
void main() {
  // Числа движка — его собственные (наряд FIX-1, п. 3: секунда тишины, пола ожидания нет); тест
  // трогает только окно переоткрытия, чтобы им можно было управлять.
  const config = SpeechTurnConfig(reopenGap: Duration(milliseconds: 100));

  // ПРАВИЛО: DAY-FIX-3, Ч.1.2 — плагин не владеет концом попытки; результаты копятся в склейку.
  // ЛОВИТ: возврат к «finalResult = конец ответа». iOS ставит его на любой запинке короче трёх
  // секунд, и «My… back hurts» приходило как «My» — то есть ошибкой в append-only журнале.
  test('finalResult плагина не закрывает запись: результаты копятся в склейку до тишины', () {
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

      // Микрофон переоткрыт, запись жива, ход не отдан.
      expect(mic.opened, 2);
      expect(result, isNull);

      // …человек договаривает — и запись закрывается склейкой через секунду после последнего слова.
      fake.elapse(const Duration(milliseconds: 300));
      mic.say('hurts');
      fake.elapse(const Duration(milliseconds: 500));
      expect(result, isNull, reason: 'тишина закрыла запись раньше своей секунды');
      fake.elapse(const Duration(milliseconds: 700));

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'my back hurts');
      expect(mic.cancels, greaterThan(0));
    });
  });

  // ПРАВИЛО: наряд SPEECH-2, Ч.2.1 — движок НЕ закрывается по совпадению; закрывают его тишина,
  // тап, сторож записи и ошибка канала, и больше ничего.
  // ЛОВИТ: возврат `isAnswer`. На телефоне 08.09 это выглядело так: человек говорит длинную
  // реплику, на первом узнанном ключевом слове микрофон закрывается и карточка ставит «верно».
  // Фразу никто не дослушал — а тренажёр, который учит фразам, засчитал слово.
  test('ключ, прозвучавший в начале длинной фразы, запись НЕ закрывает', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const ['a place to rent'], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Ключ — первыми же словами.
      mic.say('a place to rent');
      fake.elapse(const Duration(milliseconds: 500));
      expect(result, isNull, reason: 'запись закрылась на узнанном ключе');

      // …и человек продолжает: остальная реплика доезжает в ту же склейку.
      mic.say('a place to rent for long term living');
      fake.elapse(const Duration(seconds: 6));

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(
        result?.transcript,
        'a place to rent for long term living',
        reason: 'на зачёт ушёл первый частичный результат, а не финальная склейка (Ч.2.3)',
      );
    });
  });

  // ПРАВИЛО: наряд FIX-1, п. 3 — ОДНО правило на все микрофоны приложения: запись закрывает секунда
  // тишины после последнего слова или тап, и оценивается сказанное целиком.
  // ЛОВИТ: возврат пола ожидания в пять секунд и любую «свою» паузу у отдельного экрана.
  test('запись закрывает секунда тишины после последнего слова — и пола под ней нет', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      mic.say('I');
      fake.elapse(const Duration(milliseconds: 800));
      expect(result, isNull, reason: 'запись закрылась раньше секунды тишины');

      fake.elapse(const Duration(milliseconds: 400));
      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'I');
    });
  });

  // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b): ТИШИНА ПО ДЛИНЕ. Пока `heardAll` говорит «нет» — не все
  // смысловые слова строки услышаны, — запись ждёт [SpeechTurnConfig.silenceWhileIncomplete] (2 с); как только
  // говорит «да», закрывает прежняя секунда. Мерится от последнего слова, как и была; кто `heardAll` не дал
  // (разговор, коллекции), живёт по одной секунде.
  // ЛОВИТ: ученика, которого обрывают через секунду посреди строки, пока он ищет следующее слово (проход 21.09), и
  // строку, сказанную целиком, которая держит лишнюю секунду.
  test('тишина ждёт, пока не услышаны все слова: 2 с посреди строки, 1 с после неё', () {
    fakeAsync((fake) {
      bool whole(String heard) => heard.split(' ').length >= 4;

      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const ['my lower back hurts'], localeId: 'en_US', heardAll: whole).then((r) => result = r);
      fake.flushMicrotasks();

      mic.say('my lower');
      fake.elapse(const Duration(milliseconds: 1500));
      expect(result, isNull, reason: 'посреди строки секунды мало — тишина ждёт недосказанное');
      mic.say('my lower back hurts');
      fake.elapse(const Duration(milliseconds: 900));
      expect(result, isNull);
      fake.elapse(const Duration(milliseconds: 200));
      expect(result?.transcript, 'my lower back hurts', reason: 'строка целиком — закрывает прежняя секунда');

      final short = _DrivenRecognizer();
      SpeechTurnResult? cut;
      SpeechTurn(short, config: config).listen(expected: const ['my lower back hurts'], localeId: 'en_US', heardAll: whole).then((r) => cut = r);
      fake.flushMicrotasks();
      short.say('my lower');
      fake.elapse(const Duration(milliseconds: 1900));
      expect(cut, isNull);
      fake.elapse(const Duration(milliseconds: 200));
      expect(cut?.transcript, 'my lower', reason: 'недосказанное закрывают две секунды тишины, не больше');

      final any = _DrivenRecognizer();
      SpeechTurnResult? talk;
      SpeechTurn(any, config: config).listen(expected: const [], localeId: 'en_US').then((r) => talk = r);
      fake.flushMicrotasks();
      any.say('my lower');
      fake.elapse(const Duration(milliseconds: 1100));
      expect(talk?.transcript, 'my lower', reason: 'без `heardAll` — одна секунда, как у всех микрофонов');
    });
  });

  // ДЕФЕКТ: «термин из двух слов режется до первого» (живой проход 18.09, микрофон в коллекциях).
  // ПРАВИЛО: наряд FIX-1, п. 3 — глухота переоткрытия не считается тишиной.
  // ЛОВИТ: отсчёт тишины, который идёт от последнего слова СКВОЗЬ закрытый микрофон. Плагин
  // закрывался на запинке после первого слова, переоткрытие занимало свои сотни миллисекунд, и эта
  // глухота добирала нашу секунду, пока человек договаривал второе слово.
  test('термин из двух слов не режется до первого: глухота переоткрытия не считается тишиной', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: const SpeechTurnConfig(reopenGap: Duration(milliseconds: 700)));
      SpeechTurnResult? result;
      turn.listen(expected: const ['boarding pass'], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // iOS закрыл попытку на первом слове термина.
      mic.say('boarding');
      mic.close(const SpeechAttempt.heard('boarding'));
      // Пока микрофон переоткрывается, он глух — а человек договаривает.
      fake.elapse(const Duration(milliseconds: 900));
      expect(result, isNull, reason: 'запись закрылась, пока микрофон был глух');

      // Микрофон снова жив и слышит конец термина.
      mic.say('pass');
      fake.elapse(const Duration(milliseconds: 1200));
      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'boarding pass');
    });
  });

  // ПРАВИЛО: наряд FIX-1, п. 3 — пауза, которую получает ПЛАГИН, заведомо длиннее нашей: его окно
  // это потолок, а не правило.
  // ЛОВИТ: `pauseFor: silenceAfterSpeech`, из-за которого плагин выигрывал гонку у движка и
  // закрывал микрофон ровно на границе нашего правила.
  test('плагину отдают паузу длиннее нашей — запись закрывает движок, а не он', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      unawaited(turn.listen(expected: const [], localeId: 'en_US'));
      fake.flushMicrotasks();

      expect(mic.pauseFors.first, config.enginePause);
      expect(config.enginePause > config.silenceAfterSpeech, isTrue);
      unawaited(turn.cancel());
      fake.flushMicrotasks();
    });
  });

  // ПРАВИЛО: Ч.2.1 — сторож считает ВСЮ запись, от нажатия, а не речь от первого слова.
  // ЛОВИТ: сторож, который человек может отодвигать бесконечно, продолжая говорить, — и обратное,
  // сторож, отмеряющий только паузу до первого слова. У записи есть длина, и у неё есть потолок.
  test('сторож закрывает запись через 15 с от НАЖАТИЯ, чем бы она ни была занята', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      // Человек говорит без пауз по слову в полсекунды — тишина не срабатывает ни разу.
      final words = <String>[];
      for (var i = 0; i < 40; i++) {
        words.add('w$i');
        mic.say(words.join(' '));
        fake.elapse(const Duration(milliseconds: 500));
        if (i < 29) expect(result, isNull, reason: 'полсекунды № ${i + 1} записи — она ещё открыта');
      }

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript.split(' ').length, 30, reason: 'ровно 15 секунд записи');
    });
  });

  // ПРАВИЛО: Ч.2.1 — пол сторожа 15 с; серверный `listen_seconds` может его поднять и не может
  // опустить.
  // ЛОВИТ: конфиг, уехавший вниз. Запись, закрытая раньше пятнадцати секунд, режет длинную реплику
  // на полуслове, а в прогоне ещё и пишет за человека промах в append-only журнал.
  test('сторож не может встать ниже пятнадцати секунд, каким бы малым ни приехал listen_seconds', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config.copyWith(maxRecording: const Duration(seconds: 3)));
      SpeechTurnResult? result;
      turn.listen(expected: const [], localeId: 'en_US').then((r) => result = r);
      fake.flushMicrotasks();

      fake.elapse(const Duration(seconds: 14, milliseconds: 900));
      expect(result, isNull, reason: 'запись закрыта раньше пола сторожа');

      fake.elapse(const Duration(milliseconds: 200));
      expect(result?.outcome, SpeechTurnOutcome.silent);
    });
  });

  // ПРАВИЛО: Ч.2.1 — тап «Готово» закрывает запись тем, что есть, и говорит об этом стадией.
  // ЛОВИТ: «Готово», отдающее пустоту (плагин на `stop` иногда не отдаёт ничего) и стадию,
  // неотличимую от тишины: «закрылся сам» и «закрыл человек» — разные вещи для того, кто ищет,
  // почему ход кончился рано.
  test('«Готово» закрывает запись тем, что есть — стадией closedByTap', () {
    fakeAsync((fake) {
      final diagnostics = SpeechDiagnostics(channel: const MethodChannel('test/absent'));
      final mic = _DrivenRecognizer();
      final opened = DateTime(2026, 9, 8, 12);
      var elapsed = Duration.zero;
      final turn = SpeechTurn(
        mic,
        config: config,
        diagnostics: diagnostics,
        now: () => opened.add(elapsed),
      );
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
      expect(diagnostics.phase, SpeechPhase.closedByTap);
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

  // ПРАВИЛО: DAY-FIX-3, Ч.1.5 — эхо динамика выбрасывается из склейки и НЕ заканчивает запись.
  // ЛОВИТ: эхо, ставшее ответом, и эхо, ставшее закрытием. Микрофон рядом с динамиком слышит
  // собеседника; склейка с его репликой — не то, что сказал человек, но и не повод обрывать ход.
  test('эхо-замок: узнанная реплика роли выбрасывается, запись продолжается', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      final partials = <String>[];
      SpeechTurnResult? result;
      turn
          .listen(
            expected: const ['a fever'],
            localeId: 'en_US',
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

      // Теперь человек — и его слова закрывает тишина, как любые другие.
      mic.say('I have a fever');
      fake.elapse(const Duration(seconds: 6));

      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'I have a fever');
      expect(result?.echoes, 1);
    });
  });

  // ПРАВИЛО: Ч.2.1 — сторож эхом не двигается: он считает запись от нажатия.
  // ЛОВИТ: сторож, перезаводимый эхо-сбросом. Динамик, слышимый микрофоном, добавлял бы человеку
  // времени — и запись у шумного стола жила бы вдвое дольше, чем у тихого.
  test('эхо не отодвигает сторож записи', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config);
      SpeechTurnResult? result;
      turn
          .listen(
            expected: const [],
            localeId: 'en_US',
            echoOf: (t) => t.contains('hello there'),
          )
          .then((r) => result = r);
      fake.flushMicrotasks();

      fake.elapse(const Duration(seconds: 10));
      mic.say('hello there');
      mic.close(const SpeechAttempt.heard('hello there'));
      fake.elapse(const Duration(milliseconds: 200));
      expect(result, isNull);

      // Пятнадцатая секунда ОТ НАЖАТИЯ, а не от эха.
      fake.elapse(const Duration(seconds: 5));
      expect(result?.outcome, SpeechTurnOutcome.silent);
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

  test('плагину отдаётся пауза после речи и потолок записи из конфига — окно, а не правило', () {
    fakeAsync((fake) {
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(
        mic,
        config: config.copyWith(
          silenceAfterSpeech: const Duration(seconds: 3),
          maxRecording: const Duration(seconds: 20),
        ),
      );
      turn.listen(expected: const [], localeId: 'en_US');
      fake.flushMicrotasks();

      expect(mic.pauseFors.single, const Duration(seconds: 3));
      expect(mic.timeouts.single, const Duration(seconds: 20));
      turn.cancel();
      fake.flushMicrotasks();
    });
  });

  // ПРАВИЛО: наряд DAY-GATE-1, доработка Ч.3 — подстановка транскрипта едет ТЕМ ЖЕ путём, что и
  // живой частичный результат, а не мимо движка.
  // ЛОВИТ: дверь QA, которая коротит движок. Микрофона на симуляторе нет, и подстановка — это
  // единственный способ увидеть склейку, сторож и стадии живьём; подстановка, обходящая их,
  // проверяет карточку и объявляет проверенным всё остальное.
  test('подставленный транскрипт проходит склейку и ждёт тишины — как живая речь', () {
    fakeAsync((fake) {
      final diagnostics = SpeechDiagnostics(channel: const MethodChannel('test/absent'));
      final mic = _DrivenRecognizer();
      final turn = SpeechTurn(mic, config: config, diagnostics: diagnostics);
      SpeechTurnResult? result;
      final partials = <String>[];
      turn
          .listen(
            expected: const ['my back hurts'],
            localeId: 'en_US',
            onPartial: partials.add,
          )
          .then((r) => result = r);
      fake.flushMicrotasks();

      expect(turn.injectTranscript('my back hurts'), isTrue);
      fake.flushMicrotasks();

      // Ход ещё открыт и ПИШЕТ — совпадение его больше не закрывает (наряд SPEECH-2, Ч.2.1).
      expect(result, isNull);
      expect(diagnostics.phase, SpeechPhase.listening);
      expect(partials, contains('my back hurts'));

      fake.elapse(const Duration(seconds: 6));
      expect(result?.outcome, SpeechTurnOutcome.heard);
      expect(result?.transcript, 'my back hurts');
      expect(result?.speechStartedAt, isNotNull, reason: '«сразу» меряется от первого слова');
      expect(diagnostics.phase, SpeechPhase.closedBySilence);
    });
  });

  test('подстановка в закрытый ход отбивается, а не притворяется услышанной', () {
    final turn = SpeechTurn(_DrivenRecognizer(), config: config);

    expect(turn.injectTranscript('my back hurts'), isFalse);
  });

  // ПРАВИЛО: DAY-GATE-1 Ч.0.1 + SPEECH-2 Ч.5 — служебная строка называет стадию, код отказа,
  // длительность речи и финальный транскрипт.
  // ЛОВИТ: строку, которая показывает «listening» после закрытия, «closedBySilence» на упавшем
  // канале и последний ЧАСТИЧНЫЙ текст на месте того, что ушло на зачёт. Диагностика, врущая о
  // состоянии, хуже её отсутствия: 07.09 сутки ушли на «Слушаю…», значившее пять разных вещей.
  test('стадии хода различают «договорил», «сторож» и «канал упал», и называют финал', () {
    fakeAsync((fake) {
      final diagnostics = SpeechDiagnostics(channel: const MethodChannel('test/absent'));

      // Договорил: слово, потом тишина.
      final mic1 = _DrivenRecognizer();
      SpeechTurn(mic1, config: config, diagnostics: diagnostics)
          .listen(expected: const [], localeId: 'en_US');
      fake.flushMicrotasks();
      expect(diagnostics.phase, SpeechPhase.opening, reason: 'открытие — ещё не «пишу»');
      mic1.say('my back hurts');
      expect(diagnostics.phase, SpeechPhase.listening);
      expect(diagnostics.lastPartial, 'my back hurts');
      fake.elapse(const Duration(seconds: 6));
      expect(diagnostics.phase, SpeechPhase.closedBySilence);
      expect(diagnostics.finalTranscript, 'my back hurts');
      expect(diagnostics.spokeFor, isNotNull);

      // Сторож: ни слова за всю запись.
      final mic2 = _DrivenRecognizer();
      SpeechTurn(mic2, config: config, diagnostics: diagnostics)
          .listen(expected: const [], localeId: 'en_US');
      fake.flushMicrotasks();
      expect(diagnostics.finalTranscript, '', reason: 'новый ход не показывает прошлый финал');
      fake.elapse(const Duration(seconds: 16));
      expect(diagnostics.phase, SpeechPhase.closedByTimeout);

      // Канал упал до первого слова — и код называет, чем именно.
      final mic3 = _DrivenRecognizer();
      SpeechTurn(mic3, config: config, diagnostics: diagnostics)
          .listen(expected: const [], localeId: 'en_US');
      fake.flushMicrotasks();
      mic3.close(const SpeechAttempt.unavailable());
      fake.elapse(const Duration(milliseconds: 200));
      expect(diagnostics.phase, SpeechPhase.failed);
      expect(diagnostics.lastErrorCode, 'channel_down');
    });
  });
}
