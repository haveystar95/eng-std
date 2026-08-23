import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/providers.dart';
import '../home/streak.dart';
import 'activity.dart';
import 'progress_providers.dart';

/// «Прогресс» (кадр 2.6). Everything reads the local DB — renders in airplane mode. Streak in
/// antiqua, week calendar of dots, tabular counters, a month activity chart of ink bars, and the
/// global density bar. The activity chart, week dots and «за неделю»/«сегодня» all read the local
/// `daily_activity` table, so the chart and the streak dots beside it always agree (правило 21).
class ProgressScreen extends ConsumerWidget {
  const ProgressScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final stats = ref.watch(statsProvider).value;
    final best = ref.watch(bestStreakProvider).value ?? 0;
    final activity = ref.watch(dailyActivityProvider).value ?? const <String, int>{};
    final density =
        ref.watch(globalDensityProvider).value ??
        const CollectionDensity(confirmed: 0, familiar: 0, inProgress: 0);

    final now = DateTime.now();
    final streak = stats?.streakDays ?? 0;
    final learnedTotal = stats?.mastered ?? 0;
    final weekCount = weekReviewCount(now, activity);
    final todayCount = todayReviewCount(now, activity);

    final bottomInset =
        AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s8;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: SafeArea(
        bottom: false,
        child: ListView(
          padding: EdgeInsets.fromLTRB(
            AppSpacing.screenH,
            AppSpacing.s8,
            AppSpacing.screenH,
            bottomInset,
          ),
          children: [
            Text(l.progressTitle, style: AppText.screenTitle),
            const SizedBox(height: AppSpacing.s26),
            _StreakBlock(streak: streak, best: best),
            const SizedBox(height: AppSpacing.s22),
            _WeekCalendar(dots: weekDots(now, activity)),
            const _Divider(top: 24),
            _Counters(learnedTotal: learnedTotal, weekCount: weekCount, todayCount: todayCount),
            const _Divider(top: 20),
            _ActivityChart(bars: monthBars(now, activity), month: now.month),
            const _Divider(top: 20),
            _DensityBar(density: density),
          ],
        ),
      ),
    );
  }
}

/// Antiqua streak line + «Лучший результат». Best line hides until there's a streak to name.
class _StreakBlock extends StatelessWidget {
  const _StreakBlock({required this.streak, required this.best});
  final int streak, best;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.progressStreakDays(streak), style: AppText.streak),
        if (best > 0) ...[
          const SizedBox(height: AppSpacing.s8),
          Text(l.progressBestResult(best), style: AppText.translation.copyWith(fontSize: 13)),
        ],
      ],
    );
  }
}

/// Week calendar — 7 day-dots Пн→Вс under their labels (§4 density: filled / outline today / track).
class _WeekCalendar extends StatelessWidget {
  const _WeekCalendar({required this.dots});
  final List<StreakDot> dots;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final labels = [
      l.progressDayMon,
      l.progressDayTue,
      l.progressDayWed,
      l.progressDayThu,
      l.progressDayFri,
      l.progressDaySat,
      l.progressDaySun,
    ];
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        for (var i = 0; i < 7; i++)
          Column(
            children: [
              Text(
                labels[i],
                style: AppText.counterSmall.copyWith(
                  fontSize: 11,
                  color: dots[i] == StreakDot.today ? AppColors.ink : AppColors.tertiary,
                  fontWeight: dots[i] == StreakDot.today ? FontWeight.w700 : FontWeight.w400,
                ),
              ),
              const SizedBox(height: 9),
              _Dot(dots[i]),
            ],
          ),
      ],
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot(this.kind);
  final StreakDot kind;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 11,
      height: 11,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: switch (kind) {
          StreakDot.filled => AppColors.ink,
          StreakDot.today => null,
          StreakDot.empty => AppColors.track,
        },
        border: kind == StreakDot.today ? Border.all(color: AppColors.ink, width: 2) : null,
      ),
    );
  }
}

