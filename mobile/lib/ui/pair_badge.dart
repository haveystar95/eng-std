import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// «EN→RU» — which pair a collection, or a card in a mixed session, belongs to.
///
/// ## Why a badge at all
///
/// A collection has exactly one pair and keeps it forever (DECISIONS п. 81), and the POOL mixes
/// them: a study session deals `ru→en` and `ru→pl` cards in one stream (п. 128). Without a label
/// the learner meets a Polish word in what they took to be an English session and reads the app as
/// broken. The badge is the smallest thing that answers «which language is this».
///
/// ## Codes, not flags
///
/// `tokens.html` §4б is explicit: mini-flags are the interface's ONLY decorative colour and belong
/// in LANGUAGE contexts — the onboarding list, the «Язык изучения» dropdown, the store's pair row.
/// «На карточках слов и коллекций флагов нет», and rule 14 says the same. A collection card and a
/// session card are exactly those two places, so the pair is set in TYPE: two uppercase codes and a
/// hairline arrow, in the caption size, at tertiary ink. It reads as a label and never competes
/// with the word.
///
/// The direction is «изучаемый → язык поддержки» — a collection's `target_lang → source_lang`. It
/// does not flip: unlike the search pill, which is a direction the learner sets, this states which
/// language is being LEARNED, and that is a fact about the folder.
///
/// A PHRASEBOOK ([reference]) shows a word instead of a pair: a zh/ja collection is not a course,
/// and «ZH→RU» would promise training that does not exist (пп. 84, 136).
class PairBadge extends StatelessWidget {
  const PairBadge({
    super.key,
    required this.learned,
    required this.support,
    this.reference = false,
    this.fontSize = 10.5,
  });

  /// The language being LEARNED — a collection's `target_lang`, the language of its terms.
  final String learned;

  /// The language of SUPPORT — a collection's `source_lang`, the translation shown beside them.
  final String support;

  /// A reference collection: the label replaces the pair entirely.
  final bool reference;

  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final style = TextStyle(
      fontFamily: AppFonts.inter,
      fontWeight: FontWeight.w700,
      fontSize: fontSize,
      letterSpacing: 0.7,
      color: AppColors.tertiary,
    );

    if (reference) {
      return Semantics(
        label: l.collectionReferenceBadge,
        child: Text(l.collectionReferenceBadge.toUpperCase(), style: style),
      );
    }

    final text = '${learned.toUpperCase()}→${support.toUpperCase()}';

    return Semantics(
      label: l.pairBadgeSemantics(learned.toUpperCase(), support.toUpperCase()),
      child: Text(text, style: style),
    );
  }
}
