import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/plan/plan_models.dart';

/// THE PLATE OF THE TAB, drawn from the server's day and room (кадры 21-2, 21-3, 21-4, 22-5a).
///
/// One widget maps the contract onto [DayPlate]: the five stages in their order with the room's
/// counts, the current stage's second line, the footer by the day's status, the closed day's card
/// with its «Вернутся в день N». Nothing is computed that the server did not state — the second
/// line's «≈ N мин» and «N с подсказкой» of the frames have no field in `docs/plan-api.md` and are
/// not drawn (reported).
class PlanDayPlateView extends StatelessWidget {
  const PlanDayPlateView({
    super.key,
    required this.plan,
    required this.day,
    required this.room,
    required this.onOpen,
  });

  final Plan plan;
  final PlanDayRoute day;
  final PlanDayRoom? room;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final scene = plan.sceneOf(day);
    final title = day.titleNative ?? scene?.titleNative ?? plan.displayTitle;
    final coverUrl = plan.coverImage?.url ?? scene?.image?.url;
    final cover = coverUrl == null ? null : CachedNetworkImage(coverUrl);

    if (day.lessonBuilding) {
      return DayPlate(
        label: l.planPlateLabel(day.number),
        title: l.planEntryDayBuilding(day.number),
        stages: const [],
        building: true,
        footer: DayPlateFooter.button(label: l.planPlateCtaStart, enabled: false),
      );
    }

    final stages = _stages(l);
    final cards = room?.metrics?.cardsTotal ?? day.cardsTotal;

    if (day.isClosed) {
      final minutes = room?.metrics?.minutesSpent ?? day.minutesSpent;
      final returning = room?.returningUnits ?? 0;
      final next = plan.currentDay?.number;

      return DayPlate(
        label: l.planPlateLabel(day.number),
        title: l.planClosedTitle(day.number),
        meta: l.planClosedMeta(title, l.planCardsCount(cards), l.planMinutesCount(minutes)),
        stages: stages,
        closed: true,
        returnLine: returning > 0 && next != null ? l.planClosedReturn(next, returning) : null,
        onTap: onOpen,
      );
    }

    return DayPlate(
      label: l.planPlateLabel(day.number),
      title: title,
      // «75 карточек · ≈ 20 минут» — the estimate has no field; the count stands alone.
      meta: cards > 0 ? l.planCardsCount(cards) : null,
      cover: cover,
      stages: stages,
      footer: DayPlateFooter.button(
        label: day.isInProgress ? l.planPlateCtaContinue : l.planPlateCtaStart,
        onTap: onOpen,
      ),
      onTap: onOpen,
    );
  }

  List<DayPlateStage> _stages(AppLocalizations l) {
    final r = room;
    if (r == null) return const [];
    final out = <DayPlateStage>[];
    for (final s in r.stages) {
      if (s.state == PlanStageState.absent || s.stage == PlanStage.unknown) continue;
      final state = switch (s.state) {
        PlanStageState.done => DayPlateStageState.done,
        PlanStageState.current => DayPlateStageState.current,
        _ => DayPlateStageState.locked,
      };
      String? note;
      if (state == DayPlateStageState.current && !r.day.isClosed) {
        note = s.done == 0
            ? (s.stage == PlanStage.words && r.newWordsCount > 0
                  ? l.planPlateStageSubStart(l.planNewWordsCount(r.newWordsCount))
                  : null)
            : l.planPlateStageSubUnfinished(l.planCardsCount(s.total - s.done));
      }
      out.add(
        DayPlateStage(
          name: _name(l, s.stage),
          count: l.planPlateStageCount(s.done, s.total),
          state: state,
          note: note,
        ),
      );
    }

    return out;
  }

  static String _name(AppLocalizations l, PlanStage stage) => switch (stage) {
    PlanStage.words => l.planPlateStageWords,
    PlanStage.phrases => l.planPlateStagePhrases,
    PlanStage.dialogue => l.planPlateStageDialog,
    PlanStage.listen => l.planPlateStageListen,
    PlanStage.speak => l.planPlateStageSpeak,
    PlanStage.unknown => '',
  };
}
