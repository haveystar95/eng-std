import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Records what the screen would have uploaded, without a queue, a keychain or a network — the
/// rung it echoes back is half of what QA-9 is about, so the test needs to see it.
class _RecordingReviewSync extends ReviewSync {
  _RecordingReviewSync(Ref ref)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  final List<({String termId, int? ladderStep})> uploaded = [];

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
    uploaded.add((termId: termId, ladderStep: ladderStep));
  }

  @override
  Future<void> flush() async {}
}

/// QA-9, end to end through the real session screen: miss rung 1 and the word must come back as
/// rung 1, not as rung 2.
///
/// On the device the session dealt card 11 of 16 as the REVERSE direction to a pair that was still
/// standing on rung 1 — the learner was asked the direction they had not earned, and `reviews` got
/// a `ladder_step = 2` row for a pair at rung 1. The unit test pins the rule; this pins the wiring,
/// which is the half that was actually missing: the resolution has to happen when the card is SHOWN,
/// not when the session is built.
void main() {
  const termId = '01M08AP74HM20AA0FDH2RSF5XS';
  const otherId = '01M08AP74HM20AA0FDH2RSF5XT';
  const term = 'Where can I find dog food?';

  // The mirror is closed AFTER the widget tree is gone: closing a drift database from inside
  // `onDispose` schedules a timer the test binding then reports as still pending.
  AppDatabase? db;

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));
  tearDown(() async {
    await db?.close();
    db = null;
  });

  /// Rung 1 — the TERM is the prompt, the options are translations, graded by identity.
  SessionCard forward() => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: term,
    answer: termId,
    options: const ['Где я могу найти корм для собак?', 'без злаков'],
    optionIds: const [termId, otherId],
    ladderStep: 1,
  );

  /// Rung 2 — the reverse direction, graded as text. Distinguishable on screen by its options.
  SessionCard reverse() => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: 'Где я могу найти корм для собак?',
    answer: term,
    options: const [term, 'grain-free'],
    ladderStep: 2,
  );

  SessionCard filler() => SessionCard(
    termId: otherId,
    mode: ExerciseMode.multipleChoice,
    type: 'word',
    prompt: 'без злаков',
    answer: 'grain-free',
    options: const ['grain-free', 'canned food'],
    ladderStep: 2,
  );

  late _RecordingReviewSync sync;

  Widget host() => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith(
        (ref) => db = AppDatabase.forTesting(NativeDatabase.memory()),
      ),
      reviewSyncProvider.overrideWith((ref) => sync = _RecordingReviewSync(ref)),
      studySessionProvider.overrideWith(
        (ref, args) async =>
            StudySession(sessionId: args.sessionId, cards: [forward(), filler(), reverse()]),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: SessionScreen(title: 'Корм для собаки'),
    ),
  );

  /// Answer the card on screen by tapping [option], then advance past the feedback.
  Future<void> answer(WidgetTester tester, String option) async {
    await tester.tap(find.text(option));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();
  }

  /// Tear the tree down INSIDE the test and drain what that schedules: drift cancels its query
  /// streams with a zero-duration timer, which the binding would otherwise report as still pending.
  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1)); // zero-duration timers need time to move
  }

  testWidgets('a missed rung 1 comes back as rung 1, not as rung 2', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    // Card 1 of 3 is the forward recognition. Tap the WRONG option — the pair stays on rung 1.
    expect(find.text(term), findsOneWidget, reason: 'rung 1 asks with the term as the prompt');
    await answer(tester, 'без злаков');

    await answer(tester, 'grain-free'); // the filler in between

    // Card 3 of 3 was LAID OUT as rung 2. It must be dealt as rung 1: the term back as the prompt,
    // the translations back as the options — and not the reverse card's English ones.
    expect(find.text('3 из 3'), findsOneWidget);
    expect(find.text(term), findsOneWidget);
    expect(find.text('Где я могу найти корм для собак?'), findsOneWidget);
    expect(find.text('canned food'), findsNothing, reason: 'the reverse card was not dealt');

    // …and the review log says rung 1 for the miss, not the rung the slot was planned at. On the
    // device this row read `ladder_step = 2` for a pair standing on rung 1.
    expect(sync.uploaded.first, (termId: termId, ladderStep: 1));
    expect(tester.takeException(), isNull);
    await close(tester);
  });

  testWidgets('a passed rung 1 is followed by rung 2, exactly as laid out', (tester) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    await answer(tester, 'Где я могу найти корм для собак?'); // correct tap
    await answer(tester, 'grain-free');

    // The reverse card, as planned: the translation asks and the English options answer.
    expect(find.text('3 из 3'), findsOneWidget);
    expect(find.text('grain-free'), findsOneWidget, reason: 'a reverse-card option');
    expect(sync.uploaded.first, (termId: termId, ladderStep: 1));
    expect(tester.takeException(), isNull);
    await close(tester);
  });
}
