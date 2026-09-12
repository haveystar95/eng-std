import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../plan_format.dart';
import 'entry_scaffold.dart';
import 'entry_section.dart';
import 'entry_summary.dart';

/// ШАГ ДАТЫ РАЗГОВОРА (кадр 22-3b) — три строки выбора и СЛЕДСТВИЕ, сказанное сразу.
///
/// Канва вычла календарь-сетку на весь экран: три строки отвечают на вопрос быстрее, чем месяц
/// клеток, а сетка нужна только тем, кто выбрал «Другая дата».
///
/// Вариант «Дата пока неизвестна» РАВНОПРАВЕН и стоит второй строкой, а не мелкой ссылкой снизу:
/// без него человек с открытой датой упирается в тупик и врёт календарю.
///
/// Под выбором — строка следствия: «Репетиция встанет на 16 сентября — день перед приёмом».
/// Последняя кнопка называет результат — «Собрать план», не «Готово».
class EntryDateStep extends StatelessWidget {
  const EntryDateStep({
    super.key,
    required this.goal,
    required this.languageValue,
    required this.days,
    required this.eventDate,
    required this.suggested,
    required this.onPickSuggested,
    required this.onPickUnknown,
    required this.onPickCustom,
    required this.onEditGoal,
    required this.onEditLanguage,
    required this.onEditDays,
  });

  final String goal;
  final String languageValue;
  final int days;

  /// Выбранная дата, или null — «дата пока неизвестна».
  final DateTime? eventDate;

  /// Ближняя дата, посчитанная от длины плана — первая строка выбора.
  final DateTime suggested;

  final VoidCallback onPickSuggested;
  final VoidCallback onPickUnknown;
  final VoidCallback onPickCustom;
  final VoidCallback onEditGoal;
  final VoidCallback onEditLanguage;
  final VoidCallback onEditDays;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final today = DateTime.now();
    final onSuggested = eventDate != null && _sameDay(eventDate!, suggested);
    final onCustom = eventDate != null && !onSuggested;
    final chosen = eventDate;

    return EntryContent(
      children: [
        EntryQuestion(l.planEntryDateTitle),
        const SizedBox(height: 14),
        EntrySummaryRow(
          icon: PlanIcon.goal,
          label: l.planEntryTapeGoal,
          value: goal,
          onTap: onEditGoal,
          twoLines: true,
        ),
        EntrySummaryRow(
          icon: PlanIcon.globe,
          label: l.planEntryTapeLanguage,
          value: languageValue,
          onTap: onEditLanguage,
        ),
        EntrySummaryRow(
          icon: PlanIcon.route,
          label: l.planEntryTapeDays,
          value: l.planDaysCount(days),
          onTap: onEditDays,
          last: true,
        ),
        const SizedBox(height: 32),
        EntrySectionLabel(l.planEntryDateLabel),
        const SizedBox(height: 10),
        // Ближняя дата — с числом в календаре: значок повторяет ответ, а не украшает строку.
        ChoiceCard(
          title: PlanFormat.date(onCustom ? chosen! : suggested, locale),
          subtitle: l.planEntryDateIn(
            PlanFormat.weekday(onCustom ? chosen! : suggested, locale),
            PlanFormat.daysUntil(onCustom ? chosen! : suggested, today),
          ),
          selected: chosen != null,
          onTap: onPickSuggested,
          leading: PlanDateMark(
            day: (onCustom ? chosen! : suggested).day,
            color: chosen != null ? AppColors.paper : AppColors.brassInk,
          ),
        ),
        const SizedBox(height: 8),
        ChoiceCard(
          title: l.planEntryDateUnknown,
          subtitle: l.planEntryDateUnknownSub,
          selected: chosen == null,
          onTap: onPickUnknown,
          leading: PlanIconMark(
            icon: PlanIcon.calendarUnknown,
            color: chosen == null ? AppColors.paper : AppColors.brassInk,
          ),
        ),
        const SizedBox(height: 8),
        ChoiceCard(
          title: l.planEntryDateOther,
          subtitle: l.planEntryDateOtherSub,
          selected: false,
          onTap: onPickCustom,
          leading: const PlanIconMark(icon: PlanIcon.calendarPlus),
        ),
        // СЛЕДСТВИЕ ВЫБОРА, сказанное до кнопки: репетиция встанет днём перед разговором.
        if (chosen != null) ...[
          const SizedBox(height: 32),
          _Consequence(
            text: l.planEntryDateRehearsalOn(
              PlanFormat.date(chosen.subtract(const Duration(days: 1)), locale),
            ),
          ),
        ],
      ],
    );
  }

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

/// Строка следствия — бумага radius 18, значок микрофона латунью и один абзац 14 secondary.
class _Consequence extends StatelessWidget {
  const _Consequence({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    decoration: BoxDecoration(
      color: AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(18),
      boxShadow: AppShadows.card,
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 1),
          child: PlanIconMark(icon: PlanIcon.mic, size: 16),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 14,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
        ),
      ],
    ),
  );
}
