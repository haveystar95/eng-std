import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/l10n/language_endonyms.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'search_pair.dart';

/// «Русский ⇄ English» — the two pills over the search field that say which pair is being asked in.
///
/// ## Why it exists at all
///
/// It replaced automatic detection, which read the language off the query and was wrong often
/// enough to matter: on a single word DeepL calls «gate» Norwegian and answers «улица». A wrong
/// answer there looks exactly like a right one, so there is nothing for the learner to notice and
/// nothing to correct. A stated pair can be wrong too — but visibly, and one tap fixes it.
///
/// ## Two pills and an arrow, not one pill
///
/// A pair has two halves and they are not interchangeable: one is the language being LEARNED, the
/// other the language the learner reads. Which languages may stand in which half is a fact about
/// the deployment, not about this screen — it is [SearchLanguages], read from the server — so each
/// pill offers what may stand beside ITS NEIGHBOUR, which is the only question with a stable answer
/// now that the same language can play either role (RS-3). A pill left with one language on offer
/// is a label: it opens no sheet, because a sheet with a single row is a dead end that still costs
/// a tap to close. That is what the taught side used to be, every time, while the server named one
/// taught language; it is a real picker now that the server names seven.
///
/// THE ARROW SWAPS. That is the move this control exists for — «I know this word in English»
/// versus «I need this word in English» is a switch people flip constantly, and it is its own
/// button rather than a tap on a pill so that flipping and re-picking can never be confused.
///
/// Set in the LABEL type, not the reading type: the pair is a setting, and a setting that competed
/// with the answer would be the second thing the eye lands on for a control most people touch once.
class LanguagePairBar extends StatelessWidget {
  const LanguagePairBar({
    super.key,
    required this.pair,
    required this.languages,
    required this.onChanged,
  });

  final SearchPair pair;
  final SearchLanguages languages;

  /// A swap, or a language picked in either pill — all three are the same event to the screen: a
  /// new pair to remember and to re-ask the current query in.
  final ValueChanged<SearchPair> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        _Pill(
          code: pair.source,
          role: l.searchPairFrom,
          // Each pill asks what would still make a pair with the OTHER one — see
          // [SearchLanguages.optionsAgainst]. Its own language is in that list (it is the ticked
          // row); its neighbour's never is, so «en → en» cannot be picked into being.
          options: languages.optionsAgainst(pair.target),
          onPick: (code) => onChanged(SearchPair(source: code, target: pair.target)),
        ),
        _SwapButton(label: l.searchPairSwap, onTap: () => onChanged(pair.swapped)),
        _Pill(
          code: pair.target,
          role: l.searchPairTo,
          options: languages.optionsAgainst(pair.source),
          onPick: (code) => onChanged(SearchPair(source: pair.source, target: code)),
        ),
      ],
    );
  }
}

/// One side of the pair: flag, endonym, and a chevron only when there is something to choose.
class _Pill extends StatelessWidget {
  const _Pill({
    required this.code,
    required this.role,
    required this.options,
    required this.onPick,
  });

  final String code;

  /// «С какого» / «На какой» — the slot's job, drawn as a caption over the pill.
  ///
  /// Written out rather than left to the arrow, because the arrow alone answers «which way» and not
  /// «which of these two is the language I am learning» — and after a swap the two pills have
  /// exchanged roles, which is the moment a learner needs telling.
  final String role;

  final List<String> options;
  final ValueChanged<String> onPick;

  static const _caption = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 9.5,
    letterSpacing: 0.8,
    color: AppColors.tertiary,
  );

  @override
  Widget build(BuildContext context) {
    final choosable = options.length > 1;

    return Semantics(
      button: choosable,
      label: '$role: ${_name(code)}',
      child: InkWell(
        onTap: choosable
            ? () {
                AppHaptics.light();
                _pick(context);
              }
            : null,
        borderRadius: BorderRadius.circular(AppRadii.small),
        child: Padding(
          // Tall enough to clear the 44 pt tap target together with the caption above it, tight
          // enough that the pair reads as a label rather than a pair of buttons.
          padding: const EdgeInsets.fromLTRB(8, 5, 8, 6),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(role.toUpperCase(), style: _caption),
              const SizedBox(height: 3),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  MiniFlag(languageCode: code, size: 16),
                  const SizedBox(width: 6),
                  Text(_name(code), style: AppText.sectionLabel),
                  if (choosable) ...[
                    const SizedBox(width: 3),
                    const Icon(LucideIcons.chevronDown, size: 12, color: AppColors.tertiary),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pick(BuildContext context) async {
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (sheetContext) => Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s8),
            child: Text(role, style: AppText.sectionLabel),
          ),
          for (final option in options)
            AppSheetRow(
              leading: MiniFlag(languageCode: option),
              title: Text(
                _name(option),
                style: AppText.translation.copyWith(color: AppColors.ink),
              ),
              trailing: option == code
                  ? const Icon(LucideIcons.check, size: 18, color: AppColors.ink)
                  : null,
              onTap: () => Navigator.of(sheetContext).pop(option),
            ),
        ],
      ),
    );

    if (chosen != null && chosen != code) onPick(chosen);
  }

  /// The language's name in its OWN language, which is how the app names languages everywhere else
  /// (DECISIONS п. 135).
  static String _name(String code) =>
      kLanguages.where((l) => l.code == code).firstOrNull?.endonym ?? code.toUpperCase();
}

class _SwapButton extends StatelessWidget {
  const _SwapButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkResponse(
      onTap: () {
        AppHaptics.light();
        onTap();
      },
      radius: 20,
      child: const SizedBox(
        width: 32,
        height: 32,
        child: Icon(LucideIcons.arrowRightLeft, size: 13, color: AppColors.tertiary),
      ),
    ),
  );
}
