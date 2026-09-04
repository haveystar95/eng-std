import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/plan_rehearsal_done.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// «ПОДГОТОВКА ЗАВЕРШЕНА» — кадр D·12, on sample data.
///
/// The screen ends a plan the moment it opens, and a plan can only be ended once: the live QA plan
/// (`01M1P65458MSFVG8TTNSTZFRGJ`) was closed by the first run and the second tap now gets the domain's
/// «уже завершён». So the frame is looked at here instead, with the numbers the live plan actually
/// answered — 5 scenes, 49 cards, all past stage A — and the real [PlanRehearsalDone] doing the
/// drawing, exactly as [PlanDaySummary]'s harness does next door.
///
///     flutter run -d <simulator> --target tool/plan_done_preview.dart
void main() {
  runApp(
    ProviderScope(
      overrides: [
        planProvider('01PLAN').overrideWith((ref) async => _plan),
        apiClientProvider.overrideWithValue(_ClosingApi()),
      ],
      child: const _PlanDonePreviewApp(),
    ),
  );
}

class _PlanDonePreviewApp extends StatelessWidget {
  const _PlanDonePreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: Scaffold(
      backgroundColor: AppColors.paper,
      body: PlanRehearsalDone(planId: '01PLAN', onDone: () {}),
    ),
  );
}

/// Closes the plan without a server, so the harness shows the ENDING and not the retry branch.
class _ClosingApi extends ApiClient {
  _ClosingApi() : super(TokenStore());

  @override
  Future<LearningPlan> completePlan(String planId) async => _plan;
}

/// The live QA plan as the server answered it after the run: one scene closed, four still untrained,
/// and a census of 49 cards.
final _plan = LearningPlan.fromJson({
  'id': '01PLAN',
  'status': 'completed',
  'title': 'Онлайн собеседование на английском',
  'goal_text': 'Прохожу онлайн-собеседование на английском',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'minutes_per_day': 20,
  'readiness': 0.6,
  'focus_day_index': 3,
  'stage_census': {'total': 49, 'stage_a_closed': 49},
  'days': [
    {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начало звонка', 'status': 'done'},
    {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Опыт и навыки', 'status': 'ready'},
    {'id': 'd3', 'index': 3, 'kind': 'intro', 'title': 'Рабочие ситуации', 'status': 'pending'},
    {'id': 'd4', 'index': 4, 'kind': 'intro', 'title': 'Интерес к роли', 'status': 'pending'},
    {'id': 'd5', 'index': 5, 'kind': 'intro', 'title': 'Вопросы и завершение', 'status': 'pending'},
    {'id': 'd6', 'index': 6, 'kind': 'final', 'title': 'Прогон перед событием', 'status': 'pending'},
  ],
});
