import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_window.dart';
import '../../plan_stage_text.dart';
import 'window_bits.dart';
import 'window_texts.dart';

/// РЯД ЭТАПА НА ПЛИТЕ ОКНА (кадры 23-0a…0c): значок 20 · 12 · слово · состояние словами — и через 8 полоса 3 под ним.
/// Запертый ряд серый, текущий — 600 и латунное «идёт». Цифр «N / M» в ряду нет (серия 38): пройденный ряд, который
/// можно пройти ещё раз, говорит «ещё раз» и сам ведёт туда ([onAgain], наряд FIX-3 §5).
class WindowStageRow extends StatelessWidget {
  const WindowStageRow({
    super.key,
    required this.stage,
    this.popCheck = false,
    this.aroundMinutes = false,
    this.onAgain,
  });

  final WindowStage stage;

  /// Этап только что закрыт — галка-бейдж появляется `om-check-pop`.
  final bool popCheck;

  /// A row of a review or the rehearsal (37-1, 37-2): its minutes in words, «около 4 минут».
  final bool aroundMinutes;

  /// «Ещё раз» ряда: пройденный этап проходится снова. Null — ряд ничего не предлагает и остаётся текстом.
  final VoidCallback? onAgain;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final state = stage.state;
    final again = onAgain != null && state == WindowStageState.done && stage.again;
    final nameColor = state == WindowStageState.locked ? AppColors.windowAhead : AppColors.paper;
    final stateColor = switch (state) {
      WindowStageState.done => again ? AppColors.windowCurrent : AppColors.windowDone,
      WindowStageState.current => AppColors.windowCurrent,
      WindowStageState.locked => AppColors.windowAhead,
    };
    final stateText = Text(
      WindowTexts.stageState(l, stage, around: aroundMinutes, offerAgain: onAgain != null),
      maxLines: 1,
      softWrap: false,
      style: AppTextWindow.stageState.copyWith(color: stateColor),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          height: 20,
          child: Row(
            children: [
              PlanStageMark(
                kind: planStageMark(stage.stage),
                tone: PlanStageMarkTone.window,
                popBadge: popCheck,
                state: switch (state) {
                  WindowStageState.done => PlanStageMarkState.done,
                  WindowStageState.current => PlanStageMarkState.current,
                  WindowStageState.locked => PlanStageMarkState.locked,
                },
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  planStageName(l, stage.stage),
                  maxLines: 1,
                  softWrap: false,
                  overflow: TextOverflow.clip,
                  style: AppTextWindow.stage.copyWith(
                    color: nameColor,
                    fontWeight: state == WindowStageState.current ? FontWeight.w600 : FontWeight.w500,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              if (again)
                // «Ещё раз» — само действие ряда: этап повторяется, день — нет.
                GestureDetector(behavior: HitTestBehavior.opaque, onTap: onAgain, child: stateText)
              else
                stateText,
            ],
          ),
        ),
        const SizedBox(height: 8),
        WindowBar(share: stage.share, track: AppColors.windowPaperLine, fill: AppColors.windowDoneBar, height: 3),
      ],
    );
  }
}
