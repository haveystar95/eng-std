import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/word_status.dart';

/// The dots row in «Мои слова», as a tap target that opens [showLadderLegend].
const Key ladderDotsLegendKey = Key('ladder-dots-legend');

/// «Что значат точки» — the five rungs, named, with the dot each one lights.
///
/// The pool list draws five dots per row and nothing else, which is right for two hundred rows and
/// wrong for somebody meeting it: the marks are the only place in the app that says how far a word
/// has come, and they said it in a language nobody had been taught. The words already existed — the
/// expanded word card has been naming the same rungs since кадр 16e — so this is a legend and not a
/// new vocabulary: exactly the same five strings, one tap away from the marks that stand for them.
Future<void> showLadderLegend(BuildContext context) {
  final l = AppLocalizations.of(context);

  return showAppBottomSheet<void>(
    context: context,
    builder: (context) => Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
          child: Text(l.statusLegendTitle, style: AppText.sectionLabel),
        ),
        for (final rung in LadderRung.values)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
            child: Row(
              children: [
                // The row's own dot LIT, with the ones before it walked and the ones after it
                // waiting — the mark is explained by being drawn exactly as the list draws it.
                LadderDots(step: LadderDots.rungs[rung.index]),
                const SizedBox(width: AppSpacing.s12),
                Expanded(
                  child: Text(
                    l.statusLadderStep(
                      rung.index + 1,
                      LadderRung.values.length,
                      ladderRungLabel(l, rung),
                    ),
                    style: AppText.translation,
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: AppSpacing.s8),
        // The one line that is not a rung: a word marked «знаю» in the swipe pass never walked the
        // ladder at all, and the list draws it a dash for exactly that reason.
        Row(
          children: [
            LadderKnownDash(label: l.ladderKnownDash),
            const SizedBox(width: AppSpacing.s12),
            Expanded(
              child: Text(
                l.poolKnownLegend,
                style: AppText.translation.copyWith(color: AppColors.secondary),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}
