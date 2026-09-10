import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../plan_format.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';
import 'entry_tape.dart';

/// ШАГ ДНЕЙ (кадры 22-3a/b): the five day chips in Literata 20, the date toggle between hairlines,
/// its note, the date cards when the toggle is on, and the brass line when the date shortens the
/// plan.
///
/// The «5 дней · 3 ситуации, 1 повторение, репетиция» line under the chips is the server's
/// (entry.days.calc) and the contract has no call that answers it before a plan exists — the line
/// is not drawn (reported to the architect).
class EntryDaysStep extends StatelessWidget {
  const EntryDaysStep({
    super.key,
    required this.tape,
    required this.days,
    required this.requestedDays,
    required this.dateEnabled,
    required this.eventDate,
    required this.onDays,
    required this.onDateEnabled,
    required this.onPickDate,
  });

  final List<EntryTapeRow> tape;
  final int days;

  /// The learner's own choice — the line «план сократится до N» compares against it.
  final int requestedDays;
  final bool dateEnabled;
  final DateTime? eventDate;
  final ValueChanged<int> onDays;
  final ValueChanged<bool> onDateEnabled;
  final VoidCallback onPickDate;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final left = eventDate == null ? null : PlanFormat.daysUntil(eventDate!, DateTime.now());
    final shortened = dateEnabled ? PlanFormat.shortenedDays(chosen: requestedDays, daysLeft: left) : null;
    final reduce = MediaQuery.of(context).disableAnimations;

    return EntryContent(
      children: [
        EntryTape(rows: tape),
        const SizedBox(height: 22),
        EntryQuestion(l.planEntryDaysTitle),
        const SizedBox(height: 16),
        Row(
          children: [
            for (final n in EntryState.dayChoices) ...[
              if (n != EntryState.dayChoices.first) const SizedBox(width: 8),
              Expanded(child: _DayChip(value: n, selected: n == days, onTap: () => onDays(n))),
            ],
          ],
        ),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: const BoxDecoration(
            border: Border(
              top: BorderSide(color: AppColors.dividerFaint),
              bottom: BorderSide(color: AppColors.dividerFaint),
            ),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  l.planEntryDateToggle,
                  style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.ink),
                ),
              ),
              Switch.adaptive(
                value: dateEnabled,
                onChanged: (v) {
                  AppHaptics.light();
                  onDateEnabled(v);
                },
                activeTrackColor: AppColors.ink,
                activeThumbColor: AppColors.paper,
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Text(
          l.planEntryDateNote,
          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.45, color: AppColors.tertiary),
        ),
        // Раскрытие 220 мс ease-out; при «уменьшении движения» — сразу (4о).
        AnimatedSize(
          duration: reduce ? Duration.zero : const Duration(milliseconds: 220),
          curve: AppMotion.easeOut,
          alignment: Alignment.topCenter,
          child: dateEnabled
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 16),
                    if (eventDate != null) ...[
                      ChoiceCard(
                        title: PlanFormat.date(eventDate!, locale),
                        subtitle: PlanFormat.weekday(eventDate!, locale),
                        selected: true,
                        onTap: onPickDate,
                      ),
                      const SizedBox(height: 10),
                    ],
                    ChoiceCard(
                      title: l.planDateOptionOther,
                      subtitle: l.planDateOptionOtherSub,
                      selected: false,
                      onTap: onPickDate,
                    ),
                    if (shortened != null && left != null) ...[
                      const SizedBox(height: 14),
                      Text(
                        l.planEntryDateShorten(left, shortened),
                        style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.45, color: AppColors.brassInk),
                      ),
                    ],
                  ],
                )
              : const SizedBox(width: double.infinity),
        ),
      ],
    );
  }
}

/// A day chip — 52 high, radius 16, Literata 20; paper with the card's shadow, ink when chosen.
class _DayChip extends StatelessWidget {
  const _DayChip({required this.value, required this.selected, required this.onTap});

  final int value;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    selected: selected,
    label: '$value',
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 160),
      height: 52,
      decoration: BoxDecoration(
        color: selected ? AppColors.ink : AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(16),
        boxShadow: selected ? null : AppShadows.card,
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () {
            AppHaptics.light();
            onTap();
          },
          child: Center(
            child: Text(
              '$value',
              style: TextStyle(
                fontFamily: AppFonts.literata,
                fontSize: 20,
                color: selected ? AppColors.paper : AppColors.ink,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}
