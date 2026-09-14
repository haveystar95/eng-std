import 'package:eng_std/l10n/app_localizations.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import '../../plan_stage_text.dart';

/// Вкладки программы окна — в порядке кадра 23-0d.
enum WindowTab { words, phrases, dialogue }

/// СЛОВА ОКНА ДНЯ И ШИТА СЛОВА — всё из словаря плана (`docs/plan-ui-glossary.md`), числа — из ответа сервера.
/// Здесь только выбор ключа и склейка частей; ни одного числа, посчитанного на телефоне.
abstract final class WindowTexts {
  /// Название на плите и в компактной шапке: сцена дня, у повторения и репетиции — имя дня.
  static String title(AppLocalizations l, WindowDay day) => switch (day.type) {
    PlanDayType.review => l.planRouteDayReview,
    PlanDayType.rehearsal => l.planRouteDayRehearsal,
    _ => day.titleNative ?? '',
  };

  /// «не начат · ≈ 20 минут» / «идёт · ≈ 12 мин» (23-0a, 23-0b); у пройденного дня на месте статуса —
  /// строка итога «День пройден · 19 минут» (23-0c). Без минут от сервера — одно слово.
  static String status(AppLocalizations l, WindowDay day) {
    if (day.status == WindowDayStatus.passed) return l.planWindowPassedLine(l.planMinutesCount(day.minutesSpent ?? 0));
    final (word, minutes) = switch (day.status) {
      WindowDayStatus.notStarted => (l.planWindowStateNotStarted, _approx(l, day.minutesEstimate, long: true)),
      _ => (l.planWindowStateInProgress, _approx(l, day.minutesEstimate, long: false)),
    };

    return minutes == null ? word : l.planWindowJoin(word, minutes);
  }

  /// Минуты компактной шапки: «≈ 20 мин» до конца дня, «19 минут» у пройденного.
  static String? compactMinutes(AppLocalizations l, WindowDay day) => day.status == WindowDayStatus.passed
      ? (day.minutesSpent == null ? null : l.planMinutesCount(day.minutesSpent!))
      : _approx(l, day.minutesEstimate, long: false);

  /// Слово ряда этапа: «пройдено», «идёт · ≈ 8 мин», «впереди».
  static String stageState(AppLocalizations l, WindowStage stage) => switch (stage.state) {
    WindowStageState.done => l.planPlateStateDone,
    WindowStageState.current => stage.minutesLeft == null
        ? l.planPlateStateCurrent
        : l.planWindowJoin(l.planPlateStateCurrent, _approx(l, stage.minutesLeft, long: false)!),
    WindowStageState.locked => l.planPlateStateAhead,
  };

  /// «6 / 16» — только у текущего ряда: у остальных сервер цифры не прислал, и строки нет.
  static String? stageCount(AppLocalizations l, WindowStage stage) =>
      stage.state == WindowStageState.current && stage.doneCount != null && stage.total != null
          ? l.planWindowStageCount(stage.doneCount!, stage.total!)
          : null;

  static String tabName(AppLocalizations l, WindowTab tab) => switch (tab) {
    WindowTab.words => planStageName(l, PlanStage.words),
    WindowTab.phrases => planStageName(l, PlanStage.phrases),
    WindowTab.dialogue => planStageName(l, PlanStage.dialogue),
  };

  /// Бровь вкладки из счётчиков сервера: «Слова · 8 · 6 пройдено · 2 вернутся завтра». У диалога
  /// общего числа нет (кадр 23-0d), у пустой вкладки — только имя; части с нулём не пишутся.
  static String brow(AppLocalizations l, WindowTab tab, WindowSummary summary) {
    var line = tab == WindowTab.dialogue || summary.total == 0
        ? tabName(l, tab)
        : l.planWindowJoin(tabName(l, tab), '${summary.total}');
    if (summary.done > 0) line = l.planWindowJoin(line, l.planWindowBrowDone(summary.done));
    if (summary.returns > 0) line = l.planWindowJoin(line, l.planWindowBrowReturns(summary.returns));

    return line;
  }

  /// ЦЕЛИ ОДНИМ ПРЕДЛОЖЕНИЕМ (23-0a…0c): «описать, где болит, ответить на вопросы врача и спросить про
  /// ограничения» — части через запятую, последняя через «и». Цели — сервера, склейка — словаря.
  static String goalsList(AppLocalizations l, List<WindowGoal> goals) {
    final parts = [for (final g in goals) if (g.text.trim().isNotEmpty) _lowerFirst(g.text.trim())];
    if (parts.isEmpty) return '';
    var line = parts.first;
    for (var i = 1; i < parts.length; i++) {
      line = i == parts.length - 1 ? l.planWindowGoalsJoinLast(line, parts[i]) : l.planWindowGoalsJoin(line, parts[i]);
    }

    return line;
  }

  /// Строка состояния шита слова (23-0e): «не начато» / «пройдено» / «пройдено · вернётся в день 3».
  static String sheetState(AppLocalizations l, WindowWord word) => switch (word.state) {
    WindowUnitState.pending => l.planWindowSheetNotStarted,
    WindowUnitState.done => l.planPlateStateDone,
    WindowUnitState.returnsTomorrow => l.planWindowJoin(
      l.planPlateStateDone,
      word.returnsDay == null ? l.planWindowSheetReturnsTomorrow : l.planWindowSheetReturnsOn(word.returnsDay!),
    ),
  };

  static String _lowerFirst(String s) =>
      s.length > 1 && s[0].toUpperCase() == s[0] && s[1].toLowerCase() == s[1] ? s[0].toLowerCase() + s.substring(1) : s;

  static String action(AppLocalizations l, WindowAction action) => switch (action) {
    WindowAction.start => l.planPlateCtaStart,
    WindowAction.resume => l.planPlateCtaContinue,
    WindowAction.again => l.planWindowCtaAgain,
  };

  static String? _approx(AppLocalizations l, int? minutes, {required bool long}) => minutes == null
      ? null
      : l.planWindowApprox(long ? l.planMinutesCount(minutes) : l.planMinutesShort(minutes));
}
