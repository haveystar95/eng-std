import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart';
import 'window_bits.dart';

/// THE «DIALOGUE» TAB (canvas 23-0d): exchanges 12 apart, the two bubbles of one exchange 8 apart. The partner
/// is a paper bubble at the left edge with a 28 «listen» button 10 to its right; the learner is a dark bubble
/// at the right edge and carries the state marker. Both lines have «listen» (DAY-UI-3: every line is voiced
/// by its own speaker); on the learner's line the button sits left of the bubble, the marker left of it.
///
/// The order inside an exchange follows its kind (SESSION-1b′, item 9): in an `answer` the partner speaks
/// first, in an `ask` and a `rescue` the learner does.
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
        ..._exchange(pair),
      ],
    ],
  );

  List<Widget> _exchange(WindowPair pair) {
    final partner = pair.partner == null ? null : _PartnerRow(line: pair.partner!, onListen: onListen);
    final learner = pair.learner == null ? null : _LearnerRow(line: pair.learner!, onListen: onListen);
    final rows = (pair.learnerFirst ? [learner, partner] : [partner, learner]).nonNulls.toList();

    return [
      for (final (i, row) in rows.indexed) ...[
        if (i > 0) const SizedBox(height: 8),
        row,
      ],
    ];
  }
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
        padding: const EdgeInsets.only(top: 12),
        child: WindowListenButton(onTap: () => onListen(line.text, line.audioUrl)),
      ),
    ],
  );
}

class _LearnerRow extends StatelessWidget {
  const _LearnerRow({required this.line, required this.onListen});

  final WindowLine line;
  final WindowListen onListen;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.end,
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (line.state case final state?) ...[
        Padding(padding: const EdgeInsets.only(top: 19), child: WindowUnitMarker(state: state)),
        const SizedBox(width: 10),
      ],
      Padding(
        padding: const EdgeInsets.only(top: 12),
        child: WindowListenButton(onTap: () => onListen(line.text, line.audioUrl)),
      ),
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
