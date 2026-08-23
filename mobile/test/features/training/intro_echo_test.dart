import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The intro card's optional echo: listen, say something kind, write nothing.
///
/// The tests here are almost all about ABSENCE, because that is what the feature is. The echo has
/// no verdict, no grade and no queue behind it — the intro card's contract is that it asks for
/// nothing, and an echo that could be failed would quietly make it the app's first exercise.
class _FakeRecognizer implements SpeechRecognizer {
  _FakeRecognizer({
    required this.attempt,
    required this.isReady,
    bool? hasPermission,
    this.completeOnStop = false,
  }) : _hasPermission = hasPermission ?? isReady;

  final SpeechAttempt attempt;

  @override
  final bool isReady;

  /// When true, `listenOnce` does not settle on its own — only [stop] resolves it, mirroring the
  /// real plugin. Needed to observe the card WHILE it thinks it is recording, which is the whole
  /// subject of the listening-indicator tests (QA-21).
  final bool completeOnStop;
  Completer<SpeechAttempt>? _pending;

  /// The OS-level answer (QA-21) — independent of [isReady], which is only "has *this process*
  /// already prepared". Defaults to matching [isReady] so every OLDER test in this file (written
  /// before [hasPermission] existed) keeps its original meaning unchanged.
  final bool _hasPermission;

  int calls = 0;
  int prepares = 0;
  int permissionChecks = 0;
  int stops = 0;

  /// What the echo handed over, per call — the recording window and the vocabulary hint it should
  /// be sending exactly as the speaking word form does (QA-21).
  final List<Duration> timeoutsPerCall = [];
  final List<Duration> pauseForsPerCall = [];
  final List<List<String>> contextualStringsPerCall = [];

  /// Feeds a live partial into the card mid-attempt, the way the real plugin streams them.
  void emitPartial(String text) => _onPartial?.call(text);
  ValueChanged<String>? _onPartial;

  @override
  Future<bool> prepare() async {
    prepares++;

    return isReady;
  }

  @override
  Future<bool> get hasPermission async {
    permissionChecks++;

    return _hasPermission;
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
    calls++;
    timeoutsPerCall.add(timeout);
    pauseForsPerCall.add(pauseFor);
    contextualStringsPerCall.add(contextualStrings);
    _onPartial = onPartial;

    if (completeOnStop) {
      final completer = Completer<SpeechAttempt>();
      _pending = completer;

      return completer.future;
    }

    return attempt;
  }

  @override
  Future<void> stop() async {
    stops++;
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) pending.complete(attempt);
  }

  @override
  Future<void> cancel() async {}
}

