import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/plan/plan_models.dart';
import '../route/plan_route.dart';
import '../route/route_line.dart';
import '../route/route_view.dart';
import 'entry_state.dart';

/// ПРЕВЬЮ ПЛАНА (кадры 22-4a … 22-4d).
///
/// Кнопка стоит на одном месте во всех четырёх состояниях — меняется середина. Сборка (22-4a) — живой
/// прелоадер [PlanPreloader] и срок словами; готовый план (22-4b) — полный контраст: тема плана
/// крупно, плита «Как это будет» и маршрут теми же узлами, что в табе, но без прогресса — дни здесь
/// не заперты, и под днём не этапы, а то, что человек сможет сказать.
class EntryPreviewStep extends StatelessWidget {
  const EntryPreviewStep({
    super.key,
    required this.state,
    required this.summary,
    required this.onRetry,
    required this.onEditGoal,
  });

  final EntryState state;

  /// «7 дней · английский · средний · приём 17 сентября» — одна строка под заголовком.
  final String summary;

  final VoidCallback onRetry;
  final VoidCallback onEditGoal;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plan = state.plan;
    final bottom = 150 + MediaQuery.viewPaddingOf(context).bottom;

    return switch (state.phase) {
      EntryBuildPhase.ready || EntryBuildPhase.starting when plan != null => _Ready(plan: plan, summary: summary, bottom: bottom),
      EntryBuildPhase.idle || EntryBuildPhase.building || EntryBuildPhase.ready || EntryBuildPhase.starting => _Building(
        summary: summary,
      ),
      EntryBuildPhase.unclear => _Page(
        title: l.planEntryPreviewUnclearTitle,
        summary: summary,
        body: _Notice(
          icon: PlanIcon.noticeUnclear,
          // Заголовок цитирует то, что человек написал: «"Английский" — это про что?»
          title: l.planEntryPreviewUnclearQuote(state.goal.trim()),
          sub: l.planEntryPreviewUnclearSub,
          action: l.planEntryPreviewUnclearCta,
          onAction: onEditGoal,
        ),
      ),
      EntryBuildPhase.failed => _Page(
        title: l.planEntryPreviewErrorTitle,
        summary: summary,
        body: _Notice(
          icon: PlanIcon.noticeFailed,
          title: state.offline ? l.planEntryOffline : l.planEntryPreviewErrorWhat,
          sub: l.planEntryPreviewErrorSub,
          action: l.planEntryPreviewErrorRetry,
          onAction: onRetry,
        ),
      ),
    };
  }
}

/// Шапка Literata 30 и строка сводки — 22-4a, 22-4c, 22-4d.
class _Head extends StatelessWidget {
  const _Head({required this.title, required this.summary});

  final String title, summary;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(
          fontFamily: AppFonts.literata,
          fontSize: 30,
          fontWeight: FontWeight.w500,
          letterSpacing: -0.6,
          height: 1.15,
          color: AppColors.ink,
        ),
      ),
      const SizedBox(height: 8),
      Text(summary, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.45, color: AppColors.secondary)),
    ],
  );
}

class _Page extends StatelessWidget {
  const _Page({required this.title, required this.summary, required this.body});

  final String title, summary;
  final Widget body;

  @override
  Widget build(BuildContext context) => ListView(
    padding: EdgeInsets.fromLTRB(20, 8, 20, 110 + MediaQuery.viewPaddingOf(context).bottom),
    children: [_Head(title: title, summary: summary), const SizedBox(height: 32), body],
  );
}

/// СБОРКА (22-4a) — шапка сверху, в середине экрана «Около 10 секунд» и живой прелоадер.
class _Building extends StatelessWidget {
  const _Building({required this.summary});

  final String summary;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
          child: _Head(title: l.planEntryPreviewLoadingTitle, summary: summary),
        ),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: 96 + MediaQuery.viewPaddingOf(context).bottom),
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    l.planEntryPreviewAbout,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontFamily: AppFonts.literata,
                      fontSize: 22,
                      fontWeight: FontWeight.w500,
                      height: 1.2,
                      color: AppColors.ink,
                    ),
                  ),
                  const SizedBox(height: 18),
                  PlanPreloader(lines: [l.planEntryPreviewLine1, l.planEntryPreviewLine2, l.planEntryPreviewLine3]),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// ГОТОВЫЙ ПЛАН (22-4b): «Твой план готов» тихо, тема плана Literata 22, сводка, плита «Как это
/// будет» (строка сервера `summary`; у плана без неё плиты нет), маршрут.
///
/// Появление: плита первой, 220 мс; узлы следом шагом 60 мс, fade + 8 px вверх.
class _Ready extends StatelessWidget {
  const _Ready({required this.plan, required this.summary, required this.bottom});

