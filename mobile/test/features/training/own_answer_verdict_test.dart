import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// QA-16: a WRONG verdict has to show the learner what THEY answered, with the mistake marked.
///
/// Cloze filled the blank with the correct form the moment the answer was committed, so the sentence
/// on screen read as though they had typed the right thing — and the «правильная форма» card
/// underneath then corrected an answer that was no longer visible anywhere. word_bank kept the
/// assembled line but said nothing about WHERE it went wrong: a terracotta rule under a sentence
/// that looked fine.
///
/// Both now keep the learner's own words and mark the ones that do not belong, with the same wavy
/// terracotta pick_correct puts under a broken fragment.
void main() {
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
      supportedLocales: const [Locale('ru')],
      // Reduce-motion, so the verdict's reveal animations resolve at once and the tests can
      // settle instead of racing tickers. Same host shape as identity_verdict_test.
      home: MediaQuery(
        data: const MediaQueryData(disableAnimations: true),
        child: Scaffold(
          body: SingleChildScrollView(
            child: SessionExerciseCard(
              card: card,
              speechLocaleId: 'en_US',
              autoPronounce: false,
              // The «когда снова» line reads the local DB, and its stream keeps a timer alive
              // past teardown. Nothing here is about the due date.
              showDue: false,
              onAnswered: (_) {},
              onSpeak: (text, {bool slow = false}) async {},
            ),
          ),
        ),
      ),
    ),
  );

  /// Every string the tree draws, whether as `Text` or as a `Text.rich` span tree.
  List<String> renderedText(WidgetTester tester) => tester
      .widgetList<Text>(find.byType(Text))
      .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
      .toList();

  /// The span carrying [word], wherever in the tree it is drawn.
  TextStyle? styleOf(WidgetTester tester, String word) {
    for (final text in tester.widgetList<Text>(find.byType(Text))) {
      final span = text.textSpan;
      if (span == null) continue;
      TextStyle? found;
      span.visitChildren((child) {
        if (child is TextSpan && (child.text ?? '') == word) {
          found = child.style;
          return false;
        }
        return true;
      });
      if (found != null) return found;
    }
    return null;
  }

  SessionCard clozeCard() => SessionCard(
    termId: '01AAAAAAAAAAAAAAAAAAAAAAA1',
    mode: ExerciseMode.cloze,
    type: 'phrase',
    prompt: 'снять наличные',
    answer: 'withdraw cash',
    example: 'I need to withdraw cash before we leave.',
    exampleTranslation: 'Мне нужно снять наличные перед отъездом.',
  );

  testWidgets('cloze keeps the learner own word in the blank after a wrong answer', (tester) async {
    await tester.pumpWidget(host(clozeCard()));
    await tester.pump();

    await tester.enterText(find.byType(TextField), 'withdraw money');
    await tester.pump();
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    // What they typed is still in the sentence…
    expect(
      renderedText(tester).where((t) => t.contains('withdraw money')),
      isNotEmpty,
      reason: 'the blank must show the answer that was actually given',
    );
    // …and the wrong word carries the wavy terracotta mark.
    expect(styleOf(tester, 'withdraw money')?.decorationStyle, TextDecorationStyle.wavy);
  });

  testWidgets('cloze leaves an accepted answer unmarked', (tester) async {
    await tester.pumpWidget(host(clozeCard()));
    await tester.pump();

    await tester.enterText(find.byType(TextField), 'withdraw cash');
    await tester.pump();
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    // A mark on a right answer is a correction where there was no mistake.
    expect(styleOf(tester, 'withdraw cash')?.decorationStyle, isNot(TextDecorationStyle.wavy));
  });

  testWidgets('cloze never prints the TERM own capital into the sentence', (tester) async {
    // The owner's case: «Tell me about yourself» is stored capitalised, because that is how the
    // phrase is written on its own — and the example embeds it in the middle of a question. Filling
    // the blank with the term as stored produced «Could you Tell me about yourself…».
    await tester.pumpWidget(
      host(
        SessionCard(
          termId: '01AAAAAAAAAAAAAAAAAAAAAAA2',
          mode: ExerciseMode.cloze,
          type: 'phrase',
          prompt: 'расскажите о себе',
          answer: 'Tell me about yourself',
          example: 'Could you tell me about yourself and your career background?',
          exampleTranslation: 'Не могли бы вы рассказать о себе и о своём опыте?',
        ),
      ),
    );
    await tester.pump();

    await tester.enterText(find.byType(TextField), 'tell me about yourself');
    await tester.pump();
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pumpAndSettle();

    expect(
      renderedText(tester).where((t) => t.contains('Could you Tell me about yourself')),
      isEmpty,
      reason: 'the sentence keeps ITS letters; the term is not pasted in as stored',
    );
  });

  testWidgets('word_bank marks the word that does not belong, and keeps the line', (tester) async {
    await tester.pumpWidget(
      host(
        SessionCard(
          termId: '01AAAAAAAAAAAAAAAAAAAAAAA3',
          mode: ExerciseMode.wordBank,
          type: 'phrase',
          prompt: 'снять наличные',
          answer: 'withdraw cash',
          chips: const ['withdraw', 'cash', 'money'],
        ),
      ),
    );
    await tester.pump();

    // Assemble the wrong line: the right first word, then the wrong second one.
    await tester.tap(find.text('withdraw'));
    await tester.pump();
    await tester.tap(find.text('money'));
    await tester.pump();
    await tester.tap(find.text('Проверить'));
    await tester.pumpAndSettle();

    // Both words are still on screen — the learner's own sentence, not a corrected one…
    expect(find.text('withdraw'), findsWidgets);
    expect(find.text('money'), findsWidgets);

    // …and only the one that does not belong is marked. Each word appears twice — once on the
    // assembly line, once in the chip bank below — so this asks whether ANY drawing of it carries
    // the mark, which is the question the learner's eye asks too.
    bool marked(String word) => tester
        .widgetList<Text>(find.text(word))
        .any((t) => t.style?.decorationStyle == TextDecorationStyle.wavy);

    expect(marked('money'), isTrue, reason: 'the word that does not belong is named');
    expect(marked('withdraw'), isFalse, reason: 'the word that does belong is left alone');
  });
}
