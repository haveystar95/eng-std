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

  // ── §4о — день плана ──

  /// «Думаем» после записи — столбики гаснут, потом вердикт.
  static const speechThinking = Duration(milliseconds: 300);

  /// «Произнеси» после «Услышали» уходит сама.
  static const spokenAutoLeave = Duration(milliseconds: 600);

  /// Раскрытие перевода в пузыре собеседника (23-6b).
  static const translationReveal = Duration(milliseconds: 200);

  /// Сегмент шапки доливается в итоге этапа.
  static const stageSegmentFill = Duration(milliseconds: 160);

  /// Пульс кружка воспроизведения перед волной.
  static const wavePulse = Duration(milliseconds: 240);

  // ── Окно дня — таблица «Тайминг · серия 23» канвы (DAY-UI-3): одна константа на строку таблицы, ──
  // ── имя — как у строки. Ничего не анимируется двумя правилами сразу: у плиты и пилюли — только   ──
  // ── позиция прокрутки, у чипа — только сдвиг к сегменту, у шита — подъём, у фона — затемнение.  ──

  /// «плита → компактная шапка (пилюля едет вместе)» — НЕ анимация, а функция прокрутки: последние
  /// 160 px пути плиты она переходит в шапку 56 (`linear по позиции`).
  static const windowPlateToHeaderSpan = 160.0;

  /// «примагничивание в точке отпускания» — 260 мс, ease-out-cubic: один `animateTo` ленты.
  static const windowSnap = Duration(milliseconds: 260);

  /// «смена вкладки · чип скользит к сегменту» — 220 мс по тапу или свайпу, ease-out-cubic.
  static const windowTabChip = Duration(milliseconds: 220);

  /// «смена вкладки · содержимое сдвигается по горизонтали» — 220 мс, тем же контроллером, что и чип.
  static const windowTabContent = Duration(milliseconds: 220);

  /// «23-0e · шит поднимается» — 320 мс, ease-out-cubic.
  static const windowSheetRise = Duration(milliseconds: 320);

  /// «23-0e · фон затемняется до 40 %» — 320 мс, ease-out-cubic, вместе с подъёмом.
  static const windowSheetScrim = Duration(milliseconds: 320);

  /// «23-0e · закрытие („Закрыть“ или тяга вниз)» — 260 мс, ease-out-cubic.
  static const windowSheetClose = Duration(milliseconds: 260);

  /// ease-out-cubic таблицы — `cubic-bezier(.33,1,.68,1)` у всех строк выше.
  static const windowEaseOutCubic = Cubic(.33, 1, .68, 1);

  /// Галка этапа, закрытого в сессии, — при возврате в окно через 300 мс (`om-check-pop`: масштаб
  /// 0 → 1 за 180 мс, `cubic-bezier(.34,1.4,.5,1)`).
  static const windowStageCheck = Duration(milliseconds: 180);
  static const windowStageCheckDelay = Duration(milliseconds: 300);
  static const windowStageCheckCurve = Cubic(.34, 1.4, .5, 1);

  /// Фото: тон → картинка растворением 200 мс (слоты слов, плита, шит, круг компактной шапки).
  static const windowPhotoFade = Duration(milliseconds: 200);

  // ── Сессия дня — таблица «Тайминг · сессия» канвы `session-canvas.dc.html` (наряд SESSION-1b): одна ──
  // ── константа на строку таблицы. Пульсы и волны — ПЕРИОДЫ повторяющихся движений, не переходы.   ──

  /// «Смена карточки» — 220 мс, ease-out `cubic-bezier(0,0,.58,1)`, сдвиг 24 px и затухание.
  static const sessionCardChange = Duration(milliseconds: 220);
  static const sessionCardShift = 24.0;
  static const sessionEaseOut = Cubic(0, 0, .58, 1);

  /// «Галка „верно“» — 180 мс, ease-out-back `cubic-bezier(.34,1.56,.64,1)`, масштаб 0→1.
  static const sessionCheckPop = Duration(milliseconds: 180);
  static const sessionEaseOutBack = Cubic(.34, 1.56, .64, 1);

  /// «Подъём листа „верно“» — 220 мс, подъём 2 px и возврат.
  static const sessionLift = Duration(milliseconds: 220);

  /// «Полоса прогресса доливается» — 260 мс после 120 мс, ease-in-out.
  static const sessionBarFill = Duration(milliseconds: 260);
  static const sessionBarFillDelay = Duration(milliseconds: 120);

  /// «Бусина этапа перекрашивается в шалфей» — 180 мс после 120 мс, ease-out.
  static const sessionBeadFill = Duration(milliseconds: 180);

  /// «Автопереход после верного» — задержка перед сменой карточки.
  static const sessionAutoAdvance = Duration(milliseconds: 600);

  /// «Покачивание „неверно“» — 120 мс × 2, ±4 px, ease-in-out.
  static const sessionShake = Duration(milliseconds: 120);
  static const sessionShakeOffset = 4.0;

  /// «Подложка шалфея у верного варианта» — 160 мс после 120 мс, 0→15 %.
  static const sessionSageWash = Duration(milliseconds: 160);
  static const sessionSageWashDelay = Duration(milliseconds: 120);

  /// «Точка „вернётся завтра“ вырастает» — 180 мс, ease-out-back.
  static const sessionReturnDot = Duration(milliseconds: 180);

  /// «Плитка в строку сборки» — 160 мс, ease-out.
  static const sessionTileMove = Duration(milliseconds: 160);

  /// «Волна микрофона», «Волна „прослушать“ / „На слух“» — кадр 60 мс, linear, только пока идёт звук.
  static const sessionWaveFrame = Duration(milliseconds: 60);

  /// «Живая строка: слово появляется» — 120 мс; «Совпавшее слово перекрашивается в шалфей» — 160 мс.
  static const sessionLiveWord = Duration(milliseconds: 120);
  static const sessionMatchedWord = Duration(milliseconds: 160);

  /// «Курсор-подчерк до первого звука» — период 1000 мс, steps(1).
  static const sessionCaretPeriod = Duration(milliseconds: 1000);

  /// «Кольцо шалфея у кнопки „слушаю“» — период 1200 мс, только пока идёт запись.
  static const sessionListenPulse = Duration(milliseconds: 1200);

  /// «Шит выхода 30-8» — 280 мс; «Фон под шитом до 40 %» — 320 мс; оба ease-out-cubic.
  static const sessionExitSheet = Duration(milliseconds: 280);
  static const sessionExitScrim = Duration(milliseconds: 320);

  /// «Подстановка в окно (чип → окно)» — 160 мс; «Чип выбран (чернила)» — 120 мс.
  static const sessionChipToSlot = Duration(milliseconds: 160);
  static const sessionChipSelect = Duration(milliseconds: 120);

  /// «Окно зачтено шалфеем» — 160 мс после 120 мс; каркас и окно перекрашиваются раздельно.
  static const sessionSlotSage = Duration(milliseconds: 160);
  static const sessionSlotSageDelay = Duration(milliseconds: 120);

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
