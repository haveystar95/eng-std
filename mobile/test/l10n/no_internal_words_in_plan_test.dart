/// ВНУТРЕННИХ СЛОВ НА ЭКРАНАХ ПЛАНА НЕТ — наряд DAY-GATE-1, Ч.2.7.
///
/// «Материал», «Прогон сцены», «Разогрев», «Спасатели» — это имена, которыми механику зовём МЫ.
/// Каждое из них правдиво внутри и ничего не значит снаружи: живой прогон 07.09 упёрся в «Материал
/// пройден» над днём, который человек не считал пройденным, и в «Спасатели» — слово, которое ничего
/// не обещает тому, кто растерялся посреди разговора.
///
/// Продукт зовёт их так (решение владельца, доработка окна 2):
///
///   Материал     → Слова и фразы   («Слова и фразы пройдены · разговор около N минут»)
///   Прогон сцены → Скажи сам
///   Разогрев     → Из прошлых дней
///   Спасатели    → На всякий случай  (+ подпись `planRescueHint`)
///
/// **Этим отменена DAY-FIX-2, Ч.6.2**, которая объявляла три из этих четырёх обязательными словами
/// и заносила их в `docs/plan-ui-glossary.md`. Отмена записана в словаре — там же, где стояло
/// старое правило, потому что правило, отменённое молча, возвращается следующим нарядом.
///
/// Проверка стоит на ЗНАЧЕНИЯХ ключей планового контура; описания (`@key`) остаются техническими,
/// их читает разработчик. Устроена так же, как соседний гард слов лестницы, и по той же причине.
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  // Корни, а не слова целиком: «материала», «разогреве», «спасателей» — те же слова в падеже, и
  // проверка, которая ловит только именительный, ловит половину.
  const internalRu = ['атериал', 'рогон', 'азогрев', 'пасател'];
  const internalEn = ['material', 'run-through', 'scene run', 'warm-up', 'rescue'];

  test('lib/l10n/app_ru.arb: у плановых строк нет внутренних слов', () {
    expect(_offenders('lib/l10n/app_ru.arb', internalRu), isEmpty);
  });

  test('lib/l10n/app_en.arb: у плановых строк нет внутренних слов', () {
    expect(_offenders('lib/l10n/app_en.arb', internalEn), isEmpty);
  });

  // Замена «Спасателей» несёт смысл не в имени, а в подписи, и подпись обязана существовать в обоих
  // языках: «На всякий случай» без неё — это пять фраз без повода.
  test('у «На всякий случай» есть подпись в обоих языках', () {
    for (final file in ['lib/l10n/app_ru.arb', 'lib/l10n/app_en.arb']) {
      final arb = jsonDecode(File(file).readAsStringSync()) as Map<String, dynamic>;
      expect(arb['planRescueHint'], isA<String>(), reason: '$file: нет planRescueHint');
      expect((arb['planRescueHint'] as String).trim(), isNotEmpty, reason: file);
    }
  });
}

List<String> _offenders(String file, List<String> words) {
  final arb = jsonDecode(File(file).readAsStringSync()) as Map<String, dynamic>;
  final offenders = <String>[];

  arb.forEach((key, value) {
    if (key.startsWith('@') || value is! String || !key.startsWith('plan')) return;
    final lower = value.toLowerCase();
    for (final word in words) {
      if (lower.contains(word)) offenders.add('$key: $value');
    }
  });

  return offenders;
}
