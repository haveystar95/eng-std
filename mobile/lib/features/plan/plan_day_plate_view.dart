import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/plan/plan_models.dart';
import 'plan_format.dart';

/// ПЛИТА ДНЯ НА ТАБЕ, собранная из ответа сервера (кадры 21-2, 21-3, 21-4, 22-5a).
///
/// Один виджет раскладывает контракт на [DayPlate]: пять этапов в порядке канвы со счётом из
/// кабинета, вторая строка у текущего, подвал по статусу дня, и у закрытого дня — светлая бумага
/// с двумя строками о том, когда откроется следующий и что в него вернётся.
///
/// НИЧЕГО НЕ ДОСЧИТЫВАЕТСЯ ЗА СЕРВЕР. Оценки «≈ 20 минут» у дня и «N с подсказкой» у этапа в
/// контракте нет (у дня приходит только `minutes_spent` пройденного), поэтому строк с ними на
/// плите нет — расхождение названо в отчёте наряда, а не закрыто выдуманным числом.
class PlanDayPlateView extends StatelessWidget {
  const PlanDayPlateView({
    super.key,
    required this.plan,
    required this.day,
    required this.room,
    required this.onOpen,
    this.onRetryLesson,
  });

  final Plan plan;
  final PlanDayRoute day;
  final PlanDayRoom? room;
  final VoidCallback onOpen;

  /// «Повторить» у несобравшегося дня (22-5c) — повтор урока, не пересборка плана.
  final VoidCallback? onRetryLesson;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final scene = plan.sceneOf(day);
    final title = day.titleNative ?? scene?.titleNative ?? plan.displayTitle;
    final coverUrl = scene?.image?.url ?? plan.coverImage?.url;
    final cover = coverUrl == null ? null : CachedNetworkImage(coverUrl);

    final cards = room?.metrics?.cardsTotal ?? day.cardsTotal;
    String? cardsMeta(AppLocalizations l) => cards > 0 ? l.planCardsCount(cards) : null;

    // Кадр 22-5a: день ещё пишется — на месте этапов строка о сроке и разрешение уйти. Кнопки
    // здесь НЕТ: нажимать пока не на что, а приглушённая кнопка обещала бы, что скоро можно.
    if (day.lessonBuilding) {
      return DayPlate(
        label: l.planPlateLabel(day.number),
        title: title,
        meta: cardsMeta(l),
        cover: cover,
        stages: const [],
        notice: DayPlateNotice(
          title: l.planPlateBuildingTitle(day.number),
          sub: l.planPlateBuildingSub,
          spinner: true,
        ),
      );
    }

    // Кадр 22-5c: день не собрался — маршрут остаётся, потерян только день, и действие одно.
    if (day.lessonFailed) {
      return DayPlate(
        label: l.planPlateLabel(day.number),
        title: title,
        meta: cardsMeta(l),
        cover: cover,
        stages: const [],
        notice: DayPlateNotice(
          title: l.planPlateFailedTitle,
          sub: l.planPlateFailedSub(day.number),
        ),
        footer: DayPlateFooter.button(label: l.planPlateCtaRetry, onTap: onRetryLesson),
      );
    }

    final stages = _stages(l);

    if (day.isClosed) {
      final minutes = room?.metrics?.minutesSpent ?? day.minutesSpent;
      final returning = room?.returningUnits ?? 0;
      final next = plan.currentDay;

      return DayPlate(
        label: l.planPlateLabel(day.number),
        title: l.planClosedTitle(day.number),
        // «75 карточек · 19 минут» — только числа: название сцены стоит в маршруте, и второй раз
        // на плите канва его не повторяет.
        meta: l.planClosedCount(l.planCardsCount(cards), l.planMinutesCount(minutes)),
        stages: stages,
        closed: true,
        nextDayLine: _nextDayLine(l, locale, next),
        returnLine: returning > 0 && next != null ? l.planClosedReturn(next.number, returning) : null,
        onTap: onOpen,
      );
    }

    return DayPlate(
      label: l.planPlateLabel(day.number),
      title: title,
      meta: cardsMeta(l),
      cover: cover,
      stages: stages,
      footer: DayPlateFooter.button(
        label: day.isInProgress ? l.planPlateCtaContinue : l.planPlateCtaStart,
        onTap: onOpen,
      ),
      onTap: onOpen,
    );
  }

  /// «День 3 откроется завтра, 12 сентября» (21-4) — дата из слота следующего дня, слово «завтра»
  /// из его же готовой подписи; ни то, ни другое клиент не выводит сам.
  String? _nextDayLine(AppLocalizations l, String locale, PlanDayRoute? next) {
    if (next == null) return null;
    final date = PlanFormat.parseWireDate(next.slot.date);
    if (date == null) return null;
    final when = PlanFormat.date(date, locale);

    return next.slot.code == PlanSlotCode.tomorrow
        ? l.planClosedNextTomorrow(next.number, when)
        : l.planClosedNextOn(next.number, when);
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
          kind: _mark(s.stage),
          name: _name(l, s.stage),
          count: l.planPlateStageCount(s.done, s.total),
          state: state,
          note: note,
        ),
      );
    }

    return out;
  }

  /// Этап контракта → значок канвы (`assets/stages/`).
  static PlanStageMarkKind _mark(PlanStage stage) => switch (stage) {
    PlanStage.words => PlanStageMarkKind.words,
    PlanStage.phrases => PlanStageMarkKind.phrases,
    PlanStage.dialogue => PlanStageMarkKind.dialogue,
    PlanStage.listen => PlanStageMarkKind.listen,
    PlanStage.speak || PlanStage.unknown => PlanStageMarkKind.speak,
  };

  static String _name(AppLocalizations l, PlanStage stage) => switch (stage) {
    PlanStage.words => l.planPlateStageWords,
    PlanStage.phrases => l.planPlateStagePhrases,
    PlanStage.dialogue => l.planPlateStageDialog,
    PlanStage.listen => l.planPlateStageListen,
    PlanStage.speak => l.planPlateStageSpeak,
    PlanStage.unknown => '',
  };
}
