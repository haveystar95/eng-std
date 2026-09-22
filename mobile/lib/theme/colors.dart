import 'package:flutter/painting.dart';

/// Colour tokens — the single source of truth for the paper/ink system.
///
/// Values are exact from `backend2/docs/design/tokens.html` §1. Rule 01: the
/// interface is monochrome — colour appears only in photographs and in the
/// three triage verdicts. No raw hex colour may live outside `lib/theme/`
/// (enforced by `test/theme/no_hex_outside_theme_test.dart`).
abstract final class AppColors {
  // Ink is the base of every neutral; alpha variants are derived from its rgb
  // so a reviewer can see they match #2E2620.
  static const int _inkR = 46, _inkG = 38, _inkB = 32; // #2E2620

  /// Фон всех экранов и шитов; текст на чернильных кнопках.
  static const paper = Color(0xFFF6F3EC);

  /// Основной текст, главные кнопки, прогресс, активный таб. 12.1:1 на бумаге.
  static const ink = Color(0xFF2E2620);

  /// Примеры употребления курсивом, длинные пояснения.
  static const inkBody = Color(0xFF4A443D);

  /// Переводы, транскрипции, лейблы секций, вторичные строки.
  static const secondary = Color(0xFF6E6862);

  /// Плейсхолдеры, подсказки, оси графика, «Отменить последний».
  static const tertiary = Color(0xFF8A857E);

  /// Разделители и рамки полей/чипов/пилюли. У карточек рамок нет — только тень.
  static const hairline = Color.fromARGB(36, _inkR, _inkG, _inkB); // .14

  /// Подложка прогресса, пустые точки стрика и календаря.
  static const track = Color.fromARGB(46, _inkR, _inkG, _inkB); // .18

  /// Пунктирная рамка недоступного действия (офлайн-карточка генерации, кадр 9c).
  static const dashed = Color.fromARGB(61, _inkR, _inkG, _inkB); // .24

  /// Слоёная бумага — поверхность карточек, светлее фона. Рамки нет, только тень.
  static const surfaceRaised = Color(0xFFFCFAF5);

  /// Подложка на месте фотографии — фото-герой карточки слова и врезка в результате поиска
  /// (макет «Фаза 3», кадры 03/05/06). Тёплая плита, а не серый прямоугольник: слово без картинки
  /// должно читаться как слово без картинки, а не как дыра в композиции.
  static const photoPlate = Color(0xFFE7E2D7);

  /// Служебная подпись НА [photoPlate] («подбираем фото…», атрибуция). Тише [tertiary], потому
  /// что лежит на подложке, а не на бумаге.
  static const plateLabel = Color(0xFFA8A29A);

  /// Поле ввода внутри карточки — чистая белая бумага.
  static const field = Color(0xFFFFFFFF);

  /// ФОН ЭКРАНОВ ПЛАНА И ГЛАВНОЙ — «#EFEBE3, на полтона глубже paper» (токен-лист 4и; кадры 21-x).
  /// Плита и слоёная бумага лежат на нём, а не сливаются с ним.
  static const ground = Color(0xFFEFEBE3);

  /// ПОДЛОЖКА ФОТО-МИНИАТЮРЫ И АВАТАРА — #E3DCCF (токен-лист 4г, 4и «подложка пустого слота»):
  /// кружок обложки плана, фото 48 на маршруте, кружок-аватар в шапке.
  static const photoPlaceholder = Color(0xFFE3DCCF);

  /// [ground] БЕЗ ЦВЕТА — верхний край градиента, которым содержимое уходит под закреплённую
  /// кнопку (кадры 22-1 … 22-4b). `Colors.transparent` здесь даёт серый провал на середине
  /// перехода: градиент интерполирует и альфу, и RGB, а у прозрачного чёрного RGB чёрный.
  static const groundClear = Color(0x00EFEBE3);

  /// ШИММЕР — «#EAE5DB с проблеском #F7F4EE» (кадр 7a, источник для 22-4a): узлы и фото маршрута,
  /// пока сервер собирает план.
  static const shimmerBase = Color(0xFFEAE5DB);
  static const shimmerHighlight = Color(0xFFF7F4EE);

  /// Подтверждения удаления (центральные alert-окна).
  static const alertSurface = Color(0xFFF8F6F0);

  /// Затемнение под шитами и алертами.
  static const scrim = Color.fromARGB(107, _inkR, _inkG, _inkB); // .42

