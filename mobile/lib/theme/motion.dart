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

  // ── Day session — the «Timing · session» table of canvas `session-canvas.dc.html` (work order SESSION-1b):
  // ── one constant per table row. Pulses and waves are PERIODS of repeating motion, not transitions.

  /// «Card change» — 220 ms, ease-out `cubic-bezier(0,0,.58,1)`, 24 px shift and fade.
  static const sessionCardChange = Duration(milliseconds: 220);
  static const sessionCardShift = 24.0;
  static const sessionEaseOut = Cubic(0, 0, .58, 1);

  /// «"Correct" check mark» — 180 ms, ease-out-back `cubic-bezier(.34,1.56,.64,1)`, scale 0→1.
  static const sessionCheckPop = Duration(milliseconds: 180);
  static const sessionEaseOutBack = Cubic(.34, 1.56, .64, 1);

  /// «"Correct" sheet lift» — 220 ms, lift 2 px and back.
  static const sessionLift = Duration(milliseconds: 220);

  /// «Progress bar fills up» — 260 ms after 120 ms, ease-in-out.
  static const sessionBarFill = Duration(milliseconds: 260);
  static const sessionBarFillDelay = Duration(milliseconds: 120);

  /// «Stage bead turns sage» — 180 ms after 120 ms, ease-out.
  static const sessionBeadFill = Duration(milliseconds: 180);

  /// «Auto-advance after correct» — the delay before the card changes.
  static const sessionAutoAdvance = Duration(milliseconds: 600);

  /// «"Wrong" shake» — 120 ms × 2, ±4 px, ease-in-out.
  static const sessionShake = Duration(milliseconds: 120);
  static const sessionShakeOffset = 4.0;

  /// «Sage wash on the correct option» — 160 ms after 120 ms, 0→15 %.
  static const sessionSageWash = Duration(milliseconds: 160);
  static const sessionSageWashDelay = Duration(milliseconds: 120);

  /// «"Coming back tomorrow" dot grows» — 180 ms, ease-out-back.
  static const sessionReturnDot = Duration(milliseconds: 180);

  /// «Tile into the assembly row» — 160 ms, ease-out.
  static const sessionTileMove = Duration(milliseconds: 160);

  /// «Mic wave», «"Listen" / "By ear" wave» — 60 ms frame, linear, only while sound plays.
  static const sessionWaveFrame = Duration(milliseconds: 60);

  /// «Live line: a word appears» — 120 ms; «A matched word turns sage» — 160 ms.
  static const sessionLiveWord = Duration(milliseconds: 120);
  static const sessionMatchedWord = Duration(milliseconds: 160);

  /// «Underscore caret before the first sound» — period 1000 ms, steps(1).
  static const sessionCaretPeriod = Duration(milliseconds: 1000);

  /// «Sage ring around the "listening" button» — period 1200 ms, only while recording.
  static const sessionListenPulse = Duration(milliseconds: 1200);

  /// «Exit sheet 30-8» — 280 ms; «Scrim under the sheet up to 40 %» — 320 ms; both ease-out-cubic.
  static const sessionExitSheet = Duration(milliseconds: 280);
  static const sessionExitScrim = Duration(milliseconds: 320);

  /// «Substitution into the slot (chip → slot)» — 160 ms; «Chip selected (ink)» — 120 ms.
  static const sessionChipToSlot = Duration(milliseconds: 160);
  static const sessionChipSelect = Duration(milliseconds: 120);

  /// «Slot passed in sage» — 160 ms after 120 ms; the frame and the slot recolor separately.
  static const sessionSlotSage = Duration(milliseconds: 160);
  static const sessionSlotSageDelay = Duration(milliseconds: 120);

  // ── Series 33–35 and 30-7 (work order SESSION-1c) — the same table.

  /// «Partner bubble · appears» — 200 ms, ease-out, fade and an 8 px shift.
  static const sessionBubbleIn = Duration(milliseconds: 200);
  static const sessionBubbleShift = 8.0;

  /// «Dialogue feed · a line» — 180 ms after 60 ms, ease-out, one by one top down.
  static const sessionFeedLine = Duration(milliseconds: 180);
  static const sessionFeedLineDelay = Duration(milliseconds: 60);

  /// «Player · time bar» — a 250 ms frame, linear; the exchange marks do not move.
  static const sessionPlayerFrame = Duration(milliseconds: 250);

  /// «Listen and answer», the whole visit as one stream: a pause between two lines (work order SESSION-2a §5, +300 ms
  /// over what the files carry) — the ring holds on the speaker who has just finished.
  static const sessionVisitLineGap = Duration(milliseconds: 300);

  /// «Active role pulse» — period 1600 ms, ease-out, only while playing.
  static const sessionRolePulse = Duration(milliseconds: 1600);

  /// «The line's text opens» — 200 ms, ease-out, a fade in place of the wave.
  static const sessionTextReveal = Duration(milliseconds: 200);

  /// «The pause ring around the microphone» — 3000 ms, linear, brass 1.5, the ring shrinks to 72 (the card's
  /// `pause_ms` wins when it differs).
  static const sessionPauseRing = Duration(milliseconds: 3000);

  /// «The frame hint after silence» — 200 ms after 5000 ms, ease-out, a fade above the microphone.
  static const sessionHintIn = Duration(milliseconds: 200);
  static const sessionHintSilence = Duration(milliseconds: 5000);

  /// «Day summary plate 30-7» — 200 ms after 80 ms, ease-out, a fade.
  static const sessionDayPlate = Duration(milliseconds: 200);
  static const sessionDayPlateDelay = Duration(milliseconds: 80);

  /// «The constructions sheet» (37-8d) rises and falls as the exit sheet does.
  static const talkPhraseSheet = sessionExitSheet;

  // ── The talk across scenes and its plates (наряд CLIENT-FIX-4) — the captions of кадры 39-1, 37-8, 37-11.

  /// «The transition card rides in from below — a 24 shift and a fade, 220 ms» (39-1).
  static const talkSceneCardIn = Duration(milliseconds: 220);
  static const talkSceneCardShift = 24.0;

  /// «On „Продолжить“ it folds into the divider, 220 ms ease-out» (39-1).
  static const talkSceneCardFold = Duration(milliseconds: 220);

  /// «The scene strip changes with a 200 ms fade» (39-1).
  static const talkSceneStripFade = Duration(milliseconds: 200);

  /// «The doctor's first line — 300 ms later» (39-1): the breath between «Продолжить» and the next role's greeting.
  static const talkSceneGreetingDelay = Duration(milliseconds: 300);

  /// «Said — the plate gets a 15 % sage wash with a check for a moment and leaves to the left after 600 ms» (37-8).
  static const talkPlateSaidHold = Duration(milliseconds: 600);

  /// The plate leaving the row: it slides left while its width folds (the canvas names no duration — the card change's
  /// 220 ms, ease-out).
  static const talkPlateLeave = Duration(milliseconds: 220);

  /// «The last plate left — the row folds, the dock goes down by 58» (37-11b), the same 220 ms.
  static const talkRowFold = Duration(milliseconds: 220);

  /// «Дальше» on the talk's summary (37-12, 37-12b) takes no tap for this long after the summary appears — a guard, not
  /// an animation: «Итог» of 37-11 stands in the same place, and a double tap there must not pass the summary unread
  /// (решение архитектора при приёмке CLIENT-FIX-4, 25.09).
  static const talkSummaryArm = Duration(milliseconds: 600);

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
