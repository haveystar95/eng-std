import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// СПИСОК ДНЕЙ ПЛАНА — кадр D·07, на образцовых данных.
///
/// Пять честных статусов сразу на одном экране — состояние, которого живой план не даёт: чтобы
/// увидеть «не собрался» рядом с «собирается» и «ждёт очереди», надо, чтобы сборка сорвалась ровно
/// на одном дне из четырёх, а платить за это генерацией нельзя.
///
/// Данные — форма живого плана владельца (`01M1M0AY…`, ru→en, событие через два дня), поэтому
/// харнесс показывает то, что человек действительно увидит, а не выдумку.
///
///     flutter run -d <simulator> --target tool/plan_list_preview.dart
void main() {
  runApp(
    ProviderScope(
      overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan)],
      child: const _PlanListPreviewApp(),
    ),
  );
}

class _PlanListPreviewApp extends StatelessWidget {
  const _PlanListPreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: const Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(bottom: false, child: PlanScreen(planId: '01PLAN')),
    ),
  );
}

final _plan = LearningPlan.fromJson({
  'id': '01PLAN',
  'status': 'active',
  'title': 'Иду к врачу с ребёнком',
  'goal_text': 'Иду к врачу с ребёнком, надо объяснить симптомы и понять назначение',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-07',
  'minutes_per_day': 20,
  'readiness': 0.5,
  'days_to_event': 3,
  'deadline_tight': false,
  'focus_day_index': 2,
  'next_day_index': 3,
  'stage_census': {'total': 51, 'stage_a_closed': 21},
  'can_already': [],
  'days': [
    {
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Запись или обращение в регистратуру',
      'status': 'done',
      'outcome': ['Объяснить, что ребёнку нужен врач, и понять шаг'],
    },
    {
      'id': 'd2',
      'index': 2,
      'kind': 'intro',
      'title': 'Разговор с врачом о симптомах',
      'status': 'ready',
      'outcome': ['Описать, что и когда началось, ответить на уточнения'],
    },
    {
      'id': 'd3',
      'index': 3,
      'kind': 'intro',
      'title': 'Разговор с врачом о лечении',
      'status': 'failed',
      'generation_attempts': 1,
      'fail_code': 'day.example_is_a_term',
      'outcome': ['Понять назначение и спросить про дозировку'],
    },
    {
      'id': 'd4',
      'index': 4,
      'kind': 'intro',
      'title': 'Аптека и следующий визит',
      'status': 'generating',
      'outcome': ['Забрать лекарство, договориться о повторном приёме'],
    },
    {
      'id': 'd5',
      'index': 5,
      'kind': 'final_run',
      'title': 'Прогон перед событием',
      'status': 'pending',
    },
  ],
});