  /// Затемнение под плавающим контекстным меню (§4в — чуть плотнее).
  static const menuScrim = Color.fromARGB(117, _inkR, _inkG, _inkB); // .46

  /// Заливка использованного чипа сборки и тихих кнопок (§2б).
  static const faintInk = Color.fromARGB(15, _inkR, _inkG, _inkB); // .06

  /// Нейтральная плашка значения на карточке-уроке 32-1 — `rgba(46,38,32,.05)`.
  static const meaningPlate = Color.fromARGB(13, _inkR, _inkG, _inkB); // .05

  /// Разделитель внутри контекстного меню и списков от текста (§4в).
  static const dividerFaint = Color.fromARGB(26, _inkR, _inkG, _inkB); // .10

  // ── Три вердикта разбора — единственный цвет UI (правило 02) ──

  /// «Не знаю». Заливка только здесь (правило 20). Подпись — [onVerdictUnknown].
  /// БРАС — the one warm mark the product allows itself, and only on the dark plate.
  ///
  /// Кадры 19-1 / 19-4: the «СЕССИЯ» badge over the session tile and the target icon of the word
  /// challenge. It is NOT a general accent — nothing on the paper ground wears it, and the rule
  /// «один акцент на экран» is what keeps it meaning «начни отсюда».
  ///
  /// In `../backend2/docs/design/tokens.html` — the source of truth for this palette, and it carries
  /// this row. The frames introduced brass and the token list has since caught up.
  static const brass = Color(0xFFB79363);

  /// The dark session plate is a GRADIENT in the token list, not a flat fill: #332A23 → #292219.
  /// Two stops eight units apart — it reads as one dark surface with a light source, not as a
  /// gradient, which is why it is the one place the palette allows one.
  static const plateTop = Color(0xFF332A23);
  static const plateBottom = Color(0xFF292219);

  /// The same warm mark on PAPER, where #B79363 is too light to read: the generation card's arrow
  /// and the evening's reward line (кадры 19-1, 19-2). One family, two grounds.
  static const brassInk = Color(0xFF8C6A3A);

  /// The generation card's own outline and icon plate — brass at the weight paper can carry.
  static const brassHairline = Color(0x8CB79363); // .55 of brass
  static const brassPlate = Color(0xFF9C7638);

  /// THE PLAN'S OWN FRAME — a full-strength brass outline, used by nothing else.
  ///
  /// «Латунь — служебная метка плана» (макет «Фаза 4»). The plan card on the home screen and the
  /// «срок мал» recommendation are drawn as brass-outlined paper, against the dark plate the day's
  /// session wears: two different materials, so the eye reads them as two different piles of work
  /// rather than as one list with a heading. This is the outline at the weight the frames draw it —
  /// [brassHairline] is the same colour softened for the generation card, which is a quieter thing.
  static const brassFrame = brass;

  /// The wash inside that frame — `rgba(183,147,99,.07)`. Barely there on purpose: it separates the
  /// plan card from the paper without becoming a second surface colour.
  static const brassWash = Color.fromARGB(18, 183, 147, 99);

  /// THE CHOSEN CARD of the plan entry — «выбранное состояние #F1EADC» (записка «Вход v4»).
  ///
  /// A warm sand, and the one fill in the entry that is neither paper nor the accent: the level
  /// card, the picked date, the chosen minutes. The записка is explicit that a selection is not an
  /// outline — «выбранная карточка не просто обводится: она темнеет до тёплого песочного, получает
  /// латунную галочку и мягкую тень — выбор ощущается как нажатая клавиша».
  static const planSelected = Color(0xFFF1EADC);

  /// «Неактивное» of the same записка — #B4AEA6, one step quieter than [tertiary].
  ///
  /// The step of the build screen that has not started yet, and the rows of a locked thing. NOT
  /// [plateLabel]: that one is a caption ON the photo plate and is a different job at a similar
  /// value.
  static const planInactive = Color(0xFFB4AEA6);

  static const verdictUnknown = Color(0xFFB5533C);

  /// «Не уверен». Подпись — [onVerdictUnsure].
  static const verdictUnsure = Color(0xFFA6761F);

  /// «Знаю». Подпись — [onVerdictKnown].
  static const verdictKnown = Color(0xFF4E6B52);

