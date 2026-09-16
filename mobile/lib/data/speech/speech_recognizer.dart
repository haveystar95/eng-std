import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:speech_to_text/speech_recognition_error.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:speech_to_text/speech_to_text.dart';

import 'speech_diagnostics.dart';

/// What a listening attempt ended as. The card reads this and nothing else about the plugin.
enum SpeechOutcome {
  /// Something was heard and transcribed. [SpeechAttempt.text] is non-empty.
  heard,

  /// The recogniser ran and came back with nothing — silence, noise, a word it could not place.
  /// A CHANNEL failure: it says nothing about whether the learner knew the answer.
  silent,

  /// The recogniser could not run at all: permission refused, no on-device model for the locale,
  /// the engine busy. Also a channel failure, and the card treats it identically.
  unavailable,
}

/// One finished listening attempt.
@immutable
class SpeechAttempt {
  const SpeechAttempt(this.outcome, [this.text = '']);

  const SpeechAttempt.heard(String text) : this(SpeechOutcome.heard, text);
  const SpeechAttempt.silent() : this(SpeechOutcome.silent);
  const SpeechAttempt.unavailable() : this(SpeechOutcome.unavailable);

  final SpeechOutcome outcome;

  /// The transcript, or '' when nothing was heard. This is the RAW answer the review carries — the
  /// server grades it, exactly as it grades typed text.
  final String text;

  bool get isHeard => outcome == SpeechOutcome.heard && text.trim().isNotEmpty;
}

/// On-device speech recognition, as the speaking trainer and the intro's echo need it.
///
/// An interface with one real implementation, for one reason that is not testability alone: a
/// simulator has no microphone, so a widget test of the speaking card can only exist if the card
/// talks to a seam. It is also what keeps the plugin's callback-and-status protocol out of a card
/// that already has an answering state machine.
///
/// Everything here is about the CHANNEL. Nothing in this file knows what a correct answer is.
abstract class SpeechRecognizer {
  /// Is recognition usable at all — permission granted and an engine available?
  ///
  /// LAZY on purpose: this is what triggers the iOS microphone + speech permission prompts, so it
  /// is called when the learner first taps a record button, never at app start. A person who never
  /// opens a speaking card is never asked. Returns false rather than throwing on refusal — a
  /// refused permission is an ordinary state of this trainer, not an error.
  Future<bool> prepare();

  /// Has [prepare] already succeeded in this app run? Read without side effects, so a surface that
  /// must stay silent until permission exists (the intro's echo button) can ask without prompting.
  ///
  /// NOT the same question as "does the OS say we're authorized" (QA-21) — [prepare] is what sets
  /// this true, and nothing calls [prepare] on app start, so this is false for the whole first part
  /// of a run even when the learner granted the permission in a PAST run (or in iOS Settings,
  /// outside the app entirely). A surface that must reflect the real OS answer without waiting for
  /// some other card to call [prepare] first needs [hasPermission] instead.
  bool get isReady;

  /// Does the OS already say yes — WITHOUT prompting, and without needing [prepare] to have run
  /// first? This is `SFSpeechRecognizer.authorizationStatus()` (+ the mic permission), asked
  /// directly. Unlike [isReady], this reflects permission granted in a past run or via iOS
  /// Settings, not just "has this process's [prepare] already succeeded" — the gap QA-21 fixes: a
  /// brand-new word's intro card is often the FIRST speech-touching card in a fresh app run (its
  /// echo is the only thing that could call [prepare], and it deliberately never does), so
  /// [isReady] alone left the echo hidden despite the OS having already said yes.
  Future<bool> get hasPermission;

  /// Listen once and return what was heard.
  ///
  /// [expected] is the words this card is hoping for. It is a HINT to the engine, never a check:
  /// whatever comes back is graded normally, and a learner who says something else gets what they
  /// said. It chooses the SFSpeechRecognitionTaskHint (a single word is a `search`, a sentence is
  /// `dictation`).
  ///
  /// [contextualStrings] is the other half of that hint, and the one that actually fixes QA-20's
  /// mishearings (e.g. «What are your strengths» heard as «What are you strengths»): it is passed
  /// through to `SFSpeechRecognitionRequest.contextualStrings`, which tells the recogniser what
  /// vocabulary to expect — the difference between transcribing «bespoke» and «be spoke». Upstream
  /// `speech_to_text` 7.4.0 does not expose this at all (its Swift side sets only `taskHint` and
  /// `addsPunctuation`); [PluginSpeechRecognizer] talks to a vendored fork
  /// (`packages/speech_to_text`, see its `UPSTREAM.md`) that adds it. Like [expected], it is a hint
  /// only — never a check.
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout,
    Duration pauseFor,
    List<String> contextualStrings,
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  });

  /// Stop an attempt early (the learner tapped «стоп»), settling on whatever has been heard.
  Future<void> stop();

  /// Abandon an attempt and keep nothing (the card was left).
  Future<void> cancel();
}

