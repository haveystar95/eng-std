import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Render smoke test — the exercise session is device-batched, so this catches layout throws and
/// confirms the header + a multiple_choice card build from an overridden session. Answering (which
/// fires TTS + records) is exercised on-device, not here.
void main() {
  Widget host(SessionCard card) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      studySessionProvider.overrideWith(
        (ref, args) async => StudySession(sessionId: args.sessionId, cards: [card]),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: SessionScreen(title: 'Аэропорт'),
    ),
  );

  SessionCard choiceCard({int? ladderStep}) => SessionCard(
    termId: '01AAAAAAAAAAAAAAAAAAAAAAA1',
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: 'посадочный талон',
    answer: 'boarding pass',
    options: const ['boarding pass', 'baggage claim', 'departure gate', 'check-in'],
    ladderStep: ladderStep,
  );

  testWidgets('Session renders the header phase, the prompt and the choice options', (
    tester,
  ) async {
    await tester.pumpWidget(host(choiceCard()));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Знакомство'), findsOneWidget); // off the ladder → the phase names it
    expect(find.text('1 из 1'), findsOneWidget);
    expect(find.text('посадочный талон'), findsOneWidget);
    expect(find.text('boarding pass'), findsOneWidget);
    expect(find.text('baggage claim'), findsOneWidget);
  });

  // QA-OBS-4: the same card, dealt at a recognition rung, was headed «Знакомство» — the name of the
  // step before it. The mode cannot tell the two apart; the rung can, and the card carries it.
  for (final step in [
    LearningLadder.stepRecognitionForward,
    LearningLadder.stepRecognitionReverse,
  ]) {
    testWidgets('a card dealt at rung $step is headed «Узнавание»', (tester) async {
      await tester.pumpWidget(host(choiceCard(ladderStep: step)));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Узнавание'), findsOneWidget);
      expect(find.text('Знакомство'), findsNothing);
    });
  }
}
