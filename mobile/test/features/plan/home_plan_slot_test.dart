import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/home_plan_card.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The plan's slot on the home screen (кадры 08 / 09).
///
/// One slot, two faces, and the rule the frames state out loud: «место плана занято приглашением на
/// бумаге — тише активного плана, но на той же позиции: обещание не мигрирует по экрану». So the
/// two things this file asserts are that both faces render at all and that the empty one is an
/// INVITATION rather than an absence.
MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: Scaffold(body: home),
);

LearningPlan _plan() => LearningPlan.fromJson({
  'id': '01PLAN',
  'status': 'active',
  'title': 'К врачу из-за боли',
  'goal_text': 'Иду к врачу',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-02',
  'minutes_per_day': 20,
  'readiness': 0.5,
  // Twelve of fourteen cards past stage A — what the card leads with until the canonical readiness
  // formula arrives (P2-v0.4/SIT-1). The percentage stays on the wire and stops being the headline.
  'stage_census': {'total': 14, 'stage_a_closed': 12},
  'focus_day_index': 2,
  'days_to_event': 2,
  'can_already': [
    {'text': 'Сказать, зачем пришёл', 'day_index': 1, 'hit': true},
    {'text': 'Сказать, как давно это длится', 'day_index': 2, 'hit': false},
  ],
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить симптомы', 'status': 'ready'},
    {'id': 'd3', 'index': 3, 'kind': 'final', 'title': 'Прогон приёма', 'status': 'pending'},
  ],
});

void main() {
  testWidgets('with a plan the slot leads with the plan\'s own progress, not with words done', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [activePlanProvider.overrideWith((ref) async => _plan())],
        child: _app(const HomePlanSlot()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('ПЛАН · ДЕНЬ 2 ИЗ 3'), findsOneWidget);
    expect(find.text('К врачу из-за боли'), findsOneWidget);
    // ОДНО СЛОВО О ДНЕ ФОКУСА, серверное (наряд DAY-FIX-2, Ч.3) — не перепись карточек «12 из
    // 14» и не процент. Фикстура без `day_state` читается как «не начат».
    expect(find.text('День 2 · не начат'), findsOneWidget);
    expect(find.textContaining(' из 14'), findsNothing);
    expect(find.textContaining('ступень'), findsNothing);
    expect(find.text('50'), findsNothing);
    expect(find.text('%'), findsNothing);
    // The focus day is named beside the action, so the button is not «continue what exactly».
    expect(find.text('Уточнить симптомы'), findsOneWidget);
    expect(find.text('Продолжить'), findsOneWidget);
  });

  testWidgets('with no plan the SAME slot invites one — it is not an empty gap', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [activePlanProvider.overrideWith((ref) async => null)],
        child: _app(const HomePlanSlot()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Есть дата и цель?'), findsOneWidget);
    expect(find.text('Составить'), findsOneWidget);
  });

  testWidgets('while the plan is unknown the slot draws NOTHING — never a placeholder', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          // Never completes: the state the screen is in on a cold start with a slow network.
          activePlanProvider.overrideWith((ref) => Completer<LearningPlan?>().future),
        ],
        child: _app(const HomePlanSlot()),
      ),
    );
    await tester.pump();

    // The day below is the point of this screen; a shimmering box above it would be the first thing
    // the learner looks at every morning, for a card that may turn out not to exist.
    expect(find.text('Есть дата и цель?'), findsNothing);
    expect(find.byType(CircularProgressIndicator), findsNothing);
  });
}
