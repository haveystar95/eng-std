import 'package:eng_std/l10n/app_localizations.dart';

import '../../../../data/plan/day_window.dart';
import '../../../../data/plan/plan_models.dart';
import '../../plan_format.dart';
import '../../plan_stage_text.dart';

/// Вкладки программы окна — в порядке кадра 23-0d.
enum WindowTab { words, phrases, dialogue }

/// The plate of a review or the rehearsal (кадры 37-1, 37-2): its brow, its title, one status line and one sentence
/// of what the day holds.
typedef WindowSystemDay = ({String brow, String title, String status, String lead});

/// СЛОВА ОКНА ДНЯ И ШИТА СЛОВА — всё из словаря плана (`docs/plan-ui-glossary.md`), числа — из ответа сервера.
/// Здесь только выбор ключа и склейка частей; ни одного числа, посчитанного на телефоне.
abstract final class WindowTexts {
  /// Название на плите и в компактной шапке: сцена дня, у повторения и репетиции — имя дня.
  static String title(AppLocalizations l, WindowDay day) => switch (day.type) {
    PlanDayType.review => l.planRouteDayReview,
    PlanDayType.rehearsal => l.planRouteDayRehearsal,
    _ => day.titleNative ?? '',
  };

  /// THE PLATE OF A REVIEW OR THE REHEARSAL (кадры 37-1, 37-2, наряд CLIENT-CONV-1b); null — a scene day.
  ///
  /// The brow is the day's kind, the title — the plan's name on the rehearsal («Приём у врача») and «Что уже было» on
  /// a review. The status line: the rehearsal opens with «перед событием» and the day's date as a weekday («в
  /// четверг»; «сегодня» / «завтра» come ready in the slot), then the state word, then the minutes the server sent —
  /// all of it before the start, what was spent once passed; while the day goes on the current row says its own.
  /// The two leads name the partner «собеседник»: the role arrives in the nominative only (as on 37-5).
  static WindowSystemDay? system(AppLocalizations l, WindowDay day, {required String planTitle, PlanDaySlot? slot}) {
    final (state, minutes) = switch (day.status) {
      WindowDayStatus.notStarted => (
        l.planWindowStateNotStarted,
        day.minutesEstimate == null ? null : l.planTalkEntryMinutes(day.minutesEstimate!),
      ),
      WindowDayStatus.inProgress => (l.planWindowStateInProgress, null),
      WindowDayStatus.passed => (l.planSessionStateDone, day.minutesSpent == null ? null : l.planMinutesCount(day.minutesSpent!)),
      WindowDayStatus.locked => (
        l.planPlateBySubscription,
        day.minutesEstimate == null ? null : l.planTalkEntryMinutes(day.minutesEstimate!),
      ),
    };
    String line(List<String> parts) => parts.reduce((a, b) => l.planDot(a, b));

    return switch (day.type) {
      PlanDayType.rehearsal => (
        brow: l.planRouteDayRehearsal,
        title: planTitle,
        status: line([l.planWindowRehearsalBefore, ?_when(l, slot), state, ?minutes]),
        lead: l.planWindowRehearsalLead,
      ),
      PlanDayType.review => (
        brow: l.planRouteDayReview,
        title: l.planWindowReviewTitle,
        status: line([state, ?minutes]),
        lead: l.planWindowReviewLead,
      ),
      PlanDayType.scene || PlanDayType.unknown => null,
    };
  }

  /// When the day stands: «сегодня» / «завтра» as the server wrote them, otherwise its date's weekday — «в четверг».
  static String? _when(AppLocalizations l, PlanDaySlot? slot) {
    switch (slot?.code) {
      case PlanSlotCode.today || PlanSlotCode.tomorrow:
        final label = slot?.labelNative?.trim() ?? '';
        return label.isEmpty ? null : label;
      case PlanSlotCode.date || PlanSlotCode.past:
        final date = PlanFormat.parseWireDate(slot?.date);
        return date == null ? null : l.planWindowOnWeekday(_weekdays[date.weekday - 1]);
      case PlanSlotCode.unscheduled || PlanSlotCode.unknown || null:
        return null;
    }
  }

  static const _weekdays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

  /// «не начат · ≈ 20 минут» / «идёт · ≈ 12 мин» (23-0a, 23-0b); у пройденного дня на месте статуса —
  /// строка итога «День пройден · 19 минут» (23-0c). Без минут от сервера — одно слово.
  static String status(AppLocalizations l, WindowDay day) {
    if (day.status == WindowDayStatus.passed) return l.planWindowPassedLine(l.planMinutesCount(day.minutesSpent ?? 0));
    final (word, minutes) = switch (day.status) {
      WindowDayStatus.notStarted => (l.planWindowStateNotStarted, _approx(l, day.minutesEstimate, long: true)),
      // 23-0a «по подписке»: «по подписке · ≈ 20 минут».
      WindowDayStatus.locked => (l.planPlateBySubscription, _approx(l, day.minutesEstimate, long: true)),
      _ => (l.planWindowStateInProgress, _approx(l, day.minutesEstimate, long: false)),
    };

    return minutes == null ? word : l.planDot(word, minutes);
  }

