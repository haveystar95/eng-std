import 'package:flutter/animation.dart';

/// Motion tokens — durations, curves and gesture thresholds.
/// Base interactions from `tokens.html` §4д, exercises from §4е.
///
/// Two hard rules from the spec:
/// * no animation is longer than [maxDuration] (420 ms);
/// * with «Уменьшение движения» on, only colour and opacity changes remain —
///   translations, scales, shakes and flips are dropped. Call sites gate the
///   motion parts on [MediaQuery.disableAnimations]; see the `respectMotion`
///   helper in `lib/ui/`.
abstract final class AppMotion {
  // ── §4д — базовые ──

  /// Переворот карточки: rotateY 0→180, на середине смещение тени.
  static const flip = Duration(milliseconds: 280); // ease-out

  /// Возврат карточки после незавершённого свайпа. spring(.55).
  static const swipeReturn = Duration(milliseconds: 220);

  /// Подтверждение вердикта — карточка уходит в сторону кнопки.
  static const verdictLeave = Duration(milliseconds: 180); // ease-in
  static const verdictEnter = Duration(milliseconds: 220); // ease-out, +16 снизу

  /// Заливка сегмента счётчика.
  static const segmentFill = Duration(milliseconds: 160); // linear

  /// Закрытие дневной цели.
  static const goalBar = Duration(milliseconds: 420); // ease-out
  static const goalDot = Duration(milliseconds: 200);
  static const goalDotDelay = Duration(milliseconds: 260);

  /// Появление готовой коллекции (шиммер гаснет, фото проявляется).
  static const collectionReady = Duration(milliseconds: 320); // ease-out
  static const collectionReadyTextDelay = Duration(milliseconds: 80);

  /// Контекстное меню — раскрытие от точки вызова (scale .94→1).
  static const menuOpen = Duration(milliseconds: 160); // ease-out
  static const menuClose = Duration(milliseconds: 120); // ease-in

  // ── §4е — упражнения ──

  /// Верный вариант: подчёркивание рисуется слева направо.
  static const answerCorrect = Duration(milliseconds: 220); // ease-out, haptic success

  /// Неверный вариант: shake ±3 px, 3 колебания.
  static const answerWrong = Duration(milliseconds: 180); // haptic warning

  /// Раскрытие блока разбора в той же карточке.
  static const feedbackReveal = Duration(milliseconds: 200); // ease-out

  /// Чип летит в строку сборки. spring(.6), haptic light.
  static const chipToLine = Duration(milliseconds: 260);
  static const chipReturn = Duration(milliseconds: 200); // ease-in

  /// Принят ответ с опечаткой.
  static const typoColor = Duration(milliseconds: 160);
  static const typoForm = Duration(milliseconds: 200);
  static const typoFormDelay = Duration(milliseconds: 80);

  /// Слово «пишется само»: 24 мс на знак, суммарно ≤ 350 мс.
  static const writePerChar = Duration(milliseconds: 24);
  static const writeTotalCap = Duration(milliseconds: 350);

  /// Заполнение пропуска (cloze).
  static const fillBlank = Duration(milliseconds: 200); // ease-out

  /// Пульс кнопки воспроизведения в аудировании.
  static const listenPulse = Duration(milliseconds: 240); // ease-out

  /// Переход к следующему заданию.
  static const nextTaskLeave = Duration(milliseconds: 180); // ease-in, −24
  static const nextTaskEnter = Duration(milliseconds: 220); // ease-out, +24

  /// Ни одна анимация не длиннее этого.
  static const maxDuration = Duration(milliseconds: 420);

  // ── Кривые ──
  static const easeOut = Curves.easeOut;
  static const easeIn = Curves.easeIn;
  static const linear = Curves.linear;

  // ── Свайп/жесты (§4д) ──
  static const swipeTilt = 6.0; // ±6° — карточка следует за пальцем
  static const verdictLeaveTilt = 3.0; // ±3° при уходе по кнопке
  static const swipeThresholdFraction = 0.32; // 32 % ширины
  static const swipeFlingVelocity = 600.0; // px/s — бросок
  static const swipeBgTintMax = 0.16; // фон подкрашивается до 16 %

  // ── Пружины (реализуются через SpringDescription на месте вызова) ──
  static const springReturnRatio = 0.55; // spring(.55) — возврат карточки
  static const springChipRatio = 0.6; // spring(.6) — чип в строку
}
