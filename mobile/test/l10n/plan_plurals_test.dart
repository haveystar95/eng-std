import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';

/// КАНОН НАРЯДА PLAN-UI, §5: формы числа таблицы текстов канваса «План» —
/// «1 карточка / 2 карточки / 5 карточек», дни, ситуации, фразы, слова, повторения.
///
/// Каждая строка ниже — ровно та форма, которую называет колонка «Формы числа» таблицы; тест
/// падает, когда ARB отдаёт «5 карточки» или «2 дней», и молчит про всё, чего таблица не
/// называет.
void main() {
  late AppLocalizations l;

  setUpAll(() async {
    l = await AppLocalizations.delegate.load(const Locale('ru'));
  });

  test('карточки — 1 карточка / 2 карточки / 5 карточек / 21 карточка', () {
    expect(l.planCardsCount(1), '1 карточка');
    expect(l.planCardsCount(2), '2 карточки');
    expect(l.planCardsCount(5), '5 карточек');
    expect(l.planCardsCount(21), '21 карточка');
    expect(l.planCardsCount(75), '75 карточек');
  });

  test('минуты — 1 минута / 2 минуты / 5 минут', () {
    expect(l.planMinutesCount(1), '1 минута');
    expect(l.planMinutesCount(2), '2 минуты');
    expect(l.planMinutesCount(20), '20 минут');
  });

  test('дни — 1 день / 2 дня / 5 дней', () {
    expect(l.planDaysCount(1), '1 день');
    expect(l.planDaysCount(2), '2 дня');
    expect(l.planDaysCount(7), '7 дней');
    expect(l.planDaysCount(11), '11 дней');
  });

  test('новые слова — 1 новое слово / 2 новых слова / 5 новых слов', () {
    expect(l.planNewWordsCount(1), '1 новое слово');
    expect(l.planNewWordsCount(2), '2 новых слова');
    expect(l.planNewWordsCount(8), '8 новых слов');
  });

  test('«1 карточка вернётся / 2 карточки вернутся / 5 карточек вернутся» — глагол склоняется', () {
    expect(l.planClosedReturn(3, 1), '1 карточка вернётся в день 3 →');
    expect(l.planClosedReturn(3, 3), '3 карточки вернутся в день 3 →');
    expect(l.planClosedReturn(3, 5), '5 карточек вернутся в день 3 →');
  });

  test('состав длины плана — ситуации и повторения склоняются (22-3a)', () {
    expect(l.planEntryDaysScenes(1), '1 ситуация');
    expect(l.planEntryDaysScenes(3), '3 ситуации');
    expect(l.planEntryDaysScenes(6), '6 ситуаций');
    expect(l.planEntryDaysReviews(1), '1 повторение');
    expect(l.planEntryDaysReviews(2), '2 повторения');
    expect(l.planEntryDaysReviews(5), '5 повторений');
  });

  test('«через N день/дня/дней» у ближней даты (22-3b)', () {
    expect(l.planEntryDateIn('четверг', 1), 'четверг · через 1 день');
    expect(l.planEntryDateIn('четверг', 3), 'четверг · через 3 дня');
    expect(l.planEntryDateIn('четверг', 7), 'четверг · через 7 дней');
  });

  test('составные строки собираются из форм, а не дублируют их', () {
    expect(
      l.planClosedCount(l.planCardsCount(75), l.planMinutesCount(19)),
      '75 карточек · 19 минут',
    );
    expect(l.planPlateStageSubStart(l.planNewWordsCount(8)), 'начни отсюда · 8 новых слов');
    expect(l.planOverdueMeta(l.planDaysCount(4), 7), 'Пройдено 4 дня из 7');
  });
}
