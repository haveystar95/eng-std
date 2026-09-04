/// ЯЗЫК ИНТЕРФЕЙСА — ЭТО ЯЗЫК ПОДДЕРЖКИ ПАРЫ, а не язык телефона (наряд DAY-2-FIX, Ч.1.2).
///
/// Живой прогон владельца вышел целиком по-английски: «said aloud», «rung A», «choose what you will
/// say», «Event in 1 day». Ни одна из этих строк не была хардкодом — все они лежат в `app_ru.arb` в
/// правильном переводе, и приложение честно печатало ту локаль, которую ему назвали. Назвали не ту:
/// телефон у владельца английский, а разговаривает продукт на языке поддержки пары («для ru→en это
/// русский», канон диалога §4).
///
/// Явный выбор человека в профиле по-прежнему главнее всего: настройка — это решение, а язык
/// поддержки — умолчание.
library;

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/locale_controller.dart';

void main() {
  test('«системный» значит язык поддержки, когда интерфейс его знает', () {
    expect(
      LocaleController.localeFor(UiLanguageOption.system, 'ru'),
      const Locale('ru'),
    );
  });

  test('английский телефон не переводит на английский аккаунт с русской поддержкой', () {
    // Ровно случай владельца. Устройство здесь не спрашивается вовсе: [localeFor] возвращает
    // конкретную локаль, и `localeResolutionCallback` до устройства не доходит.
    final locale = LocaleController.localeFor(UiLanguageOption.system, 'ru');
    expect(locale, isNot(const Locale('en')));
  });

  test('явный выбор в профиле сильнее языка поддержки', () {
    expect(
      LocaleController.localeFor(UiLanguageOption.english, 'ru'),
      const Locale('en'),
    );
    expect(
      LocaleController.localeFor(UiLanguageOption.russian, 'en'),
      const Locale('ru'),
    );
  });

  test('язык поддержки без перевода интерфейса отдаёт решение устройству', () {
    // uk, de, pl — законные родные языки аккаунта ([kNativeLanguageCodes]), которых у интерфейса
    // пока нет. Это не ошибка и не повод показывать пустой экран: решает устройство, а хвост
    // resolveLocale доводит до русского.
    expect(LocaleController.localeFor(UiLanguageOption.system, 'uk'), isNull);
    expect(LocaleController.localeFor(UiLanguageOption.system, null), isNull);
    expect(LocaleController.localeFor(UiLanguageOption.system, '  '), isNull);
    expect(resolveLocale(const Locale('uk'), kSupportedLocales), kFallbackLocale);
  });

  test('регистр и пробелы языка поддержки не меняют ответа', () {
    expect(LocaleController.localeFor(UiLanguageOption.system, 'RU'), const Locale('ru'));
    expect(LocaleController.localeFor(UiLanguageOption.system, ' en '), const Locale('en'));
  });
}
