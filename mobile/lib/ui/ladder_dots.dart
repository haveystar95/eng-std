import 'package:flutter/material.dart';

import '../theme/theme.dart';

/// The word's place on the acquisition ladder, as five dots (кадры 16d/16e).
///
/// Read left to right: what is behind, where the word is now, what is ahead.
///
///  * PASSED — an outline. The rung is done, so the dot is spent: it holds its place without asking
///    for attention.
///  * CURRENT — a filled dot, one pixel larger. The only ink on the row that is solid, which is what
///    makes it findable in a list of twenty words at a glance.
///  * AHEAD — pale. Present, because the shape of the whole ladder is the information; quiet,
///    because none of it has happened yet.
///
/// No labels and no numbers in the list: a row stays a row of the dictionary. The expanded card
/// captions the same five dots ([LadderTrack]), where there is room for the words.
class LadderDots extends StatelessWidget {
  const LadderDots({super.key, required this.step, this.size = 5.5, this.gap = 5});

  /// The rung, 0–5. Null means the word is OUTSIDE the ladder (a triage «знаю»), and the caller
  /// draws the dash instead — see [LadderKnownDash].
  final int? step;

  final double size;
  final double gap;

  /// The rungs the dots stand for. Rung 1 and 2 are both «узнавание» and share one dot: the list is
  /// about how far the word has come, and the direction a recognition asked in is not that.
  static const List<int> rungs = [0, 1, 3, 4, 5];

  /// Which dot is lit for a given rung — rung 2 folds into rung 1's dot.
  static int indexFor(int step) {
    final folded = step == 2 ? 1 : step;
    final at = rungs.indexOf(folded);
    return at < 0 ? 0 : at;
  }

  @override
  Widget build(BuildContext context) {
    final current = step == null ? -1 : indexFor(step!);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 0; i < rungs.length; i++) ...[
          if (i > 0) SizedBox(width: gap),
          _Dot(
            state: i < current
                ? _DotState.passed
                : i == current
                    ? _DotState.current
                    : _DotState.ahead,
            size: size,
          ),
        ],
      ],
    );
  }
}

enum _DotState { passed, current, ahead }

class _Dot extends StatelessWidget {
  const _Dot({required this.state, required this.size});
  final _DotState state;
  final double size;

  @override
  Widget build(BuildContext context) {
    // The current dot is a touch larger, which is what separates it from a passed one at a glance
    // without adding a colour the palette does not have.
    final d = state == _DotState.current ? size + 1 : size;
    return SizedBox(
      width: size + 1,
      height: size + 1,
      child: Center(
        child: Container(
          width: d,
          height: d,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: switch (state) {
              _DotState.current => AppColors.ink,
              _DotState.passed => Colors.transparent,
              _DotState.ahead => AppColors.track,
            },
            border: state == _DotState.passed ? Border.all(color: AppColors.track) : null,
          ),
        ),
      ),
    );
  }
}

/// «— знаю» for a word marked known in triage (кадр 16d).
///
/// A dash, not dots: the word never walked the ladder, and drawing five pale dots for it would say
/// «at the very beginning», which is the opposite of what «знаю» means. The whole row is set paler
/// but stays readable — the word is not deleted, it is simply not in the work.
class LadderKnownDash extends StatelessWidget {
  const LadderKnownDash({super.key, required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 1, color: AppColors.track),
        const SizedBox(width: 6),
        Text(label, style: AppText.ladderDash),
      ],
    );
  }
}

/// The same five dots, captioned and joined by a line — the expanded word card (кадр 16e).
///
/// The line is what the list version cannot afford: the INK part of it is the road already walked,
/// so the ladder reads as a path rather than as five separate marks. The current caption is
/// semibold; the rest are quiet.
class LadderTrack extends StatelessWidget {
  const LadderTrack({super.key, required this.step, required this.labels});

  final int? step;

  /// Captions for [LadderDots.rungs], in order.
  final List<String> labels;

  @override
  Widget build(BuildContext context) {
    final current = step == null ? -1 : LadderDots.indexFor(step!);

    return LayoutBuilder(
      builder: (context, constraints) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 12,
              child: Row(
                children: [
                  for (var i = 0; i < LadderDots.rungs.length; i++) ...[
                    if (i > 0)
                      Expanded(
                        child: Container(
                          height: 1,
                          // Inked up to the current rung, pale after it: the drawn part is the road
                          // already walked.
                          color: i <= current ? AppColors.ink : AppColors.track,
                        ),
                      ),
                    _Dot(
                      state: i < current
                          ? _DotState.passed
                          : i == current
                              ? _DotState.current
                              : _DotState.ahead,
                      size: 7,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.s4),
            Row(
              children: [
                for (var i = 0; i < labels.length; i++)
                  Expanded(
                    child: Align(
                      // First and last captions hug the ends so they don't overhang the card.
                      alignment: i == 0
                          ? Alignment.centerLeft
                          : i == labels.length - 1
                              ? Alignment.centerRight
                              : Alignment.center,
                      child: Text(
                        labels[i],
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: i == current ? AppText.ladderLabelCurrent : AppText.ladderLabel,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }
}
