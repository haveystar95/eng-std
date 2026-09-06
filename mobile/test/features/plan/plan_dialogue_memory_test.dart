/// ПАМЯТЬ ДИАЛОГА ЖИВЁТ В ЭКРАНЕ, А НЕ В ОБОЛОЧКЕ — наряд DAY-FIX-2, Ч.5.1.
///
/// Оболочка разговора пересоздаётся на каждой карточке посадки, и всё, что она помнила сама
/// («реплика уже прозвучала», «текст открыт»), умирало на такте 2. Так реплика собеседника звучала
/// второй раз, какой бы ключ оболочка ни держала (DAY-2-FIX — по ходу, потом по пузырю). Теперь
/// множество прозвучавших реплик отдаёт ЭКРАН, а открытость текста выводится из ходов: карточка
/// впереди своя — значит такт 1 позади, и текст стоит открытым.
library;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/plan/plan_dialogue.dart';
import 'package:eng_std/l10n/app_localizations.dart';

void main() {
  const chain = PlanDialogue(
    dayIndex: 1,
    sceneTitle: 'На приёме',
    turns: [
      PlanDialogueTurn(
        turn: 'role',
        termId: '01HEAR',
        text: 'What brings you in today?',
        translation: 'Что вас сегодня беспокоит?',
        shelf: 'hear',
      ),
      PlanDialogueTurn(
        turn: 'you',
        termId: '01SAY',
        text: 'My lower back hurts.',
        translation: 'У меня болит поясница.',
        shelf: 'say',
      ),
    ],
  );

  Widget shell({
    required int turnIndex,
    required List<String> spoken,
    required Set<String> spokenLines,
    Set<String> dealt = const {'01HEAR', '01SAY'},
  }) => MaterialApp(
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru')],
    home: Scaffold(
      body: SingleChildScrollView(
        child: PlanDialogueShell(
          dialogue: chain,
          turnIndex: turnIndex,
          onSpeak: spoken.add,
          dealtTerms: dealt,
          spokenLines: spokenLines,
          onSpokeLine: spokenLines.add,
          card: const Text('card'),
        ),
      ),
    ),
  );

  testWidgets('такт 1 звучит один раз и записывается в память экрана', (tester) async {
    final spoken = <String>[];
    final lines = <String>{};

    await tester.pumpWidget(shell(turnIndex: 0, spoken: spoken, spokenLines: lines));
    await tester.pumpAndSettle();

    expect(spoken, ['What brings you in today?']);
    expect(lines, {'01HEAR'});
    // Текст на такте 1 скрыт — человек разбирает реплику на слух.
    expect(find.text('What brings you in today?'), findsNothing);
    expect(find.textContaining('текст скрыт'), findsOneWidget);
  });

  testWidgets('такт 2 — новая оболочка — реплику НЕ переигрывает и держит текст открытым', (
    tester,
  ) async {
    final spoken = <String>[];
    // Экран помнит: реплика уже звучала на такте 1.
    final lines = <String>{'01HEAR'};

    await tester.pumpWidget(shell(turnIndex: 1, spoken: spoken, spokenLines: lines));
    await tester.pumpAndSettle();

    // Ни звука сама по себе: только «Ещё раз».
    expect(spoken, isEmpty);
    // Такт 1 позади (карточка впереди — своя), текст реплики открыт и остаётся.
    expect(find.text('What brings you in today?'), findsOneWidget);
    expect(find.textContaining('текст скрыт'), findsNothing);
    expect(find.text('Скрыть текст'), findsNothing);

    await tester.tap(find.text('Ещё раз'));
    await tester.pumpAndSettle();
    expect(spoken, ['What brings you in today?']);
  });

  testWidgets('знакомая реплика без такта 1 остаётся скрытой до «Показать текст»', (tester) async {
    final spoken = <String>[];
    final lines = <String>{};

    // Реплика роли сегодня без карточки: разбор не нужен, но и не было — текст по запросу.
    await tester.pumpWidget(
      shell(turnIndex: 1, spoken: spoken, spokenLines: lines, dealt: const {'01SAY'}),
    );
    await tester.pumpAndSettle();

    expect(spoken, ['What brings you in today?']);
    expect(find.text('What brings you in today?'), findsNothing);
    expect(find.text('Показать текст'), findsOneWidget);
  });
}
