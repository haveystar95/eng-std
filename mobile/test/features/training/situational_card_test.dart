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

/// THE SITUATIONAL CARD ON SCREEN — наряд SIT-1, Ч-1/Ч-2/Ч-7.
///
/// One mechanic, three shelves: a position on the learner's own language, options on the one being
/// learned, a tap. What this file pins is the two things that make it that card rather than an
/// ordinary choice — the POSITION above the options, and the silence of «Тебе скажут» until it has
/// been answered — plus the answer path, which is where a card that renders perfectly can still
/// upload the wrong thing.
void main() {
  const roleLine = 'What seems to be the problem?';
  const reply = 'My child has a fever.';
  const replyTranslation = 'У моего ребёнка температура.';

  SessionCard hearCard() => SessionCard(
    termId: '01HEAR',
    mode: ExerciseMode.situationalHear,
    type: 'phrase',
    // The line rides in the PROMPT: the device needs it to speak it, and the card is what keeps it
    // hidden until the meaning has been chosen.
    prompt: roleLine,
    // Identity-graded — the options are MEANINGS, so the key is this card's own term id.
    answer: '01HEAR',
    options: const ['Спрашивает, срочно ли это', 'Спрашивает, что случилось', 'Просит подойти к стойке'],
    optionIds: const ['01OTHER', '01HEAR', '01THIRD'],
    ladderStep: 3,
  );

  SessionCard sayCard() => SessionCard(
    termId: '01SAY',
    mode: ExerciseMode.situationalSay,
    type: 'phrase',
    // NO PROMPT: the question is the situation, and a translation here would answer the card.
    prompt: null,
    answer: reply,
    options: const [reply, 'We need a doctor today.', 'Is it at the front desk?'],
    speakingKey: 'a fever',
    ladderStep: 3,
  );

  // «Тебе скажут» announces the SCENE and never the вводка: the вводка says what is about to
  // happen, which on this card is the answer.
  const heard = PlanSituation(
    source: PlanSituation.sourceScene,
    context: 'Стойка регистратуры',
  );

  const situation = PlanSituation(
    source: PlanSituation.sourceRoleLine,
    context: 'Вы у стойки регистратуры.',
    roleLine: roleLine,
    roleLineTermId: '01HEAR',
  );

  group('the answer path', () {
    test('«Тебе скажут» is graded by the tapped option’s id, never by text', () {
      expect(hearCard().isIdentityGraded, isTrue);
      expect(ExerciseMode.situationalHear.gradesByOptionId, isTrue);
      expect(hearCard().answer, '01HEAR');
      // …and nothing about it is ever produced by the learner.
      expect(ExerciseMode.situationalHear.speaksAfterChoice, isFalse);
    });

    test('the two speak shelves are graded by TEXT, and say the line afterwards', () {
      expect(sayCard().isIdentityGraded, isFalse);
      expect(ExerciseMode.situationalSay.gradesByOptionId, isFalse);
      expect(ExerciseMode.situationalSay.speaksAfterChoice, isTrue);
      expect(ExerciseMode.situationalAsk.speaksAfterChoice, isTrue);
    });

    test('all three are tapped: no typos to forgive, no example to ask for', () {
      for (final mode in [
        ExerciseMode.situationalHear,
        ExerciseMode.situationalSay,
        ExerciseMode.situationalAsk,
      ]) {
        expect(mode.isSituational, isTrue);
        expect(mode.forgivesTypos, isFalse);
        expect(mode.isTyped, isFalse);
        expect(mode.isAssembled, isFalse);
        expect(mode.asksForExample(3), isFalse);
      }
    });
  });

  group('the card on screen', () {
    late List<SessionAnswer> answers;
    late List<String> spoken;

    setUp(() {
      answers = [];
      spoken = [];
    });

    Widget host(SessionCard c, {PlanSituation? position, bool speaks = false}) => ProviderScope(
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
                onSpeak: (text, {bool slow = false}) async => spoken.add(text),
                showDue: false,
                situation: position,
                sayIntent: null,
              ),
            ),
          ),
        ),
      ),
    );

    testWidgets('«Ты ответишь» draws the position above the options, and no translation', (
      tester,
    ) async {
      await tester.pumpWidget(host(sayCard(), position: situation, speaks: true));
      await tester.pumpAndSettle();

      // The moment, on the learner's own language, and what was said to them, on the one they are
      // learning — кадр D-04.
      expect(find.text('СИТУАЦИЯ'), findsOneWidget);
      expect(find.text('Вы у стойки регистратуры.'), findsOneWidget);
      expect(find.text(roleLine), findsOneWidget);
      for (final option in sayCard().options!) {
        expect(find.text(option), findsOneWidget);
      }
      // THE ONE THING THAT MUST NOT BE THERE: a gloss of the right option would make the card a
      // translation exercise (канон §4, §13).
      expect(find.text(replyTranslation), findsNothing);
    });

    testWidgets('«Тебе скажут» prints nothing until it has been answered', (tester) async {
      await tester.pumpWidget(host(hearCard(), position: heard));
      await tester.pumpAndSettle();

      // The scene is named; the LINE is not on screen at all — the sound is the question.
      expect(find.text('СЕЙЧАС УСЛЫШИТЕ'), findsOneWidget);
      expect(find.text('Стойка регистратуры'), findsOneWidget);
      expect(find.text(roleLine), findsNothing);
      expect(find.text('Показать текст'), findsNothing);
      for (final option in hearCard().options!) {
        expect(find.text(option), findsOneWidget);
      }
    });

    testWidgets('…and offers the text once the meaning has been chosen', (tester) async {
      await tester.pumpWidget(host(hearCard(), position: heard));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Спрашивает, что случилось'));
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.correct);
      // The id, not the meaning: no translation ever enters an answer key.
      expect(answers.single.response, '01HEAR');

      expect(find.text('Показать текст'), findsOneWidget);
      expect(find.text(roleLine), findsNothing);

      await tester.tap(find.text('Показать текст'));
      await tester.pumpAndSettle();
      expect(find.text(roleLine), findsOneWidget);
    });

    // ПРАВИЛО: наряд DAY-GATE-1, Ч.2.4/Ч.2.5 — «повтори вслух» живёт В ОДНОМ месте, на эхе
    // знакомства, а блок «Скажи вслух» после выбора удалён вместе с ключом.
    // ЛОВИТ: возвращение второго «скажи вслух». Их было два, и они делали разное одним словом:
    // на знакомстве микрофон слушает, после выбора — только кнопка «прочитать ещё раз», которую
    // человек нажимал, думая, что его слышат. Живой прогон 07.09 упёрся ровно в это.
    //
    // Прежний тест ЗАКРЕПЛЯЛ ЭТОТ ДЕФЕКТ: он требовал, чтобы блок «СКАЖИ ВСЛУХ» стоял после
    // выбора, — и переписан под канон, а не подогнан под код.
    testWidgets('после выбора нет второго «скажи вслух» — оценка одна, и она за выбор', (
      tester,
    ) async {
      await tester.pumpWidget(host(sayCard(), position: situation, speaks: true));
      await tester.pumpAndSettle();

      await tester.tap(find.text(reply));
      await tester.pumpAndSettle();

      // ONE answer for the card — the CHOICE, ровно как и было.
      expect(answers, hasLength(1));
      expect(answers.single.response, reply);
      expect(find.text('СКАЖИ ВСЛУХ'), findsNothing);
    });

    testWidgets('a situational card with no position degrades to the choice it is underneath', (
      tester,
    ) async {
      // A card met outside a plan envelope, and a card played INSIDE the conversation — where the
      // shell owns the moment and the server sends no position at all (наряд DAY-2, Ч.1.5). Either
      // way it is a playable card, and the heading goes with the position: «СИТУАЦИЯ» over nothing
      // is a label for something that is not there, and on the dialogue screen it stood over the
      // very line the bubble above had just said it was hiding.
      await tester.pumpWidget(host(sayCard()));
      await tester.pumpAndSettle();

      expect(find.text('СИТУАЦИЯ'), findsNothing);
      expect(find.text('Вы у стойки регистратуры.'), findsNothing);
      expect(find.text(reply), findsOneWidget);
      // The question is still asked — the card degrades to its own instruction, not to silence.
      expect(find.textContaining('выбери'), findsOneWidget);
    });
  });
}