/// The real one, over `speech_to_text` → `SFSpeechRecognizer`.
class PluginSpeechRecognizer implements SpeechRecognizer {
  PluginSpeechRecognizer([SpeechToText? speech, this._diagnostics])
    : _speech = speech ?? SpeechToText();

  final SpeechToText _speech;

  /// Журнал канала (наряд DAY-GATE-1, Ч.0.1). Всё, что этот класс раньше говорил в `debugPrint` —
  /// то есть НИКОМУ в релизной сборке, где консоли нет, — теперь ещё и сюда.
  final SpeechDiagnostics? _diagnostics;

  bool _initialized = false;

  /// The attempt in flight. The plugin reports results and its own status through callbacks, so the
  /// «one attempt» this class promises is a completer that whichever callback arrives first settles.
  Completer<SpeechAttempt>? _attempt;
  String _heard = '';

  @override
  bool get isReady => _initialized && _speech.isAvailable;

  @override
  Future<bool> get hasPermission => _speech.hasPermission;

  /// HOW LONG A NATIVE CALL MAY TAKE TO ANSWER before it is treated as a refusal.
  ///
  /// Neither `initialize` nor `listen` promises to return. On the simulator the audio session comes
  /// up against nothing at all, and `AVAudioEngine startAndReturnError:` sat inside
  /// `AURemoteIO::fetchWorkgroup()` until the RPC timed out and aborted the process — a `SIGABRT`
  /// about two minutes after the tap, with the card frozen on «Слушаю…» the whole time
  /// (E2E-SIM-2, С-4). A channel that has not started in eight seconds has not started; saying so
  /// is a `SpeechOutcome.unavailable`, which is a state this trainer already knows how to be in.
  ///
  /// It bounds the DART await and cannot stop a native abort on its own — the card's own watchdog is
  /// the other half — but it is what turns «the engine never answered» into an answer.
  static const _startTimeout = Duration(seconds: 8);

  @override
  Future<bool> prepare() async {
    if (_initialized) return _speech.isAvailable;
    try {
      // `initialize` is what raises the two iOS prompts. A refusal comes back as false; the plugin
      // also throws on some platform errors, which is the same answer as far as a card is concerned.
      // A permission prompt is answered by a PERSON, so the bound here is generous: it is here for
      // an engine that never comes up, not for a learner who is reading the dialog.
      _initialized = await _speech
          .initialize(onStatus: _onStatus, onError: _onError)
          .timeout(_startTimeout * 4, onTimeout: () => false);
    } catch (e) {
      debugPrint('[speech] initialize failed: $e');
      _diagnostics?.note('initialize threw: $e');
      _initialized = false;
    }
    final ready = _initialized && _speech.isAvailable;
    // «Поднялся» и «не поднялся» — оба факта, и второй важнее: именно он стоит за «Слушаю…», из
    // которого ничего не выходит, и именно его на устройстве не было видно ничем.
    _diagnostics?.note(
      ready ? 'initialize ok' : 'initialize refused (available=${_speech.isAvailable})',
    );
    return ready;
  }

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async {
    // ПУСТАЯ ЛОКАЛЬ — НЕ «ЯЗЫК ПО УМОЛЧАНИЮ» (наряд DAY-GATE-1, Ч.0.2, находка F1).
    //
    // iOS строит `SFSpeechRecognizer(locale: Locale(identifier: ""))`, получает nil и отвечает
    // `noRecognizerError`; хуже, он ЗАПОМИНАЕТ эту локаль как текущую вместе с нулевым
    // распознавателем. Место, где эта строка приезжала пустой, найдено и починено, но проверка
    // остаётся здесь: цена ошибки — молча неработающий микрофон, а цена проверки — одна строка.
    if (localeId.trim().isEmpty) {
      _diagnostics?.note('listen refused: empty localeId');
      return const SpeechAttempt.unavailable();
    }
    if (!await prepare()) return const SpeechAttempt.unavailable();
    // A second tap while listening is a stop, not a second attempt.
    if (_speech.isListening) await _speech.stop();

    final attempt = Completer<SpeechAttempt>();
    _attempt = attempt;
    _heard = '';

    try {
      await _speech.listen(
        listenOptions: SpeechListenOptions(
          localeId: localeId,
          // Give up after the window rather than holding the microphone open: a card that listens
          // forever looks like the app has hung, which is worse than a retry.
          listenFor: timeout,
          // …and settle once the speaker has clearly stopped, instead of waiting the window out. A
          // one-word answer followed by silence reads as the app not responding — but too short a
          // pause cuts off a mid-sentence hesitation instead (QA-20); the caller picks the window
          // that fits what is being read (see SpokenAnswer's word/example-form constants).
          pauseFor: pauseFor,
          // On-device, always. The audio of someone practising vocabulary alone in a room is not
          // something to send anywhere, and the trainer has to work in the metro with no signal.
          onDevice: true,
          partialResults: true,
          cancelOnError: true,
          // What is left of the accuracy hint (see the interface doc): tell the engine the SHAPE of
          // what is coming. Apple's `search` hint is documented for short standalone terms, which is
          // what the word form asks for; a sentence read aloud is `dictation`.
          listenMode: _hintFor(expected),
          // The card prints the transcript live and grades it word by word; invented commas and
          // full stops only make the two disagree about what was said.
          autoPunctuation: false,
          // iOS mutes system sounds and haptics while a recording session is active; the verdict sound
          // of a voice answer plays the moment the recording is stopped, before the plugin has let the
          // session go (SESSION-1b′, owner's check: the verdict must be heard right after the stop).
          enableHapticFeedback: true,
        ),
        // See this method's own doc comment: the vendored fork's addition, and the actual fix for
        // QA-20's mishearings (`expected` above only picks the taskHint).
        contextualStrings: contextualStrings.isEmpty ? null : contextualStrings,
        onResult: (result) => _onResult(result, onPartial),
        // УРОВЕНЬ ЗВУКА — для амплитуды под микрофоном (токен-лист 2б, кадр 23-3b). iOS отдаёт
        // децибелы примерно от −2 до 10; в 0…1 их приводит вызывающий.
        onSoundLevelChange: onLevel == null ? null : (level) => onLevel(level),
      ).timeout(_startTimeout);
    } catch (e) {
      // A refusal, a throw, or an engine that never came up at all — all three are the same answer
      // to a card, and the third one is why there is a timeout on the await (see [_startTimeout]).
      debugPrint('[speech] listen failed: $e');
      _diagnostics?.note('listen failed: ${e is PlatformException ? e.code : e}');
      _settle(const SpeechAttempt.unavailable());
    }

    return attempt.future;
  }