  /// Всё деструктивное без заливки: текст и иконка (меню, свайп, профиль,
  /// алерты). 5.4:1 на бумаге. Правило 20 — это не заливка.
  static const destructiveText = Color(0xFF9A4430);

  /// ТЕРРАКОТА НА УГОЛЬНОЙ ПЛИТЕ — беда, о которой говорит тёмная плита дня (кадр 22-5c, «День
  /// не собрался»). Отдельный токен, а не [destructiveText] с прозрачностью: #9A4430 на #2E2620
  /// не читается вовсе, поэтому канва развела два случая по светлоте, а не по alpha.
  static const destructiveOnPlate = Color(0xFFE2A08A);

  // ── ПЛАН · ДЕНЬ (токен-лист 4к–4о, кадры 23-x) ───────────────────────────
  //
  // Одна краска сверх чернил на карточку: латунь лейбла собеседника, шалфей/охра/терракота
  // вердикта и полосок, тёмная плита под числом, фото на знакомстве. Всё ниже — те же три
  // вердикта и те же чернила в других плотностях; новых цветов план не заводит (4к-2).

  /// Тонировка верного варианта — `rgba(78,107,82,.08)` (4л).
  static const sageTint = Color.fromARGB(20, 78, 107, 82);

  /// Шалфейная подложка под словом/ключом, который услышали — `rgba(78,107,82,.12)` (23-3c, 23-5).
  static const sageWash = Color.fromARGB(31, 78, 107, 82);

  /// Тонировка неверного варианта — `rgba(154,68,48,.06)` (4л).
  static const terracottaTint = Color.fromARGB(15, 154, 68, 48);

  /// Подложка блока задания — `rgba(46,38,32,.04)` (4м).
  static const taskBlock = Color.fromARGB(10, _inkR, _inkG, _inkB);

  /// Контур пустого маркера 22 и тихих столбиков амплитуды — `rgba(46,38,32,.22)` (4л).
  static const markerOutline = Color.fromARGB(56, _inkR, _inkG, _inkB);

  /// Контур кружка воспроизведения 44 — `rgba(46,38,32,.28)`; он же — тихая часть волны.
  static const playOutline = Color.fromARGB(71, _inkR, _inkG, _inkB);

  /// Подчёркивание слова в примере знакомства — `rgba(46,38,32,.55)` (16a, 23-1).
  static const exampleUnderline = Color.fromARGB(140, _inkR, _inkG, _inkB);

  /// Подложка пустого фото-слота — `#E3DCCF` (4и, 4н).
  static const photoSlot = Color(0xFFE3DCCF);

  /// Бумага на плите в разных плотностях: подложка полоски `.14`, контур маркера «научишься»
  /// `.35`, вторичные строки `.5 / .65 / .72 / .9`.
  static const paperTrack = Color.fromARGB(36, 246, 243, 236);
  static const paperMarkerOutline = Color.fromARGB(89, 246, 243, 236);
  static const paper50 = Color.fromARGB(128, 246, 243, 236);
  static const paper65 = Color.fromARGB(166, 246, 243, 236);
  static const paper72 = Color.fromARGB(184, 246, 243, 236);
  static const paper90 = Color.fromARGB(230, 246, 243, 236);

  /// Прозрачная бумага — верх градиента дока, из которого выходит кнопка.
  static const paperClear = Color(0x00F6F3EC);

  /// Подложка полоски этапа на бумаге — `rgba(46,38,32,.12)` (23-9, 23-14).
  static const barTrack = Color.fromARGB(31, _inkR, _inkG, _inkB);

  /// Тень микрофона 80 — `0 10 28 rgba(46,38,32,.22)`.
  static const micShadow = Color.fromARGB(56, _inkR, _inkG, _inkB);

  /// Хайрлайн на бумаге в примере знакомства — `rgba(46,38,32,.12)`.
  static const hairlineSoft = Color.fromARGB(31, _inkR, _inkG, _inkB);

  // Подписи поверх заливок вердиктов.
  static const onVerdictUnknown = Color(0xFFFFFFFF);
  static const onVerdictUnsure = ink;
  static const onVerdictKnown = paper;

  /// Текстовая ссылка канвы — `a { color:#8C4A34 }` («Изменить» над кнопкой превью 22-4b).
  static const link = Color(0xFF8C4A34);

  /// Последнее слово, которое распознавание ещё уточняет, — `#A9A39B` (кадр 22-1).
  static const dictationPending = Color(0xFFA9A39B);

