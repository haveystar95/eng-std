import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'session_bits.dart';

/// THE LINE SHEET (canvas component «Лист 35-3», work order SESSION-1c): a plate (#EFEBE3, 208 high; 288 in 34-6) with
/// the wave while the line is closed or the line itself once it opens, «listen» 44 in the plate's bottom-right corner;
/// under the plate, in a 20 field, the eyebrow («Line»), a meta line («text hidden») and whatever the card adds (the
/// translation after the pass, the «slowly» button). Frames 34-6, 34-7, 35-3, 35-4.
class SessionLineSheet extends StatelessWidget {
  const SessionLineSheet({
    super.key,
    required this.plate,
    required this.eyebrow,
    this.listen,
    this.meta,
    this.below,
    this.plateHeight = 208,
    this.revealed = false,
  });

  /// The plate's content — [SessionPlateWave] or the line's text.
  final Widget plate;
  final String eyebrow;
  final Widget? listen;
  final String? meta;
  final Widget? below;
  final double plateHeight;

  /// The plate holds text — it is laid from the left and grows with the text instead of centring a wave.
  final bool revealed;

  @override
  Widget build(BuildContext context) => SessionSheet(
    padding: EdgeInsets.zero,
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ColoredBox(
          color: AppColors.ground,
          child: Stack(
            children: [
              Container(
                key: const ValueKey('line-sheet-plate'),
                constraints: BoxConstraints(minHeight: plateHeight),
                padding: EdgeInsets.fromLTRB(20, 20, 20, listen == null ? 20 : 68),
                alignment: revealed ? Alignment.centerLeft : Alignment.center,
                child: AnimatedSwitcher(
                  duration: AppMotion.sessionTextReveal,
                  switchInCurve: AppMotion.sessionEaseOut,
                  child: KeyedSubtree(key: ValueKey(revealed), child: plate),
                ),
              ),
              if (listen != null) Positioned(right: 16, bottom: 16, child: listen!),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SessionEyebrow(eyebrow),
              if (meta != null) ...[const SizedBox(height: 4), Text(meta!, key: const ValueKey('line-sheet-meta'), style: AppTextSession.meta)],
              ?below,
            ],
          ),
        ),
      ],
    ),
  );
}

/// The wave 80 × 24 in the middle of a plate — it moves only while [playing].
class SessionPlateWave extends StatelessWidget {
  const SessionPlateWave({super.key, required this.playing});

  final bool playing;

  @override
  Widget build(BuildContext context) => SessionWave(heights: SessionWave.five, width: 80, playing: playing);
}
