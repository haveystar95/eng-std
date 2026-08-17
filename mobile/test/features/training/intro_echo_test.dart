import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The intro card's optional echo: listen, say something kind, write nothing.
///
/// The tests here are almost all about ABSENCE, because that is what the feature is. The echo has
/// no verdict, no grade and no queue behind it — the intro card's contract is that it asks for
/// nothing, and an echo that could be failed would quietly make it the app's first exercise.
class _FakeRecognizer implements SpeechRecognizer {
  _FakeRecognizer({required this.attempt, required this.isReady});

  final SpeechAttempt attempt;

  @override
  final bool isReady;

  int calls = 0;
  int prepares = 0;

  @override
  Future<bool> prepare() async {
    prepares++;

    return isReady;
  }

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    ValueChanged<String>? onPartial,
  }) async {
    calls++;

    return attempt;
  }

  @override
  Future<void> stop() async {}

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
    await tester.pumpWidget(host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)));
    await tester.pumpAndSettle();

    expect(find.text('Повторить вслух'), findsOneWidget);
  });

  testWidgets('says «услышал тебя» when it heard anything at all', (tester) async {
    final recognizer = _FakeRecognizer(attempt: const SpeechAttempt.heard('reserve ation'), isReady: true);
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Повторить вслух'));
    await tester.pumpAndSettle();

    // A mangled first attempt at a new word still counts: this is a statement about the microphone,
    // NOT a check against the term. Telling someone their first try was wrong is the fastest way to
    // make them stop saying anything out loud.
    expect(find.text('Услышал тебя'), findsOneWidget);
    expect(find.text('Попробуй ещё'), findsNothing);
    expect(recognizer.calls, 1);
  });

  testWidgets('invites another go when it heard nothing — and never calls it wrong', (tester) async {
    await tester.pumpWidget(host(_FakeRecognizer(attempt: const SpeechAttempt.silent(), isReady: true)));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Повторить вслух'));
    await tester.pumpAndSettle();

    expect(find.text('Попробуй ещё'), findsOneWidget);
    // The intro's own exit is untouched and still the only way forward: the echo is skippable in
    // the most literal sense — you can ignore it entirely.
    expect(find.text('Повторить вслух'), findsOneWidget);
  });
}
