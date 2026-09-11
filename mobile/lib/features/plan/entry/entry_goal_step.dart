import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../plan_format.dart';
import 'entry_scaffold.dart';
import 'entry_state.dart';

/// ШАГ ЦЕЛИ (кадры 22-1a/b/c): the question, the three-part hint over the field, the paper field
/// with the example placeholder and the microphone, the one-line nudge under a short answer, the
/// five chips whose templates fill the field.
class EntryGoalStep extends StatelessWidget {
  const EntryGoalStep({
    super.key,
    required this.controller,
    required this.focus,
    required this.chip,
    required this.listening,
    required this.onChip,
    required this.onMic,
  });

  final TextEditingController controller;
  final FocusNode focus;
  final EntryGoalChip? chip;
  final bool listening;
  final ValueChanged<EntryGoalChip> onChip;
  final VoidCallback onMic;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return EntryContent(
      children: [
        EntryQuestion(l.planEntryGoalTitle),
        const SizedBox(height: 10),
        Text(
          l.planEntryGoalHint,
          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.secondary),
        ),
        const SizedBox(height: 14),
        _GoalField(controller: controller, focus: focus, listening: listening, onMic: onMic),
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: controller,
          builder: (context, value, _) => PlanFormat.isShortGoal(value.text)
              ? Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Text(
                    l.planEntryGoalShort,
                    style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary),
                  ),
                )
              : const SizedBox.shrink(),
        ),
        const SizedBox(height: 16),
        ChipWrap(
          children: [
            for (final c in EntryGoalChip.values)
              AppChip(
                label: _chipLabel(l, c),
                paper: true,
                selected: chip == c,
                onTap: () => onChip(c),
              ),
          ],
        ),
      ],
    );
  }

  static String _chipLabel(AppLocalizations l, EntryGoalChip c) => switch (c) {
    EntryGoalChip.doctor => l.planEntryGoalChipDoctor,
    EntryGoalChip.rent => l.planEntryGoalChipRent,
    EntryGoalChip.interview => l.planEntryGoalChipInterview,
    EntryGoalChip.trip => l.planEntryGoalChipTrip,
    EntryGoalChip.other => l.planEntryGoalChipOther,
  };

  /// The template a chip writes into the field (кадр 22-1b); «Другое» clears it.
  static String? templateFor(AppLocalizations l, EntryGoalChip c) => switch (c) {
    EntryGoalChip.doctor => l.planEntryGoalTemplateDoctor,
    EntryGoalChip.rent => l.planEntryGoalTemplateRent,
    EntryGoalChip.interview => l.planEntryGoalTemplateInterview,
    EntryGoalChip.trip => l.planEntryGoalTemplateTrip,
    EntryGoalChip.other => null,
  };
}

/// The field: #FCFAF5, radius 18, padding 14/15/12, min-height 136, the card's shadow — deeper in
/// focus instead of a frame; text 17/1.45, placeholder tertiary; the microphone at the bottom right.
class _GoalField extends StatelessWidget {
  const _GoalField({
    required this.controller,
    required this.focus,
    required this.listening,
    required this.onMic,
  });

  final TextEditingController controller;
  final FocusNode focus;
  final bool listening;
  final VoidCallback onMic;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return AnimatedBuilder(
      animation: focus,
      builder: (context, _) => AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        constraints: const BoxConstraints(minHeight: 136),
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(18),
          boxShadow: focus.hasFocus ? AppShadows.fieldFocus : AppShadows.card,
        ),
        padding: const EdgeInsets.fromLTRB(15, 14, 15, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: controller,
              focusNode: focus,
              maxLines: null,
              minLines: 3,
              textCapitalization: TextCapitalization.sentences,
              style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 1.45, color: AppColors.ink),
              cursorColor: AppColors.ink,
              decoration: InputDecoration(
                isDense: true,
                border: InputBorder.none,
                contentPadding: EdgeInsets.zero,
                hintText: l.planEntryGoalPlaceholder,
                hintMaxLines: 4,
                hintStyle: const TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 1.45, color: AppColors.tertiary),
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: Semantics(
                button: true,
                label: listening ? l.planEntryGoalDictateStop : l.planEntryGoalDictate,
                child: InkResponse(
                  radius: 22,
                  onTap: onMic,
                  child: SizedBox(
                    width: AppSpacing.minTap,
                    height: 36,
                    child: Icon(
                      listening ? LucideIcons.square : LucideIcons.mic,
                      size: 20,
                      color: listening ? AppColors.ink : AppColors.secondary,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