  // ── Маршрут плана (канва PLAN-DES-3, кадры 21-2 … 22-4b) ──

  /// Линия маршрута впереди — `rgba(46,38,32,.22)` на табе.
  static const routeAhead = Color.fromARGB(56, _inkR, _inkG, _inkB);

  /// Линия превью и примера витрины — `rgba(46,38,32,.25)`: прогресса там нет, линия ровная.
  static const routeQuiet = Color.fromARGB(64, _inkR, _inkG, _inkB);

  /// Контур незапертого, но ещё не начатого этапа на линии — `rgba(46,38,32,.40)`.
  static const routeStageOutline = Color.fromARGB(102, _inkR, _inkG, _inkB);

  /// Кольцо текущего этапа — `0 0 0 3px rgba(140,106,58,.30)`.
  static const routeCurrentRing = Color.fromARGB(77, 140, 106, 58);

  /// Метки узлов этапов пройденного дня — `rgba(46,38,32,.7)`; запертого — `.4`.
  static const routeWalkedLabel = Color.fromARGB(179, _inkR, _inkG, _inkB);
  static const routeLockedText = Color.fromARGB(102, _inkR, _inkG, _inkB);

  /// Мета запертого дня — tertiary под вуалью `.4` канвы (`#8A857E` @ .4).
  static const routeLockedMeta = Color.fromARGB(102, 138, 133, 126);

  /// Вуаль над картинкой запертого дня — ground `.6`.
  static const routeVeil = Color.fromARGB(153, 239, 235, 227);

  /// Тень латунного узла дня — `0 2px 10px rgba(140,106,58,.28)`.
  static const routeCurrentGlow = Color.fromARGB(71, 140, 106, 58);

  // ── Окно дня (канва plan-canvas, кадры 23-0a … 23-0e, наряды DAY-UI-2, DAY-UI-3) ──

  /// Плита окна под фото дня — `#2A231D`; им же залита плита, пока фото нет.
  static const windowPlate = Color(0xFF2A231D);

  /// Скрим над фото плиты: `rgba(24,20,16,.86)` → `.82` на 120 → `.90` на 300 → `.96` у низа.
  static const windowScrimTop = Color.fromARGB(219, 24, 20, 16);
  static const windowScrimHigh = Color.fromARGB(209, 24, 20, 16);
  static const windowScrimLow = Color.fromARGB(230, 24, 20, 16);
  static const windowScrimBottom = Color.fromARGB(245, 24, 20, 16);

  /// Тень плиты на бумагу — `0 8px 24px rgba(0,0,0,.18)`.
  static const windowPlateShadow = Color.fromARGB(46, 0, 0, 0);

  /// Бровь «ДЕНЬ 2» светлой латунью — `#EFD9B4`.
  static const windowBrow = Color(0xFFEFD9B4);

  /// Строка статуса под названием — `#C8C0B4`.
  static const windowStatus = Color(0xFFC8C0B4);

  /// Предложение целей «Научишься …» — `rgba(246,243,236,.78)`.
  static const windowGoals = Color.fromARGB(199, 246, 243, 236);

  /// Запертый этап: значок, имя и «впереди» — `#BDB6AC`.
  static const windowAhead = Color(0xFFBDB6AC);

  /// Пройденный этап на плите и «Научился:» — `#9DB89F`, полоса — `#7FA184`.
  static const windowDone = Color(0xFF9DB89F);
  static const windowDoneBar = Color(0xFF7FA184);

  /// «идёт · ≈ 8 мин» у текущего этапа — `#E3C08A`.
  static const windowCurrent = Color(0xFFE3C08A);

  /// Подложка полосы и хайрлайн на плите — `rgba(246,243,236,.16)`.
  static const windowPaperLine = Color.fromARGB(41, 246, 243, 236);

  /// Кнопка главного действия и своя реплика в диалоге — `#1B1A18`.
  static const windowInk = Color(0xFF1B1A18);

  /// Тень пилюли вкладок — `0 2px 8px rgba(46,38,32,.08)`: одна и та же на шве и под шапкой.
  static const windowPillShadow = Color.fromARGB(20, _inkR, _inkG, _inkB);

  /// Затемнение окна под шитом слова (23-0e) — `rgba(24,20,16,.4)` в конце подъёма.
  static const windowSheetScrim = Color.fromARGB(102, 24, 20, 16);

