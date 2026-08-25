import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The introduction card (кадр 16b): the term set in BOLD inside its example, and the «новое слово»
/// badge below the content rather than above it.
///
/// The bolding failed on exactly the terms that need it most. A sentence-like term carries its own
/// final mark — «I have a fever.» — and its example embeds the sentence in a longer one, where that
/// full stop occurs nowhere: the search missed, the card fell back to plain text, and single-word
/// terms went on looking fine because a word has no trailing mark to trip over.
void main() {
  group('termSearchForm — the term as it is looked for in its own example', () {
    test('drops a trailing full stop', () {
      expect(termSearchForm('I have a fever.'), 'I have a fever');
    });

    test('drops a trailing question mark', () {
      expect(termSearchForm('How much does this bag cost?'), 'How much does this bag cost');
    });

    test('leaves a single word untouched — there was never anything to strip', () {
      expect(termSearchForm('fever'), 'fever');
      expect(termSearchForm('grain-free'), 'grain-free');
    });

    test('strips only the TAIL — inner punctuation is part of the term', () {
      expect(termSearchForm('Well, thanks!'), 'Well, thanks');
      expect(termSearchForm("it's fine…"), "it's fine");
    });

    test('a term that is nothing but punctuation stays as it is', () {
      // Normalising it away would leave the empty string, which matches at 0 and bolds nothing.
      expect(termSearchForm('?!'), '?!');
    });
  });

  group('finding the term inside the example', () {
    test('a phrase with a full stop is found in the sentence that embeds it', () {
      const example = 'I have a fever and feel very weak.';
      expect(spanPositionIn(example, 'I have a fever.'), -1, reason: 'the raw term is not there');
      expect(spanPositionIn(example, termSearchForm('I have a fever.')), 0);
    });

    test('a phrase with a question mark is found too', () {
      const example = 'How much does this bag cost if I take two?';
      expect(spanPositionIn(example, termSearchForm('How much does this bag cost?')), 0);
    });

    test('a single word is unaffected by the normalisation', () {
      expect(spanPositionIn('She had a fever all night.', termSearchForm('fever')), 10);
    });
  });

  group('the rendered card', () {
    SessionCard card({required String term, required String example}) => SessionCard(
      termId: 't1',
      mode: ExerciseMode.intro,
      type: 'phrase',
      prompt: 'у меня температура',
      answer: term,
      example: example,
    );

    Widget host(SessionCard c) => ProviderScope(
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          body: SingleChildScrollView(
            child: SessionIntroCard(
              card: c,
              speechLocaleId: 'en_US',
              autoPronounce: false,
              onSpeak: (text, {bool slow = false}) async {},
            ),
          ),
        ),
      ),
    );

    /// The bold runs of the example line, in order.
    List<String> boldRuns(WidgetTester tester, String example) {
      final rich = tester
          .widgetList<Text>(find.byType(Text))
          .firstWhere((t) => (t.textSpan?.toPlainText() ?? '') == example);
      final out = <String>[];
      rich.textSpan!.visitChildren((span) {
        if (span is TextSpan && span.style?.fontWeight == FontWeight.w700) {
          out.add(span.text ?? '');
        }
        return true;
      });
      return out;
    }

    testWidgets('a sentence-like term is bolded inside its example', (tester) async {
      const example = 'I have a fever and feel very weak.';
      await tester.pumpWidget(host(card(term: 'I have a fever.', example: example)));
      await tester.pump(const Duration(milliseconds: 400));

      expect(boldRuns(tester, example), ['I have a fever']);
    });

    testWidgets('the bolded run is the EXAMPLE own letters, not the term as stored', (
      tester,
    ) async {
      // «Tell me about yourself» is stored capitalised, because that is how the phrase is written on
      // its own — and the example embeds it mid-question, lowercase. Drawing the term's own text in
      // the sentence produced «Could you Tell me about yourself…»: a capital in the middle of a
      // sentence, which reads as a typo in the content the learner is being taught from.
      const example = 'Could you tell me about yourself and your career background?';
      await tester.pumpWidget(host(card(term: 'Tell me about yourself', example: example)));
      await tester.pump(const Duration(milliseconds: 400));

      expect(boldRuns(tester, example), ['tell me about yourself']);
      // The whole line, reassembled, is still the example verbatim — the bold changes the WEIGHT of
      // those letters and never the letters themselves.
      expect(
        tester
            .widgetList<Text>(find.byType(Text))
            .map((t) => t.textSpan?.toPlainText() ?? '')
            .where((t) => t.contains('tell me about yourself')),
        contains(example),
      );
    });

    testWidgets('the «новое слово» badge sits BELOW the term and the example', (tester) async {
      const example = 'I have a fever and feel very weak.';
      await tester.pumpWidget(host(card(term: 'I have a fever.', example: example)));
      await tester.pump(const Duration(milliseconds: 400));

      final badge = tester.getTopLeft(find.text('НОВОЕ СЛОВО')).dy;
      final term = tester.getTopLeft(find.text('I have a fever.')).dy;
      final line = tester.getTopLeft(find.text(example)).dy;
      expect(term, lessThan(badge), reason: 'the word meets the reader first');
      expect(line, lessThan(badge), reason: 'the badge is a footnote, not a heading');
    });
  });
}
