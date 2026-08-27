import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import 'word_challenge.dart';

/// СЛОВО-ВЫЗОВ, кадр 19-4 — the one thing on the home screen a learner can touch without starting
/// anything, and the counter that brings them back at noon.
///
/// Light, with a hairline outline, and no large type: «один акцент на экран» — the dark plate is the
/// thing to press first, and a challenge that competed with it would be a game where a lesson
/// should be.
///
/// It takes a [WordChallenge] and knows nothing about where it came from. That is the seam DAILY-1
/// walks through: the server will pick the word by level and add «73% ответили верно», and this file
/// will not change.
class WordChallengeCard extends StatelessWidget {
  const WordChallengeCard({
    super.key,
    required this.challenge,
    required this.onAnswer,
    required this.onLearn,
    required this.onTomorrow,
    this.enrolled = false,
  });

  final WordChallenge challenge;
  final void Function(String option) onAnswer;
  final VoidCallback onLearn;
  final VoidCallback onTomorrow;

  /// «Учить» has already been pressed for this word — the button says so and stops being a button.
  final bool enrolled;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    // кадр 19-4г: once «Завтра новое» is pressed the card is a line, in the same place and the same
    // typography as «Завтра выпадет N слов». The screen neither collapses into emptiness nor keeps
    // a dead block on it.
    if (challenge.collapsed) {
      return MinTapHeight(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  l.challengeCollapsed,
                  style: AppText.translation.copyWith(fontSize: 14, color: AppColors.tertiary),
                ),
              ),
              const Icon(LucideIcons.arrowRight, size: 15, color: AppColors.tertiary),
            ],
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
      decoration: BoxDecoration(
        color: AppColors.surfaceRaised,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _header(l),
          if (!challenge.answered) ..._question(l) else ..._revealed(l),
        ],
      ),
    );
  }

  /// The label with its brass target, and the run on the right.
  ///
  /// The counter is the ONE number on the card and it sits right so it does not read as a heading.
  /// At 0 it is absent: «угадано 0 подряд» is a way of saying «you have no run», which is a thing to
  /// leave unsaid rather than to print.
  Widget _header(AppLocalizations l) {
    // A miss says so; anything else shows the run, and a run of 0 says nothing at all.
    final String? right = switch (challenge) {
      WordChallenge(answered: true, isCorrect: false) => l.challengeStreakReset,
      _ when challenge.streak > 0 => l.challengeStreak(challenge.streak),
      _ => null,
    };

    return Row(
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Expanded(
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(LucideIcons.target, size: 14, color: AppColors.brass),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  l.challengeLabel.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppText.sectionLabel,
                ),
              ),
            ],
          ),
        ),
        if (right != null) ...[
          const SizedBox(width: AppSpacing.s8),
          Text(
            right,
            style: AppText.translation.copyWith(fontSize: 12, color: AppColors.tertiary),
          ),
        ],
      ],
    );
  }

  /// кадр 19-4а — the word, then three options.
  ///
  /// A ROW of three is canonical and fits 390 pt at 13 pt; the column is the fallback the frame's own
  /// note describes, and it triggers on the LONGEST option rather than on a guess about the language.
  List<Widget> _question(AppLocalizations l) {
    final stacked = challenge.options.any((o) => o.length > _kInlineOption);

    return [
      const SizedBox(height: 11),
      Text(
        challenge.text,
        style: AppText.stepTitle.copyWith(fontSize: 24, height: 1.1),
      ),
      const SizedBox(height: 13),
      if (stacked)
        Column(
          children: [
            for (final option in challenge.options) ...[
              if (option != challenge.options.first) const SizedBox(height: 8),
              _Option(label: option, stacked: true, onTap: () => onAnswer(option)),
            ],
          ],
        )
      else
        Row(
          children: [
            for (final option in challenge.options) ...[
              if (option != challenge.options.first) const SizedBox(width: 7),
              Expanded(child: _Option(label: option, onTap: () => onAnswer(option))),
            ],
          ],
        ),
    ];
  }

  /// кадры 19-4б and 19-4в — the same card with the answer on it.
  ///
  /// The two states differ by ONE line: the praise on a hit, the explanation of the miss on a miss.
  /// Everything else — the answer, the example, both buttons — is identical, so that getting it
  /// wrong does not look like a special scenario the app has feelings about. There is no red and no
  /// «неверно» anywhere.
  List<Widget> _revealed(AppLocalizations l) {
    final example = _example(l);
    final mistake = _mistake(l);

    return [
      if (challenge.isCorrect) ...[
        const SizedBox(height: 11),
        Row(
          children: [
            Container(
              width: 24,
              height: 24,
              alignment: Alignment.center,
              decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
              child: const Icon(LucideIcons.check, size: 13, color: AppColors.paper),
            ),
            const SizedBox(width: 9),
            Text(l.challengePraise, style: AppText.stepTitle.copyWith(fontSize: 22)),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          l.challengeAnswer(challenge.text, challenge.translation),
          style: AppText.stepTitle.copyWith(fontSize: 18),
        ),
      ] else ...[
        const SizedBox(height: 11),
        Text(
          l.challengeAnswer(challenge.text, challenge.translation),
          style: AppText.stepTitle.copyWith(fontSize: 22, height: 1.15),
        ),
      ],
      // WHERE «73% ответили верно» GOES. The stub has no such number — nobody is counting — and a
      // percentage invented on the device would be the one lie on a card whose whole job is to be
      // checkable. The gap is reserved so the line lands here without moving anything when DAILY-1
      // brings it from the server.
      const SizedBox(height: 6),
      if (example != null)
        Text(
          example,
          style: AppText.translation.copyWith(
            fontSize: 13.5,
            height: 1.5,
            color: AppColors.secondary,
          ),
        ),
      if (mistake != null) ...[
        const SizedBox(height: 14),
        Container(
          padding: const EdgeInsets.only(top: 14),
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: AppColors.dividerFaint)),
          ),
          child: Text(
            mistake,
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.tertiary,
            ),
          ),
        ),
      ],
      const SizedBox(height: AppSpacing.s16),
      Row(
        children: [
          Expanded(
            child: _ChallengeButton(
              label: enrolled ? l.challengeLearning : l.challengeLearn,
              outlined: true,
              onTap: enrolled ? null : onLearn,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(child: _ChallengeButton(label: l.challengeTomorrow, onTap: onTomorrow)),
        ],
      ),
    ];
  }

  /// «He was reluctant to leave — Он неохотно уходил», or the sentence alone when the mirror has no
  /// translation for it. Null when there is no sentence — and then nothing is drawn.
  String? _example(AppLocalizations l) {
    final sentence = challenge.example?.trim();
    if (sentence == null || sentence.isEmpty) return null;
    final translation = challenge.exampleTranslation?.trim();

    return (translation == null || translation.isEmpty)
        ? sentence
        : l.challengeExample(sentence, translation);
  }

  /// «Вы выбрали „надёжный" — это reliable».
  ///
  /// Only when the chosen option is a translation of a term this device can name. The stub builds
  /// its wrong options out of other terms of the same pair, so it almost always can — but «almost»
  /// is why this returns null instead of printing half a sentence.
  String? _mistake(AppLocalizations l) {
    if (challenge.isCorrect) return null;
    final chosen = challenge.chosen;
    final owner = chosen == null ? null : challenge.optionOwners[chosen];

    return (chosen == null || owner == null) ? null : l.challengeMistake(chosen, owner);
  }
}

