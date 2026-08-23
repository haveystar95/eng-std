import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_queue.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Rung 1 is answered by TAPPING and graded by IDENTITY: the key is the card's own term id, the
/// client uploads the tapped option's term id, and `ladder_step: 1` is what tells the server to
/// read it that way.
///
/// Before this, the client uploaded the option's TEXT and sent no rung at all — so the server
/// compared a Russian translation against the English term and folded every rung-1 answer, right
/// or wrong, as `again`. The client's own instant check did the same against a ULID, so a correct
/// tap showed «Not quite» and no option ever got a check mark. Verified on the device: a correct
/// tap came back `grade=again, is_correct=f, ladder_step=NULL`.
void main() {
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';
  const otherId = '01M00WHZFYJSYW76Z4B4BBASYD';
  const thirdId = '01M00WHZFYJSYW76Z4B4BBASZE';
  const term = 'over the counter';

  /// Rung 1: prompt is the TERM, options are translations, key is the term id.
  SessionCard forwardCard() => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: term,
    answer: termId,
    example: 'You can buy this medicine over-the-counter.',
    options: const ['по расписанию', 'без рецепта', 'за наличные'],
    optionIds: const [otherId, termId, thirdId],
    ladderStep: 1,
  );

  /// Rung 2: ordinary text grading.
  SessionCard reverseCard() => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: 'без рецепта',
    answer: term,
    options: const [term, 'on schedule', 'in cash'],
    ladderStep: 2,
  );

  group('the answer key', () {
    test('an identity card resolves the tapped option to its term id', () {
      final card = forwardCard();
      expect(card.optionIdAt(0), otherId);
      expect(card.optionIdAt(1), termId);
      expect(card.optionIdAt(1), card.answer, reason: 'the correct option IS the card term');
      expect(card.optionIdAt(9), isNull, reason: 'out of range must not throw');
    });

    test('a text-graded card has no option ids at all', () {
      final card = reverseCard();
      expect(card.isIdentityGraded, isFalse);
      expect(card.optionIdAt(0), isNull);
    });
  });

  group('the uploaded payload', () {
    PendingReview review({required String response, int? ladderStep}) => PendingReview(
      id: '01M00WHZFYJSYW76Z4B4BBAS11',
      termId: termId,
      exerciseMode: 'multiple_choice',
      response: response,
      clientSeq: 7,
      answeredAt: '2026-08-17T13:00:00.000Z',
      ladderStep: ladderStep,
    );

    test('a rung-1 answer carries the rung and an id as the response', () {
      final json = review(response: termId, ladderStep: 1).toBatchJson();
      expect(json['ladder_step'], 1);
      expect(json['response'], termId);
    });

    test('a rung-2 answer carries the rung and the answer TEXT', () {
      final json = review(response: term, ladderStep: 2).toBatchJson();
      expect(json['ladder_step'], 2);
      expect(json['response'], term);
    });

    test('an off-ladder answer omits the key entirely', () {
      expect(review(response: term).toBatchJson().containsKey('ladder_step'), isFalse);
    });

    test('rung 0 is never sent — an intro is an exposure, not an answer', () {
      // The contract's ladder_step is 1–5; 0 would be rejected as out of range.
      expect(
        review(response: term, ladderStep: 0).toBatchJson().containsKey('ladder_step'),
        isFalse,
      );
    });

    test('a queue row survives a round trip through JSON', () {
      final original = review(response: termId, ladderStep: 1);
      expect(PendingReview.fromJson(original.toJson()).ladderStep, 1);
    });

    test('a row queued before this field existed reads back as null, not 0', () {
      final legacy = Map<String, dynamic>.from(review(response: term).toJson())
        ..remove('ladder_step');
      expect(PendingReview.fromJson(legacy).ladderStep, isNull);
    });
  });

  group('the instant check', () {
    late List<SessionAnswer> answers;

    setUp(() => answers = []);

    Future<void> onSpeak(String text, {bool slow = false}) async {}

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
                onAnswered: answers.add,
                onSpeak: onSpeak,
                showDue: false,
              ),
            ),
          ),
        ),
      ),
    );

    testWidgets('a correct rung-1 tap is CORRECT and uploads the term id', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('без рецепта'));
      await tester.pumpAndSettle();

      expect(answers, hasLength(1));
      expect(answers.single.verdict, LocalCheck.correct);
      expect(answers.single.response, termId, reason: 'the id is the key, not the translation');
    });

    testWidgets('a wrong rung-1 tap is WRONG and uploads that option own id', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('по расписанию'));
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.wrong);
      expect(answers.single.response, otherId);
    });

    testWidgets('the correct rung-1 option gets its check mark', (tester) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      expect(find.byIcon(LucideIcons.check), findsNothing);

      await tester.tap(find.text('без рецепта'));
      await tester.pumpAndSettle();

      // Two, and both earned: the tick on the correct option and the one in the verdict row.
      expect(find.byIcon(LucideIcons.check), findsNWidgets(2));
      expect(find.byIcon(LucideIcons.x), findsNothing, reason: 'nothing here is wrong');
    });

    testWidgets('rung 2 still grades as text and uploads text', (tester) async {
      await tester.pumpWidget(host(reverseCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text(term));
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.correct);
      expect(answers.single.response, term);
    });

    testWidgets('the display still shows words — the key never leaks back into the UI', (
      tester,
    ) async {
      await tester.pumpWidget(host(forwardCard()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('по расписанию'));
      await tester.pumpAndSettle();

      final shown = tester
          .widgetList<Text>(find.byType(Text))
          .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '');
      expect(shown, contains(term));
      expect(shown.where((s) => s.contains(termId)), isEmpty);
    });
  });

  group('parity with the server', () {
    // Mirror of SubmitReviewsHandler::expectedFor + isForwardRecognition: on rung 1 (multiple_choice,
    // not practice) the expected answer is the card's own term id, and correctness is exact equality.
    bool serverAcceptsForwardRecognition(String response, String cardTermId) =>
        response == cardTermId;

    /// What the client now decides, in the same terms.
    bool clientAccepts(SessionCard card, String response) => card.isIdentityGraded
        ? response == card.answer
        : SessionGrader.check(response, card.answer, variants: card.acceptedVariants).isAccepted;

    test('a correct tap: both sides accept', () {
      final card = forwardCard();
      expect(clientAccepts(card, termId), isTrue);
      expect(serverAcceptsForwardRecognition(termId, card.termId), isTrue);
    });

    test('a wrong tap: both sides refuse — the lapse is earned, not manufactured', () {
      final card = forwardCard();
      expect(clientAccepts(card, otherId), isFalse);
      expect(serverAcceptsForwardRecognition(otherId, card.termId), isFalse);
    });

    test('the old behaviour — uploading the option TEXT — is refused by both', () {
      final card = forwardCard();
      expect(clientAccepts(card, 'без рецепта'), isFalse);
      expect(
        serverAcceptsForwardRecognition('без рецепта', card.termId),
        isFalse,
        reason: 'this is exactly what folded correct taps as lapses on the device',
      );
    });
  });
}
