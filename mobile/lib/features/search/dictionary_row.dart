import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

/// What sits at the right end of a dictionary row.
enum RowTrailing {
  /// A chevron — the row leads somewhere (кадр 02: to the word's card).
  chevron,

  /// The CEFR level, quietly (кадр 01: a reminder, not a destination marker).
  level,

  /// Nothing — the row is a listing, not an action (кадры 03/04, «Ещё в базе»).
  none,
}

/// One line of the search screen: a word, what it means, and at most one mark on the right.
///
/// The ENTIRE hierarchy decision of направление 1a lives here. While somebody types, the thing that
/// leads anywhere is this list, so it is set at reading size in Literata with the translation beside
/// it — a row in a paper dictionary. The pills this replaced carried the same words at chip size
/// with no translation at all, which made them decoration competing with the field above them.
///
/// [prefix] is the fragment already typed. It is set semibold INSIDE the term rather than
/// highlighted with a colour: the palette is monochrome (rule 01/02), and weight says «this part is
/// yours» without spending the one accent the app has.
class DictionaryRow extends StatelessWidget {
  const DictionaryRow({
    super.key,
    required this.term,
    this.translation,
    this.level,
    this.prefix,
    this.trailing = RowTrailing.none,
    this.showDivider = true,
    this.height = 56,
    this.termStyle,
    this.onTap,
  });

  final String term;
  final String? translation;
  final String? level;

  /// The typed fragment to set semibold, when the term actually starts with it.
  final String? prefix;

  final RowTrailing trailing;
  final bool showDivider;
  final double height;

  /// Overrides the term's size where the mockup asks for one (23 while typing, 19 in the
  /// secondary lists). Colour and family stay [AppText.searchRowTerm]'s.
  final TextStyle? termStyle;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final row = Container(
      constraints: BoxConstraints(minHeight: height),
      decoration: showDivider
          ? const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppColors.dividerFaint)),
            )
          : null,
      child: Row(
        children: [
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Flexible(
                  child: _Term(
                    term: term,
                    prefix: prefix,
                    style: termStyle ?? AppText.searchRowTerm,
                  ),
                ),
                if ((translation ?? '').isNotEmpty) ...[
                  const SizedBox(width: AppSpacing.s12),
                  Flexible(
                    child: Text(
                      translation!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.searchRowTranslation,
                    ),
                  ),
                ],
              ],
            ),
          ),
          switch (trailing) {
            RowTrailing.chevron => const Padding(
              padding: EdgeInsets.only(left: AppSpacing.s8),
              child: Icon(LucideIcons.chevronRight, size: 16, color: AppColors.tertiary),
            ),
            RowTrailing.level when (level ?? '').isNotEmpty => Padding(
              padding: const EdgeInsets.only(left: AppSpacing.s8),
              child: Text(level!, style: AppText.levelMark),
            ),
            _ => const SizedBox.shrink(),
          },
        ],
      ),
    );

    if (onTap == null) return row;

    return Semantics(
      button: true,
      label: translation == null ? term : '$term, $translation',
      child: InkWell(
        onTap: onTap,
        // No radius and no splash colour of its own: a row in a list of rows, not a card.
        child: row,
      ),
    );
  }
}

class _Term extends StatelessWidget {
  const _Term({required this.term, required this.prefix, required this.style});

  final String term;
  final String? prefix;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final typed = prefix?.trim() ?? '';
    final matches = typed.isNotEmpty && term.toLowerCase().startsWith(typed.toLowerCase());
    if (!matches) {
      return Text(term, maxLines: 1, overflow: TextOverflow.ellipsis, style: style);
    }

    return Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: term.substring(0, typed.length),
            style: const TextStyle(fontWeight: FontWeight.w500),
          ),
          TextSpan(text: term.substring(typed.length)),
        ],
      ),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: style,
    );
  }
}

/// The uppercase label above a list («ВЫ ИСКАЛИ», «СЛОВА В БАЗЕ», «ЕЩЁ В БАЗЕ»).
class SearchSectionLabel extends StatelessWidget {
  const SearchSectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) =>
      Text(text.toUpperCase(), style: AppText.sectionLabel.copyWith(color: AppColors.tertiary));
}
