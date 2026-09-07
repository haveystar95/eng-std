import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_rehearsal_done.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// «ПОДГОТОВКА ЗАВЕРШЕНА» — кадр D·12 (наряд DAY-2, Ч.2.4).
///
/// The ending states three facts and no more. The frame lists four; «реплик в плане», «сказали сами,
/// без ключа» and «прогон вслух 17 мин» are numbers this product does not compute, and the записка
/// of the series is explicit — «числа только те, что сервер действительно знает».
/// The plan as the server answers it once the run-through has closed it.
LearningPlan plan() => LearningPlan.fromJson({
    'id': '01PLAN',
    'status': 'completed',
    'title': 'К врачу из-за боли',
    'goal_text': 'Иду к врачу, болит спина',
    'support_lang': 'ru',
    'target_lang': 'en',
    'level': 'basic',
    'minutes_per_day': 20,
    'readiness': 0.6,
    'focus_day_index': 3,
    'stage_census': {'total': 27, 'stage_a_closed': 25},
    'days': [
      {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'done'},
      {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Симптомы', 'status': 'done'},
      {'id': 'd3', 'index': 3, 'kind': 'final', 'title': 'Прогон', 'status': 'pending'},
    ],
});

Widget host() => ProviderScope(
  overrides: [
    apiClientProvider.overrideWithValue(_ClosingApi()),
    planProvider('01PLAN').overrideWith((ref) async => plan()),
  ],
  child: MaterialApp(
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru')],
    home: Scaffold(
      body: PlanRehearsalDone(planId: '01PLAN', onDone: () {}),
    ),
  ),
);

void main() {
  testWidgets('states what the server knows, in words, and not what it does not', (
    tester,
  ) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    expect(find.text('Подготовка завершена'), findsWidgets);

    // Two teaching scenes, both closed; the run-through is not a scene. In WORDS — no «2 из 2»
    // on a plan screen (наряд DAY-FIX-2, Ч.5.6).
    expect(find.text('Сцены пройдены'), findsOneWidget);
    expect(find.text('все'), findsOneWidget);
    // The card count is gone with the counters.
    expect(find.text('Карточек в плане'), findsNothing);
    // Слова лестницы с экранов ушли (наряд DAY-2-FIX, Ч.3а): вердикт человеческий.
    expect(find.text('Познакомились со словами и фразами'), findsOneWidget);
    expect(find.textContaining('ступень'), findsNothing);
    expect(find.text('не со всем'), findsOneWidget);
    expect(find.textContaining(' из '), findsNothing);

    // …and nothing that was never measured.
    expect(find.textContaining('вслух'), findsNothing);
    expect(find.textContaining('%'), findsNothing);
  });

  testWidgets('«К плану» is the one action — the cheat sheet is gone, the day screen is the sheet', (
    tester,
  ) async {
    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    expect(find.textContaining('шпаргалк'), findsNothing);
    expect(find.text('К плану'), findsOneWidget);
    // The archive is named rather than promised away: the plan's words do not return by themselves.
    expect(find.textContaining('Автоматических повторений не будет'), findsOneWidget);
  });
}

/// Closes the plan without a network, and answers nothing else.
class _ClosingApi implements ApiClient {
  @override
  Future<LearningPlan> completePlan(String planId) async => plan();

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
