import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import '../support/nbsp.dart';

/// СТРОКА НЕ КОНЧАЕТСЯ ОДНОБУКВЕННЫМ СЛОВОМ И НЕ НАЧИНАЕТСЯ С ТИРЕ (доработка CLIENT-START п. 1: «Правила и /
/// Конфиденциальность», «говорить: в / «Диалоге»», «Живой разговор с ИИ / — не по сценарию.», «сказал, и / отвечает»,
/// «ничего не / пропадёт», «Быт и / город»). Во всех строках наряда CLIENT-START — старт, вход, листы «зачем»,
/// предразрешения, профиль, хвосты плана — неразрывный пробел (U+00A0) после однобуквенного слова («в», «и», «с», «к»,
/// «о», «у», «а», «я») и после «не», и перед «—»; жёсткий перенос — только после тире. В en — после «a», «A», «I» и
/// перед «—».
void main() {
  for (final (file, one) in [('app_ru.arb', r'[а-яёА-ЯЁ]|[Нн]е'), ('app_en.arb', 'a|A|I')]) {
    test('$file: строки CLIENT-START — неразрывный пробел после однобуквенного слова и «не» и перед «—»', () {
      final offences = <String>[];
      for (final MapEntry(:key, :value) in _arb(file).entries) {
        if (key.startsWith('@') || value is! String || !_ofTheWorkOrder(key)) continue;
        for (final m in RegExp('(?<![\\p{L}\\p{N}-])(?:$one)[ \\n]', unicode: true).allMatches(value)) {
          offences.add('$key: «${m.group(0)!.trim()}» — слово и следующее через обычный пробел или перенос');
        }
        if (value.contains(' —')) offences.add('$key: «… —» — тире после обычного пробела');
        if (RegExp(r'(^|\n)\s*—').hasMatch(value)) offences.add('$key: строка начинается с тире');
      }
      expect(offences, isEmpty, reason: offences.join('\n'));
    });
  }

  // ЛОВИТ: хелпер тестов, который рисует строку не так, как `.arb`, — ожидания разошлись бы с приложением молча.
  test('nbTypo: «Войти с Google», «Не сейчас», «Правила и Конфиденциальность», «Язык — под каждый разговор»', () {
    expect(nbTypo('Войти с Google'), 'Войти с${nbsp}Google');
    expect(nbTypo('Не сейчас'), 'Не$nbspсейчас');
    expect(nbTypo('Правила и Конфиденциальность'), 'Правила и$nbspКонфиденциальность');
    expect(nbTypo('говорить: в «Диалоге», «Говорю сам» и в разговоре'), 'говорить: в$nbsp«Диалоге», «Говорю сам» и$nbspв$nbspразговоре');
    expect(nbTypo('Язык — под каждый разговор'), 'Язык$nbsp— под каждый разговор');
    expect(nbTypo('Напомнить про день 2 завтра в 19:00?'), 'Напомнить про день 2$nbspзавтра в${nbsp}19:00?');
    expect(nbTypo('Once a day'), 'Once a${nbsp}day');
  });
}

/// The work order's own strings: its screens' prefixes (every key under them is CLIENT-START's) and the plan's tails
/// it wrote (§6) with the rules of the talk's entry they stand beside (37-5).
bool _ofTheWorkOrder(String key) => const ['start', 'intro', 'account', 'mic', 'notify'].any(key.startsWith) || _keys.contains(key);

const _keys = {
  'profileNativeLangConfirmBody',
  'planPlateFailedTitle',
  'planPlateNoNetwork',
  'planPlateBySubscription',
  'planPlateOpensWithSubscription',
  'planPlateSubscription',
  'planRouteMetaOpensWithSubscription',
  'planRouteMetaBySubscription',
  'planKitLabel',
  'planKitSub',
  'planKitAll',
  'planKitCollapse',
  'planEntrySubscriptionTitle',
  'planEntryActiveLimitTitle',
  'planEntryToTab',
  'planTalkEntryRuleStart',
  'planTalkEntryRuleStartRole',
};

Map<String, dynamic> _arb(String name) => jsonDecode(File('lib/l10n/$name').readAsStringSync()) as Map<String, dynamic>;