void main() {
  SessionCard introCard() => SessionCard(
    termId: 'T1',
    mode: ExerciseMode.intro,
    type: 'word',
    prompt: 'бронь',
    answer: 'reservation',
    transcription: 'ˌrezərˈveɪʃn',
    example: 'I have a reservation for tonight.',
    exampleTranslation: 'У меня бронь на сегодня.',
    ladderStep: 0,
  );

  Widget host(_FakeRecognizer recognizer) => ProviderScope(
    overrides: [speechRecognizerProvider.overrideWithValue(recognizer)],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: SessionIntroCard(
            card: introCard(),
            autoPronounce: false,
            onSpeak: (String text, {bool slow = false}) async {},
          ),
        ),
      ),
    ),
  );

  testWidgets('is hidden until the microphone has been permitted elsewhere', (tester) async {
    final recognizer = _FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: false);
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    // An intro card must never be the thing that raises a permission prompt: it is the card that
    // asks for nothing, and the learner meets that question on their first speaking card instead.
    expect(find.text('Повторить вслух'), findsNothing);
    expect(recognizer.prepares, 0);
    expect(recognizer.calls, 0);
    // The card itself is unaffected — the word, its transcription and «Понятно» are all still there.
    expect(find.text('reservation'), findsOneWidget);
  });

  testWidgets('offers the echo once the microphone is available', (tester) async {
    await tester.pumpWidget(
      host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Повторить вслух'), findsOneWidget);
  });

  testWidgets('a mangled attempt still counts — the transcript is shown, never marked', (
    tester,
  ) async {
    final recognizer = _FakeRecognizer(
      attempt: const SpeechAttempt.heard('reserve ation'),
      isReady: true,
    );
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Повторить вслух'));
    await tester.pumpAndSettle();

    // A mangled first attempt at a new word still counts: this is a statement about the microphone,
    // NOT a check against the term. Telling someone their first try was wrong is the fastest way to
    // make them stop saying anything out loud. Since QA-21 it prints WHAT it heard rather than a
    // bare «Услышал тебя» — still no verdict, just the words, which is the one thing the learner
    // needs to judge their own attempt by.
    expect(find.text('Услышали: «reserve ation»'), findsOneWidget);
    expect(find.text('Попробуй ещё'), findsNothing);
    expect(recognizer.calls, 1);
  });

  testWidgets('invites another go when it heard nothing — and never calls it wrong', (
    tester,
  ) async {
    await tester.pumpWidget(
      host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Повторить вслух'));
    await tester.pumpAndSettle();

    expect(find.text('Попробуй ещё'), findsOneWidget);
    // The intro's own exit is untouched and still the only way forward: the echo is skippable in
    // the most literal sense — you can ignore it entirely.
    expect(find.text('Повторить вслух'), findsOneWidget);
  });

  group('a brand-new word\'s intro card, first to touch speech this run (QA-21)', () {
    // isReady: false here on purpose — this is the exact gap: no card has called `prepare` yet
    // THIS run, so isReady cannot be the thing that answers. hasPermission is the OS's own answer,
    // asked directly and without a prompt (see the interface doc), and is what QA-21 adds.

    testWidgets('the echo appears once the OS confirms the mic is already permitted', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.silent(),
        isReady: false,
        hasPermission: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      expect(find.text('Повторить вслух'), findsOneWidget);
      // Asked once, at mount — and still never `prepare`, so still never a prompt.
      expect(recognizer.permissionChecks, 1);
      expect(recognizer.prepares, 0);
    });

    testWidgets('stays hidden when the OS says no too', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.silent(),
        isReady: false,
        hasPermission: false,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      expect(find.text('Повторить вслух'), findsNothing);
      expect(recognizer.permissionChecks, 1);
      expect(recognizer.prepares, 0);
    });

    testWidgets('skips the OS round trip entirely once isReady is already true', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.silent(),
        isReady: true,
        hasPermission: false,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      // isReady already says yes — asking the OS again would be redundant, not wrong, so this pins
      // it as a cheap-path guarantee rather than a strict correctness requirement.
      expect(find.text('Повторить вслух'), findsOneWidget);
      expect(recognizer.permissionChecks, 0);
    });
  });

  group('the echo makes the recording legible (QA-21)', () {
    testWidgets('tapping it shows that the phone is recording, and offers a way to stop', (
      tester,
    ) async {
      // Held open, so the card can be observed WHILE it thinks it is listening.
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pump();

      // Before this, a tap changed nothing at all on screen — the button just went disabled, so a
      // live microphone looked exactly like a dead one.
      expect(find.text('Слушаю…'), findsOneWidget);
      expect(find.text('Готово'), findsOneWidget, reason: 'and the tap became a stop');
      expect(find.text('Повторить вслух'), findsNothing);
    });

    testWidgets('a live partial appears while still recording', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pump();
      recognizer.emitPartial('reser');
      await tester.pump();

      // Seeing your own words appear is the clearest possible «yes, it hears you».
      expect(find.text('Услышали: «reser»'), findsOneWidget);
    });

    testWidgets('the second tap stops the recording and settles on what was heard', (tester) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
        completeOnStop: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pump();
      await tester.tap(find.text('Готово'));
      await tester.pumpAndSettle();

      expect(recognizer.stops, 1);
      expect(find.text('Услышали: «reservation»'), findsOneWidget);
      expect(find.text('Слушаю…'), findsNothing);
    });

    testWidgets('an empty result invites another go instead of printing nothing', (tester) async {
      await tester.pumpWidget(
        host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pumpAndSettle();

      expect(find.text('Попробуй ещё'), findsOneWidget);
      expect(find.textContaining('Услышали'), findsNothing);
    });

    testWidgets('tapping again after a result starts over — the old text is replaced', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pumpAndSettle();
      expect(find.text('Услышали: «reservation»'), findsOneWidget);

      await tester.tap(find.text('Повторить вслух'));
      await tester.pumpAndSettle();

      // A second attempt, not a second line: the old transcript is gone, replaced by this one.
      expect(recognizer.calls, 2);
      expect(find.text('Услышали: «reservation»'), findsOneWidget);
    });

    testWidgets('it sends the same window and vocabulary hint the speaking word form does', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer(
        attempt: const SpeechAttempt.heard('reservation'),
        isReady: true,
      );
      await tester.pumpWidget(host(recognizer));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Повторить вслух'));
      await tester.pumpAndSettle();

      // A one-word term, so the short window — the same rule the speaking card follows, which is
      // what keeps a phrase-shaped term from being cut off here too.
      expect(recognizer.timeoutsPerCall.single, SpokenAnswer.wordFormListenFor);
      expect(recognizer.pauseForsPerCall.single, SpokenAnswer.wordFormPauseFor);
      expect(recognizer.contextualStringsPerCall.single, ['reservation']);
    });
  });
}
