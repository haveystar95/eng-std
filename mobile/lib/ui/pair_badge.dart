import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'mini_flag.dart';

/// 🇬🇧 EN → RU — which pair a collection, or a card in a mixed session, belongs to.
///
/// ## Why it exists
///
/// A collection has exactly one pair and keeps it forever (DECISIONS п. 81), and the POOL mixes
/// them: a study session deals `en←es` and `pl←ru` cards in one stream (п. 128). Without a label the
/// learner meets a Polish word in what they took to be an English session and reads the app as
/// broken. The badge is the smallest thing that answers «which language is this».
///
/// ## One flag and two codes — how the format settled
///
/// Rule 14 of `tokens.html` §4б used to end «на карточках слов и коллекций флагов нет», and the
/// first cut of this badge obeyed it: two uppercase codes, `EN→ES`, set in type. The owner overruled
/// it on sight (24.08) — a pair is read at a glance or not at all, and two two-letter codes are
/// read, not glanced at. The rule was amended rather than quietly broken (DECISIONS п. 148): the
/// pair badge is a LANGUAGE context, which is the very thing rule 14 admits flags for; what stays
/// forbidden is decorative flags beside a word or a title that is not about languages.
///
/// Two flags then turned out to be a glance with nothing to check it against: half the flags in the
/// catalogue are a tricolour, and `pl` beside `ru` is two horizontal bands of white-over-red versus
/// white-over-blue-over-red at 12 pt. The format is now **`🇬🇧 EN → RU`** — the flag carries the
/// glance for the language being LEARNED, which is the half that matters most, and the codes carry
/// the certainty for both halves. One flag rather than two because two saturated circles at the end
/// of a title line become the brightest thing on the screen (see below), and this way only the
/// studied side spends that.
///
/// ## Inside a quiet chip, and small
///
/// Bare flags at 15 were the second try and lost too: the palette is paper and ink, so two
/// saturated circles at the end of a title line become the brightest thing on the screen and take
/// the eye off the collection's name. They sit in a `faintInk` chip instead, at 12 — the frame is
/// what stops the colour floating on the sheet, and the smaller diameter is what keeps a LABEL from
/// out-shouting the thing it labels. Chosen off a side-by-side of six candidates on a real shelf
/// row (`tool/pair_badge_preview.dart`), which is the only way this kind of question answers itself.
///
/// The direction is «изучаемый → язык поддержки» — a collection's `target_lang → source_lang`. It
/// does not flip: unlike the search pill, which is a direction the learner sets, this states which
/// language is being LEARNED, and that is a fact about the folder.
///
/// A language {@link MiniFlag} has no painter for falls back to its neutral coded circle, so a pair
/// is always drawable — including one with a reference language on it.
///
/// A PHRASEBOOK ([reference]) shows a WORD instead of a pair: a zh/ja collection is not a course,
/// and a pair of flags would promise training that does not exist (пп. 84, 136).
class PairBadge extends StatelessWidget {
  const PairBadge({
    super.key,
    required this.learned,
    required this.support,
    this.reference = false,
    this.size = 12,
  });

  /// The language being LEARNED — a collection's `target_lang`, the language of its terms.
  final String learned;

  /// The language of SUPPORT — a collection's `source_lang`, the translation shown beside them.
  final String support;

  /// A reference collection: the label replaces the pair entirely.
  final bool reference;

  /// Flag diameter; the chip is sized around it. The «Слова» mini-flag is specified at 22 (§4б),
  /// which is a PICKER row's size — a badge riding a title line is a label, not a choice, and the
  /// inner hairline contour is what keeps it legible this small.
  final double size;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    if (reference) {
      // The SAME chip, so a phrasebook and a course read as one kind of thing said two ways.
      return Semantics(
        label: l.collectionReferenceBadge,
        child: _Chip(
          size: size,
          child: Text(
            l.collectionReferenceBadge.toUpperCase(),
            style: TextStyle(
              fontFamily: AppFonts.inter,
              fontWeight: FontWeight.w700,
              fontSize: size * 0.72,
              letterSpacing: 0.7,
              color: AppColors.tertiary,
            ),
          ),
        ),
      );
    }

    return Semantics(
      label: l.pairBadgeSemantics(learned.toUpperCase(), support.toUpperCase()),
      child: _Chip(
        size: size,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            MiniFlag(languageCode: learned, size: size),
            SizedBox(width: size * 0.34),
            _Code(learned, size: size),
            // Hairline and tertiary: the arrow says «into», it is not part of the colour.
            Padding(
              padding: EdgeInsets.symmetric(horizontal: size * 0.25),
              child: Icon(
                LucideIcons.arrowRight,
                size: size * 0.66,
                color: AppColors.tertiary,
              ),
            ),
            _Code(support, size: size),
          ],
        ),
      ),
    );
  }
}

/// A language code inside the badge — `EN`, `RU`. Set in the same weight and colour the reference
/// badge uses, so the two chips read as one family.
class _Code extends StatelessWidget {
  const _Code(this.code, {required this.size});

  final String code;
  final double size;

  @override
  Widget build(BuildContext context) => Text(
    code.toUpperCase(),
    style: TextStyle(
      fontFamily: AppFonts.inter,
      fontWeight: FontWeight.w700,
      fontSize: size * 0.72,
      letterSpacing: 0.4,
      color: AppColors.secondary,
      height: 1,
    ),
  );
}

/// The quiet ground the badge sits on: a `faintInk` pill, fully rounded, with just enough padding to
/// keep the flags off its edge. It carries no border — a hairline here would read as a second,
/// competing frame beside the collection cover's own.
class _Chip extends StatelessWidget {
  const _Chip({required this.size, required this.child});

  final double size;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.symmetric(horizontal: size * 0.5, vertical: size * 0.25),
    decoration: BoxDecoration(
      color: AppColors.faintInk,
      borderRadius: BorderRadius.circular(999),
    ),
    child: child,
  );
}
