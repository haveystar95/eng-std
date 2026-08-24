import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// Кадр 07 — the button gone out to a STATE: an outline on layered paper with a green tick. It is
/// not disabled-looking, it is finished-looking, which is a different thing and the difference is
/// the whole frame.
///
/// Shared, because the search screen has to say the same thing in the same shape: a word that is
/// already in a collection is finished business there too, and the button that would offer to build
/// it must be replaced rather than merely disabled (Ч.4, состояние «б»).
class SavedStateLine extends StatelessWidget {
  const SavedStateLine({super.key, required this.label});

  final String label;

  /// GROWS rather than truncates. It was a fixed 54 pt of one clipped line, which was right while
  /// the label was «В коллекции „…"» and wrong the moment it also had to say the word is now being
  /// studied: on the simulator that sentence ended at «Сохранено в коллекцию „Сохранённ…», so the
  /// half the learner could not otherwise know was the half that got cut.
  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: AppWordCard.actionHeight),
    padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16, vertical: AppSpacing.s12),
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: AppColors.surfaceRaised,
      borderRadius: BorderRadius.circular(AppRadii.button),
      border: Border.all(color: AppColors.dashed, width: 1.5),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown),
        const SizedBox(width: 9),
        Flexible(
          child: Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: AppText.sheetButton,
          ),
        ),
      ],
    ),
  );
}

/// The second action, as a line rather than a button — it is still there, it just no longer argues
/// with the state above it (кадры 07/09).
///
/// Set in INK, not terracotta. Rule 01 keeps the interface monochrome and terracotta is reserved
/// for what destroys: «Удалить аккаунт», the «Не то» verdict. Adding a word to one more collection
/// destroys nothing, and painting it in the delete colour made the safest action on the card look
/// like the dangerous one (QA-OBS-19).
class QuietLinkAction extends StatelessWidget {
  const QuietLinkAction({super.key, required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 46,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 18, color: AppColors.ink),
            const SizedBox(width: AppSpacing.s8),
            Text(
              label,
              style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.ink, fontSize: 15),
            ),
          ],
        ),
      ),
    ),
  );
}
