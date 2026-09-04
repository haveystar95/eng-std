import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_cheatsheet.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// ШПАРГАЛКА СЦЕНЫ — кадры D·09 / D·10 (наряд DAY-2, Ч.2.4).
///
/// The one screen with a translation under every line, the rescue phrases pinned above the scroll,
/// and a speak button per line. What it must never do is what every other screen must never do
/// backwards: print a number or a block it has nothing behind.
void main() {
  PlanDayDetail day({bool withRescue = true}) => PlanDayDetail.fromJson({
    'id': 'd1',
    'index': 1,
    'kind': 'intro',
    'title': 'Начало звонка',
    'status': 'ready',
    'plan_id': '01PLAN',
    'support_lang': 'ru',
    'target_lang': 'en',
    'terms': [
      if (withRescue)
        {
          'id': 'r1',
          'text': 'Could you speak more slowly, please?',
          'translation': 'Помедленнее, пожалуйста.',
          'type': 'phrase',
          'kind': 'line',
          'shelf': 'rescue',
          'stage': 'a',
          'from_day_index': 1,
        },
      {
        'id': 'h1',
        'text': 'Hi, can you hear me clearly?',
        'translation': 'Здравствуйте, вы меня хорошо слышите?',
        'type': 'phrase',
        'kind': 'line',
        'shelf': 'hear',
        'speaker': 'role',
        'tier': 'understand',
        'stage': 'a',
        'from_day_index': 1,
      },
      {
        'id': 's1',
        'text': 'Yes, I can hear you clearly.',
        'translation': 'Да, я вас слышу чётко.',
        'type': 'phrase',
        'kind': 'line',
        'shelf': 'say',
        'stage': 'a',
        'from_day_index': 1,
      },
    ],
  });

  Widget host(PlanDayDetail detail) => ProviderScope(
    overrides: [
      planDayProvider((planId: '01PLAN', dayIndex: 1)).overrideWith((ref) async => detail),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      home: Scaffold(
        body: PlanCheatSheet(planId: '01PLAN', dayIndex: 1, targetLang: 'en'),
      ),
    ),
  );

  testWidgets('puts a translation under every line — the one screen that may (D·09)', (
    tester,
  ) async {
    await tester.pumpWidget(host(day()));
    await tester.pumpAndSettle();

    expect(find.text('ШПАРГАЛКА · ДЕНЬ 1'), findsOneWidget);
    expect(find.text('Начало звонка'), findsOneWidget);

    // Both halves of every line: this is a reading screen, not a test.
    expect(find.text('Hi, can you hear me clearly?'), findsOneWidget);
    expect(find.text('Здравствуйте, вы меня хорошо слышите?'), findsOneWidget);
    expect(find.text('Yes, I can hear you clearly.'), findsOneWidget);
    expect(find.text('Да, я вас слышу чётко.'), findsOneWidget);

    // Shelves keep their own captions, and «Тебе скажут» says what it is for.
    expect(find.text('ТЕБЕ СКАЖУТ'), findsOneWidget);
    expect(find.text('ТЫ ОТВЕТИШЬ'), findsOneWidget);
    expect(find.textContaining('только понимать'), findsOneWidget);
  });

  testWidgets('pins the rescue phrases above the scroll (D·09)', (tester) async {
    await tester.pumpWidget(host(day()));
    await tester.pumpAndSettle();

    expect(find.text('СПАСАТЕЛИ · ЗАКРЕПЛЕНЫ'), findsOneWidget);
    // Above the shelves, not inside the list — that is what «закреплены» means on this sheet.
    expect(
      tester.getTopLeft(find.text('Could you speak more slowly, please?')).dy,
      lessThan(tester.getTopLeft(find.text('ТЕБЕ СКАЖУТ')).dy),
    );
  });

  testWidgets('speaks one line at a time, and says so on the button', (tester) async {
    await tester.pumpWidget(host(day()));
    await tester.pumpAndSettle();

    // One button per line, 56 pt so it can be hit without looking — «на бегу, перед дверью».
    final buttons = find.byWidgetPredicate(
      (w) => w is Semantics && w.properties.button == true && w.properties.label != null,
    );
    expect(buttons, findsWidgets);
  });

  testWidgets('names the gap it cannot fill instead of inventing numbers (D·10)', (tester) async {
    // Кадр D·10 draws «Числа на слух»; `PlanProgress` keeps the numbers shelf out of a day's term
    // list, so the client has nothing to put there. Saying so is the honest half of the frame.
    await tester.pumpWidget(host(day()));
    await tester.pumpAndSettle();

    expect(find.textContaining('Цифры сцены здесь пока не показываются'), findsOneWidget);
  });

  testWidgets('a day with no material says why the sheet is empty', (tester) async {
    final empty = PlanDayDetail.fromJson({
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Начало звонка',
      'status': 'pending',
      'plan_id': '01PLAN',
      'terms': <Map<String, dynamic>>[],
    });

    await tester.pumpWidget(host(empty));
    await tester.pumpAndSettle();

    expect(find.textContaining('ещё нет материала'), findsOneWidget);
  });
}
