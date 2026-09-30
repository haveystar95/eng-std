import 'package:flutter/widgets.dart';

import 'package:eng_std/theme/theme.dart';

import 'wordmark_metrics.dart';

/// THE LETTERS OF THE WORDMARK «Ritora» at the start screen's size (frame 41-1 after 40-1: Literata 600, opsz 72,
/// 44/48, tracking −0.01em, ink).
///
/// Images, not text: the canvas draws the mark in Literata 600 and the app bundles Literata at 400/500 only, so the
/// letters were cut from the canvas's own rendering (`scripts/brand/render_brand.mjs`). The box is the canvas's text
/// line box — [kWordmarkImageWidth] × [kWordmarkHeight] — so a caller lays it out exactly as the canvas's flex row does.
///
/// [letterOpacity] fades the six letters one by one (the «itora» reveal of 41-1b). Each letter is its own image of the
/// same box — the word split pixel by pixel (`letters_split.swift`) — so a glyph that reaches under its neighbour, like
/// the leg of the «R», is never sliced, and the six at full opacity are exactly the word.
class WordmarkLetters extends StatelessWidget {
  const WordmarkLetters({super.key, this.letterOpacity});

  /// Opacity of «R», «i», «t», «o», «r», «a»; null — the whole word.
  final List<double>? letterOpacity;

  static const word = 'assets/brand/wordmark.png';
  static String letter(int k) => 'assets/brand/wordmark_$k.png';

  static const _box = Size(kWordmarkImageWidth, kWordmarkHeight);

  static Widget _image(String asset) => Image(
    image: AssetImage(asset),
    width: _box.width,
    height: _box.height,
    fit: BoxFit.fill,
    excludeFromSemantics: true,
    gaplessPlayback: true,
  );

  @override
  Widget build(BuildContext context) {
    final opacity = letterOpacity;
    if (opacity == null || opacity.every((o) => o >= 1)) return _image(word);
    return SizedBox.fromSize(
      size: _box,
      child: Stack(
        children: [
          for (var k = 0; k < 6; k++)
            if (opacity[k] > 0) Opacity(opacity: opacity[k].clamp(0.0, 1.0), child: _image(letter(k))),
        ],
      ),
    );
  }
}

/// «R» ALONE — the launch screen's image (LaunchScreen.storyboard shows the same file), so frame 41-1a starts on the
/// very pixels iOS left on screen. The «R» box sits [kRMarkInset] inside it, with the same air on both sides.
class WordmarkR extends StatelessWidget {
  const WordmarkR({super.key});

  static const asset = 'assets/brand/r_mark.png';

  @override
  Widget build(BuildContext context) => const Image(
    image: AssetImage(asset),
    width: kRMarkImageWidth,
    height: kWordmarkHeight,
    fit: BoxFit.fill,
    excludeFromSemantics: true,
    gaplessPlayback: true,
  );
}

/// THE BRASS DOT of the mark — Ø 7 on the baseline at the start screen's size (41-1). Drawn, not cut: it is the part
/// of the mark that moves on its own.
class BrandDot extends StatelessWidget {
  const BrandDot({super.key});

  static const size = 7.0;

  @override
  Widget build(BuildContext context) => const SizedBox.square(
    dimension: size,
    child: DecoratedBox(decoration: BoxDecoration(color: AppColors.brassInk, shape: BoxShape.circle)),
  );
}

/// Geometry of the mark's row (41-1): the word, a gap of 4, the dot 7 whose bottom stands 7 above the line box's.
abstract final class WordmarkGeometry {
  static const gap = 4.0;
  static const dotLift = 7.0;

  /// The dot's top inside the 48-tall row.
  static const dotTop = kWordmarkHeight - dotLift - BrandDot.size;

  /// The whole row «Ritora.» — what the finished mark centres.
  static const rowWidth = kWordmarkWidth + gap + BrandDot.size;

  /// The start screen's column: the row, 8, the slogan's 20-tall line (41-1c).
  static const sloganGap = 8.0;
  static const sloganLine = 20.0;

  /// The canvas centres the column in the screen minus 64 at the bottom (`padding-bottom:64px`).
  static const columnBottomPad = 64.0;

  /// How far the row rises when the slogan joins the column: half of the slogan's 8 + 20.
  static const sloganLift = (sloganGap + sloganLine) / 2;
}
