import 'package:flutter/painting.dart';

/// Elevation tokens — cards carry warm shadows instead of borders (rule 13).
/// Offsets/blur from `tokens.html` §1 (card), §3 (pill glass), §4в (menu).
abstract final class AppShadows {
  static const int _inkR = 46, _inkG = 38, _inkB = 32; // #2E2620

  /// Слоёная бумага (PaperCard): без рамки — двойная тёплая тень.
  /// `0 2 8 rgba(46,38,32,.04)` + `0 12 28 rgba(46,38,32,.07)`.
  static const card = <BoxShadow>[
    BoxShadow(color: Color.fromARGB(10, _inkR, _inkG, _inkB), blurRadius: 8, offset: Offset(0, 2)),
    BoxShadow(color: Color.fromARGB(18, _inkR, _inkG, _inkB), blurRadius: 28, offset: Offset(0, 12)),
  ];

  /// Плавающее контекстное меню (§4в).
  /// `0 4 12 rgba(46,38,32,.10)` + `0 22 48 rgba(46,38,32,.26)`.
  static const menu = <BoxShadow>[
    BoxShadow(color: Color.fromARGB(26, _inkR, _inkG, _inkB), blurRadius: 12, offset: Offset(0, 4)),
    BoxShadow(color: Color.fromARGB(66, _inkR, _inkG, _inkB), blurRadius: 48, offset: Offset(0, 22)),
  ];

  /// Элемент, поднятый над scrim у контекстного меню (якорь).
  /// `0 8–10 30 rgba(46,38,32,.30)`.
  static const anchor = <BoxShadow>[
    BoxShadow(color: Color.fromARGB(77, _inkR, _inkG, _inkB), blurRadius: 30, offset: Offset(0, 9)),
  ];

  /// Плавающая таб-пилюля (§3, «Стекло пилюли»).
  /// `0 12 32 rgba(60,50,40,.16)`.
  static const pill = <BoxShadow>[
    BoxShadow(color: Color.fromARGB(41, 60, 50, 40), blurRadius: 32, offset: Offset(0, 12)),
  ];
}
