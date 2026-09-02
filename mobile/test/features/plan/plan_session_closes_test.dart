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
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// A SITTING ENDS WHEN THE LAST CARD IS ANSWERED — Д-1, Д-28.
///
/// `sessionCompletionSync.record` used to live in the summary WIDGETS, one copy each. The plan's
/// summary was the second one and did not have it, so the whole live run — 103 answers over three
/// plan days — sent zero `POST /study/sessions/{id}/complete`: `ended_at` stayed null, the day
/// stayed `ready`, day n+1 was never queued, and the plan screen went on offering «Продолжить день
/// 2» after day 3 had been walked (Д-40). It was patched by copying the call into the second
/// summary; this pins the fix that cannot be undone by a third screen — closing the run belongs to
/// the SESSION, not to whatever is drawn over the end of it.
class _CompletionSpy implements SessionCompletionSync {
  final List<String> recorded = [];

  @override
  Future<void> record({required String sessionId, DateTime? endedAt}) async {
    recorded.add(sessionId);
  }

  @override
  Future<void> flush() async {}

  @override
  Future<int> pendingCount() async => 0;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _SilentReviewSync extends ReviewSync {
  _SilentReviewSync(Ref ref)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

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
  }) async {}

  @override
  Future<void> flush() async {}
}

/// The plan API, stubbed down to the two calls these screens make.
class _PlanApi implements ApiClient {
  final List<String> completed = [];

  @override
  Future<LearningPlan> completePlan(String planId) async {
    completed.add(planId);
    return _plan();
  }

  @override
  Future<LearningPlan> plan(String planId) async => _plan();

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

LearningPlan _plan() => LearningPlan.fromJson(const {
  'id': '01PLAN',
  'status': 'completed',
  'title': 'К врачу с ребёнком',
  'goal_text': 'Поход к врачу с ребёнком',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-05',
  'minutes_per_day': 20,
  // Day 1 CLOSED — what the server answers after a sitting that actually passed the day. The
  // summary reads the day's own status rather than assuming «the sitting was strict»: a miss does
  // not close its rung, and a day with one wrong card stays `ready`.
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
    {'id': 'd5', 'index': 5, 'kind': 'final', 'title': 'Прогон перед событием', 'status': 'pending'},
  ],
  'readiness': 0.1,
  'focus_day_index': 5,
});

void main() {
  const planId = '01PLAN';
  const termId = '01M08AP74HM20AA0FDH2RSF5XS';

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  PlanSessionTask task() => PlanSessionTask(
    card: SessionCard(
      termId: termId,
      mode: ExerciseMode.multipleChoice,
      type: 'phrase',
      prompt: 'У него жар.',
      answer: 'He has a fever.',
      options: const ['He has a fever.', 'I came with my son.'],
    ),
    stage: PlanStage.a,
    ordinal: 1,
    ofSteps: 1,
    fromDayIndex: 1,
    softened: false,
  );

  Widget host({
    required _CompletionSpy spy,
    required _PlanApi api,
    required bool finalDay,
  }) => ProviderScope(
    overrides: [
      apiClientProvider.overrideWithValue(api),
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        // Closed on dispose rather than in a tearDown: the screen watches the settings out of the
        // mirror, and a drift stream cancelled while the database is still open leaves a timer the
        // test binding reports as pending.
        ref.onDispose(db.close);
        return db;
      }),
      reviewSyncProvider.overrideWith(_SilentReviewSync.new),
      sessionCompletionSyncProvider.overrideWithValue(spy),
      planProvider(planId).overrideWith((ref) async => _plan()),
      planSessionProvider.overrideWith(
        (ref, args) async => PlanSession(
          sessionId: args.sessionId,
          planId: planId,
          dayIndex: args.dayIndex ?? 1,
          strict: !finalDay,
          tasks: [task()],
        ).asStudySession(),
      ),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: SessionScreen(
        title: 'День 1',
        planId: planId,
        planDayIndex: finalDay ? 5 : 1,
        planIsFinalDay: finalDay,
      ),
    ),
  );

  Future<void> playTheOneCard(WidgetTester tester) async {
    await tester.pumpAndSettle();
    await tester.tap(find.text('He has a fever.'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();
  }

  /// Tear the tree down INSIDE the test, so the local mirror's stream is cancelled while the fake
  /// clock is still turning. Cancelling a drift query stream schedules a zero-duration timer, and
  /// one left behind by the tree's own disposal is reported as «a Timer is still pending».
  Future<void> teardownTree(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpAndSettle();
  }

  testWidgets('a plan day closes its run when the last card is answered (Д-28)', (tester) async {
    final spy = _CompletionSpy();

    await tester.pumpWidget(host(spy: spy, api: _PlanApi(), finalDay: false));
    await playTheOneCard(tester);

    expect(spy.recorded.length, 1);
    // The milestone screen is still the milestone screen — it simply no longer owns the closing.
    expect(find.text('День 1 пройден'), findsOneWidget);

    await teardownTree(tester);
  });

  testWidgets('the FINAL day’s run-through closes its run too — the summary is a different screen', (
    tester,
  ) async {
    // The half a per-screen fix cannot cover. This sitting ends on «Подготовка завершена», which
    // never carried a `record` call and never will: whichever screen is drawn, the SESSION ended.
    final spy = _CompletionSpy();
    final api = _PlanApi();

    await tester.pumpWidget(host(spy: spy, api: api, finalDay: true));
    await playTheOneCard(tester);

    expect(spy.recorded.length, 1);
    // …and the run-through is what CLOSES THE PLAN (Д-27) — the act the app had no way to perform.
    expect(api.completed, [planId]);
    expect(find.text('Подготовка завершена'), findsWidgets);

    await teardownTree(tester);
  });
}