/// The longest option that still fits a row of three at 390 pt. Beyond it the block falls into a
/// column — the frame's own rule, and it is about WIDTH, so it counts characters and not words.
const int _kInlineOption = 13;

class _Option extends StatelessWidget {
  const _Option({required this.label, required this.onTap, this.stacked = false});

  final String label;
  final VoidCallback onTap;
  final bool stacked;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(13),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          // 44 pt — the minimum tap target, and the frame's own height for these.
          height: 44,
          alignment: stacked ? Alignment.centerLeft : Alignment.center,
          padding: EdgeInsets.symmetric(horizontal: stacked ? 16 : 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: AppColors.track),
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: stacked ? TextAlign.left : TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: stacked ? 14.5 : 13,
              fontWeight: FontWeight.w600,
              color: AppColors.inkBody,
            ),
          ),
        ),
      ),
    );
  }
}

/// The card's two buttons: «Учить» outlined and heavier, «Завтра новое» flat and quiet. Same size,
/// because they are two ordinary choices and not an action beside an escape.
class _ChallengeButton extends StatelessWidget {
  const _ChallengeButton({required this.label, required this.onTap, this.outlined = false});

  final String label;
  final VoidCallback? onTap;
  final bool outlined;

  @override
  Widget build(BuildContext context) {
    final enabled = onTap != null;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(13),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(13),
            border: outlined
                ? Border.all(color: enabled ? AppColors.dashed : AppColors.hairline, width: 1.5)
                : null,
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              fontWeight: outlined ? FontWeight.w700 : FontWeight.w600,
              color: outlined
                  ? (enabled ? AppColors.ink : AppColors.tertiary)
                  : AppColors.tertiary,
            ),
          ),
        ),
      ),
    );
  }
}
