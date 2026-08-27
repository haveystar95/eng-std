import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/home/home_cta.dart';
import 'package:eng_std/features/training/training_home_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Ч.3 — «честная кнопка сессии».
///
/// A button that promises WORDS while the session counts CARDS reads as broken halfway through: the
/// card said «32 слова» and the header counted to 61. The two numbers are both true and both belong
/// on the offer, because they answer different questions — words are the work, cards are the length.
void main() {
  group('sessionSizeLabel', () {
    late AppLocalizations l;

    setUp(() async => l = await AppLocalizations.delegate.load(const Locale('ru')));

    test('names both units', () {
      expect(sessionSizeLabel(l, words: 5, cards: 15), '5 слов · ~15 карточек');
    });

    test('the tilde is on the CARDS and not on the words', () {
      // The word count is exact — those are the words the session draws from. The composition can
      // still shift by the time it is dealt, so only the card count is approximate.
      final label = sessionSizeLabel(l, words: 1, cards: 3);
      expect(label.startsWith('1 слово ·'), isTrue);
      expect(label.contains('~3'), isTrue);
    });

    test('says nothing about cards when there is nothing to say', () {
      // A screen that has not learned the card count yet must not print «~0 карточек».
      expect(sessionSizeLabel(l, words: 4, cards: 0), '4 слова');
    });
  });

  group('LearningLadder.chainLength — сколько карточек стоит слово', () {
    test('a first meeting is its whole chain, not one card', () {
      // Intro, then BOTH recognitions, in the same sitting: a word met and then not asked for a day
      // has been met and abandoned.
      expect(LearningLadder.chainLength(LearningLadder.stepIntro), 3);
      expect(LearningLadder.chainLength(LearningLadder.stepRecognitionForward), 2);
      expect(LearningLadder.chainLength(LearningLadder.stepRecognitionReverse), 1);
    });

    test('a graduated word is one card, at every rung above recognition', () {
      expect(LearningLadder.chainLength(LearningLadder.stepAssembly), 1);
      expect(LearningLadder.chainLength(LearningLadder.stepTyping), 1);
      expect(LearningLadder.chainLength(LearningLadder.stepDictation), 1);
      // …and a rung a newer build might write is read as one card rather than as a crash.
      expect(LearningLadder.chainLength(9), 1);
      expect(LearningLadder.chainLength(-1), 1);
    });
  });

  group('карточка дня', () {
    Future<void> pump(WidgetTester tester, HomeSession session) async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      addTearDown(db.close);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appDatabaseProvider.overrideWithValue(db),
            connectivityProvider.overrideWith((ref) => Stream.value(true)),
            statsProvider.overrideWith(
              (ref) => Stream.value(
                Stats(
                  totalWords: 9,
                  learned: 0,
                  mastered: 0,
                  dueToday: 0,
                  reviewsTotal: 0,
                  streakDays: 1,
                ),
              ),
            ),
            homePlanProvider.overrideWith(
              (ref) => Stream.value(
                HomePlanView.ready(
                  HomePlan(
                    state: HomeStateKind.plan,
                    session: session,
                    inWork: const HomeInWork(total: 9, waiting: 4, perDay: 5, newRemaining: 2),
                    hardest: const [],
                    store: const HomeStore(count: 0, topics: []),
                  ),
                ),
              ),
            ),
          ],
          child: MaterialApp(
            locale: const Locale('ru'),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: const [Locale('ru')],
            home: const Scaffold(body: TrainingHomeScreen()),
          ),
        ),
      );
      await tester.pump();
      await tester.pump();
    }

    testWidgets('says how many CARDS the day is, beside how many words', (tester) async {
      await pump(
        tester,
        const HomeSession(
          repeat: 2,
          newTerms: 3,
          triage: 0,
          total: 5,
          cards: 11, // 2 repeats + 3 first meetings at three cards each
          estimatedMinutes: 1,
          avgSecondsPerCard: 8,
          triageMinutes: null,
        ),
      );

      expect(find.text('5 слов'), findsOneWidget);
      expect(find.text('~11 карточек'), findsOneWidget);
    });

    testWidgets('a day with no cards says nothing about cards', (tester) async {
      // The empty rule, unchanged: a block with no data is not drawn, not drawn as a zero.
      await pump(
        tester,
        const HomeSession(
          repeat: 0,
          newTerms: 0,
          triage: 0,
          total: 0,
          cards: 0,
          estimatedMinutes: null,
          avgSecondsPerCard: 8,
          triageMinutes: null,
        ),
      );

      expect(find.textContaining('карточ'), findsNothing);
    });
  });
}
