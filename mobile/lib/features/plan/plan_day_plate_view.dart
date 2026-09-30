import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/plan/day_window.dart' show DayWindow;
import '../../data/plan/plan_models.dart';
import 'plan_format.dart';
import 'plan_stage_text.dart';

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
    this.onSubscription,
    this.retryOffline = false,
  });

  final Plan plan;
  final PlanDayRoute day;
  final PlanDayRoom? room;
  final VoidCallback onOpen;

  /// «Повторить» у несобравшегося дня (22-5c) — повтор урока, не пересборка плана.
  final VoidCallback? onRetryLesson;

  /// «Подписка» on a day that opens with a subscription (21-3 «по подписке») — the profile's subscription group
  /// until PAY-1 brings the paywall.
  final VoidCallback? onSubscription;

  /// The last «Повторить» could not leave — there really is no network: «Нет сети» under the title.
  final bool retryOffline;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final scene = plan.sceneOf(day);
    final title = day.titleNative ?? scene?.titleNative ?? plan.displayTitle;
    // Тот же кроп, что у узла маршрута (52 и 56 при любой плотности берут один размер): фото,
    // скачанное для маршрута, встаёт на плите без второй загрузки.
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final coverUrl = scene?.image?.urlFor(56, dpr) ?? plan.coverImage?.url;
    final cover = coverUrl == null ? null : CachedNetworkImage(coverUrl);

    final cards = _cardsTotal;
    String? cardsMeta(AppLocalizations l) => cards > 0 ? l.planCardsCount(cards) : null;

    // «Day 3 · catching up» (GEN-3 `catch_up`): the next day opens as soon as this one closes — said on the day's
    // own plate, the closed day's plate names the next one by its date anyway.
    final label = plan.catchUp ? l.planPlateLabelCatchUp(day.number) : l.planPlateLabel(day.number);

    // 21-3 «по подписке»: the day is there, its rows ahead, and it opens with a subscription — not with a date.
    // FIRST, before the lesson's state: a free learner's locked day has no lesson written for it (`pending`), and
    // «Собираем день» over it would promise a day the subscription holds.
    if (day.lockedBySubscription) {
      final count = cardsMeta(l);
      final stages = _stages(l);
      return DayPlate(
        // Only «ДЕНЬ N»: «догоняем» says the day opens as soon as the one before closes — this one waits for the
        // subscription (доработка CLIENT-START п. 5; the window's brow, 23-0a, is «ДЕНЬ N» already).
        label: l.planPlateLabel(day.number),
        title: title,
        meta: count == null ? l.planPlateBySubscription : l.planDot(l.planPlateBySubscription, count),
        cover: cover,
        stages: stages,
        footer: DayPlateFooter.locked(
          note: l.planPlateOpensWithSubscription,
          action: l.planPlateSubscription,
          onTap: onSubscription,
        ),
        // The plate opens the day's window (23-0a «по подписке» — the free learner sees it whole); «Подписка» leads on.
        onTap: onOpen,
      );
    }

    // Кадр 22-5a: день ещё пишется — на месте этапов строка о сроке и разрешение уйти. Кнопки
    // здесь НЕТ: нажимать пока не на что, а приглушённая кнопка обещала бы, что скоро можно.
    if (day.lessonBuilding) {
      return DayPlate(
        label: label,
        title: title,
        meta: cardsMeta(l),
        cover: cover,
        stages: const [],
        notice: DayPlateNotice(
          title: l.planPlateBuildingTitle(day.number),
          sub: l.planPlateBuildingSub,
          preloaderLines: [l.planEntryPreviewLine1, l.planEntryPreviewLine2, l.planEntryPreviewLine3],
        ),
      );
    }

    // Кадр 22-5c: день не собрался — маршрут остаётся, потерян только день, и действие одно. It is a lesson that failed
    // its checks twice, not the network (plan-api «Для CLIENT-START»): «Нет сети» only when a retry could not leave.
    if (day.lessonFailed) {
      return DayPlate(
        label: label,
        title: title,
        meta: cardsMeta(l),
        cover: cover,
        stages: const [],
        notice: DayPlateNotice(
          title: l.planPlateFailedTitle,
          sub: retryOffline ? l.planPlateNoNetwork : null,
          offline: retryOffline,
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
      label: label,
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

  /// СКОЛЬКО КАРТОЧЕК В ДНЕ — «75 карточек» на плите (кадры 21-2, 21-3, 21-4).
  ///
  /// Три источника по убыванию точности, и третий появился не от лени: у ещё НЕ ОТКРЫТОГО дня
  /// сервер отдаёт `metrics: null` и `cards_total: 0`, но его кабинет уже несёт все пять этапов с
  /// их `total` — 32 + 18 + 1 + 16 + 8. Сумма СВОИХ ЖЕ чисел сервера — не выдуманная оценка (в
  /// отличие от «≈ 20 минут», которого нет нигде), поэтому строка на плите остаётся, а не
  /// исчезает у каждого не начатого дня. Расхождение названо в отчёте наряда.
  int get _cardsTotal {
    final metrics = room?.metrics?.cardsTotal ?? 0;
    if (metrics > 0) return metrics;
    if (day.cardsTotal > 0) return day.cardsTotal;
    final stages = room?.stages ?? const <PlanStageProgress>[];

    return stages.fold(0, (sum, s) => sum + s.total);
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
          kind: planStageMark(s.stage),
          name: planStageName(l, s.stage),
          // СОСТОЯНИЕ СЛОВАМИ, не счёт (кадры 21-2 … 21-4): «пройдено», «идёт», «впереди».
          count: switch (state) {
            DayPlateStageState.done => l.planPlateStateDone,
            DayPlateStageState.current => l.planPlateStateCurrent,
            DayPlateStageState.locked => l.planPlateStateAhead,
          },
          state: state,
          note: note,
        ),
      );
    }
    if (out.isEmpty && day.lockedBySubscription) return _windowRowsAhead(l, r);

    return out;
  }

  /// 21-3 «по подписке»: a free learner's locked day has no lesson written yet, so the room's own stages are all
  /// `absent` — the day's rows are the window's (`window.stages`, the server's list for this day), every one «впереди».
  List<DayPlateStage> _windowRowsAhead(AppLocalizations l, PlanDayRoom r) {
    final DayWindow window;
    try {
      window = DayWindow.fromJson(r.windowJson);
    } on PlanContractError catch (e) {
      debugPrint('[plate] window of a subscription day: $e');
      return const [];
    }
    return [
      for (final s in window.stages)
        if (s.stage != PlanStage.unknown)
          DayPlateStage(
            kind: planStageMark(s.stage),
            name: planStageName(l, s.stage),
            count: l.planPlateStateAhead,
            state: DayPlateStageState.locked,
          ),
    ];
  }
}
