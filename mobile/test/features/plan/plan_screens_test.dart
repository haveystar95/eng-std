import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_builder_screen.dart';
import 'package:eng_std/features/plan/plan_day_screen.dart';
import 'package:eng_std/features/plan/plan_day_summary.dart';
import 'package:eng_std/features/plan/plan_screen.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// The plan screens, on data rather than on a live server.
///
/// What is asserted here is the handful of things the design is EXPLICIT about and that a later
/// refactor could quietly lose: the empty tab explains the difference between a collection and a
/// plan, a day separates phrases from words and marks every word with its stage, and the day's
/// summary says «в работе» rather than «выучено».
MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: Scaffold(body: home),
);

LearningPlan _plan({int focus = 1}) => LearningPlan.fromJson({
  'id': '01PLAN',
  'status': 'active',
  'title': 'К врачу из-за боли',
  'goal_text': 'Иду к врачу, болит спина',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-02',
  'minutes_per_day': 20,
  'readiness': 0.5,
  'focus_day_index': focus,
  'next_day_index': focus + 1,
  'days_to_event': 2,
  'deadline_tight': false,
  'can_already': [
    {'text': 'Сказать, зачем пришёл', 'day_index': 1, 'hit': false},
  ],
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'ready'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
  ],
});

PlanDayDetail _day() => PlanDayDetail.fromJson({
  'id': 'd2',
  'index': 2,
  'kind': 'intro',
  'title': 'Уточнить симптомы и помощь',
  'status': 'ready',
  'outcome': ['Описать, какая это боль'],
  'plan_id': '01PLAN',
  'plan_title': 'К врачу из-за боли',
  'support_lang': 'ru',
  'target_lang': 'en',
  'terms': [
    {
      'id': 't1',
      'text': "It's a sharp pain.",
      'translation': 'Это острая боль.',
      'type': 'phrase',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't2',
      'text': 'sharp',
      'translation': 'острый',
      'type': 'word',
      'stage': 'a',
      'from_day_index': 2,
    },
    {
      'id': 't3',
      'text': 'back',
      'translation': 'спина',
      'type': 'word',
      'stage': 'b',
      'from_day_index': 1,
    },
  ],
});

SessionCard _card(String id, String type) => SessionCard.fromJson({
  'term_id': id,
  'exercise_mode': 'multiple_choice',
  'type': type,
  'answer': id,
});

/// A plan session envelope with no carried words — enough for the summary's arithmetic.
class _Envelope implements PlanSessionEnvelope {
  @override
  String get planId => '01PLAN';
  @override
  int get dayIndex => 1;
  @override
  bool get strict => true;
  @override
  String? stageLetterAt(int i) => 'A';
  @override
  ({int ordinal, int of})? stepAt(int i) => (ordinal: 1, of: 3);
  @override
  int? carriedFromAt(int i) => null;
}

