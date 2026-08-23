import 'package:flutter/painting.dart';

/// National-flag colours for [MiniFlag] (`tokens.html` §4б). Rule 14: mini-flags
/// are the ONLY decorative colour in the interface — so these live here, in the
/// theme, rather than as raw hex scattered through `lib/ui/`. Values mirror the
/// token frames' simplified renderings.
abstract final class FlagPalette {
  // en · Великобритания (Union Jack: тёмно-синее поле, белый+красный косой
  // крест, поверх — белый+красный прямой)
  static const gbNavy = Color(0xFF1A3A6B);
  static const gbWhite = Color(0xFFFFFFFF);
  static const gbRed = Color(0xFFC8102E);

  // pt · Португалия
  static const ptGreen = Color(0xFF046A38);
  static const ptRed = Color(0xFFDA291C);
  static const ptYellow = Color(0xFFFFE900);

  // de · Германия
  static const deBlack = Color(0xFF1A1A1A);
  static const deRed = Color(0xFFDD0000);
  static const deGold = Color(0xFFFFCE00);

  // es · Испания
  static const esRed = Color(0xFFAA151B);
  static const esGold = Color(0xFFF1BF00);

  // fr · Франция
  static const frBlue = Color(0xFF0055A4);
  static const frWhite = Color(0xFFFFFFFF);
  static const frRed = Color(0xFFEF4135);

  // ro · Румыния (кобальт / хромовый жёлтый / киноварь — вертикальный триколор,
  // как у Франции, поэтому и рисуется тем же painter'ом)
  static const roBlue = Color(0xFF002B7F);
  static const roYellow = Color(0xFFFCD116);
  static const roRed = Color(0xFFCE1126);
}
