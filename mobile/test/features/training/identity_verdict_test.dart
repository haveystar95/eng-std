import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The verdict card must show WORDS, never the grading key.
///
/// Ladder rung 1 (`term → translation`) is the one card graded by identity: its `answer` is the
/// card's own term id, and the options are translations. The card that shipped printed that key as
/// the «correct form» headline and fed it to TTS — a raw ULID where «over the counter» belonged,
/// read out loud letter by letter. The transcription and the example beside it were right, because
/// those come from their own fields and never touch the key. These tests pin the three as separate
/// things: key (id) ≠ shown text ≠ spoken text.
void main() {
  // The exact id from the device report, so the regression is pinned to the shape that shipped.
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';
  const term = 'over the counter';
  const transcription = 'ˌoʊvər ðə ˈkaʊntər';
  const example = 'You can buy this medicine over-the-counter.';

  /// Rung 1: the prompt is the TERM, the options are translations, the key is the term id.
  SessionCard forwardCard() => SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'phrase',
        prompt: term,
        answer: termId,
        transcription: transcription,
        example: example,
        options: const ['без рецепта', 'по расписанию', 'за наличные'],
        optionIds: const [termId, '01M00WHZFYJSYW76Z4B4BBASYD', '01M00WHZFYJSYW76Z4B4BBASZE'],
      );

  /// Rung 2: the ordinary direction — prompt is the translation, and the key really is the term.
  SessionCard reverseCard() => SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'phrase',
        prompt: 'без рецепта',
        answer: term,
        transcription: transcription,
        example: example,
        options: const [term, 'on schedule', 'in cash'],
      );

  group('SessionCard.answerText', () {
    test('an identity-graded card shows the term, not its id', () {
      final card = forwardCard();
      expect(card.isIdentityGraded, isTrue);
      expect(card.answerText, term);
      expect(card.answerText, isNot(card.answer));
      expect(card.answerText, isNot(card.termId));
    });

    test('every other card is unchanged — the key IS the text there', () {
      final card = reverseCard();
      expect(card.isIdentityGraded, isFalse);
      expect(card.answerText, card.answer);
      expect(card.answerText, term);
    });

    test('the key never leaks through, even with no prompt to fall back on', () {
      final card = SessionCard(
        termId: termId,
        mode: ExerciseMode.multipleChoice,
        type: 'word',
        answer: termId,
        options: const ['a', 'b'],
        optionIds: const [termId, 'T2'],
      );
      // Empty is a blemish; the id would be the bug.
      expect(card.answerText, isEmpty);
      expect(card.answerText, isNot(contains(termId)));
    });
  });

  group('the verdict card', () {
    late List<String> spoken;

    setUp(() => spoken = []);

    Future<void> onSpeak(String text, {bool slow = false}) async => spoken.add(text);

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
            // Reduce-motion: the «writes itself» headline renders in full on the first frame
            // instead of one character at a time.
            home: MediaQuery(
              data: const MediaQueryData(disableAnimations: true),
              child: Scaffold(
                body: SingleChildScrollView(
                  child: SessionExerciseCard(
                    card: card,
                    autoPronounce: false,
                    onAnswered: (_) {},
                    onSpeak: onSpeak,
                    showDue: false,
                  ),
                ),
              ),
            ),
          ),
        );

    /// Every string this screen actually renders — plain [Text] and [Text.rich] alike.
    List<String> visibleText(WidgetTester tester) => tester
        .widgetList<Text>(find.byType(Text))
        .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
        .where((s) => s.isNotEmpty)
        .toList();

    testWidgets('a wrong pick on rung 1 headlines the TERM, never the id', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('по расписанию'));
      await tester.pumpAndSettle();

      final shown = visibleText(tester);
      expect(shown, contains(term));
      expect(
        shown.where((s) => s.contains(termId)),
        isEmpty,
        reason: 'the grading key must never reach the screen',
      );
      // The fields that were already right must stay right — they are the proof the leak was the
      // answer field alone and not the whole card.
      expect(shown, contains('/$transcription/'));
      expect(shown, contains(example));
    });

    testWidgets('the verdict speak button reads the term out loud, not the id', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('по расписанию'));
      await tester.pumpAndSettle();
      spoken.clear(); // ignore anything the card said on appearance

      await tester.tap(find.byIcon(LucideIcons.volume2));
      await tester.pumpAndSettle();

      expect(spoken, isNotEmpty);
      expect(spoken, everyElement(isNot(contains(termId))));
      expect(spoken.last, term);
    });

    testWidgets('rung 2 (translation → term) headlines the term too', (tester) async {
      await tester.pumpWidget(host(reverseCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('on schedule'));
      await tester.pumpAndSettle();

      expect(visibleText(tester), contains(term));
    });
  });

  group('no verdict anywhere may render a ULID', () {
    // Crockford base32, 26 chars — the shape of every id this API mints.
    final ulid = RegExp(r'\b[0-9A-HJKMNP-TV-Z]{26}\b');

    test('the guard recognises the id that shipped', () {
      expect(ulid.hasMatch(termId), isTrue);
      expect(ulid.hasMatch(term), isFalse);
      expect(ulid.hasMatch(example), isFalse);
    });

    test('answerText is ULID-free on every card shape the session deals', () {
      final cards = [
        forwardCard(),
        reverseCard(),
        SessionCard(
          termId: termId,
          mode: ExerciseMode.intro,
          type: 'phrase',
          prompt: 'без рецепта',
          answer: term,
          example: example,
        ),
        SessionCard(
          termId: termId,
          mode: ExerciseMode.dictation,
          type: 'phrase',
          answer: example,
          example: example,
        ),
      ];

      for (final card in cards) {
        expect(
          ulid.hasMatch(card.answerText),
          isFalse,
          reason: 'a ${card.mode.wire} card would print an id as its correct answer',
        );
      }
    });
  });
}
