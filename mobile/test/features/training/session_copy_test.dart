import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// What the card SAYS — the two lines the device caught telling the learner the wrong thing.
///
/// 1. The instruction under a recognition prompt. Rung 1 shows the English term and offers Russian
///    translations; «выбери английский эквивалент» printed there asks for the opposite of what the
///    card wants. The direction is knowable — it is exactly the card graded by identity.
/// 2. The wrong-answer verdict. «Не то — правильная форма ниже» points DOWN, which is right for a
///    typed answer and wrong for a tapped one: there the correct option is marked in the list above,
///    and below sits only a reminder of the term (QA-8).
void main() {
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';

  /// Rung 1: prompt is the TERM, options are translations, the key is the term id.
  SessionCard forwardCard() => SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'cold',
        answer: termId,
        options: const ['простуда', 'жара', 'счёт'],
        optionIds: const [termId, 'T2', 'T3'],
      );

  /// Rung 2: the ordinary direction — prompt is the translation, key is the term's text.
  SessionCard reverseCard() => SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        prompt: 'простуда',
        answer: 'cold',
        options: const ['cold', 'heat', 'bill'],
      );

  SessionCard typingCard() => SessionCard(
        termId: termId,
        mode: ExerciseMode.typing,
        type: 'word',
        prompt: 'простуда',
        answer: 'cold',
      );

  Widget host(SessionCard card) => ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
        ],
        child: MaterialApp(
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru'), Locale('en')],
          home: MediaQuery(
            data: const MediaQueryData(disableAnimations: true),
            child: Scaffold(
              body: SingleChildScrollView(
                child: SessionExerciseCard(
                  card: card,
                  autoPronounce: false,
                  onAnswered: (_) {},
                  onSpeak: (text, {bool slow = false}) async {},
                  showDue: false,
                ),
              ),
            ),
          ),
        ),
      );

  group('the instruction knows which way the card asks', () {
    testWidgets('term → translation asks for the translation', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      expect(find.textContaining('выбери перевод'), findsOneWidget);
      expect(find.textContaining('выбери английский эквивалент'), findsNothing);
    });

    testWidgets('translation → term still asks for the English equivalent', (tester) async {
      await tester.pumpWidget(host(reverseCard()));
      await tester.pumpAndSettle();

      expect(find.textContaining('выбери английский эквивалент'), findsOneWidget);
      expect(find.textContaining('выбери перевод'), findsNothing);
    });
  });

  group('the wrong-answer verdict points where the answer is', () {
    testWidgets('a tapped card points UP, to the marked option', (tester) async {
      await tester.pumpWidget(host(reverseCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('heat'));
      await tester.pumpAndSettle();

      expect(find.text('Не то — верный ответ отмечен выше'), findsOneWidget);
      expect(find.text('Не то — правильная форма ниже'), findsNothing);
    });

    testWidgets('a rung-1 identity card points UP as well', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('жара'));
      await tester.pumpAndSettle();

      expect(find.text('Не то — верный ответ отмечен выше'), findsOneWidget);
    });

    testWidgets('a typed card keeps the old line — the form really is below', (tester) async {
      await tester.pumpWidget(host(typingCard()));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'warm');
      await tester.testTextInput.receiveAction(TextInputAction.done);
      await tester.pumpAndSettle();

      expect(find.text('Не то — правильная форма ниже'), findsOneWidget);
      expect(find.text('Не то — верный ответ отмечен выше'), findsNothing);
    });
  });
}
