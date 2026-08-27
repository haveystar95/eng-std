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

  /// Подтверждения удаления (центральные alert-окна).
  static const alertSurface = Color(0xFFF8F6F0);

  /// Затемнение под шитами и алертами.
  static const scrim = Color.fromARGB(107, _inkR, _inkG, _inkB); // .42

  /// Затемнение под плавающим контекстным меню (§4в — чуть плотнее).
  static const menuScrim = Color.fromARGB(117, _inkR, _inkG, _inkB); // .46

  /// Заливка использованного чипа сборки и тихих кнопок (§2б).
  static const faintInk = Color.fromARGB(15, _inkR, _inkG, _inkB); // .06

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
  /// NOT YET IN `../backend2/docs/design/tokens.html`, which is the source of truth for this palette
  /// — the frames introduced it and the token list has not caught up. Add the row there; if the
  /// owner decides against brass, this constant is the one place to delete.
  static const brass = Color(0xFFB79363);

  static const verdictUnknown = Color(0xFFB5533C);

  /// «Не уверен». Подпись — [onVerdictUnsure].
  static const verdictUnsure = Color(0xFFA6761F);

  /// «Знаю». Подпись — [onVerdictKnown].
  static const verdictKnown = Color(0xFF4E6B52);

  /// Всё деструктивное без заливки: текст и иконка (меню, свайп, профиль,
  /// алерты). 5.4:1 на бумаге. Правило 20 — это не заливка.
  static const destructiveText = Color(0xFF9A4430);

  // Подписи поверх заливок вердиктов.
  static const onVerdictUnknown = Color(0xFFFFFFFF);
  static const onVerdictUnsure = ink;
  static const onVerdictKnown = paper;
}
