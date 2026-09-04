import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// B+ · СБОРКА НА ЭКРАНЕ — наряд SCENE-RUN, Ч.1.4.
///
/// Тот же экран диалога и тот же тренажёр: сервер кладёт на карточку блоки вместо вариантов, и
/// этого достаточно, чтобы ход перестал быть выбором. Здесь прибито ровно то, что человек видит:
/// вариантов нет, блоки есть, клавиатуры не появляется, а ответом уезжает собранная реплика.
void main() {
  const reply = 'My child has a fever';

  SessionCard assembleCard() => SessionCard(
    termId: '01SAY',
    mode: ExerciseMode.situationalSay,
    type: 'phrase',
    prompt: null,
    answer: reply,
    // Ни одного варианта — их место заняли блоки: слова самой реплики плюс чужие из плана.
    options: null,
    chips: const ['fever', 'has', 'front desk', 'My', 'a', 'child', 'today'],
    speakingKey: 'a fever',
    ladderStep: 3,
  );

  late List<SessionAnswer> answers;

  setUp(() => answers = []);

  Widget host(SessionCard c, {PlanSituation? position}) => ProviderScope(
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
              card: c,
              speechLocaleId: 'en_US',
              answerLang: 'en',
              autoPronounce: false,
              onAnswered: answers.add,
              onSpeak: (text, {bool slow = false}) async {},
              showDue: false,
              situation: position,
              speaksAfterChoice: true,
              inDialogue: true,
            ),
          ),
        ),
      ),
    ),
  );

  testWidgets('кладёт блоки и не даёт ни одного варианта', (tester) async {
    await tester.pumpWidget(host(assembleCard()));
    await tester.pumpAndSettle();

    for (final block in assembleCard().chips!) {
      expect(find.text(block), findsOneWidget);
    }
    // Целой реплики на экране нет: если бы она лежала вариантом, собирать было бы нечего.
    expect(find.text(reply), findsNothing);
    // И клавиатуры нет ни при каком уровне — канон §9.
    expect(find.byType(TextField), findsNothing);
  });

  testWidgets('собранная реплика уезжает ответом, и «Проверить» появляется только с блоками', (
    tester,
  ) async {
    await tester.pumpWidget(host(assembleCard()));
    await tester.pumpAndSettle();

    // Кнопки проверки нет, пока ничего не собрано: проверять нечего.
    expect(find.text('Проверить'), findsNothing);

    for (final block in ['My', 'child', 'has', 'a', 'fever']) {
      await tester.tap(find.text(block));
      await tester.pumpAndSettle();
    }

    await tester.tap(find.text('Проверить'));
    await tester.pumpAndSettle();

    expect(answers.single.response, reply);
    expect(answers.single.verdict, LocalCheck.correct);
    // Ответ уезжает тем же режимом: сборка — это подача ступени B, а не второй тренажёр, и в
    // append-only журнал ложится ровно один ответ, как и на выборе.
    expect(assembleCard().mode, ExerciseMode.situationalSay);
  });

  testWidgets('неверная сборка остаётся неверной — опечатки тут не прощаются', (tester) async {
    await tester.pumpWidget(host(assembleCard()));
    await tester.pumpAndSettle();

    for (final block in ['My', 'child', 'has', 'a', 'today']) {
      await tester.tap(find.text(block));
      await tester.pumpAndSettle();
    }
    await tester.tap(find.text('Проверить'));
    await tester.pumpAndSettle();

    expect(answers.single.verdict, isNot(LocalCheck.correct));
    expect(ExerciseMode.situationalSay.forgivesTypos, isFalse);
  });
}
