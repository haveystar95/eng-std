import 'dart:convert';
import 'dart:io';

import 'package:flutter/widgets.dart' show Locale;
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/plan_stage_text.dart' show PlanDot;
import 'package:eng_std/l10n/app_localizations.dart';

import '../support/nbsp.dart';

/// ЧИСЛО И СЛОВО НЕ РВУТСЯ ПЕРЕНОСОМ (приёмка CLIENT-CONV-1c 22.09: «пройдено · 3 / минуты», «не начат · около 13 /
/// минут»). Во всех строках `.arb` с числом — неразрывный пробел (U+00A0) между числом и словом за ним, и после «·» /
/// «≈» перед числом. Число — цифра или плейсхолдер числового типа (`int`, `num`, `double`), в том числе переменная
/// plural / select; тип читается из шаблона `app_ru.arb`.
void main() {
  final template = _arb('app_ru.arb');

  Set<String> numbersOf(String key, String value) {
    final meta = template['@$key'];
    final placeholders = meta is Map<String, dynamic> ? meta['placeholders'] : null;
    final out = <String>{
      if (placeholders is Map<String, dynamic>)
        for (final e in placeholders.entries)
          if (e.value is Map && const {'int', 'num', 'double'}.contains((e.value as Map)['type'])) e.key,
    };
    for (final m in RegExp(r'\{(\w+),\s*(?:plural|select)').allMatches(value)) {
      out.add(m.group(1)!);
    }
    return out;
  }

  for (final file in ['app_ru.arb', 'app_en.arb']) {
    test('$file: между числом и словом, после «·» и «≈» перед числом — неразрывный пробел', () {
      final arb = _arb(file);
      final offences = <String>[];
      for (final entry in arb.entries) {
        if (entry.key.startsWith('@') || entry.value is! String) continue;
        final value = entry.value as String;
        final names = numbersOf(entry.key, value);
        final number = names.isEmpty ? r'\d' : '(?:\\d|\\{(?:${names.map(RegExp.escape).join('|')})\\})';
        for (final m in RegExp('$number (?=\\p{L})', unicode: true).allMatches(value)) {
          offences.add('${entry.key}: «${m.group(0)}…» — число и слово через обычный пробел');
        }
        for (final m in RegExp('[·≈] (?=$number)', unicode: true).allMatches(value)) {
          offences.add('${entry.key}: «${m.group(0)}…» — после знака перед числом обычный пробел');
        }
      }
      expect(offences, isEmpty, reason: offences.join('\n'));
    });

    // ПРАВИЛО: склейка «a · b», собранная кодом, у которой вторая часть начинается с числа («· 3 минуты», «· 8»,
    // «· 2 сцены»), держит число при точке — `planWindowJoinNumber`; обычная склейка — через обычный пробел.
    // ЛОВИТ: «пройдено · / 3 минуты» — перенос между точкой и числом, когда число собрано кодом из двух строк.
    test('$file: склейка «a · N» — неразрывный пробел после «·», «a · b» — обычный', () {
      expect(_arb(file)['planWindowJoinNumber'], '{first} ·$nbsp{second}');
      expect(_arb(file)['planWindowJoin'], '{first} · {second}');
    });

    // ПРАВИЛО (30-6, приёмка 22.09, третий заход): хвост заголовка итога этапа «пройдено · N минут» неразрывен целиком —
    // неразрывные пробелы вокруг «·», — а тире держится при имени этапа: строка переносится только после тире.
    // ЛОВИТ: «Говорю сам — пройдено / · 6 минут» (кадр 21 третьего захода) и тире в начале строки.
    test('$file: 30-6 — «·» склеена с обеих сторон, тире — с именем этапа', () {
      final arb = _arb(file);
      expect(arb['planSessionPassedMinutes'], '{title}$nbsp·$nbsp{minutes}');
      for (final key in arb.keys.where((k) => k.startsWith('planSessionPassed') && k != 'planSessionPassedMinutes')) {
        final title = arb[key] as String;
        expect(title.contains(' —'), isFalse, reason: '$key: «$title» — тире после обычного пробела');
      }
    });
  }

  // ПРАВИЛО: склейку «a · b» код собирает одним хелпером `planDot`: число во второй части держится при точке, слово —
  // нет; тот же вид даёт тестам `nb()`.
  // ЛОВИТ: «пройден · / 9 минут» в строке состояния дня (37-1, 37-2) и «идёт · около» с точкой, приклеенной к слову.
  test('planDot: «пройден · 9 минут» — число при точке, «идёт · около 5 минут» — точка отдельно', () {
    final l = lookupAppLocalizations(const Locale('ru'));
    final done = l.planDot('пройден', l.planMinutesCount(9));
    expect(done, 'пройден ·${nbsp}9$nbspминут');
    expect(done, nb('пройден · 9 минут'));
    final going = l.planDot('идёт', l.planTalkEntryMinutes(5));
    expect(going, 'идёт · около 5$nbspминут');
    expect(going, nb('идёт · около 5 минут'));
  });

  // ПРАВИЛО (наряд FIX-3 §8): СТРОКА СЕРВЕРА печатается как пришла — неразрывный пробел телефон в неё не вставляет,
  // даже когда она начинается с числа. Это его типографика для СВОИХ чисел, и правка чужого текста — уже ложь о том,
  // что прислал сервер (строки с числом сервер шлёт обычным пробелом: `until_phrase`, `highlights`, `route_summary`).
  // ЛОВИТ: «Разговор ·<nbsp>5 minutes, please.» — склейку, наведённую на текст урока.
  test('planDotPlain: строка сервера с числом склеивается обычным пробелом', () {
    final l = lookupAppLocalizations(const Locale('ru'));
    expect(l.planDotPlain('Разговор', '5 minutes, please.'), 'Разговор · 5 minutes, please.');
    expect(l.planDotPlain('Разговор', 'Приём у врача'), 'Разговор · Приём у врача');
    expect(l.planDot('Разговор', '5 minutes, please.'), 'Разговор ·${nbsp}5 minutes, please.',
        reason: 'своя склейка числа — по-прежнему неразрывная');
  });
}

Map<String, dynamic> _arb(String name) => jsonDecode(File('lib/l10n/$name').readAsStringSync()) as Map<String, dynamic>;