  /// Минуты компактной шапки: «≈ 20 мин» до конца дня, «19 минут» у пройденного.
  static String? compactMinutes(AppLocalizations l, WindowDay day) => day.status == WindowDayStatus.passed
      ? (day.minutesSpent == null ? null : l.planMinutesCount(day.minutesSpent!))
      : _approx(l, day.minutesEstimate, long: false);

  /// The word of a stage row: «пройдено», «идёт · ≈ 8 мин», and on a row ahead its planned minutes «≈ 4 мин» when the
  /// server sent them (`stages[].minutes`, BACK-TAILS-2; кадры 23-0a, 37-1, 37-2), else «впереди». The current row says
  /// its remainder (`minutes_left`): it is truer than the plan once the stage is under way. [around] — a review or the
  /// rehearsal, whose frames say the minutes in words, as their status line does: «около 4 минут», «идёт · около 6 минут».
  /// СОСТОЯНИЕ РЯДА СЛОВАМИ (кадры 23-0a…0c, 30-1; наряд FIX-3 §5). Пройденный ряд, который можно пройти ещё раз
  /// (`stages[].again`), говорит «ещё раз» — это и есть его действие; пройденный разговор без повторов — «лимит на
  /// сегодня» (суточный кап сервера). Цифр «N / M» в рядах нет.
  static String stageState(AppLocalizations l, WindowStage stage, {bool around = false, bool offerAgain = true}) {
    String? minutes(int? m) => m == null ? null : (around ? l.planTalkEntryMinutes(m) : _approx(l, m, long: false));
    return switch (stage.state) {
      WindowStageState.done when offerAgain && stage.again => l.planWindowStageAgain,
      WindowStageState.done when offerAgain && stage.stage == PlanStage.conversation => l.planWindowTalkLimitToday,
      WindowStageState.done => l.planPlateStateDone,
      WindowStageState.current => switch (minutes(stage.minutesLeft)) {
        null => l.planPlateStateCurrent,
        final m => l.planDot(l.planPlateStateCurrent, m),
      },
      WindowStageState.locked => minutes(stage.minutes) ?? l.planPlateStateAhead,
    };
  }

  static String tabName(AppLocalizations l, WindowTab tab) => switch (tab) {
    WindowTab.words => planStageName(l, PlanStage.words),
    WindowTab.phrases => planStageName(l, PlanStage.phrases),
    WindowTab.dialogue => planStageName(l, PlanStage.dialogue),
  };

  /// Бровь вкладки из счётчиков сервера: «Слова · 8 · 6 пройдено · 2 вернутся завтра». У диалога
  /// общего числа нет (кадр 23-0d), у пустой вкладки — только имя; части с нулём не пишутся.
  static String brow(AppLocalizations l, WindowTab tab, WindowSummary summary, {int returnsTomorrow = 0}) {
    var line = tab == WindowTab.dialogue || summary.total == 0
        ? tabName(l, tab)
        : l.planDot(tabName(l, tab), '${summary.total}');
    if (summary.done > 0) line = l.planDot(line, l.planWindowBrowDone(summary.done));
    // «2 вернутся завтра» — по СОСТОЯНИЯМ единиц; `summary.returns` с наряда FIX-3 §9 значит другое — сколько
    // единиц ВЕРНУЛОСЬ из прошлых дней, и его печатает группа «Вернулось из дня N», а не бровь.
    if (returnsTomorrow > 0) line = l.planDot(line, l.planWindowBrowReturns(returnsTomorrow));

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
    WindowUnitState.returnsTomorrow => l.planDot(
      l.planPlateStateDone,
      word.returnsDay == null ? l.planWindowSheetReturnsTomorrow : l.planWindowSheetReturnsOn(word.returnsDay!),
    ),
  };

  static String _lowerFirst(String s) =>
      s.length > 1 && s[0].toUpperCase() == s[0] && s[1].toLowerCase() == s[1] ? s[0].toLowerCase() + s.substring(1) : s;

  static String action(AppLocalizations l, WindowAction action) => switch (action) {
    WindowAction.start => l.planPlateCtaStart,
    WindowAction.resume => l.planPlateCtaContinue,
  };

  static String? _approx(AppLocalizations l, int? minutes, {required bool long}) => minutes == null
      ? null
      : l.planWindowApprox(long ? l.planMinutesCount(minutes) : l.planMinutesShort(minutes));
}
