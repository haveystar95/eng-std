import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/typography.dart';

import '../support/nbsp.dart';

/// ОДНО ПРАВИЛО ТИПОГРАФИКИ ДЛЯ ВСЕХ СТРОК ИНТЕРФЕЙСА (наряд CLIENT-22-1 §2; до него — только строки CLIENT-START).
/// Каждая строка `app_ru.arb` и `app_en.arb` уже стоит по правилу `typeset` (`lib/data/typography.dart`) своего языка —
/// тому же, которым приложение при показе ставит родные тексты сервера: в ru неразрывный пробел (U+00A0) после каждого
/// слова в одну-две буквы и после предлогов списка («под», «для», «при», «над», «без», «про», «из-за»), в en — после
/// однобуквенного слова, в обоих — перед «—». Строка не начинается с тире и после жёсткого переноса.
/// Поправил строку — `dart run tool/typeset_arb.dart`.
void main() {
  for (final (file, language) in [('app_ru.arb', 'ru'), ('app_en.arb', 'en')]) {
    // ЛОВИТ: «Поговори с / регистратором», «Ровно на те / дни», «именно под / него», «Быт и / город» — строку, которую
    // правило ещё поменяло бы.
    test('$file: каждая строка стоит по правилу своего языка', () {
      final offences = <String>[
        for (final MapEntry(:key, :value) in _arb(file).entries)
          if (!key.startsWith('@') && value is String && typeset(value, language) != value)
            '$key: «$value» → «${typeset(value, language)}»',
      ];
      expect(offences, isEmpty, reason: offences.join('\n'));
    });

    // ЛОВИТ: «и {gender, select, female{она} other{он}} повторит» — ветка ICU кончается коротким словом, а слово, которое
    // оно держит, стоит за скобками: правило в шаблоне его не видит, и на экране «он / повторит». Такая ветка берёт
    // слово внутрь: «{…female{она повторит} other{он повторит}}».
    test('$file: ни одна ветка select / plural не кончается коротким словом перед текстом за скобками', () {
      final short = language == 'ru' ? r'(?:\p{L}{1,2}|под|для|при|над|без|про|из-за)' : r'\p{L}';
      final branch = RegExp('[\\w=]+\\{(?:[^{}]*[ \\u00A0])?$short\\}+ ', unicode: true, caseSensitive: false);
      final offences = <String>[
        for (final MapEntry(:key, :value) in _arb(file).entries)
          if (!key.startsWith('@') && value is String && branch.hasMatch(value)) '$key: «$value»',
      ];
      expect(offences, isEmpty, reason: offences.join('\n'));
    });

    // ЛОВИТ: «Живой разговор с ИИ / — не по сценарию.» — жёсткий перенос, после которого строка начинается с тире.
    test('$file: ни одна строка не начинается с тире', () {
      final offences = <String>[
        for (final MapEntry(:key, :value) in _arb(file).entries)
          if (!key.startsWith('@') && value is String && RegExp(r'(^|\n)\s*—').hasMatch(value)) key,
      ];
      expect(offences, isEmpty, reason: offences.join('\n'));
    });
  }

  // ЛОВИТ: хелпер тестов, который рисует строку не так, как `.arb`, — ожидания разошлись бы с приложением молча.
  test('nbTypo — то же правило: «Войти с Google», «Не сейчас», «про день 2», «на те дни», «Once a day»', () {
    expect(nbTypo('Войти с Google'), 'Войти с${nbsp}Google');
    expect(nbTypo('Не сейчас'), 'Не$nbspсейчас');
    expect(nbTypo('Правила и Конфиденциальность'), 'Правила и$nbspКонфиденциальность');
    expect(nbTypo('говорить: в «Диалоге», «Говорю сам» и в разговоре'), 'говорить: в$nbsp«Диалоге», «Говорю сам» и$nbspв$nbspразговоре');
    expect(nbTypo('Язык — под каждый разговор'), 'Язык$nbsp— под$nbspкаждый разговор');
    expect(nbTypo('Напомнить про день 2 завтра в 19:00?'), 'Напомнить про$nbspдень 2$nbspзавтра в${nbsp}19:00?');
    expect(nbTypo('Ровно на те дни'), 'Ровно на$nbspте$nbspдни');
    expect(nbTypo('Once a day'), 'Once a${nbsp}day');
  });
}

Map<String, dynamic> _arb(String name) => jsonDecode(File('lib/l10n/$name').readAsStringSync()) as Map<String, dynamic>;
