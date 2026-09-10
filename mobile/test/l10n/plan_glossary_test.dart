/// СЛОВАРЬ ПОДПИСЕЙ ПЛАНА — наряд PLAN-UI (§5): «ни одной строки в коде, ни одной строки в файле
/// без экрана».
///
/// Каждая строка на экранах плана берётся из ARB, и каждый ключ ARB с префиксом `plan*` стоит в
/// `docs/plan-ui-glossary.md` («ключ → русская подпись → где стоит»). Ключ, которого в словаре
/// нет, — подпись, которую никто не согласовал с кадром.
///
/// Проверка — на ИМЕНАХ ключей, а не на текстах: текст правится в ARB и подтягивается в словарь
/// скриптом (`docs/plan-ui-glossary.md` — как обновлять); имя — контракт между экраном и словарём.
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

  test('ни одной строки plan* без экрана: каждый ключ читается из кода', () {
    final arb = jsonDecode(File('lib/l10n/app_ru.arb').readAsStringSync()) as Map<String, dynamic>;
    final sources = Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((f) => f.path.endsWith('.dart') && !f.path.contains('/l10n/'))
        .map((f) => f.readAsStringSync())
        .join('\n');

    final unused = <String>[
      for (final key in arb.keys)
        if (!key.startsWith('@') && key.startsWith('plan') && !sources.contains('.$key')) key,
    ];

    expect(unused, isEmpty, reason: 'строки без экрана:\n${unused.join('\n')}');
  });
}
