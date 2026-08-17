import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/practice/learning_ladder.dart';

/// The expanded word card (кадр 16e): the same five dots the row shows, captioned and joined by a
/// line, plus the example, the accepted variants and one action.
///
/// It exists because the row deliberately cannot explain itself — five unlabelled dots are the right
/// density for a list of twenty words and the wrong density for «what does this mean about my word».
/// One tap opens the labels; nothing else changes.
Future<void> showWordLadderSheet({
  required BuildContext context,
  required Word word,
  required VoidCallback onSpeak,
  required VoidCallback onTrain,
}) {
  return showAppBottomSheet<void>(
    context: context,
    builder: (context) => _WordLadderSheet(word: word, onSpeak: onSpeak, onTrain: onTrain),
  );
}

class _WordLadderSheet extends StatelessWidget {
  const _WordLadderSheet({required this.word, required this.onSpeak, required this.onTrain});

  final Word word;
  final VoidCallback onSpeak;
  final VoidCallback onTrain;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final example = word.example;
    final trainable = LearningLadder.admitsPractice(word.ladderStep);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Flexible(child: Text(word.term, style: AppTextExercise.introTerm)),
            const SizedBox(width: AppSpacing.s8),
            _SpeakButton(onTap: onSpeak),
          ],
        ),
        if ((word.transcription ?? '').isNotEmpty) ...[
          const SizedBox(height: 2),
          Text('/${word.transcription}/', style: AppText.transcription),
        ],
        const SizedBox(height: AppSpacing.s4),
        Text(word.translation, style: AppTextExercise.introTranslation),
        const SizedBox(height: AppSpacing.s16),
        const Divider(height: 1, color: AppColors.hairline),
        const SizedBox(height: AppSpacing.s16),
        // A «знаю» word has no rung to caption: the ladder is not where it lives. Saying so in one
        // line is honest; drawing five captioned dots for it would not be.
        if (word.isKnown)
          Row(
            children: [
              LadderKnownDash(label: l.ladderKnownDash),
            ],
          )
        else ...[
          Text(l.ladderTitle, style: AppText.ladderTitle),
          const SizedBox(height: AppSpacing.s12),
          LadderTrack(
            step: word.ladderStep,
            labels: [l.ladderStep0, l.ladderStep1, l.ladderStep3, l.ladderStep4, l.ladderStep5],
          ),
        ],
        if (example != null && example.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.s22),
          Text(example, style: AppTextExercise.introExample),
        ],
        const SizedBox(height: AppSpacing.s22),
        // A word still at rung 0 has not been introduced, and practice introduces nothing — so the
        // action is inert rather than absent: an option that vanishes reads as a bug, one that is
        // greyed out with a reason reads as a rule. Same gate the session builder applies
        // ([LearningLadder.admitsPractice]) — asked here so the button cannot promise a session
        // that would come back empty.
        PrimaryButton(
          label: l.ladderTrainWord,
          enabled: trainable,
          onPressed: () {
            Navigator.of(context).maybePop();
            onTrain();
          },
        ),
        if (!trainable) ...[
          const SizedBox(height: AppSpacing.s8),
          Text(
            l.ladderTrainLockedIntro,
            textAlign: TextAlign.center,
            style: AppText.ladderLockedNote,
          ),
        ],
      ],
    );
  }
}

class _SpeakButton extends StatelessWidget {
  const _SpeakButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: onTap,
        radius: 24,
        child: Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.hairline),
          ),
          child: const Icon(LucideIcons.volume2, size: 16, color: AppColors.ink),
        ),
      ),
    );
  }
}
