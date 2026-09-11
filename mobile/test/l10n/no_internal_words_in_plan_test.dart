/// СЛОВАРЬ ЭКРАНОВ ПЛАНА — токен-лист 4к-4, наряд PLAN-UI (§5): «Все слова на экранах —
/// человеческие».
///
/// Слова, которых на экранах плана не бывает: ступень, такт, пара, ключ, промпт, модель,
/// генерация, умение, чек-пойнт, готовность в процентах, A/B, ИИ (кроме одного упоминания на
/// входе в план — которого в кадрах 22-x нет). Плюс «завтра» как замена «дальше» — но это
/// проверяется глазами, не гардом. Проверка стоит на ЗНАЧЕНИЯХ ключей планового контура
/// (`plan*`); описания (`@key`) остаются техническими — их читает разработчик.
///
/// Корни, а не слова целиком: «ступени», «умения», «моделью» — те же слова в падеже.
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  const forbiddenRu = ['ступен', 'такт', 'промпт', 'модел', 'генерац', 'умени', 'чек-пойнт', 'готовност', 'ИИ'];
  const forbiddenEn = ['rung', 'prompt', 'model', 'generation', 'skill', 'checkpoint', 'readiness', ' AI'];

  test('lib/l10n/app_ru.arb: у плановых строк нет слов не из словаря 4к-4', () {
    expect(_offenders('lib/l10n/app_ru.arb', forbiddenRu), isEmpty);
  });

  test('lib/l10n/app_en.arb: у плановых строк нет слов не из словаря 4к-4', () {
    expect(_offenders('lib/l10n/app_en.arb', forbiddenEn), isEmpty);
  });
}

List<String> _offenders(String file, List<String> words) {
  final arb = jsonDecode(File(file).readAsStringSync()) as Map<String, dynamic>;
  final offenders = <String>[];

  arb.forEach((key, value) {
    if (key.startsWith('@') || value is! String || !key.startsWith('plan')) return;
    for (final word in words) {
      // «ИИ» и « AI» — по регистру: «иди» и «main» — не про модель.
      final hit = word == 'ИИ' || word == ' AI' ? value.contains(word) : value.toLowerCase().contains(word);
      if (hit) offenders.add('$key: $value');
    }
  });

  return offenders;
}
