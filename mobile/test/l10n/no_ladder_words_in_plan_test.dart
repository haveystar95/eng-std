/// СЛОВ ЛЕСТНИЦЫ НА ПЛАНОВЫХ ЭКРАНАХ НЕТ — наряд DAY-2-FIX, Ч.3а.
///
/// «Ступень A», «rung A», «stage B» — это внутреннее имя механики. Оно правдиво, оно нужно коду и
/// оно ничего не значит для того, кто читает его на экране: живой прогон владельца упёрся в
/// «21 · 51 карточка · 21 закрыла ступень A» и в латунную «B» в углу тренировки, и ни одна из
/// надписей не отвечала на вопрос «что со мной сейчас происходит».
///
/// Продукт говорит о материале словами зрелости — познакомился → применяю → говорю сам (канон §2).
/// Проверка стоит на ЗНАЧЕНИЯХ ключей планового контура: описания (`@key`) остаются техническими,
/// потому что их читает разработчик, а не человек с телефоном.
///
/// Контур словаря (`status*`, `home*`, `triage*`) сюда НЕ входит и правится своим нарядом: там
/// лестница — это лестница пула, у неё свои экраны и свой словарь.
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  const ladder = ['ступен', 'rung', 'stage ', 'stage a', 'stage b', 'stage c'];

  for (final file in ['lib/l10n/app_ru.arb', 'lib/l10n/app_en.arb']) {
    test('$file: у плановых строк нет слов лестницы', () {
      final arb = jsonDecode(File(file).readAsStringSync()) as Map<String, dynamic>;
      final offenders = <String>[];

      arb.forEach((key, value) {
        if (key.startsWith('@') || value is! String) return;
        if (!key.startsWith('plan')) return;
        final lower = value.toLowerCase();
        for (final word in ladder) {
          if (lower.contains(word)) offenders.add('$key: $value');
        }
      });

      expect(offenders, isEmpty, reason: 'слова лестницы на плановых экранах:\n${offenders.join('\n')}');
    });
  }
}
