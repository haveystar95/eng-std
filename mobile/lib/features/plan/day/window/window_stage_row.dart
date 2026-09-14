import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_window.dart';
import '../../plan_stage_text.dart';
import 'window_bits.dart';
import 'window_texts.dart';

/// РЯД ЭТАПА НА ПЛИТЕ ОКНА (кадры 23-0a…0c): значок 20 · 12 · слово · состояние словами · цифра —
/// только у текущего — и полоса 4 под ним. Запертый ряд серый, текущий — 600 и латунное «идёт».
class WindowStageRow extends StatelessWidget {
  const WindowStageRow({super.key, required this.stage, this.popCheck = false});

  final WindowStage stage;

  /// Этап только что закрыт — галка-бейдж появляется `om-check-pop`.
  final bool popCheck;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final state = stage.state;
    final count = WindowTexts.stageCount(l, stage);
    final nameColor = state == WindowStageState.locked ? AppColors.windowAhead : AppColors.paper;
    final stateColor = switch (state) {
      WindowStageState.done => AppColors.windowDone,
      WindowStageState.current => AppColors.windowCurrent,
      WindowStageState.locked => AppColors.windowAhead,
    };

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
              Text(WindowTexts.stageState(l, stage), maxLines: 1, softWrap: false, style: AppTextWindow.stageState.copyWith(color: stateColor)),
              if (count != null) ...[
                const SizedBox(width: 12),
                Text(count, maxLines: 1, softWrap: false, style: AppTextWindow.stageCount),
              ],
            ],
          ),
        ),
        const SizedBox(height: 8),
        WindowBar(share: stage.share, track: AppColors.windowPaperLine, fill: AppColors.windowDoneBar),
      ],
    );
  }
}
