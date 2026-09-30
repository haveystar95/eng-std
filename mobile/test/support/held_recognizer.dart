/// A RECOGNIZER THAT KEEPS THE LEARNER TALKING — it hands its partials over at once and holds the recording open until
/// the turn stops it (the silence after the last word, or a tap on the microphone). The goal step's dictation under
/// test (кадр 22-1 «во время диктовки», наряд CLIENT-22-1 §1).
library;

import 'dart:async';

import 'package:flutter/foundation.dart' show ValueChanged;

import 'package:eng_std/data/speech/speech_recognizer.dart';

class HeldRecognizer implements SpeechRecognizer {
  HeldRecognizer(this.partials);

  final List<String> partials;
  final _closed = Completer<void>();

  void _close() {
    if (!_closed.isCompleted) _closed.complete();
  }

  @override
  Future<void> stop() async => _close();

  @override
  Future<void> cancel() async => _close();

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
    for (final p in partials) {
      onLevel?.call(6);
      onPartial?.call(p);
    }
    await _closed.future;
    return SpeechAttempt.heard(partials.last);
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
