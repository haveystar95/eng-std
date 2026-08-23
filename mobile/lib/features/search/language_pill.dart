import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/language_endonyms.dart';
import 'package:eng_std/theme/theme.dart';

import 'search_pair.dart';

/// «EN → RU» — the one control on the search screen that says which way the answer comes back.
///
/// ## Why it exists at all
///
/// It replaced automatic detection, which read the language off the query and was wrong often
/// enough to matter: on a single word DeepL calls «gate» Norwegian and answers «улица». A wrong
/// answer there looks exactly like a right one, so there is nothing for the learner to notice and
/// nothing to correct. A stated direction can be wrong too — but visibly, and one tap fixes it.
///
/// ## Why it is this small
///
/// The pill sits beside the field, not above the results, and it is set in the LABEL type, not the
/// reading type. It is a setting, and a setting that competed with the answer would be the second
/// thing the eye lands on for a control most people touch once. Two codes and an arrow: the codes
/// are already the shortest true name a language has, and the arrow carries the whole meaning.
///
/// ONE TAP SWAPS. That is the move this control exists for — «I know this word in English» versus
/// «I need this word in English» is a switch people flip constantly. The second language of the
/// pair is behind a LONG press, because changing it is a once-a-month decision and putting it on
/// the same gesture would make the common move ambiguous.
class LanguagePill extends StatelessWidget {
  const LanguagePill({
    super.key,
    required this.pair,
    required this.languages,
    required this.onSwap,
    required this.onPick,
  });

  final SearchPair pair;
  final SearchLanguages languages;

  /// A tap: the same two languages, the other way round.
  final VoidCallback onSwap;

  /// A long press: choose the other half of the pair.
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      // Read out as what it DOES, since «EN стрелка RU» is not a sentence. The picker is reachable
      // from the same node via its long-press action.
      label: '${_name(pair.source)} → ${_name(pair.target)}',
      child: InkWell(
        onTap: () {
          AppHaptics.light();
          onSwap();
        },
        onLongPress: () {
          AppHaptics.light();
          _pick(context);
        },
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          // Tall enough to clear the 44 pt tap target with the field's own height, tight enough
          // that it reads as a label rather than a button.
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_code(pair.source), style: AppText.sectionLabel),
              const SizedBox(width: 5),
              const Icon(LucideIcons.arrowRight, size: 12, color: AppColors.tertiary),
              const SizedBox(width: 5),
              Text(_code(pair.target), style: AppText.sectionLabel),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pick(BuildContext context) async {
    final taught = languages.taught;
    final current = pair.otherThan(taught);
    // Nothing to choose between — one language on offer means the long press has no second state
    // to offer and a sheet would be a dead end.
    if (languages.natives.length < 2) return;

    final chosen = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: AppColors.surfaceRaised,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.sheet)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: AppSpacing.s12),
            for (final code in languages.natives)
              ListTile(
                leading: Text(_flag(code), style: const TextStyle(fontSize: 22)),
                title: Text(_name(code), style: AppText.translation.copyWith(color: AppColors.ink)),
                trailing: code == current
                    ? const Icon(LucideIcons.check, size: 18, color: AppColors.ink)
                    : null,
                onTap: () => Navigator.of(sheetContext).pop(code),
              ),
            const SizedBox(height: AppSpacing.s12),
          ],
        ),
      ),
    );

    if (chosen != null && chosen != current) onPick(chosen);
  }

  /// «EN», «RU» — the label. Upper-cased here rather than stored that way: the code is data.
  static String _code(String code) => code.toUpperCase();

  /// The language's name in its OWN language, which is how the app names languages everywhere else.
  static String _name(String code) =>
      kLanguages.where((l) => l.code == code).firstOrNull?.endonym ?? code.toUpperCase();

  static String _flag(String code) =>
      kLanguages.where((l) => l.code == code).firstOrNull?.flag ?? '';
}