  /// Тень шита — `0 -12px 40px rgba(24,20,16,.18)`.
  static const windowSheetShadow = Color.fromARGB(46, 24, 20, 16);

  // ── Day session — canvas `session-canvas.dc.html`, series 30–32 (work order SESSION-1b). The colors are the
  // ── app's own: paper, ink, sage (`verdictKnown`), brass (`brassInk`) — only their fractions live here.

  /// Shadow of the material sheet and the options — `0 4px 16px rgba(46,38,32,.08)`; it is also the fill of
  /// the inactive «Next» button (32-8).
  static const sessionSheetShadow = Color.fromARGB(20, _inkR, _inkG, _inkB);

  /// Sage wash 15 % — the correct option, a passed slot.
  static const sessionSageWash = Color.fromARGB(38, 78, 107, 82);

  /// Frame slot fill, 8 % brass.
  static const sessionWindowFill = Color.fromARGB(20, 140, 106, 58);

  /// Frame slot fill of a construction the learner has said — 8 % sage (плашки 37-7, лист 37-8d, итог 37-12).
  static const sessionSaidSlotFill = Color.fromARGB(20, 78, 107, 82);

  /// Sage ring 30 % around the «listening» button.
  static const sessionListenRing = Color.fromARGB(77, 78, 107, 82);

  /// Brass ring 30 % around the current stage dot.
  static const sessionBrassRing = Color.fromARGB(77, 140, 106, 58);

  /// Shadow of the 72 mic button — `0 8px 24px rgba(46,38,32,.18)`.
  static const sessionMicShadow = Color.fromARGB(46, _inkR, _inkG, _inkB);

  /// Track of the «No hints» switch — `rgba(46,38,32,.16)`.
  static const sessionToggleTrack = Color.fromARGB(41, _inkR, _inkG, _inkB);

  /// Shadow of the switch knob — `0 1px 3px rgba(46,38,32,.25)`.
  static const sessionToggleKnobShadow = Color.fromARGB(64, _inkR, _inkG, _inkB);

  /// Shadow of the exit sheet 30-8 — `0 -12px 40px rgba(24,20,16,.28)`.
  static const sessionExitSheetShadow = Color.fromARGB(71, 24, 20, 16);

  /// Shadow of the header plate and the scene strip on the canvas — `0 2px 8px rgba(46,38,32,.04)`.
  static const sessionFaintShadow = Color.fromARGB(10, _inkR, _inkG, _inkB);

  // ── Series 33–35 and the day summary 30-7 (work order SESSION-1c).

  /// Sage on ink — `#9CBF9F`: the matched words of the live line in the own (dark) bubble, a slot passed «by
  /// meaning» there, «by meaning ✓» (35-2).
  static const sessionSageOnInk = Color(0xFF9CBF9F);

  /// Paper 45 % on ink — the words of the own bubble that did not match (35-2, 35-5).
  static const sessionPaperDim = Color.fromARGB(115, 246, 243, 236);

  /// An empty slot on ink — 18 % brass (33-2, 33-4).
  static const sessionWindowFillOnInk = Color.fromARGB(46, 140, 106, 58);

  /// The divider between the rows of the day plate 30-7 — `rgba(246,243,236,.14)`.
  static const sessionPlateDivider = Color.fromARGB(36, 246, 243, 236);

  /// «Прослушать» 44 inside the learner's own ink bubble (34-5, наряд CLIENT-CONV-1b) — an outline of paper at 55 %.
  static const sessionOwnListenOutline = Color.fromARGB(140, 246, 243, 236);

  /// The card under a day window of a review or the rehearsal (37-1, 37-2) — `0 2px 8px rgba(46,38,32,.06)`.
  static const windowSourceShadow = Color.fromARGB(15, _inkR, _inkG, _inkB);

  /// Доминантный тон картинки с провода (`image.tone`, `#RRGGBB`) — заливка круга, пока картинка
  /// в пути (наряд PLAN-UI-3). Не цвет палитры, а цвет фотографии: поэтому он приходит с сервера и
  /// читается здесь, где hex законен. Кривой ответ — null, и круг остаётся бумажным.
  static Color? wireTone(String? hex) {
    final m = RegExp(r'^#?([0-9a-fA-F]{6})$').firstMatch((hex ?? '').trim());
    if (m == null) return null;

    return Color(int.parse(m.group(1)!, radix: 16) | 0xFF000000);
  }
}
