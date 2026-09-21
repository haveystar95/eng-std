import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/conversation/talk_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// THE TALK'S WORDING — two defects the LIVE RUN caught on the simulator (наряд CLIENT-CONV-1a, §4):
/// both screens were drawn from real server answers, and both read wrong in Russian.
void main() {
  // ПРАВИЛО: намерение сервера встаёт в «Скажи, что …» ПРИДАТОЧНЫМ — строчная буква, без точки.
  // Слова — сервера, клиент меняет только регистр первой буквы и закрывающую точку.
  // ЛОВИТ: «Скажи, что У моего сына температура.» — так чип выглядел в живом прогоне.
  group('намерение в чипе — придаточное', () {
    test('заглавная и точка сервера не попадают внутрь чужого предложения', () {
      expect(TalkTexts.clause('У моего сына температура.'), 'у моего сына температура');
      expect(TalkTexts.clause('  Это у него уже три дня.  '), 'это у него уже три дня');
    });

    test('сокращение, вопрос и многоточие остаются как есть', () {
      expect(TalkTexts.clause('США не в страховке.'), 'США не в страховке', reason: 'аббревиатура держит заглавные');
      expect(TalkTexts.clause('Можно ли ему в школу?'), 'можно ли ему в школу?', reason: 'вопрос — часть сказанного');
      expect(TalkTexts.clause('Ну, как сказать...'), 'ну, как сказать...', reason: 'многоточие — не точка');
      expect(TalkTexts.clause('у него болит горло'), 'у него болит горло', reason: 'уже придаточное');
    });
  });

  // ПРАВИЛО: после «около» — родительный падеж; у строки свой plural, а не planMinutesCount.
  // ЛОВИТ: «около 3 минуты» — так вход 37-5 выглядел в живом прогоне.
  group('«около N минут» на входе в разговор', () {
    Future<String> minutesLine(WidgetTester tester, int minutes) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru'), Locale('en')],
          home: Scaffold(
            body: TalkEntryView(
              scene: null,
              minutes: minutes,
              rehearsal: false,
              noHints: false,
              onNoHints: (_) {},
              onStart: () {},
              onBack: () {},
            ),
          ),
        ),
      );
      return tester.widget<Text>(find.byKey(const ValueKey('talk-entry-minutes'))).data!;
    }

    testWidgets('родительный падеж после «около»', (tester) async {
      expect(await minutesLine(tester, 1), 'около 1 минуты');
      expect(await minutesLine(tester, 3), 'около 3 минут');
      expect(await minutesLine(tester, 6), 'около 6 минут');
      expect(await minutesLine(tester, 21), 'около 21 минуты');
    });
  });
}
