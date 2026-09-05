/// СЛОВАРЬ ПОДПИСЕЙ ПЛАНА — наряд DAY-FIX-2, Ч.6.
///
/// Каждая строка на экранах плана берётся из ARB, и каждый ключ ARB с префиксом `plan*` стоит в
/// `docs/plan-ui-glossary.md` («ключ → русская подпись → где стоит»). Ключ, которого в словаре нет,
/// — подпись, которую никто не согласовал: живой прогон 05.09 нашёл на трёх экранах три разных
/// слова об одном дне ровно потому, что словаря не было.
///
/// Проверка — на ИМЕНАХ ключей, а не на текстах: текст правится в ARB и подтягивается в словарь
/// скриптом; имя — контракт между экраном и словарём.
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('каждый ключ plan* из app_ru.arb стоит в docs/plan-ui-glossary.md', () {
    final arb = jsonDecode(File('lib/l10n/app_ru.arb').readAsStringSync()) as Map<String, dynamic>;
    final glossary = File('../docs/plan-ui-glossary.md').readAsStringSync();

    final missing = <String>[
      for (final key in arb.keys)
        if (!key.startsWith('@') && key.startsWith('plan') && !glossary.contains('`$key`')) key,
    ];

    expect(missing, isEmpty, reason: 'ключи без строки в словаре:\n${missing.join('\n')}');
  });

  test('английский ARB знает те же ключи plan*, что и русский', () {
    final ru = jsonDecode(File('lib/l10n/app_ru.arb').readAsStringSync()) as Map<String, dynamic>;
    final en = jsonDecode(File('lib/l10n/app_en.arb').readAsStringSync()) as Map<String, dynamic>;

    final missing = <String>[
      for (final key in ru.keys)
        if (!key.startsWith('@') && key.startsWith('plan') && !en.containsKey(key)) key,
    ];

    expect(missing, isEmpty, reason: 'нет в app_en.arb:\n${missing.join('\n')}');
  });

  test('на экранах плана нет «N из M» и счётчиков вида {done}/{total}', () {
    final arb = jsonDecode(File('lib/l10n/app_ru.arb').readAsStringSync()) as Map<String, dynamic>;
    final counters = RegExp(r'\{[a-z]+\} из \{[a-z]+\}|\{[a-z]+\}/\{[a-z]+\}');

    final offenders = <String>[
      for (final entry in arb.entries)
        if (!entry.key.startsWith('@') &&
            entry.key.startsWith('plan') &&
            entry.value is String &&
            counters.hasMatch(entry.value as String) &&
            !_countersStillAllowed.contains(entry.key))
          '${entry.key}: ${entry.value}',
    ];

    expect(offenders, isEmpty, reason: 'счётчики на экранах плана:\n${offenders.join('\n')}');
  });
}

/// Счётчики ВНЕ экранов дня, диалога и итога — расписание и вход в план, где «День 2 из 4» и «На
/// событии сказал 3 из 5» остаются по кадрам (наряд DAY-FIX-2 их не трогает).
const _countersStillAllowed = {
  'planDayOfTotal',
  'planDayOfPlan',
  'planDaysHeader',
  'planCanAlready',
  'planFinishedAtEvent',
};
