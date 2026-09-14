import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';
import 'window_phrases.dart' show WindowListen;

/// ВКЛАДКА «ДИАЛОГ» (кадр 23-0d): пары пузырей. Собеседник — бумага у левой кромки и «прослушать»
/// 28 справа от пузыря; ученик — тёмный пузырь справа, и маркер состояния стоит у НЕГО, а не у
/// собеседника: состояние у своей реплики, голос у чужой.
class WindowDialogue extends StatelessWidget {
  const WindowDialogue({super.key, required this.pairs, required this.onListen});

  final List<WindowPair> pairs;
  final WindowListen onListen;

  static const bubbleMax = 260.0;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final (i, pair) in pairs.indexed) ...[
        if (i > 0) const SizedBox(height: 12),
        if (pair.partner case final partner?) _PartnerRow(line: partner, onListen: onListen),
        if (pair.partner != null && pair.learner != null) const SizedBox(height: 8),
        if (pair.learner case final learner?) _LearnerRow(line: learner),
      ],
    ],
  );
}

class _PartnerRow extends StatelessWidget {
  const _PartnerRow({required this.line, required this.onListen});

  final WindowLine line;
  final WindowListen onListen;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Flexible(
        child: _Bubble(line: line, fill: AppColors.paper, text: AppTextWindow.target, translation: AppTextWindow.translation),
      ),
      const SizedBox(width: 10),
      Padding(
        padding: const EdgeInsets.only(top: 10),
        child: PlayCircle(
          size: 28,
          label: AppLocalizations.of(context).planWindowListen,
          onTap: () => onListen(line.text, line.audioUrl, partner: true),
        ),
      ),
    ],
  );
}

class _LearnerRow extends StatelessWidget {
  const _LearnerRow({required this.line});

  final WindowLine line;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.end,
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (line.state case final state?)
        Padding(padding: const EdgeInsets.only(top: 17), child: WindowUnitMarker(state: state)),
      const SizedBox(width: 10),
      Flexible(
        child: _Bubble(
          line: line,
          fill: AppColors.windowInk,
          text: AppTextWindow.target.copyWith(color: AppColors.paper),
          translation: AppTextWindow.translation.copyWith(color: AppColors.paper72),
        ),
      ),
    ],
  );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.line, required this.fill, required this.text, required this.translation});

  final WindowLine line;
  final Color fill;
  final TextStyle text;
  final TextStyle translation;

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: const BoxConstraints(maxWidth: WindowDialogue.bubbleMax),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(color: fill, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(line.text, style: text),
          if (line.translation.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(line.translation, style: translation),
          ],
        ],
      ),
    ),
  );
}
