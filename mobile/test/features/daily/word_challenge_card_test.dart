import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/locale_controller.dart';
import 'package:eng_std/features/daily/word_challenge.dart';
import 'package:eng_std/features/daily/word_challenge_card.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// КАДР 19-4 — the four states of the challenge, asserted by what each one says.
///
/// The frame's own rule for the pair «верно» / «неверно» is that they differ by ONE line: no red, no
/// «неверно», the same two buttons. A miss that looked like a special scenario would teach the
/// learner not to answer.
void main() {
  WordChallenge challenge({
    int streak = 0,
    String? chosen,
    bool collapsed = false,
    List<String>? options,
  }) => WordChallenge(
    termId: 't1',
    text: 'reluctant',
    translation: 'неохотный',
    options: options ?? const ['неохотный', 'надёжный', 'заметный'],
    streak: streak,
    example: 'He was reluctant to leave',
    exampleTranslation: 'Он неохотно уходил',
    chosen: chosen,
    collapsed: collapsed,
    optionOwners: const {'надёжный': 'reliable', 'заметный': 'noticeable'},
  );

  final answered = <String>[];
  var learned = 0, tomorrow = 0;

  setUp(() {
    answered.clear();
    learned = 0;
    tomorrow = 0;
  });

  Future<void> pump(WidgetTester tester, WordChallenge c, {bool enrolled = false}) =>
      tester.pumpWidget(
        MaterialApp(
          locale: const Locale('ru'),
          supportedLocales: kSupportedLocales,
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          home: Scaffold(
            body: WordChallengeCard(
              challenge: c,
              enrolled: enrolled,
              onAnswer: answered.add,
              onLearn: () => learned++,
              onTomorrow: () => tomorrow++,
            ),
          ),
        ),
      );

  group('а · вопрос', () {
    testWidgets('the word, three options, and the run', (tester) async {
      await pump(tester, challenge(streak: 6));

      expect(find.text('reluctant'), findsOneWidget);
      expect(find.text('неохотный'), findsOneWidget);
      expect(find.text('надёжный'), findsOneWidget);
      expect(find.text('заметный'), findsOneWidget);
      expect(find.text('угадано 6 подряд'), findsOneWidget);
      // Nothing is revealed while the question stands.
      expect(find.textContaining('Он неохотно уходил'), findsNothing);
    });

    testWidgets('a run of nothing is not «угадано 0 подряд»', (tester) async {
      await pump(tester, challenge());

      expect(find.textContaining('подряд'), findsNothing);
    });

    testWidgets('a tap reports the option and nothing else', (tester) async {
      await pump(tester, challenge());
      await tester.tap(find.text('надёжный'));

      expect(answered, ['надёжный']);
    });
  });

  group('б · верно', () {
    testWidgets('one short praise, the answer, the example, both buttons', (tester) async {
      await pump(tester, challenge(streak: 7, chosen: 'неохотный'));

      expect(find.text('Знаешь!'), findsOneWidget);
      expect(find.text('reluctant — неохотный'), findsOneWidget);
      expect(find.text('He was reluctant to leave — Он неохотно уходил'), findsOneWidget);
      expect(find.text('угадано 7 подряд'), findsOneWidget);
      expect(find.text('Учить'), findsOneWidget);
      expect(find.text('Завтра новое'), findsOneWidget);
      // The options are gone — the card is an answer now, not a question.
      expect(find.text('заметный'), findsNothing);
    });

    testWidgets('«Учить» is a real act, and says so once it is done', (tester) async {
      await pump(tester, challenge(chosen: 'неохотный'));
      await tester.tap(find.text('Учить'));
      expect(learned, 1);

      await pump(tester, challenge(chosen: 'неохотный'), enrolled: true);
      expect(find.text('Слово в очереди'), findsOneWidget);
      expect(find.text('Учить'), findsNothing);
    });

    testWidgets('«Завтра новое» folds it away', (tester) async {
      await pump(tester, challenge(chosen: 'неохотный'));
      await tester.tap(find.text('Завтра новое'));

      expect(tomorrow, 1);
    });
  });

  group('в · неверно', () {
    testWidgets('no red and no «неверно» — the answer, and one line about the miss', (
      tester,
    ) async {
      await pump(tester, challenge(chosen: 'надёжный'));

      expect(find.text('reluctant — неохотный'), findsOneWidget);
      expect(find.text('Вы выбрали «надёжный» — это reliable'), findsOneWidget);
      expect(find.text('серия сброшена'), findsOneWidget);
      // The same two buttons as a hit: a mistake is not a special scenario.
      expect(find.text('Учить'), findsOneWidget);
      expect(find.text('Завтра новое'), findsOneWidget);
      expect(find.textContaining('еверно'), findsNothing);
    });

    testWidgets('an option nothing is known about explains nothing rather than half a sentence', (
      tester,
    ) async {
      final c = WordChallenge(
        termId: 't1',
        text: 'reluctant',
        translation: 'неохотный',
        options: const ['неохотный', 'надёжный', 'заметный'],
        streak: 0,
        chosen: 'надёжный',
        // No owners: the wrong option belongs to no term this device can name.
        optionOwners: const {},
      );
      await pump(tester, c);

      expect(find.textContaining('Вы выбрали'), findsNothing);
      expect(find.text('reluctant — неохотный'), findsOneWidget);
    });
  });

  group('г · свёрнуто', () {
    testWidgets('one line, and not a dead block', (tester) async {
      await pump(tester, challenge(chosen: 'неохотный', collapsed: true));

      expect(find.text('Завтра новое слово'), findsOneWidget);
      expect(find.text('reluctant'), findsNothing);
      expect(find.text('Учить'), findsNothing);
      expect(find.textContaining('СЛОВО-ВЫЗОВ'), findsNothing);
    });
  });

  testWidgets('long options fall into a column, short ones stay in a row', (tester) async {
    // The frame's own rule: three across fits 390 pt until an option runs past ~13 characters.
    await pump(tester, challenge(options: const ['да', 'нет', 'может']));
    final short = tester.getSize(find.ancestor(of: find.text('да'), matching: find.byType(Row)).first);

    await pump(
      tester,
      challenge(options: const ['неохотный и осторожный', 'надёжный', 'заметный']),
    );
    expect(find.byType(Column), findsWidgets);
    expect(find.text('неохотный и осторожный'), findsOneWidget);
    expect(short.width, greaterThan(0));
  });
}