  @override
  Future<void> stop() async {
    if (_speech.isListening) await _speech.stop();
    // No settle here: stopping makes the plugin deliver its final result, which settles the attempt
    // with what was actually heard. Settling now would throw that transcript away.
  }

  @override
  Future<void> cancel() async {
    if (_speech.isListening) await _speech.cancel();
    _settle(const SpeechAttempt.silent());
  }

  /// The SFSpeechRecognitionTaskHint that fits what this card is asking for. A single term is a
  /// short standalone utterance (`search`); a whole sentence read aloud is `dictation`.
  static ListenMode _hintFor(List<String> expected) {
    final longest = expected.fold(
      0,
      (n, s) =>
          s.trim().split(RegExp(r'\s+')).length > n ? s.trim().split(RegExp(r'\s+')).length : n,
    );
    return longest > 2 ? ListenMode.dictation : ListenMode.search;
  }

  void _onResult(SpeechRecognitionResult result, ValueChanged<String>? onPartial) {
    _heard = result.recognizedWords;
    onPartial?.call(_heard);
    if (result.finalResult) _settle(_finish());
  }

  /// The plugin says «notListening»/«done» when the window closes. If no final result arrived, the
  /// attempt still has to end — a card waiting forever on a microphone is the worst of the failure
  /// modes, because it looks like the app rather than the room.
  void _onStatus(String status) {
    if (status == 'notListening' || status == 'done') _settle(_finish());
  }

  void _onError(SpeechRecognitionError error) {
    debugPrint('[speech] error: ${error.errorMsg}');
    // КОД ПЛАГИНА ДОСЛОВНО — `error_speech_recognizer_request_not_authorized`,
    // `error_listen_failed`, `error_no_match` и прочие. Разница между ними и есть разница между
    // «нет разрешения», «не поднялась аудиосессия» и «человек молчал».
    _diagnostics?.note('recognizer error: ${error.errorMsg} (permanent=${error.permanent})');
    // Every recogniser error is a CHANNEL failure. None of them is evidence about memory, so none
    // of them may become an answer.
    _settle(_heard.trim().isEmpty ? const SpeechAttempt.unavailable() : _finish());
  }

  SpeechAttempt _finish() =>
      _heard.trim().isEmpty ? const SpeechAttempt.silent() : SpeechAttempt.heard(_heard.trim());

  void _settle(SpeechAttempt attempt) {
    final pending = _attempt;
    _attempt = null;
    if (pending != null && !pending.isCompleted) pending.complete(attempt);
  }
}
