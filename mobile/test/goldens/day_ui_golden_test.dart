/// СОСТОЯНИЯ ДНЯ ПО КАДРАМ — golden-тесты наряда DAY-UI: «ответ сервера → экран».
///
/// Каждый тест — одно состояние из наряда: кадр канвы «План» (23-x) или правка «Базы» (12a, 12b,
/// 12i, 16a). Вход — сохранённые ответы backend2 (`fixtures/`), выход — `test/goldens/<кадр>.png`.
/// Таблица «кадр → снимок → расхождения» отчёта собрана по этим файлам.
library;

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_contract.dart';
import 'package:eng_std/features/plan/day/day_card_frame.dart';
import 'package:eng_std/features/plan/day/day_room_screen.dart';
import 'package:eng_std/features/plan/day/day_session_screen.dart';
import 'package:eng_std/features/plan/day/term_sheet.dart';
import 'package:eng_std/ui/ui.dart';

import 'golden_support.dart';

void main() {
  setUpAll(loadAppFonts);

  late Directory audioDir;
  late ScriptedRecognizer mic;
  setUp(() {
    audioDir = Directory.systemTemp.createTempSync('golden-audio');
    mic = ScriptedRecognizer();
  });
  tearDown(() {
    try {
      audioDir.deleteSync(recursive: true);
    } catch (_) {}
  });

  final planI = Plan.fromJson(fixture('plan-intermediate'));
  final planB = Plan.fromJson(fixture('plan-beginner'));
  DayRoom roomI() => DayRoom.fromJson(fixture('room-intermediate-d1-in-progress'));
  DayRoom roomB() => DayRoom.fromJson(fixture('room-beginner-d1-open'));
  DaySheet sheetI() => DaySheet.fromJson(fixture('sheet-intermediate-d1'));
  DaySheet sheetB() => DaySheet.fromJson(fixture('sheet-beginner-d1'));
  List<Map<String, dynamic>> cardsJson() =>
      (fixture('cards-intermediate-d1')['cards'] as List).cast<Map<String, dynamic>>();
  List<DayCard> parseCards(List<Map<String, dynamic>> list) => [
    for (final j in list) ?DayCard.fromJson(j),
  ];
  List<DayCard> cardsI() => parseCards(cardsJson());

  /// Кабинет дня против [api], снятый целиком (страница выше экрана).
  Future<GoldenApi> pumpRoom(WidgetTester tester, GoldenApi api, {Plan? plan, double height = 1900}) async {
    await setFrame(tester, height: height);
    muteNativeChannels(tester);
    await tester.pumpWidget(goldenApp(home: DayRoomScreen(plan: plan ?? planI, number: 1), api: api, recognizer: mic, audioDir: audioDir));
    await settle(tester, frames: 8);
    return api;
  }

  /// Сессия дня, открытая на первой неотвеченной карточке из [cards].
  Future<GoldenApi> pumpSession(WidgetTester tester, List<DayCard> cards, {bool start = true}) async {
    await setFrame(tester);
    muteNativeChannels(tester);
    final api = GoldenApi(plan: planI, room: roomI(), cards: cards, sheet: sheetI());
    await tester.pumpWidget(goldenApp(home: DaySessionScreen(plan: planI, room: roomI()), api: api, recognizer: mic, audioDir: audioDir));
    await settle(tester);
    if (start) {
      await tester.tap(find.byType(PrimaryButton));
      await settle(tester);
    }
    return api;
  }

  Future<void> tapText(WidgetTester tester, String text) async {
    await tester.tap(find.text(text).first);
    await settle(tester);
  }

  Future<void> tapTiles(WidgetTester tester, List<String> words) async {
    for (final w in words) {
      final tile = find.byWidgetPredicate((x) => x is WordTile && x.state == TileState.available && x.text == w).first;
      await tester.ensureVisible(tile);
      await tester.tap(tile);
      await tester.pump(const Duration(milliseconds: 60));
    }
    await settle(tester);
  }

  Future<void> tapTrayOrder(WidgetTester tester, int count) async {
    for (var i = 0; i < count; i++) {
      await tester.ensureVisible(find.byKey(ValueKey('tray-$i')));
      await tester.tap(find.byKey(ValueKey('tray-$i')));
      await tester.pump(const Duration(milliseconds: 60));
    }
    await settle(tester);
  }

  Future<void> tapMic(WidgetTester tester) async {
    await tester.tap(find.byType(RecordButton));
    await tester.pump(const Duration(milliseconds: 100));
  }

  /// Сказать [text] в открытый микрофон. Ход закрывает сторож тишины движка — не раньше 5 с от
  /// открытия ([SpeechTurnConfig.minWaitBeforeSilence]) — потом 300 мс «думаем» и вердикт.
  Future<void> say(WidgetTester tester, String text) async {
    mic.hear(text);
    await tester.pump(const Duration(milliseconds: 80));
    mic.silence();
    // Сторож 5 с + 300 мс «думаем»; дальше — 50 мс, чтобы снимок «услышали» успел до авто-ухода 600 мс.
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pump(const Duration(milliseconds: 50));
  }

  group('кабинет дня (23-0x)', () {
    testWidgets('23-0a — не начат: пять этапов без счётчиков, программа из шита', (tester) async {
      await pumpRoom(tester, GoldenApi(plan: planB, room: roomB(), cards: [], sheet: sheetB()), plan: planB);
      await expectGolden(tester, '23-0a-room-open');
    });

    testWidgets('23-0b · 23-0d — идёт, брошен на «Слова»: три цвета полосок, маркеры, стык секций', (tester) async {
      final json = cardsJson();
      final words = json.where((c) => c['stage'] == 'words').toList()..sort((a, b) => (a['position'] as int).compareTo(b['position'] as int));
      for (var i = 0; i < 12; i++) {
        words[i]['result'] = i == 5 ? 'failed' : (i == 9 ? 'hinted' : 'passed');
        words[i]['attempts'] = i == 5 ? 2 : 1;
        words[i]['returns'] = i == 5;
      }
      final room = fixture('room-intermediate-d1-in-progress');
      (room['stages'] as List)[0]['done'] = 12;
      room['day']['cards_done'] = 12;
      room['day']['minutes_spent'] = 4;
      await pumpRoom(tester, GoldenApi(plan: planI, room: DayRoom.fromJson(room), cards: parseCards(json), sheet: sheetI()), height: 2400);
      await expectGolden(tester, '23-0b-room-in-progress');
    });

    testWidgets('23-0c — закрыт: галка, три числа Literata 56, «Далось труднее всего», «В работе»', (tester) async {
      final json = cardsJson();
      var n = 0;
      for (final c in json) {
        n++;
        c['result'] = n % 11 == 0 ? 'failed' : (n % 7 == 0 ? 'hinted' : 'passed');
        c['attempts'] = n % 11 == 0 ? 2 : 1;
        c['returns'] = n % 11 == 0;
      }
      final room = fixture('room-intermediate-d1-in-progress');
      room['day']['status'] = 'closed';
      room['day']['cards_done'] = 75;
      room['day']['minutes_spent'] = 19;
      for (final s in room['stages'] as List) {
        s['done'] = s['total'];
        s['state'] = 'done';
      }
      room['metrics'] = {
        'cards_total': 75,
        'cards_done': 75,
        'minutes_spent': 19,
        'first_try_share': 0.84,
        'hardest_unit_kind': 'phrase',
        'hardest_unit_ref': 'p4',
        'hardest_unit_text': 'How much is the deposit?',
      };
      for (final u in room['program'] as List) {
        u['cards_done'] = u['cards_total'];
        u['state'] = 'passed';
      }
      await pumpRoom(tester, GoldenApi(plan: planI, room: DayRoom.fromJson(room), cards: parseCards(json), sheet: sheetI()), height: 2600);
      await expectGolden(tester, '23-0c-room-closed');
    });

    testWidgets('23-14 — шит слова', (tester) async {
      await setFrame(tester);
      muteNativeChannels(tester);
      final term = sheetI().words.first;
      await tester.pumpWidget(goldenApp(
        home: Scaffold(
          body: Builder(
            builder: (context) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                showTermSheet(context, term: term, state: SheetTermState.passed, dayNumber: 1, returnDay: 2, targetLang: 'en', partnerRole: 'Агент');
              });
              return const SizedBox.expand();
            },
          ),
        ),
        api: GoldenApi(plan: planI, room: roomI(), cards: cardsI()),
        recognizer: mic,
        audioDir: audioDir,
      ));
      await settle(tester);
      await expectGolden(tester, '23-14-sheet-word');
    });

    testWidgets('23-15 — шит фразы: ключ подчёркнут, «В разговоре», «с подсказкой» охрой', (tester) async {
      await setFrame(tester);
      muteNativeChannels(tester);
      final term = sheetI().phrases.first;
      await tester.pumpWidget(goldenApp(
        home: Scaffold(
          body: Builder(
            builder: (context) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                showTermSheet(
                  context,
                  term: term,
                  state: SheetTermState.hinted,
                  dayNumber: 1,
                  returnDay: 2,
                  targetLang: 'en',
                  partnerRole: 'Агент',
                  inTalkPartner: "Yes, it is. It's on King Street.",
                  inTalkOwn: term.textTarget,
                );
              });
              return const SizedBox.expand();
            },
          ),
        ),
        api: GoldenApi(plan: planI, room: roomI(), cards: cardsI()),
        recognizer: mic,
        audioDir: audioDir,
      ));
      await settle(tester);
      await expectGolden(tester, '23-15-sheet-phrase');
    });
  });

  group('каркас сессии (23-2, 23-9, 23-11)', () {
    testWidgets('23-2a — вход в этап «Слова»', (tester) async {
      await pumpSession(tester, cardsI(), start: false);
      await expectGolden(tester, '23-2a-stage-entry');
    });

    testWidgets('23-2b — продолжаем: «Слушаю и отвечаю», галки у пройденного', (tester) async {
      final listen = cardsI().where((c) => c.stage == DayStage.listen).toList()..sort((a, b) => a.position.compareTo(b.position));
      final cards = cardsUpTo(cardsI(), (c) => c.id == listen[6].id);
      await pumpSession(tester, cards, start: false);
      await expectGolden(tester, '23-2b-stage-resume');
    });

    testWidgets('23-9 — итог этапа «Слова закрыты»', (tester) async {
      final words = cardsI().where((c) => c.stage == DayStage.words).toList()..sort((a, b) => a.position.compareTo(b.position));
      final cards = cardsUpTo(cardsI(), (c) => c.id == words.last.id, onlyStages: [DayStage.words, DayStage.phrases]);
      await pumpSession(tester, cards);
      // Последняя карточка «Слова» закрывает этап — какого бы вида она ни была.
      final last = words.last;
      if (last.kind.isIntro) {
        await tapText(tester, 'Понятно');
      } else if (last.kind.isSpoken) {
        await tapMic(tester);
        await say(tester, last.expected.isNotEmpty ? last.expected : last.textTarget);
        await tester.pump(const Duration(milliseconds: 700));
      } else {
        await tapText(tester, last.options.firstWhere((o) => o.correct).text);
        await tapText(tester, 'Дальше');
      }
      await expectGolden(tester, '23-9-stage-done');
    });

    testWidgets('23-11 — «Продолжить позже?»', (tester) async {
      final cards = cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenQuestion);
      await pumpSession(tester, cards);
      await tester.tap(find.byType(DayCloseButton));
      await settle(tester);
      await expectGolden(tester, '23-11-exit-alert');
    });
  });

  group('слова (23-1, 23-3, 12a, 12i, 23-13)', () {
    testWidgets('23-1 — знакомство со словом', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordIntro));
      await expectGolden(tester, '23-1-word-intro');
    });

    testWidgets('23-13 — вернувшееся слово: латунная пилюля', (tester) async {
      final json = cardsJson();
      final intro = json.firstWhere((c) => c['kind'] == 'word_intro');
      intro['source'] = 'returned';
      intro['source_day_id'] = planI.days.first.id;
      await pumpSession(tester, cardsUpTo(parseCards(json), (c) => c.kind == DayCardKind.wordIntro));
      await expectGolden(tester, '23-13-word-intro-returned');
    });

    testWidgets('23-3a — произнеси, до записи', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordSay));
      await expectGolden(tester, '23-3a-say-idle');
    });

    testWidgets('23-3b — произнеси, пишу: амплитуда живёт', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordSay));
      await tapMic(tester);
      mic.speaking('avai');
      await tester.pump(const Duration(milliseconds: 100));
      await expectGolden(tester, '23-3b-say-listening');
      await mic.cancel();
      await settle(tester, frames: 4);
    });

    testWidgets('23-3c — произнеси, услышали: шалфей на слове и микрофоне', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordSay));
      await tapMic(tester);
      await say(tester, 'available');
      await expectGolden(tester, '23-3c-say-heard');
    });

    testWidgets('23-3d — произнеси, не расслышали дважды: «Ещё раз» и «Пропустить»', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordSay));
      await tapMic(tester);
      await say(tester, 'nothing like the word');
      await tapMic(tester);
      await say(tester, 'still nothing');
      await expectGolden(tester, '23-3d-say-retry-skip');
    });

    testWidgets('12a — выбор из четырёх (по определению, Intermediate)', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordChoose));
      await expectGolden(tester, '12a-choose');
    });

    testWidgets('12a — верно: маркер шалфеем и тонировка', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordChoose));
      await tapText(tester, 'available');
      await expectGolden(tester, '12a-choose-correct');
    });

    testWidgets('12a — неверно: терракота, верный тише, «Вернётся в конце этапа»', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordChoose));
      await tapText(tester, 'rent');
      await expectGolden(tester, '12a-choose-wrong');
    });

    testWidgets('12i — слово в пример', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordCloze));
      await expectGolden(tester, '12i-cloze');
    });

    testWidgets('12i — слово вставлено верно', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.wordCloze));
      await tapText(tester, 'available');
      await expectGolden(tester, '12i-cloze-correct');
    });
  });

  group('фразы (23-4, 23-5, 12b)', () {
    testWidgets('23-4 — знакомство с фразой', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseIntro));
      await expectGolden(tester, '23-4-phrase-intro');
    });

    testWidgets('23-5 — повтори вслух, до записи', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseRepeat));
      await expectGolden(tester, '23-5-repeat-idle');
    });

    testWidgets('23-5 — повтори вслух, услышали: подложка на ключе', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseRepeat));
      await tapMic(tester);
      await say(tester, 'is the flat still available');
      await expectGolden(tester, '23-5-repeat-heard');
    });

    testWidgets('12b — сборка фразы', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseAssemble));
      await expectGolden(tester, '12b-assemble');
    });

    testWidgets('12b — собрано верно: подложка шалфеем, маркер слева', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseAssemble));
      await tapTiles(tester, ['Is', 'the', 'flat', 'still', 'available']);
      await expectGolden(tester, '12b-assemble-correct');
    });

    testWidgets('12b — ошибка: терракота, лишняя плитка, верная фраза ниже', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.phraseAssemble));
      await tapTrayOrder(tester, 5);
      await expectGolden(tester, '12b-assemble-wrong');
    });
  });

  group('диалог (23-6)', () {
    testWidgets('23-6a — весь разговор, переводы открыты (Beginner)', (tester) async {
      final json = cardsJson();
      json.firstWhere((c) => c['kind'] == 'dialogue_read')['payload']['translations_collapsed'] = false;
      await pumpSession(tester, cardsUpTo(parseCards(json), (c) => c.kind == DayCardKind.dialogueRead));
      await expectGolden(tester, '23-6a-dialogue-open');
    });

    testWidgets('23-6b — переводы свёрнуты (Intermediate), один раскрыт тапом', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.dialogueRead));
      await tapText(tester, 'перевод');
      await expectGolden(tester, '23-6b-dialogue-collapsed');
    });
  });

  group('слушаю и отвечаю (23-7)', () {
    testWidgets('23-7a — «что он спросил», реплика звучит', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenQuestion));
      await expectGolden(tester, '23-7a-listen-question');
    });

    testWidgets('23-7b — верно: карточка собеседника раскрывает текст', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenQuestion));
      await tapText(tester, 'Water');
      await expectGolden(tester, '23-7b-listen-correct');
    });

    testWidgets('23-7c — неверно: объяснение, «Вернётся в конце этапа»', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenQuestion));
      await tapText(tester, 'Gas');
      await expectGolden(tester, '23-7c-listen-wrong');
    });

    testWidgets('23-7d — «ответь по-английски», три варианта', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.answerChoose));
      await expectGolden(tester, '23-7d-answer-choose');
    });

    testWidgets('23-7e — «ты начинаешь»: сборка, потом ответ собеседника', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.answerAssemble));
      await expectGolden(tester, '23-7e-answer-assemble');
      await tapTiles(tester, ['Hello', 'is', 'the', 'flat', 'still', 'available']);
      await expectGolden(tester, '23-7e-answer-assemble-replied');
    });

    testWidgets('23-7f — услышал → собери (только Intermediate)', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenAssemble));
      await expectGolden(tester, '23-7f-listen-assemble');
    });

    testWidgets('23-7g — собрано верно: подложка шалфеем, перевод под ней', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenAssemble));
      await tapTiles(tester, ['Yes', 'it', 'is', "It's", 'on', 'King', 'Street']);
      await expectGolden(tester, '23-7g-listen-assemble-correct');
    });

    testWidgets('23-7h — ошибка: терракота, лишняя плитка вздрагивает, верная реплика ниже', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.listenAssemble));
      await tapTrayOrder(tester, 7);
      await expectGolden(tester, '23-7h-listen-assemble-wrong');
    });
  });

  group('говорю сам (23-8)', () {
    testWidgets('23-8a — свой ход: блок задания, микрофон, «Подсказка»', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.speak));
      await expectGolden(tester, '23-8a-speak-idle');
    });

    testWidgets('23-8b — подсказка-ключ', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.speak));
      await tapText(tester, 'Подсказка');
      await expectGolden(tester, '23-8b-speak-hint-key');
    });

    testWidgets('23-8c — подсказка-текст: зачёт с подсказкой', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.speak));
      await tapText(tester, 'Подсказка');
      await tapText(tester, 'Подсказка');
      await expectGolden(tester, '23-8c-speak-hint-text');
    });

    testWidgets('23-8d — сказал: зачёт, ответ собеседника звучит сам', (tester) async {
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.kind == DayCardKind.speak));
      await tapMic(tester);
      await say(tester, 'hello is the flat still available');
      await expectGolden(tester, '23-8d-speak-heard');
    });

    testWidgets('23-8e — лента прошедших обменов', (tester) async {
      final speak = cardsI().where((c) => c.kind == DayCardKind.speak).toList()..sort((a, b) => a.position.compareTo(b.position));
      await pumpSession(tester, cardsUpTo(cardsI(), (c) => c.id == speak[2].id));
      await expectGolden(tester, '23-8e-speak-feed');
    });
  });
}
