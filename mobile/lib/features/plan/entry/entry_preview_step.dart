import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/plan/plan_models.dart';
import '../plan_route.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';
import 'entry_tape.dart';

/// ПРЕВЬЮ (кадры 22-4a … 22-4d): the skeleton while the route is being asked for, the route once
/// it is, the «не собрался» card, the «цель непонятна» card — under the tape of the answers.
class EntryPreviewStep extends StatelessWidget {
  const EntryPreviewStep({
    super.key,
    required this.tape,
    required this.state,
    required this.languageName,
    required this.onRetry,
    required this.onEditGoal,
    required this.onRemoveScene,
  });

  final List<EntryTapeRow> tape;
  final EntryState state;
  final String languageName;
  final VoidCallback onRetry;
  final VoidCallback onEditGoal;
  final ValueChanged<PlanScene> onRemoveScene;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plan = state.plan;
    final levelName = switch (state.level) {
      PlanLevel.beginner => l.planEntryLevelBeginner,
      PlanLevel.intermediate => l.planEntryLevelIntermediate,
    };
    final days = plan?.daysTotal ?? state.days;

    final Widget head;
    final Widget body;
    switch (state.phase) {
      case EntryBuildPhase.idle:
      case EntryBuildPhase.building:
        head = _Head(
          title: l.planEntryPreviewLoadingTitle,
          sub: l.planEntryPreviewLoadingSub,
          cover: null,
        );
        body = const _RouteSkeleton();
      case EntryBuildPhase.ready:
      case EntryBuildPhase.starting:
        head = _Head(
          title: l.planEntryPreviewTitle,
          sub: l.planEntryPreviewSub(l.planDaysCount(days), languageName, levelName),
          cover: plan?.coverImage?.url,
        );
        body = plan == null
            ? const _RouteSkeleton()
            : PlanRoute(plan: plan, preview: true, onRemoveScene: onRemoveScene);
      case EntryBuildPhase.unclear:
        head = _Head(
          title: l.planEntryPreviewTitle,
          sub: l.planEntryPreviewSub(l.planDaysCount(days), languageName, levelName),
          cover: null,
        );
        body = _Notice(
          title: l.planEntryPreviewUnclearTitle,
          sub: l.planEntryPreviewUnclearSub,
          action: l.planEntryPreviewUnclearCta,
          onAction: onEditGoal,
        );
      case EntryBuildPhase.failed:
        head = _Head(
          title: l.planEntryPreviewTitle,
          sub: l.planEntryPreviewSub(l.planDaysCount(days), languageName, levelName),
          cover: null,
        );
        body = _Notice(
          title: l.planEntryPreviewErrorTitle,
          sub: state.offline ? l.planEntryOffline : l.planEntryPreviewErrorSub,
          action: l.planEntryPreviewErrorRetry,
          onAction: onRetry,
          secondary: l.planEntryPreviewErrorEdit,
          onSecondary: onEditGoal,
        );
    }

    return EntryContent(
      children: [
        EntryTape(rows: tape),
        const SizedBox(height: 22),
        head,
        const SizedBox(height: 14),
        body,
      ],
    );
  }
}

/// «Маршрут» / «Собираем маршрут» with the plan's cover circle beside it once there is one.
class _Head extends StatelessWidget {
  const _Head({required this.title, required this.sub, required this.cover});

  final String title;
  final String sub;
  final String? cover;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      if (cover != null) ...[
        Container(
          width: 52,
          height: 52,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.photoPlaceholder,
            border: Border.all(color: AppColors.brassHairline),
          ),
          child: Image(image: CachedNetworkImage(cover!), fit: BoxFit.cover),
        ),
        const SizedBox(width: 14),
      ],
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            EntryQuestion(title),
            const SizedBox(height: 6),
            Text(sub, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.secondary)),
          ],
        ),
      ),
    ],
  );
}

