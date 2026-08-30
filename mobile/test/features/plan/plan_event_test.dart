import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/plan_notifications.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/plan_feedback_screen.dart';
import 'package:eng_std/features/plan/plan_rehearsal_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE TWO ENDS OF THE EVENT, on the device (PLAN-1c, Ч.5).
///
/// The morning is phrases and no words; the evening is a checklist ticked by hand whose answer is
/// the only thing about a plan the app could not have worked out for itself.
class _FakeApi extends ApiClient {
  _FakeApi() : super(TokenStore());

  List<int>? sent;

  @override
  Future<PlanRehearsal> planRehearsal(String planId) async => PlanRehearsal.fromJson({
    'plan_id': planId,
    'title': 'К врачу из-за боли',
    'lines': [
      {
        'id': 't1',
        'text': "It's been like this for a week.",
        'translation': 'Это длится уже неделю.',
        'cue': 'How long has it been like this?',
        'role': 'Врач-терапевт',
        'day_index': 1,
      },
      {
        'id': 't2',
        'text': 'It gets worse when I bend.',
        'translation': 'Становится хуже, когда наклоняюсь.',
        'day_index': 2,
      },
    ],
  });

  @override
  Future<LearningPlan> submitPlanFeedback(String planId, List<int> hitIndexes) async {
    sent = hitIndexes;
    return _plan(feedback: hitIndexes);
  }
}

LearningPlan _plan({List<int>? feedback}) => LearningPlan.fromJson({
  'id': '01PLAN',
  'status': feedback == null ? 'active' : 'completed',
  'title': 'К врачу из-за боли',
  'goal_text': 'Иду к врачу',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-02',
  'minutes_per_day': 20,
  'readiness': 0.4,
  'focus_day_index': 3,
  'days_to_event': 0,
  'can_already': [
    {'text': 'Сказать, зачем пришёл', 'day_index': 1, 'hit': false},
    {'text': 'Назвать, где именно болит', 'day_index': 1, 'hit': false},
    {'text': 'Сказать, как давно это длится', 'day_index': 2, 'hit': false},
  ],
  // Absent means «never asked» — the wire distinguishes that from an empty list, and so does this.
  'event_feedback': ?feedback,
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
  ],
});

MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: home,
);

void main() {
  testWidgets('the rehearsal shows ONE phrase at a time, with the line it answers', (tester) async {
    final api = _FakeApi();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(api)],
        child: _app(const PlanRehearsalScreen(planId: '01PLAN', targetLang: 'en')),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('СКАЖИ ВСЛУХ'), findsOneWidget);
    expect(find.text("It's been like this for a week."), findsOneWidget);
    // The cue is the interlocutor's own utterance, attributed to them by name.
    expect(find.text('Врач-терапевт скажет: «How long has it been like this?»'), findsOneWidget);
    // …and the NEXT phrase is not on screen yet: three minutes, one sentence at a time.
    expect(find.text('It gets worse when I bend.'), findsNothing);
    expect(find.text('1 из 2'), findsOneWidget);
  });

  testWidgets('«Как прошло?» sends exactly the checkpoints that were ticked', (tester) async {
    final api = _FakeApi();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(api),
          // The feedback screen kicks a background sync when it closes the plan — the ordinary day
          // has just got its words back. In a test that means the real drift file, so: memory.
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
        ],
        child: _app(PlanFeedbackScreen(plan: _plan())),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Как прошло?'), findsOneWidget);
    // Every ability the plan promised is offered, whether or not the app thinks it was learned:
    // what happened at the event is not something the app gets an opinion about.
    expect(find.text('Сказать, зачем пришёл'), findsOneWidget);
    expect(find.text('Сказать, как давно это длится'), findsOneWidget);

    await tester.tap(find.text('Сказать, зачем пришёл'));
    await tester.tap(find.text('Сказать, как давно это длится'));
    await tester.pump();
    await tester.tap(find.text('Сохранить и завершить'));
    await tester.pumpAndSettle();

    expect(api.sent, [0, 2]);
  });

  testWidgets('an empty answer is a real answer — the plan can still be closed', (tester) async {
    final api = _FakeApi();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(api),
          // The feedback screen kicks a background sync when it closes the plan — the ordinary day
          // has just got its words back. In a test that means the real drift file, so: memory.
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
        ],
        child: _app(PlanFeedbackScreen(plan: _plan())),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Сохранить и завершить'));
    await tester.pumpAndSettle();

    // «Ничего из этого не пригодилось» has to be sendable, or a plan whose event went badly can
    // never be closed and goes on holding its words out of the ordinary day forever.
    expect(api.sent, isEmpty);
  });

  test('a notification payload names both the screen and the plan', () {
    expect(PlanNotifications.rehearsalPayload('01PLAN'), 'plan-rehearsal:01PLAN');
    expect(PlanNotifications.feedbackPayload('01PLAN'), 'plan-feedback:01PLAN');
    expect(PlanNotifications.planPayload('01PLAN'), 'plan:01PLAN');
    // The three ids are FIXED, so rescheduling replaces rather than accumulates.
    expect(
      {PlanNotifications.idDayBefore, PlanNotifications.idMorning, PlanNotifications.idEvening},
      hasLength(3),
    );
  });
}
