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

  // pl · Польша (белая полоса сверху, «червонная» снизу)
  static const plWhite = Color(0xFFFFFFFF);
  static const plRed = Color(0xFFDC143C);

  // it · Италия (тот же вертикальный триколор, что у Франции, другие краски)
  static const itGreen = Color(0xFF008C45);
  static const itWhite = Color(0xFFF4F5F0);
  static const itRed = Color(0xFFCD212A);

  // ru · Россия (горизонтальный триколор)
  static const ruWhite = Color(0xFFFFFFFF);
  static const ruBlue = Color(0xFF0039A6);
  static const ruRed = Color(0xFFD52B1E);

  // uk · Украина (лазурь / золото)
  static const uaBlue = Color(0xFF0057B7);
  static const uaYellow = Color(0xFFFFD700);

  // tr · Турция (алое поле, белые полумесяц и звезда)
  static const trRed = Color(0xFFE30A17);
  static const trWhite = Color(0xFFFFFFFF);

  // zh · Китай (алое поле, жёлтые звёзды)
  static const cnRed = Color(0xFFDE2910);
  static const cnYellow = Color(0xFFFFDE00);

  // ja · Япония (белое поле, алый круг)
  static const jpWhite = Color(0xFFFFFFFF);
  static const jpRed = Color(0xFFBC002D);

  // ro · Румыния (кобальт / хромовый жёлтый / киноварь — вертикальный триколор,
  // как у Франции, поэтому и рисуется тем же painter'ом)
  static const roBlue = Color(0xFF002B7F);
  static const roYellow = Color(0xFFFCD116);
  static const roRed = Color(0xFFCE1126);
}
