import 'dart:async';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// THERE IS ALWAYS A WAY OUT OF A SPEAKING CARD — E2E-SIM-2, С-4.
///
/// The recogniser is supposed to settle every attempt: its own window closes, the plugin reports a
/// status, or an error arrives. On the simulator none of the three happened. The card sat in
/// «Слушаю…» for ever — «Не помню» disabled for the duration, «Пропустить» waiting on a channel
/// failure nobody reported — and about two minutes later the process died inside
/// `AVAudioEngine startAndReturnError:` → `AURemoteIO::fetchWorkgroup()` → RPC timeout. The only
/// exit from the card was killing the app.
///
/// The recogniser below is dead in exactly that way: it accepts the request and never answers.
class _DeadRecognizer implements SpeechRecognizer {
  int calls = 0;
  int cancels = 0;

  @override
  bool get isReady => true;

  @override
  Future<bool> prepare() async => true;

  @override
  Future<bool> get hasPermission async => true;

  /// Never completes — no result, no error, not even a partial. The whole point.
  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) {
    calls++;

    return Completer<SpeechAttempt>().future;
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async => cancels++;
}

void main() {
  late List<SessionAnswer> answers;
  late int skips;

  setUp(() {
    answers = [];
    skips = 0;
  });

  Widget host(SessionCard card, SpeechRecognizer recognizer) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final database = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
      }),
      speechRecognizerProvider.overrideWithValue(recognizer),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: SessionExerciseCard(
            card: card,
            autoPronounce: false,
            onAnswered: answers.add,
            onSkipped: () => skips++,
            onSpeak: (String text, {bool slow = false}) async {},
            speechLocaleId: 'en_US',
            answerLang: 'en',
            showDue: false,
          ),
        ),
      ),
    ),
  );

  SessionCard speakingCard() => SessionCard(
    termId: 'T1',
    mode: ExerciseMode.speaking,
    type: 'word',
    prompt: 'бронь',
    answer: 'reservation',
    ladderStep: LearningLadder.stepAssembly,
  );

  /// Is «Не помню» tappable right now? A disabled [QuietButton] carries a null `onPressed`.
  bool giveUpEnabled(WidgetTester tester) => tester
      .widget<QuietButton>(
        find.ancestor(of: find.text('Не помню'), matching: find.byType(QuietButton)).first,
      )
      .onPressed !=
      null;

  testWidgets('a microphone that never answers lets the learner out after fifteen seconds', (
    tester,
  ) async {
    final engine = _DeadRecognizer();
    await tester.pumpWidget(host(speakingCard(), engine));
    await tester.pumpAndSettle();

    await tester.tap(find.bySemanticsLabel(RegExp('Сказать|Готово')).first);
    await tester.pump();

    // While it listens the card is honest: «Не помню» is a claim about memory, and a microphone is
    // not entitled to make it on the learner's behalf. This is the state that used to be permanent.
    expect(engine.calls, 1);
    expect(find.text('Слушаю…'), findsOneWidget);
    expect(giveUpEnabled(tester), isFalse);
    expect(find.text('Пропустить'), findsNothing);

    // Fourteen seconds in, nothing has changed: the grace period is a real one, not an instant
    // give-up that would cut a slow audio session off before it came up.
    await tester.pump(const Duration(seconds: 14));
    expect(find.text('Слушаю…'), findsOneWidget);

    await tester.pump(const Duration(seconds: 2));

    // Fifteen seconds with nothing back at all is a channel that is not answering, and the card
    // says so in the words it already had for a refused microphone. Both exits are live.
    expect(find.text('Слушаю…'), findsNothing);
    expect(find.textContaining('Микрофон недоступен'), findsOneWidget);
    expect(find.text('Пропустить'), findsOneWidget);
    expect(giveUpEnabled(tester), isTrue);
    expect(engine.cancels, 1, reason: 'the abandoned attempt is closed, not left holding the mic');

    // Nothing was ANSWERED on the learner's behalf: a dead microphone is not a lapse.
    expect(answers, isEmpty);
    expect(skips, 0);
  });

  testWidgets('the card is passable with a dead engine — «Пропустить» reaches the shell', (
    tester,
  ) async {
    final engine = _DeadRecognizer();
    await tester.pumpWidget(host(speakingCard(), engine));
    await tester.pumpAndSettle();

    await tester.tap(find.bySemanticsLabel(RegExp('Сказать|Готово')).first);
    await tester.pump(const Duration(seconds: 16));

    await tester.tap(find.text('Пропустить'));
    await tester.pumpAndSettle();

    // The card hands the decision up. What the shell does with it depends on where the card stands —
    // inside a plan it writes the lapse that keeps the checklist honest, and that half is pinned in
    // `test/features/plan/plan_speaking_skip_test.dart`.
    expect(skips, 1);
    expect(answers, isEmpty);
  });

  testWidgets('«Не помню» after a stall is an ordinary answer, not a second dead end', (
    tester,
  ) async {
    final engine = _DeadRecognizer();
    await tester.pumpWidget(host(speakingCard(), engine));
    await tester.pumpAndSettle();

    await tester.tap(find.bySemanticsLabel(RegExp('Сказать|Готово')).first);
    await tester.pump(const Duration(seconds: 16));

    await tester.tap(find.text('Не помню'));
    await tester.pumpAndSettle();

    expect(answers.length, 1);
    expect(answers.single.response, isEmpty);
  });
}