/// Five rows of shimmer in the route's own shape (кадр 22-4a): the node, the photo, two bones.
class _RouteSkeleton extends StatefulWidget {
  const _RouteSkeleton();

  @override
  State<_RouteSkeleton> createState() => _RouteSkeletonState();
}

class _RouteSkeletonState extends State<_RouteSkeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _shimmer = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1400),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _shimmer.stop();
    } else if (!_shimmer.isAnimating) {
      _shimmer.repeat();
    }
  }

  @override
  void dispose() {
    _shimmer.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const widths = [(150.0, 190.0), (130.0, 170.0), (160.0, 120.0), (96.0, 180.0), (140.0, 110.0)];
    final faint = AppColors.ink.withValues(alpha: .30);

    return AnimatedBuilder(
      animation: _shimmer,
      builder: (context, _) {
        final t = _shimmer.value;

        return Column(
          children: [
            for (var i = 0; i < widths.length; i++)
              SizedBox(
                height: 64,
                child: Row(
                  children: [
                    SizedBox(
                      width: 22,
                      height: double.infinity,
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          Positioned.fill(
                            child: Column(
                              children: [
                                Expanded(child: Container(width: 1.5, color: i == 0 ? Colors.transparent : faint)),
                                Expanded(child: Container(width: 1.5, color: i == widths.length - 1 ? Colors.transparent : faint)),
                              ],
                            ),
                          ),
                          _shimmerBox(t, 22, 22, 11),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    _shimmerBox(t, 48, 48, 12),
                    const SizedBox(width: 12),
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _bone(widths[i].$1, 14, .08),
                        const SizedBox(height: 7),
                        _bone(widths[i].$2, 12, .06),
                      ],
                    ),
                  ],
                ),
              ),
          ],
        );
      },
    );
  }

  /// The shimmer gradient of кадр 7a on a box: #EAE5DB with a #F7F4EE sweep.
  Widget _shimmerBox(double t, double w, double h, double r) => Container(
    width: w,
    height: h,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(r),
      gradient: LinearGradient(
        begin: Alignment(-1 + 3 * t, 0),
        end: Alignment(1 + 3 * t, 0),
        colors: const [AppColors.shimmerBase, AppColors.shimmerHighlight, AppColors.shimmerBase],
      ),
    ),
  );

  Widget _bone(double w, double h, double alpha) => Container(
    width: w,
    height: h,
    decoration: BoxDecoration(
      color: AppColors.ink.withValues(alpha: alpha),
      borderRadius: BorderRadius.circular(3),
    ),
  );
}

/// The notice card of 22-4c / 22-4d: a muted glyph, a title 16.5/700, a line, the small ink
/// button and — when there is one — the quiet second action.
class _Notice extends StatelessWidget {
  const _Notice({
    required this.title,
    required this.sub,
    required this.action,
    required this.onAction,
    this.secondary,
    this.onSecondary,
  });

  final String title, sub, action;
  final VoidCallback onAction;
  final String? secondary;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) => PaperCard(
    radius: 22,
    padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12)),
          child: Container(
            width: 22,
            height: 22,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.ink.withValues(alpha: .30), width: 1.5),
            ),
            child: Container(width: 14, height: 1.5, color: AppColors.ink.withValues(alpha: .45)),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 16.5, fontWeight: FontWeight.w700, color: AppColors.ink),
              ),
              const SizedBox(height: 6),
              Text(
                sub,
                style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.4, color: AppColors.secondary),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
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
                          height: 38,
                          padding: const EdgeInsets.symmetric(horizontal: 18),
                          alignment: Alignment.center,
                          child: Text(
                            action,
                            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.paper),
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (secondary != null && onSecondary != null) ...[
                    const SizedBox(width: 14),
                    Semantics(
                      button: true,
                      child: InkWell(
                        onTap: () {
                          AppHaptics.light();
                          onSecondary!();
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
                          child: Text(
                            secondary!,
                            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary),
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