void main() {
  testWidgets('every example goal is one the SERVER would accept', (tester) async {
    // The live run found this the fastest way there is: «Врач» is four characters, the server's
    // `CreatePlanRequest` refuses a goal under five, and tapping an example the screen itself
    // suggests produced «Не получилось собрать план». An example that cannot be used is worse than
    // no example — so the chips are held to the server's own floor here.
    await tester.pumpWidget(
      ProviderScope(child: _app(const PlanBuilderScreen())),
    );
    await tester.pumpAndSettle();

    final chips = tester
        .widgetList<Text>(find.descendant(of: find.byType(Wrap), matching: find.byType(Text)))
        .map((t) => t.data ?? '')
        .where((s) => s.isNotEmpty);

    expect(chips, isNotEmpty);
    for (final chip in chips) {
      expect(
        chip.trim().length,
        greaterThanOrEqualTo(5),
        reason: '«$chip» is shorter than the server\'s goal_text floor of 5',
      );
    }
  });

  testWidgets('«Собрать план» stays shut until the goal clears that same floor', (tester) async {
    await tester.pumpWidget(
      ProviderScope(child: _app(const PlanBuilderScreen(initialGoal: 'Врач'))),
    );
    await tester.pumpAndSettle();

    // Four characters: the button must not offer to spend a request on a 422.
    final short = tester.widget<PrimaryButton>(
      find.widgetWithText(PrimaryButton, 'Собрать план'),
    );
    expect(short.enabled && short.onPressed != null, isFalse);
  });

  testWidgets('the empty План tab explains the difference from a collection', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          activePlanProvider.overrideWith((ref) async => null),
          planArchiveProvider.overrideWith((ref) async => <PlanSummary>[]),
        ],
        child: _app(const PlanTabScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Подготовиться к чему-то конкретному'), findsOneWidget);
    expect(find.text('Говоришь цель и дату'), findsOneWidget);
    expect(find.text('Составить план'), findsOneWidget);
  });

  testWidgets('a day sets phrases apart from words and marks every word with its stage', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 2), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('ФРАЗЫ ДНЯ'), findsOneWidget);
    expect(find.text("It's a sharp pain."), findsOneWidget);
    expect(find.text('СЛОВА В ЭТИХ ФРАЗАХ'), findsOneWidget);
    expect(find.text('sharp'), findsOneWidget);
    // The day's own word stands on A…
    expect(find.text('A'), findsOneWidget);
    // …and yesterday's is carried in with the day it came from named beside its stage.
    expect(find.text('B · со дня 1'), findsOneWidget);
    // The conversation is present and honestly locked, not hidden.
    expect(find.text('РАЗГОВОР'), findsOneWidget);
  });

  testWidgets('a running plan can always be given up — there is no other way out', (tester) async {
    // The server allows ONE running plan per learner. Without this link the only exit from a plan is
    // its own event, which makes a plan that went wrong a trap. No frame of «Фаза 4» draws it; the
    // product needs it, so the guard names it rather than leaving it to be «tidied away» later.
    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan())],
        child: _app(const PlanScreen(planId: '01PLAN')),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('Отказаться от плана'), 300);
    expect(find.text('Отказаться от плана'), findsOneWidget);
  });

  testWidgets('a day out of attempts does not offer a button that cannot work', (tester) async {
    // The server claims a day at most twice; after that `PlanDay::claim()` returns false forever,
    // so «Собрать день» there is a button no number of presses can make work. Found on the owner's
    // phone: a day failed the validator twice and the screen kept offering to build it.
    final dead = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 2,
      'fail_reason': 'day.key_not_support_language',
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => dead),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsNothing);
    expect(find.text('Собрать план заново'), findsOneWidget);
    expect(find.textContaining('больше не будет пытаться'), findsOneWidget);
  });

  testWidgets('a day that failed ONCE is still offered a retry', (tester) async {
    final retryable = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Представиться и начать разговор',
      'status': 'failed',
      'generation_attempts': 1,
      'plan_id': '01PLAN',
      'terms': [],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => retryable),
        ],
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Собрать день'), findsOneWidget);
    expect(find.text('Собрать план заново'), findsNothing);
  });

  testWidgets('the day opened out of turn warns BEFORE the button, not after the session', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          planDayProvider((planId: '01PLAN', dayIndex: 2)).overrideWith((ref) async => _day()),
        ],
        // Focus is day 1, so day 2 is ahead of it — a soft run.
        child: _app(PlanDayScreen(plan: _plan(focus: 1), dayIndex: 2)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('пройдёт мягко'), findsOneWidget);
  });

  testWidgets('the day summary counts TERMS and says «в работе», never «выучено»', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan())],
        child: _app(
          PlanDaySummary(
            envelope: _Envelope(),
            // Five cards, three distinct terms: one word arrives as several cards inside a stage
            // and the summary must not count the questions.
            cards: [
              _card('t1', 'phrase'),
              _card('t1', 'phrase'),
              _card('t2', 'word'),
              _card('t2', 'word'),
              _card('t3', 'word'),
            ],
            onDone: () {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('День 1 пройден'), findsOneWidget);
    expect(find.text('3 фразы и слова в работе'), findsOneWidget);
    expect(find.text('Ступень A пройдена'), findsOneWidget);
    expect(find.textContaining('выучен'), findsNothing);
  });
}