/// Three tabular counters in columns divided by hairlines: выучено всего · за неделю · сегодня.
class _Counters extends StatelessWidget {
  const _Counters({required this.learnedTotal, required this.weekCount, required this.todayCount});
  final int learnedTotal, weekCount, todayCount;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: _cell(learnedTotal, l.progressLearnedTotal)),
          const _VDivider(),
          Expanded(child: _cell(weekCount, l.progressThisWeek)),
          const _VDivider(),
          Expanded(child: _cell(todayCount, l.progressToday)),
        ],
      ),
    );
  }

  Widget _cell(int value, String label) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text('$value', style: AppText.counterLarge),
      const SizedBox(height: 5),
      Text(label.toUpperCase(), style: AppTextExercise.summaryLabel),
    ],
  );
}

class _VDivider extends StatelessWidget {
  const _VDivider();
  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(horizontal: 16),
    child: SizedBox(width: 1, child: ColoredBox(color: AppColors.hairline)),
  );
}

/// Month activity chart — one ink bar per day, heights relative to the busiest day; zero days are
/// hairlines (§4). Reads the local `daily_activity`, so it converges with the streak dots above.
class _ActivityChart extends StatelessWidget {
  const _ActivityChart({required this.bars, required this.month});
  final List<ActivityBar> bars;
  final int month;

  static const double _maxHeight = 104;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(child: Text(l.progressActivityMonth, style: AppText.sectionLabel)),
            Text(
              l.progressMonth('$month'),
              style: AppText.counterSmall.copyWith(fontSize: 11.5, color: AppColors.tertiary),
            ),
          ],
        ),
        const SizedBox(height: 14),
        SizedBox(
          height: _maxHeight,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (var i = 0; i < bars.length; i++) ...[
                if (i > 0) const SizedBox(width: 3),
                Expanded(child: _bar(bars[i])),
              ],
            ],
          ),
        ),
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            for (final t in const ['1', '15', '31'])
              Text(
                t,
                style: AppText.counterSmall.copyWith(fontSize: 11, color: AppColors.tertiary),
              ),
          ],
        ),
      ],
    );
  }

  // The bars sit in a Row with crossAxisAlignment.end, so each one bottom-aligns; Expanded (in the
  // parent) gives it its column width. A zero day is a hairline stub.
  Widget _bar(ActivityBar b) {
    if (b.density == InkDensity.outline) {
      return Container(height: AppInkDensity.outlineWidth, color: AppInkDensity.outlineColor);
    }
    return Container(
      height: (b.fraction * _maxHeight).clamp(1.0, _maxHeight),
      color: AppInkDensity.solid(b.density),
    );
  }
}

/// The global density bar («Все N слов») + its three-swatch legend — the whole vocabulary folded
/// into confirmed / familiar / in-progress (Mastery semantics), same rendering as a collection.
class _DensityBar extends StatelessWidget {
  const _DensityBar({required this.density});
  final CollectionDensity density;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.progressAllWords(density.total), style: AppText.sectionLabel),
        const SizedBox(height: 11),
        InkSegments.fromCounts(
          confirmed: density.confirmed,
          familiar: density.familiar,
          inProgress: density.inProgress,
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 14,
          runSpacing: 6,
          children: [
            _item(InkDensity.filled, l.collectionDensityConfirmed(density.confirmed)),
            _item(InkDensity.halftone, l.collectionDensityFamiliar(density.familiar)),
            _item(InkDensity.outline, l.collectionDensityInProgress(density.inProgress)),
          ],
        ),
      ],
    );
  }

  Widget _item(InkDensity d, String label) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      SizedBox(
        width: 9,
        height: 9,
        child: d == InkDensity.outline
            ? DecoratedBox(
                decoration: BoxDecoration(
                  border: Border.all(
                    color: AppInkDensity.outlineColor,
                    width: AppInkDensity.outlineWidth,
                  ),
                ),
              )
            : ColoredBox(color: AppInkDensity.solid(d)),
      ),
      const SizedBox(width: 6),
      Text(label, style: AppText.transcription.copyWith(fontSize: 11.5)),
    ],
  );
}

/// Full-width hairline with a configurable gap above it (16px below, matching the §2.6 rhythm).
class _Divider extends StatelessWidget {
  const _Divider({required this.top});
  final double top;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(top: top, bottom: 16),
    child: const SizedBox(height: 1, child: ColoredBox(color: AppColors.hairline)),
  );
}
