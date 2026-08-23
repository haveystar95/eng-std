import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:speech_to_text/speech_recognition_error.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:speech_to_text/speech_to_text.dart';

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
  });

  /// Stop an attempt early (the learner tapped «стоп»), settling on whatever has been heard.
  Future<void> stop();

  /// Abandon an attempt and keep nothing (the card was left).
  Future<void> cancel();
}

/// The real one, over `speech_to_text` → `SFSpeechRecognizer`.
class PluginSpeechRecognizer implements SpeechRecognizer {
  PluginSpeechRecognizer([SpeechToText? speech]) : _speech = speech ?? SpeechToText();

  final SpeechToText _speech;
  bool _initialized = false;

  /// The attempt in flight. The plugin reports results and its own status through callbacks, so the
  /// «one attempt» this class promises is a completer that whichever callback arrives first settles.
  Completer<SpeechAttempt>? _attempt;
  String _heard = '';

  @override
  bool get isReady => _initialized && _speech.isAvailable;

  @override
  Future<bool> get hasPermission => _speech.hasPermission;

  @override
  Future<bool> prepare() async {
    if (_initialized) return _speech.isAvailable;
    try {
      // `initialize` is what raises the two iOS prompts. A refusal comes back as false; the plugin
      // also throws on some platform errors, which is the same answer as far as a card is concerned.
      _initialized = await _speech.initialize(onStatus: _onStatus, onError: _onError);
    } catch (e) {
      debugPrint('[speech] initialize failed: $e');
      _initialized = false;
    }
    return _initialized && _speech.isAvailable;
  }

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) async {
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
        ),
        // See this method's own doc comment: the vendored fork's addition, and the actual fix for
        // QA-20's mishearings (`expected` above only picks the taskHint).
        contextualStrings: contextualStrings.isEmpty ? null : contextualStrings,
        onResult: (result) => _onResult(result, onPartial),
      );
    } catch (e) {
      debugPrint('[speech] listen failed: $e');
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
