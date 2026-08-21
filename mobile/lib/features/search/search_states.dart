import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// Кадр 05 — «идёт сборка».
///
/// Instead of a spinner: the card that is being written, writing itself. What is already known — the
/// word, and the grey hint's translation when there is one — is set immediately and stays put; the
/// rest of the article fills in top to bottom.
///
/// HONESTY, because this is the easy place to lie. There is exactly ONE model call and no streaming,
/// so the app cannot know that «значение» is finished before «пример» is. Therefore nothing is ever
/// TICKED before the answer lands: a row ahead of the wave carries a pale ring, the row the wave is
/// on carries an ink ring, and every ring becomes a tick at the same moment — when the response
/// actually arrives. The wave is the real elapsed time of the request and nothing else, and if the
/// request is slow the wave simply stops on the last row instead of finishing without it.
class AssemblingCard extends StatefulWidget {
  const AssemblingCard({
    super.key,
    required this.term,
    this.transcription,
    this.translation,
  });

  final String term;

  /// Known before the call only if it was typed — usually null, and the line is then simply absent.
  final String? transcription;

  /// The grey hint's answer, when one arrived. Real content, so its row ticks straight away.
  final String? translation;

  /// How long the wave takes to reach the next row. Slow enough to read, fast enough that a typical
  /// two-second answer sees the whole card move.
  static const step = Duration(milliseconds: 620);

  @override
  State<AssemblingCard> createState() => _AssemblingCardState();
}

class _AssemblingCardState extends State<AssemblingCard> {
  Timer? _timer;
  int _wave = 0;

  static const _rows = 3;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(AssemblingCard.step, (t) {
      // The wave stops on the LAST row and waits there. Letting it run off the end would be the
      // fake-progress bar this whole frame exists to avoid.
      if (_wave >= _rows - 1) {
        t.cancel();

        return;
      }
      setState(() => _wave++);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.disableAnimationsOf(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 2,
          child: reduce
              ? const ColoredBox(color: AppColors.track)
              : const LinearProgressIndicator(
                  minHeight: 2,
                  backgroundColor: AppColors.track,
                  valueColor: AlwaysStoppedAnimation(AppColors.ink),
                ),
        ),
        const SizedBox(height: AppSpacing.s26),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: AppWordCard.inlinePhoto,
              height: AppWordCard.inlinePhoto,
              decoration: BoxDecoration(
                color: AppColors.photoPlate,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            const SizedBox(width: AppSpacing.s16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.term,
                    style: AppText.searchRowTerm.copyWith(fontSize: 32, letterSpacing: -0.32),
                  ),
                  if ((widget.transcription ?? '').isNotEmpty) ...[
                    const SizedBox(height: 5),
                    Text('/${widget.transcription}/', style: AppText.transcription),
                  ],
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.s26),
        _row(0, l.searchBuildTranslation, widget.translation),
        _row(1, l.searchBuildMeaning, null),
        _row(2, l.searchBuildExample, null, last: true),
        const SizedBox(height: AppSpacing.s22),
        Text(l.searchBuildNote, style: AppText.searchNote),
      ],
    );
  }

  Widget _row(int index, String label, String? value, {bool last = false}) {
    final done = value != null && value.isNotEmpty;
    final reached = index <= _wave;

    return Container(
      height: 46,
      decoration: last
          ? null
          : const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.dividerFaint))),
      child: Row(
        children: [
          SizedBox(
            width: 15,
            height: 15,
            child: done
                ? const Icon(LucideIcons.check, size: 15, color: AppColors.verdictKnown)
                : DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: reached ? AppColors.ink : AppColors.track,
                        width: 1.6,
                      ),
                    ),
                  ),
          ),
          const SizedBox(width: AppSpacing.s12),
          Text(
            label,
            style: AppText.translation.copyWith(color: reached ? AppColors.ink : AppColors.tertiary),
          ),
          const Spacer(),
          if (done)
            Flexible(
              child: Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.right,
                style: AppText.translation.copyWith(color: AppColors.inkBody),
              ),
            )
          else
            // A pale bar in the value's place: the shape of what is coming, without a claim about
            // what it will say.
            Container(
              width: index.isEven ? 120 : 74,
              height: 9,
              decoration: BoxDecoration(
                color: AppColors.dividerFaint,
                borderRadius: BorderRadius.circular(5),
              ),
            ),
        ],
      ),
    );
  }
}

/// Кадр 08 — the day's model calls are spent.
///
/// Not one red pixel and not one «вы израсходовали». Five grey dots state the fact, then the card
/// says WHEN it comes back and what still works meanwhile. The mockup's «Отложить на завтра» and
/// «Без лимита — в подписке» are deliberately absent: neither exists in this app, and a button that
/// promises a queue nobody implemented is worse than no button.
class AiLimitCard extends StatelessWidget {
  const AiLimitCard({super.key, required this.query, required this.used, required this.cap});

  final String query;
  final int used;
  final int cap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final dots = cap > 0 && cap <= 8 ? cap : 5;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l.searchMissTitle(query), style: AppText.searchMissTitle),
        const SizedBox(height: AppSpacing.s22),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: AppColors.surfaceRaised,
            borderRadius: BorderRadius.circular(AppRadii.sheet),
            border: Border.all(color: AppColors.hairline),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  for (var i = 0; i < dots; i++) ...[
                    if (i > 0) const SizedBox(width: AppSpacing.s4),
                    Container(
                      width: 7,
                      height: 7,
                      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.track),
                    ),
                  ],
                  const SizedBox(width: 10),
                  Text(
                    l.searchLimitUsed(used > 0 ? used : dots, cap > 0 ? cap : dots),
                    style: AppText.translation.copyWith(fontSize: 13),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.s12),
              Text(l.searchLimitTitle,
                  style: AppText.searchMissTitle.copyWith(fontSize: 19, height: 1.35)),
              const SizedBox(height: AppSpacing.s8),
              Text(l.searchLimitBody, style: AppText.searchMissBody.copyWith(fontSize: 14)),
            ],
          ),
        ),
      ],
    );
  }
}
