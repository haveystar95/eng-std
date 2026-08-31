import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_day_summary.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Visual harness for «День N пройден» with the DAY counted apart from the top-up (PLAN-SESSION-FIX
/// Ч.2.1), rendered with sample data so it can be looked at without a backend or a synced plan.
///
/// It renders the REAL [PlanDaySummary], which is the only kind of preview worth trusting. Lives
/// outside `lib/` like the other two harnesses, so its Russian sample copy is exempt from the
/// cyrillic guard.
///
///     flutter run -d <simulator> --target tool/plan_summary_preview.dart
void main() {
  runApp(
    ProviderScope(
      overrides: [planProvider('01PLAN').overrideWith((ref) async => _plan)],
      child: const _PlanSummaryPreviewApp(),
    ),
  );
}

class _PlanSummaryPreviewApp extends StatelessWidget {
  const _PlanSummaryPreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: Scaffold(
      backgroundColor: AppColors.paper,
      body: PlanDaySummary(envelope: const _Envelope(dayCards: 6), cards: _cards, onDone: () {}),
    ),
  );
}

/// The live shape the fix was written for, shrunk to fit a screen: a day of five terms, topped up
/// with two the ordinary queue had due.
final _cards = <SessionCard>[
  _card('p1', 'phrase'),
  _card('p1', 'phrase'),
  _card('p2', 'phrase'),
  _card('p3', 'phrase'),
  _card('w1', 'word'),
  _card('w2', 'word'),
  // Past the seam — the top-up. On the account this was found on, two of these were French.
  _card('r1', 'phrase'),
  _card('r2', 'word'),
];

SessionCard _card(String id, String type) => SessionCard.fromJson({
  'term_id': id,
  'exercise_mode': 'multiple_choice',
  'type': type,
  'answer': id,
});

final _plan = LearningPlan.fromJson({
  'id': '01PLAN',
  'status': 'active',
  'title': 'Отпуск в Италии по-английски',
  'goal_text': 'Отпуск в Италии',
  'support_lang': 'ru',
  'target_lang': 'en',
  'level': 'basic',
  'event_date': '2026-09-04',
  'minutes_per_day': 20,
  'readiness': 0.0,
  'focus_day_index': 1,
  'next_day_index': 2,
  'days_to_event': 4,
  'deadline_tight': false,
  'can_already': <Map<String, dynamic>>[],
  'days': [
    {
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Заселиться в отель · Поесть в кафе',
      'status': 'ready',
    },
    {
      'id': 'd2',
      'index': 2,
      'kind': 'intro',
      'title': 'Поесть в кафе · Спросить дорогу и транспорт',
      'status': 'ready',
    },
  ],
});

/// Enough envelope for the summary's arithmetic: [dayCards] cards belong to the day, the rest are
/// the top-up.
class _Envelope implements PlanSessionEnvelope {
  const _Envelope({required this.dayCards});

  final int dayCards;

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
  @override
  bool isDayTaskAt(int i) => i < dayCards;
  @override
  int get dayTaskCount => dayCards;
}
