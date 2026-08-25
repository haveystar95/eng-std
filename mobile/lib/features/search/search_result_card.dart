import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/local/cached_image_provider.dart';
import '../word_card/word_card_screen.dart';
import '../word_card/word_card_subject.dart';

/// Кадр 03 — the word the learner searched for, found in the database.
///
/// The ONLY lifted leaf on the page. Everything else in this frame is a flat dictionary line, so
/// the one thing that is raised is unambiguously the answer to the question that was asked; a
/// screen where the result and the near misses are both cards makes the learner compare them.
///
/// The photo is 88 pt — enough to RECOGNISE the word, not enough to study the picture. That happens
/// on the card, which is what the terracotta line under this leaf opens, and the two photos share a
/// [Hero] so the small one becomes the big one instead of being replaced by it.
class SearchResultCard extends StatelessWidget {
  const SearchResultCard({
    super.key,
    required this.subject,
    required this.onOpen,
    this.showTransliteration = false,
  });

  final WordCardSubject subject;
  final VoidCallback onOpen;

  /// «Подсказка произношения», passed in rather than watched: this leaf stays a dumb drawing of
  /// the subject, and the screen above it reads the one provider — the same one the word card
  /// reads, so the hint cannot be on in one place and off in the other.
  final bool showTransliteration;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // A freshly looked-up word simply has no hint to show — it is not a term yet.
    final transliteration = showTransliteration ? (subject.transliteration ?? '') : '';
    // Pinned reading first, alternatives after it — «цель / задача». One reading prints as before.
    final translation = subject.readings.join(' / ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Semantics(
          button: true,
          label: subject.text,
          child: InkWell(
            onTap: onOpen,
            borderRadius: BorderRadius.circular(AppRadii.sheet),
            child: Container(
              padding: const EdgeInsets.all(AppSpacing.s16),
              decoration: BoxDecoration(
                color: AppColors.surfaceRaised,
                borderRadius: BorderRadius.circular(AppRadii.sheet),
                border: Border.all(color: AppColors.hairline),
              ),
              child: Row(
                children: [
                  _Thumb(subject: subject),
                  const SizedBox(width: AppSpacing.s16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Flexible(
                              child: Text(
                                subject.text,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: AppText.searchRowTerm.copyWith(
                                  fontSize: 29,
                                  letterSpacing: -0.29,
                                ),
                              ),
                            ),
                            if ((subject.cefr ?? '').isNotEmpty) ...[
                              const SizedBox(width: 10),
                              _OutlineLevel(subject.cefr!),
                            ],
                          ],
                        ),
                        if ((subject.transcription ?? '').isNotEmpty ||
                            transliteration.isNotEmpty) ...[
                          const SizedBox(height: AppSpacing.s4),
                          Row(
                            children: [
                              if ((subject.transcription ?? '').isNotEmpty)
                                Flexible(
                                  child: Text(
                                    '/${subject.transcription}/',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: AppText.transcription,
                                  ),
                                ),
                              if (transliteration.isNotEmpty) ...[
                                if ((subject.transcription ?? '').isNotEmpty)
                                  const SizedBox(width: AppSpacing.s8),
                                Flexible(
                                  child: Text(
                                    '[$transliteration]',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: AppText.transliteration,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ],
                        if (translation.isNotEmpty) ...[
                          const SizedBox(height: 7),
                          Text(
                            translation,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: AppText.translation.copyWith(fontSize: 16, color: AppColors.ink),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.s12),
        Semantics(
          button: true,
          child: InkWell(
            onTap: onOpen,
            child: SizedBox(
              height: 46,
              child: Center(
                child: Text(
                  l.searchOpenCard,
                  style: AppTextExercise.answerAuxButton.copyWith(
                    color: AppColors.destructiveText,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({required this.subject});

  final WordCardSubject subject;

  /// The plate a word with no photo gets: the warm ground with its own INITIAL on it.
  ///
  /// A bare plate was read on the phone as «the picture failed to load», and that reading is fair —
  /// an empty rectangle where a photo goes looks like a photo that did not arrive. Nothing is
  /// arriving: a word that has just been looked up has no photo at all (Pexels runs after the word
  /// is saved and enriched), so this is a permanent state and it has to look deliberate. A monogram
  /// does; a hole does not. Colour is [AppColors.plateLabel], which the theme already defines for
  /// exactly this — type sitting ON the plate rather than on paper.
  Widget _monogram() => ColoredBox(
    color: AppColors.photoPlate,
    child: Center(
      child: Text(
        subject.text.characters.take(1).toString().toUpperCase(),
        style: AppText.searchRowTerm.copyWith(
          fontSize: AppWordCard.inlinePhoto * 0.42,
          color: AppColors.plateLabel,
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    Widget plate = _monogram();
    if (subject.hasPhoto) {
      plate = Image(
        image: CachedNetworkImage(subject.imageUrl!),
        fit: BoxFit.cover,
        // A url that IS there and fails — offline, an evicted cache, a dead host — lands on the same
        // monogram rather than on an empty plate: the learner cannot act on the difference.
        errorBuilder: (_, _, _) => _monogram(),
      );
    }

    return SizedBox(
      width: AppWordCard.inlinePhoto,
      height: AppWordCard.inlinePhoto,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: subject.termId == null
            ? plate
            : Hero(tag: WordCardScreen.heroTag(subject.termId), child: plate),
      ),
    );
  }
}

/// The level beside the term in the result leaf — an outline mark, unlike the filled badge the
/// word card carries: here it is a fact about the row, there it is part of the headword.
class _OutlineLevel extends StatelessWidget {
  const _OutlineLevel(this.level);

  final String level;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(5),
      border: Border.all(color: AppColors.track),
    ),
    child: Text(level, style: AppText.levelMark.copyWith(color: AppColors.secondary)),
  );
}
