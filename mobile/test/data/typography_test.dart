import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/typography.dart';

import '../support/nbsp.dart';

/// THE TYPOGRAPHY RULE (наряд CLIENT-22-1 §2) on the work order's canon and at its edges.
void main() {
  String ru(String s) => typeset(s, 'ru');

  // THE CANON: none of these tears at a short word.
  test('ru: «…именно под него», «на те дни», «Поговори с регистратором», «Отвечай и спрашивай сам»', () {
    expect(ru('Назови событие — план соберётся именно под него.'), 'Назови событие$nbsp— план соберётся именно под$nbspнего.');
    expect(ru('Ровно на те дни, что остались до события.'), 'Ровно на$nbspте$nbspдни, что остались до$nbspсобытия.');
    expect(ru('Поговори с регистратором'), 'Поговори с$nbspрегистратором');
    expect(ru('Отвечай и спрашивай сам.'), 'Отвечай и$nbspспрашивай сам.');
  });

  // RULE: ru, uk, be — every word of one or two letters, and the listed prepositions of three letters and «из-за»,
  // whatever their case; nothing longer.
  // CATCHES: «те» left out because it is no preposition; «Для» at the start of a sentence missed; «что» bound.
  test('ru: every one- and two-letter word, «под / для / при / над / без / про / из-за», any case — nothing longer', () {
    expect(ru('В доме и в саду'), 'В$nbspдоме и$nbspв$nbspсаду');
    expect(ru('Для него и без неё'), 'Для$nbspнего и$nbspбез$nbspнеё');
    expect(ru('Из-за дождя, про врача, при входе, над столом'), 'Из-за$nbspдождя, про$nbspврача, при$nbspвходе, над$nbspстолом');
    expect(ru('мы идём, он ждёт'), 'мы$nbspидём, он$nbspждёт');
    expect(ru('что было, как есть, или нет'), 'что было, как есть, или нет');
  });

  // CATCHES: a «word» cut out of a longer one — «кто-то», «из-за» read as «за», «об'єм» as «єм».
  test('a part of a word is no word: hyphens, apostrophes, digits', () {
    expect(ru('кто-то там'), 'кто-то там');
    expect(typeset("об'єм файлу", 'uk'), "об'єм файлу");
    expect(ru('в 19:00'), 'в${nbsp}19:00');
    expect(ru('2 дня'), '2 дня', reason: 'numbers are not this rule');
  });

  test('uk and be: their own lists of the same form', () {
    expect(typeset('Поговори з лікарем про біль від удару', 'uk'), 'Поговори з$nbspлікарем про$nbspбіль від$nbspудару');
    expect(typeset('Размова з доктарам пра боль', 'be'), 'Размова з$nbspдоктарам пра$nbspболь');
  });

  // RULE (plan-api, LANG-1): the server composes its own strings in ru, uk or en — English for every other native.
  test('composedLanguage: ru, uk, en as they are, the rest English', () {
    expect([for (final c in ['ru', 'uk', 'en', 'be', 'pl', 'de-DE']) composedLanguage(c)], ['ru', 'uk', 'en', 'en', 'en', 'en']);
  });

  // RULE: every other native — one-letter words only; the dash everywhere.
  test('pl, en: one-letter words only, and the dash', () {
    expect(typeset('Rozmowa w szpitalu i na poczcie', 'pl'), 'Rozmowa w${nbsp}szpitalu i${nbsp}na poczcie');
    expect(typeset('Once a day — I am ready', 'en'), 'Once a${nbsp}day$nbsp— I${nbsp}am ready');
  });

  test('idempotent: a set text passes unchanged; a line break is no space', () {
    final once = ru('Слова, фразы, диалог: слушаешь и говоришь — ровно на те дни.');
    expect(ru(once), once);
    expect(ru('с\nними'), 'с\nними');
  });
}