  final Plan plan;
  final String summary;
  final double bottom;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final story = plan.summary;

    return ListView(
      padding: EdgeInsets.fromLTRB(20, 8, 20, bottom),
      children: [
        Text(l.planEntryPreviewTitle, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, color: AppColors.secondary)),
        const SizedBox(height: 6),
        Text(
          plan.displayTitle,
          style: const TextStyle(
            fontFamily: AppFonts.literata,
            fontSize: 22,
            fontWeight: FontWeight.w500,
            letterSpacing: -0.33,
            height: 1.2,
            color: AppColors.ink,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          summary,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            color: AppColors.tertiary,
            fontFeatures: [FontFeature.tabularFigures()],
          ),
        ),
        if (story != null) ...[
          const SizedBox(height: 20),
          RouteEntrance(index: 0, child: _HowItGoes(label: l.planEntryPreviewHowLabel, text: story)),
        ],
        const SizedBox(height: 54),
        RouteLine(days: planPreviewDays(l, plan, dpr), event: planRouteEvent(l, locale, plan), progress: false, entrance: true),
      ],
    );
  }
}

/// Дни превью: полный контраст, цели дня латунными точками (до трёх — столько держит узел кадра),
/// у повторения и репетиции — их строка.
List<RouteDayView> planPreviewDays(AppLocalizations l, Plan plan, double dpr) => [
  for (final d in plan.days)
    RouteDayView(
      title: l.planRouteDayTitle(d.number, planRouteDayName(l, plan, d)),
      circle: planRouteCircle(plan, d, dpr),
      tone: RouteDayTone.plain,
      children: [
        for (final goal in switch (d.type) {
          PlanDayType.review => [_reviewSub(l, plan, d)].whereType<String>(),
          PlanDayType.rehearsal => [l.planRouteDayRehearsalSub],
          _ => (plan.sceneOf(d)?.goalsNative ?? const <String>[]).take(3),
        })
          RouteChildView(label: goal, mark: RouteChildMark.goal),
      ],
    ),
];

/// «слова и фразы дней 1–3» — дни-ситуации, которые этот день повторения собирает.
String? _reviewSub(AppLocalizations l, Plan plan, PlanDayRoute day) {
  var a = 0, b = 0;
  for (final d in plan.days) {
    if (d.number >= day.number) break;
    if (d.type == PlanDayType.scene) {
      if (a == 0) a = d.number;
      b = d.number;
    } else if (d.type == PlanDayType.review) {
      a = 0;
      b = 0;
    }
  }
  if (a == 0) return null;

  return a == b ? l.planRouteDayRepeatSubOne(a) : l.planRouteDayRepeatSub(a, b);
}

/// Плита «Как это будет» — бумага r18, значок маршрута латунью, метка caps и две строки сервера.
class _HowItGoes extends StatelessWidget {
  const _HowItGoes({required this.label, required this.text});

  final String label, text;

  @override
  Widget build(BuildContext context) => PaperCard(
    radius: 18,
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const PlanIconMark(icon: PlanIcon.goal),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label.toUpperCase(),
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.54,
                  color: AppColors.tertiary,
                ),
              ),
              const SizedBox(height: 6),
              Text(text, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.45, color: AppColors.ink)),
            ],
          ),
        ),
      ],
    ),
  );
}

/// ИЗВЕЩЕНИЕ ПРЕВЬЮ (22-4c, 22-4d) — значок 40, что случилось, что уцелело, одно действие.
class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.title,
    required this.sub,
    required this.action,
    required this.onAction,
  });

  final PlanIcon icon;
  final String title, sub, action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      PlanIconMark(
        icon: icon,
        size: 40,
        color: icon == PlanIcon.noticeFailed ? AppColors.destructiveText : AppColors.brassInk,
      ),
      const SizedBox(height: 16),
      Text(
        title,
        style: const TextStyle(fontFamily: AppFonts.literata, fontSize: 22, fontWeight: FontWeight.w500, height: 1.2, color: AppColors.ink),
      ),
      const SizedBox(height: 8),
      Text(sub, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.45, color: AppColors.secondary)),
      const SizedBox(height: 20),
      Semantics(
        button: true,
        label: action,
        child: Material(
          color: AppColors.ink,
          borderRadius: BorderRadius.circular(14),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () {
              AppHaptics.light();
              onAction();
            },
            child: Container(
              height: 44,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              alignment: Alignment.center,
              child: Text(
                action,
                style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.paper),
              ),
            ),
          ),
        ),
      ),
    ],
  );
}
