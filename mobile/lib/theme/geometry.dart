import 'package:flutter/painting.dart';

/// Geometry tokens — radii, spacing and component metrics.
/// Values from `tokens.html` §3 and §4г. Base grid = 4 (steps 4/8/12/16/22/26).

/// Corner radii.
abstract final class AppRadii {
  static const thumb = 6.0; // фото-миниатюра, обложка в сетке (4–6)
  static const small = 14.0; // мелкая кнопка («Повторить» в ошибке, «Взять»)
  static const field = 18.0; // поле ввода, кнопка-иконка (16–18)
  static const button = 20.0; // главная кнопка (18–20)
  static const alert = 22.0; // центральный alert (ширина 274)
  static const card = 24.0; // карточка генерации, флип-карточка
  static const menu = 20.0; // карточка контекстного меню (§4в)
  static const sheetTop = 28.0; // bottom sheet — верхние углы
  static const chip = 26.0; // чип (24–26)
  static const pill = 28.0; // таб-пилюля, чип-пилюля
}

/// Spacing on the base-4 grid.
abstract final class AppSpacing {
  static const s4 = 4.0;
  static const s8 = 8.0;
  static const s12 = 12.0;
  static const s16 = 16.0;
  static const s22 = 22.0;
  static const s26 = 26.0;

  /// Горизонтальные поля экрана (вход/онбординг — [screenHWide]).
  static const screenH = 22.0;
  static const screenHWide = 28.0; // 26–30

  /// Внутренний отступ контейнера — единый токен; содержимое его не пересекает.
  static const cardPadding = 15.0; // карточка
  static const menuPadding = 16.0; // меню, шит

  /// Лейбл секции → контент (7–8); перед лейблом (16–20).
  static const labelToContent = 8.0;
  static const beforeLabel = 18.0;

  /// Интервал между секциями: плотная главная — 9, свободные экраны — 13.
  static const sectionDense = 9.0;
  static const sectionAiry = 13.0;

  /// Строка списка слов.
  static const wordRowPadV = 11.0;
  static const wordThumb = 46.0;
  static const wordRowGap = 12.0;

  /// Минимальная зона тапа.
  static const minTap = 44.0;
}

/// Плавающая таб-пилюля (§3). Контент уходит под неё с нижним отступом.
abstract final class AppTabBarMetrics {
  static const height = 54.0;
  static const padH = 10.0;
  static const padV = 9.0;
  static const item = 62.0;
  static const bottomInset = 22.0;
  static const blur = 22.0;
  static const saturate = 1.5;

  /// Стекло пилюли — `rgba(246,243,236,.78)`.
  static const glass = Color.fromARGB(199, 246, 243, 236);
}

/// Линия прогресса (§4б).
abstract final class AppProgress {
  static const heightCard = 3.0; // карточка
  static const heightScreen = 8.0; // экран коллекции (6–8)
  static const fillMinWidth = 8.0; // малый прогресс не исчезает
}
