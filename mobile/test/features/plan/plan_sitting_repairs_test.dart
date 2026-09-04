import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/data/session_completion_sync.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// WHAT A PLAN SITTING OWES THE LEARNER — the client half of E2E-SIM-2.
///
///   С-3/С-6  the seam's footnote is drawn AFTER the answer, and names the term rather than the
///            grading key — it used to print the reply the learner was about to choose, and on
///            `situational_hear` it printed a ULID.
///   С-4      «Пропустить» on a speaking card writes the lapse that keeps the checklist honest,
///            instead of leaving a stage-A step open for ever with nothing on screen to say so.
///   С-8      «День N/M» counts the day's own cards — the revision is not part of the day.
///   С-13     the sitting's stored position moves on «Проверить», not on «Дальше»: a kill between
///            the two used to re-ask a card that was already answered.
void main() {
  const planId = '01PLAN';

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  /// Every review the screen recorded, in order — the durable queue, stubbed to a list.
  late List<({String termId, String mode, String response})> recorded;

  SessionCard choice(String termId, {String? prompt}) => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: prompt ?? 'У него жар.',
    answer: 'He has a fever.',
    options: const ['He has a fever.', 'I came with my son.'],
  );

  PlanSessionTask task(
    SessionCard card, {
    PlanStage? stage = PlanStage.a,
    int fromDayIndex = 2,
    String section = PlanSessionTask.sectionDay,
  }) => PlanSessionTask(
    card: card,
    stage: stage,
    ordinal: 1,
    ofSteps: 1,
    fromDayIndex: fromDayIndex,
    softened: false,
    section: section,
  );

  Widget host({
    required List<PlanSessionTask> tasks,
    AppDatabase? db,
    List<int> sittings = const [],
    SpeechRecognizer? recognizer,
  }) => ProviderScope(
    overrides: [
      apiClientProvider.overrideWithValue(_PlanApi()),
      appDatabaseProvider.overrideWith((ref) {
        final database = db ?? AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
      }),
      reviewSyncProvider.overrideWith((ref) => _CapturingReviewSync(ref, recorded)),
      sessionCompletionSyncProvider.overrideWithValue(_SilentCompletion()),
      if (recognizer != null) speechRecognizerProvider.overrideWithValue(recognizer),
      planSessionProvider.overrideWith(
        (ref, args) async => PlanSession(
          sessionId: args.sessionId,
          planId: planId,
          dayIndex: args.dayIndex ?? 2,
          strict: true,
          tasks: tasks,
          sittings: sittings,
          raw: const {'session_id': 'S', 'plan_id': planId, 'day_index': 2, 'strict': true},
        ).asStudySession(),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: SessionScreen(title: 'День 2', planId: planId, planDayIndex: 2),
    ),
  );

  /// Tear the tree down inside the test, so drift's stream is cancelled while the clock still turns.
  Future<void> teardownTree(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  }

  setUp(() => recorded = []);

  group('the seam`s footnote (С-3, С-6)', () {
    testWidgets('is not drawn until the card has been answered', (tester) async {
      // The card is a REVISION card of day 1, dealt inside day 2 — the only cards the footnote is
      // for. On the stand this line was on screen from the first frame, and on «Ты ответишь» the
      // word it named was the very option the learner was about to tap.
      await tester.pumpWidget(
        host(
          tasks: [
            task(
              choice('01TERM', prompt: 'Повторите, пожалуйста.'),
              stage: PlanStage.b,
              fromDayIndex: 1,
              section: PlanSessionTask.sectionReview,
            ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('идёт со дня'), findsNothing);

      await tester.tap(find.text('He has a fever.'));
      await tester.pumpAndSettle();

      expect(find.textContaining('идёт со дня'), findsOneWidget);

      await teardownTree(tester);
    });

    testWidgets('names the TERM, never the grading key — a ULID is not a word', (tester) async {
      // `situational_hear` is graded by the id of the tapped option, so `card.answer` is a ULID.
      // The footnote printed it: «Слово «01M1MH57RKS1EPN0VRKJ5AXYC2» идёт со дня 1» (С-6).
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      await db
          .into(db.terms)
          .insert(
            TermsCompanion(
              id: const Value('01TERM'),
              termText: const Value('Could you repeat that, please?'),
              type: const Value('phrase'),
              updatedAt: Value(DateTime.utc(2026, 9, 4)),
            ),
          );

      const optionId = '01M1MH57RKS1EPN0VRKJ5AXYC2';
      final card = SessionCard(
        termId: '01TERM',
        mode: ExerciseMode.situationalHear,
        type: 'phrase',
        prompt: 'Что он сказал?',
        answer: optionId,
        options: const ['Просит повторить', 'Просит подождать'],
        optionIds: const [optionId, '01OTHER'],
      );

      await tester.pumpWidget(
        host(
          db: db,
          tasks: [
            task(card, stage: PlanStage.b, fromDayIndex: 1, section: PlanSessionTask.sectionReview),
          ],
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Просит повторить'));
      await tester.pumpAndSettle();

      expect(find.textContaining(optionId), findsNothing);
      expect(find.textContaining('Could you repeat that, please?'), findsWidgets);

      await teardownTree(tester);
    });
  });

  group('«Пропустить» on a speaking card (С-4)', () {
    testWidgets('closes the step as a lapse instead of leaving it open for ever', (tester) async {
      // A plan's stage is a CHECKLIST and a checklist step is closed by an answer. A skip that wrote
      // nothing left the speaking step of stage A open, and stage A has to close for the day to
      // pass — so a learner whose microphone was refused could not finish the day at all, with
      // nothing on any screen to say why. The card went by and the counter moved.
      final engine = _RefusingRecognizer();
      await tester.pumpWidget(
        host(
          tasks: [
            task(
              SessionCard(
                termId: '01SPEAK',
                mode: ExerciseMode.speaking,
                type: 'word',
                prompt: 'бронь',
                answer: 'reservation',
              ),
            ),
            task(choice('01NEXT')),
          ],
          recognizer: engine,
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.bySemanticsLabel(RegExp('Сказать|Готово')).first);
      await tester.pumpAndSettle();

      await tester.tap(find.text('Пропустить'));
      await tester.pumpAndSettle();

      // The step is closed HONESTLY — an empty response, which the server grades `again` exactly as
      // «Не помню» does. Not a pass: a microphone is not evidence that the learner knew the line.
      expect(recorded.length, 1);
      expect(recorded.single.termId, '01SPEAK');
      expect(recorded.single.mode, 'speaking');
      expect(recorded.single.response, isEmpty);

      // …and the sitting moved on, so the skipped card is not re-asked after a kill (С-13).
      final store = ProviderScope.containerOf(
        tester.element(find.byType(SessionScreen)),
      ).read(planSittingStoreProvider);
      expect((await store.restore(planId: planId, dayIndex: 2))?.position, 1);

      await teardownTree(tester);
    });
  });

  group('the day counter (С-8)', () {
    testWidgets('counts the day`s own cards and leaves the revision out', (tester) async {
      // The stand's own composition: two cards of the day behind twenty of the seam. The payload
      // said `day_task_count: 2`; the screen said «День 0/22».
      await tester.pumpWidget(
        host(
          tasks: [
            for (var i = 0; i < 2; i++) task(choice('01DAY$i'), fromDayIndex: 2),
            for (var i = 0; i < 20; i++)
              task(
                choice('01SEAM$i'),
                stage: PlanStage.b,
                fromDayIndex: 1,
                section: PlanSessionTask.sectionReview,
              ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('День 0/2'), findsOneWidget);
      expect(find.text('День 0/22'), findsNothing);

      await teardownTree(tester);
    });
  });

  group('присесты (С-12)', () {
    testWidgets('shows «присест пройден» where the server cut the day', (tester) async {
      // The screen exists and was never once reached in the whole live run, because the budget never
      // cut a sitting. The server cuts now; this is the other end of that wire.
      await tester.pumpWidget(
        host(
          tasks: [for (var i = 0; i < 4; i++) task(choice('01T$i'))],
          sittings: const [2, 2],
        ),
      );
      await tester.pumpAndSettle();

      for (var i = 0; i < 2; i++) {
        await tester.tap(find.text('He has a fever.'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Дальше'));
        await tester.pumpAndSettle();
      }

      expect(find.textContaining('ПРИСЕСТ'), findsOneWidget);

      await teardownTree(tester);
    });
  });

  group('where a kill resumes (С-13)', () {
    testWidgets('the stored position moves on «Проверить», not on «Дальше»', (tester) async {
      await tester.pumpWidget(
        host(tasks: [for (var i = 0; i < 3; i++) task(choice('01T$i'))]),
      );
      await tester.pumpAndSettle();

      final store = ProviderScope.containerOf(
        tester.element(find.byType(SessionScreen)),
      ).read(planSittingStoreProvider);

      await tester.tap(find.text('He has a fever.'));
      await tester.pumpAndSettle();

      // The answer is recorded and durable. A kill RIGHT HERE — before «Дальше» — used to resume on
      // this same card with an empty field, and the second answer went into the append-only log in
      // the same mode.
      expect(recorded.length, 1);
      final saved = await store.restore(planId: planId, dayIndex: 2);
      expect(saved, isNotNull);
      expect(saved!.position, 1, reason: 'the answered card is behind us');

      await teardownTree(tester);
    });

    testWidgets('forgets the sitting once its last card is answered', (tester) async {
      await tester.pumpWidget(host(tasks: [task(choice('01ONLY'))]));
      await tester.pumpAndSettle();

      final store = ProviderScope.containerOf(
        tester.element(find.byType(SessionScreen)),
      ).read(planSittingStoreProvider);

      await tester.tap(find.text('He has a fever.'));
      await tester.pumpAndSettle();

      // Nothing left to resume: a stored index past the end would be clamped back onto the last
      // card, which is the very re-ask this is here to stop.
      expect(await store.restore(planId: planId, dayIndex: 2), isNull);

      await teardownTree(tester);
    });
  });
}

/// The plan API, stubbed down to nothing — these screens never reach it.
class _PlanApi implements ApiClient {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentCompletion implements SessionCompletionSync {
  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {}

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Records what the screen would have uploaded, and uploads nothing.
class _CapturingReviewSync extends ReviewSync {
  _CapturingReviewSync(Ref ref, this._log)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  final List<({String termId, String mode, String response})> _log;

  @override
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {
    _log.add((termId: termId, mode: exerciseMode, response: response));
  }

  @override
  Future<void> flush() async {}
}

/// A microphone that refuses to start — the ordinary «нет разрешения» case, which is what puts
/// «Пропустить» on screen.
class _RefusingRecognizer implements SpeechRecognizer {
  @override
  bool get isReady => false;

  @override
  Future<bool> prepare() async => false;

  @override
  Future<bool> get hasPermission async => false;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) async => const SpeechAttempt.unavailable();

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}
}
