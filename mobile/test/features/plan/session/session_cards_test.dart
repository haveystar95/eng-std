import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/plan_models.dart' show PlanLevel;
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart' show CardListen;
import 'package:eng_std/features/plan/session/parts/session_bits.dart';
import 'package:eng_std/features/plan/session/parts/session_choice.dart';
import 'package:eng_std/features/plan/session/parts/session_tiles.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/nbsp.dart';
import '../../../support/session_harness.dart';

/// SESSION CARDS BY KIND (work order SESSION-1b §6, polish pass SESSION-1b′): each of the 15 kinds (words 6,
/// phrases 9) rendered from a server fixture card, with its «correct / wrong / pass» states and its sounds.
void main() {
  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  SessionFrameText frameLine(WidgetTester tester) => tester.widget<SessionFrameText>(find.byType(SessionFrameText).first);
  SessionTile chip(WidgetTester tester, String key) => tester.widget<SessionTile>(find.byKey(ValueKey(key)));

  group('Words (31)', () {
    testWidgets('word_intro (31-1): word, reading, translation, «In the conversation»; «Got it» → passed', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordIntro), probe, voice: voice));
      expect(find.text('Запомни слово'), findsOneWidget);
      expect(find.text('lower back'), findsOneWidget);
      expect(find.text('лоуэр бэк'), findsOneWidget);
      expect(find.text('поясница'), findsOneWidget);
      expect(find.text('the part of the back above the hips'), findsOneWidget);
      expect(find.text(nbTypo('В РАЗГОВОРЕ')), findsOneWidget);
      expect(find.text(nbTypo('У него болит поясница.')), findsOneWidget);
      await settleCard(tester);
      expect(voice.played, ['v1@1.0'], reason: 'the word plays by itself when the card appears');

      await tapText(tester, 'Понятно');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
    });

    // RULE (SESSION-1b′, item 11): a lesson card plays its word once by itself on open — the server file, without a
    // file the phone — then only by «Listen».
    // CATCHES: an intro that stays silent, one that plays again by itself, one that starts during the card change.
    testWidgets('word_intro: the word plays by itself once on open, then only by «Listen»', (tester) async {
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordIntro), CardProbe(), voice: voice));
      expect(voice.played, isEmpty, reason: 'after a short pause, not during the card change');
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['v1@1.0']);
      expect(voice.fallbacks, ['lower back'], reason: 'without a file the phone reads the word');
      await tester.pump(const Duration(seconds: 3));
      expect(voice.played, ['v1@1.0'], reason: 'once');
      await tester.tap(find.byType(CardListen).first);
      await tester.pump();
      expect(voice.played, ['v1@1.0', 'v1@1.0'], reason: '«Listen» plays it again');
      await settleCard(tester);
    });

    testWidgets('word_repeat (31-2): sample at 0.85×; heard — echo and passed; two misses — skipped, not failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordRepeat);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Скажи слово вслух'), findsOneWidget);
      expect(find.text(nbTypo('Скажи, как слышишь — регистратор поймёт')), findsOneWidget);
      expect(find.text(nbTypo('тап — говорить')), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['v1@0.85']);

      await sayDebug(tester, 'Lower back');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.heard, 'Lower back');
      expect(find.text(nbTypo('услышал — так же, как в записи')), findsOneWidget);
      expect(find.text('услышал'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1, reason: 'after a pass — auto-advance');

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await sayDebug(tester, 'lower');
      expect(miss.answers, isEmpty);
      expect(find.text(nbTypo('не расслышал, ещё раз')), findsOneWidget);
      await sayDebug(tester, 'neck');
      expect(results(miss), [SessionResult.skipped]);
      expect(miss.answers.single.attempts, 2);
      expect(find.text('Дальше'), findsOneWidget);
      await tapText(tester, 'Дальше');
      expect(miss.nexts, 1);
      await settleCard(tester);
    });

    // RULE (SESSION-2a §3): recording until the pause, not until the key — a covered phrase is graded after the pause
    // like any other, never on the partial result. And THE PAUSE GOES BY LENGTH (правка прохода 21.09, CLIENT-CONV-1b):
    // 1 s once every content word of the line is heard, 2 s while one is still missing — the silence waits for the rest.
    // CATCHES: an early stop on coverage (the owner cut mid-sentence), a pause that no longer closes a recording, a
    // learner stopping for the next word cut off after one second, and a line said through left waiting two.
    testWidgets('тишина ждёт, пока не услышаны все слова: 1 s after the whole line, up to 2 s while a word is missing', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordRepeat);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      await enterHeard(tester, 'lowerback');
      await tester.pump(const Duration(milliseconds: 950));
      expect(probe.answers, isEmpty, reason: 'covered, but the pause has not run out — still recording');
      await tester.pump(const Duration(milliseconds: 60));
      expect(results(probe), [SessionResult.passed], reason: 'glued «lowerback» covers both words — graded after the 1 s pause');
      await settleCard(tester);

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await enterHeard(tester, 'lower');
      await tester.pump(const Duration(milliseconds: 1050));
      expect(find.text(nbTypo('не расслышал, ещё раз')), findsNothing, reason: '«back» not heard yet — the silence waits for it');
      expect(miss.answers, isEmpty);
      await tester.pump(const Duration(milliseconds: 900));
      expect(find.text(nbTypo('не расслышал, ещё раз')), findsNothing, reason: 'still inside the longer pause');
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.text(nbTypo('не расслышал, ещё раз')), findsOneWidget, reason: 'the 2 s pause closes an incomplete recording');
      await settleCard(tester);
    });

    testWidgets('word_repeat without a microphone: «Microphone needed», «Skip» → skipped with no_mic', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordRepeat), probe, micAvailable: false));
      await tester.tap(find.byIcon(LucideIcons.mic).first);
      await tester.pump();
      await tester.pump(const Duration(seconds: 3));
      expect(find.text('Нужен микрофон'), findsOneWidget);
      expect(probe.noMic, isTrue);
      await tapText(tester, 'Пропустить');
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.answers.single.response?.noMic, isTrue);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    testWidgets('word_choose native_to_term (31-4): correct — passed and auto-advance; wrong — failed and «Next»', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordChoose, skip: 1);
      expect((card.payload as WordChoosePayload).termToNative, isFalse);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Выбери слово'), findsOneWidget);
      expect(find.text('ПЕРЕВОД'), findsOneWidget);
      expect(find.text('рентген'), findsOneWidget);
      await tapText(tester, 'X-ray');
      expect(results(probe), [SessionResult.passed]);
      expect(find.byIcon(LucideIcons.check), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'heating pad');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
      expect(wrong.nexts, 0, reason: 'after a wrong answer «Next» is tapped by hand');
    });

    // SESSION-1e contract: both directions come at any level — the card's `direction` decides.
    testWidgets('word_choose term_to_native (31-3): the target word with its sound — options in the native language', (tester) async {
      for (final day in [intermediate, beginner]) {
        final probe = CardProbe();
        await pumpCard(tester, probeEnv(fixtureCard(day, SessionKind.wordChoose), probe));
        expect(find.text('Выбери перевод'), findsOneWidget);
        expect(find.text('СЛОВО'), findsOneWidget);
        expect(find.text('fever'), findsOneWidget);
        await tapText(tester, 'температура');
        expect(results(probe), [SessionResult.passed]);
        await settleCard(tester);
      }
    });

    // SESSION-1e contract: «By ear» is sound → translation — the options are native, drawn like 31-3's; without a
    // file the phone reads the day's word, never the correct option.
    // CATCHES: Russian options in the target-word face; a fallback that reads the answer aloud.
    testWidgets('word_listen (31-5): sound on open, the native options are silent; correct / wrong', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordListen);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice, day: intermediate));
      expect(find.text('Выбери, что услышал'), findsOneWidget);
      expect(find.text(nbTypo('Что ты услышал?')), findsOneWidget);
      expect(tester.widgetList<SessionOption>(find.byType(SessionOption)).map((o) => o.target), everyElement(isFalse));
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['v4@1.0']);
      expect(voice.fallbacks, ['muscle strain'], reason: 'without a file the phone reads the word, not «растяжение мышцы»');
      await tapText(tester, 'растяжение мышцы');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'грелка');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    testWidgets('word_assemble (31-6): tiles in order — passed; out of order — failed and «Next»', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordAssemble);
      final p = card.payload as WordAssemblePayload;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text(nbTypo('Собери из частей')), findsOneWidget);
      expect(find.text(nbTypo('по частям')), findsOneWidget);
      Future<void> tray(String word) async {
        final key = ValueKey('tray-${p.tiles.indexOf(word)}');
        await tester.ensureVisible(find.byKey(key));
        await tester.tap(find.byKey(key));
        await tester.pump();
      }

      await tray('lower');
      await tray('back');
      await tapText(tester, 'Проверить');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.mode, 'tiles');
      await settleCard(tester);
      expect(probe.nexts, 1);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tray('back');
      await tray('lower');
      await tapText(tester, 'Проверить');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    testWidgets('word_in_line (31-7): a line with a slot and its full translation; correct — passed; wrong — failed', (tester) async {
      // The texts are the fixture's own (наряд CLIENT-22-1 §4): the line, its translation and the options as the server
      // sends them today — the lesson is rewritten with every generation, the card's behaviour is what stays.
      final card = fixtureCard(intermediate, SessionKind.wordInLine);
      final p = card.payload as WordInLinePayload;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text(nbTypo('Вставь слово в окно')), findsOneWidget);
      expect(find.text(nt(p.line.textNative)), findsOneWidget, reason: 'the full translation, set by the learner\'s typography');
      await tapText(tester, p.options.firstWhere((o) => o.id == p.correct).text);
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, p.options.firstWhere((o) => o.id != p.correct).text);
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });
  });

  group('Phrases (32)', () {
    // RULE (SESSION-1b′, item 3): a lesson card — «Look and listen»; the frame opens with the filler said in the
    // dialogue; «this part can change» above neutral chips; a chip substitutes and voices its filler, the chip does
    // not darken — the slot flashes for 600 ms; «Got it» → passed.
    // CATCHES: an empty slot on open, a chip that turns ink as if it were an answer, a flash that never ends.
    // RULE (SESSION-2b §1, кадр 32-1): a meaning is ALWAYS in the slot and its chip is ink; the reading and the
    // translation change with it; the grey «this part can change» and the «FRAME» eyebrow are gone.
    // CATCHES: a chip that does not darken (the old neutral row), a reading or a translation left at the filler the
    // ПРАВИЛО (кадр 32-1, приёмка снимков CLIENT-CONV-1a): 32-1 — КАРТОЧКА-УРОК, здесь ничего не выбирают. Значения —
    // нейтральные серые плашки во всю ширину: слово Literata 17 и перевод под ним. Ни одна не выделена — даже та, что
    // стоит в окне, — и ни одна не кнопка. Лист — ОДИН бумажный: каркас с окном, чтение, перевод, «прослушать» 44.
    // ЛОВИТ: чипы-выбор (первый — чернильный, как выбранный), обведённую плашку значения в окне и лист, разрезанный на
    // плашку фона и бумажный подвал.
    testWidgets('32-1: значения — нейтральные плашки во всю ширину, ничего не выбрано', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = fixtureCard(intermediate, SessionKind.phraseIntro);
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text(nbTypo('Посмотри и послушай')), findsOneWidget);
      expect(find.text('эту часть можно менять'), findsOneWidget);
      expect(find.byType(SessionTile), findsNothing, reason: 'ни одного чипа на уроке');
      final sheet = find.byKey(const ValueKey('lesson-sheet'));
      final width = tester.getRect(sheet).width;
      for (final f in (card.payload as PhraseIntroPayload).frame.fillers) {
        final plate = find.byKey(ValueKey('meaning-${f.index}'));
        expect(tester.getRect(plate).width, moreOrLessEquals(width, epsilon: 0.5), reason: 'во всю ширину');
        final word = find.descendant(of: plate, matching: find.text(f.target));
        expect(word, findsOneWidget);
        expect(tester.widget<Text>(word).style!.fontSize, 17, reason: 'слово Literata 17');
        expect(tester.widget<Text>(word).style!.fontFamily, AppFonts.literata);
        expect(find.descendant(of: plate, matching: find.text(nt(f.native))), findsOneWidget, reason: 'перевод под словом');
        final box = tester.widget<Container>(plate).decoration! as BoxDecoration;
        expect(box.color, AppColors.meaningPlate, reason: 'нейтральная серая плашка');
        expect(box.border, isNull, reason: 'ничего не обведено — и то, что в окне, тоже');
      }
      expect(frameLine(tester).slot, 'lower back', reason: 'в окне — значение, которое говорит диалог');
      expect(find.descendant(of: sheet, matching: find.byType(SessionFrameText)), findsOneWidget, reason: 'каркас — в листе');
      expect(find.descendant(of: sheet, matching: find.byKey(const ValueKey('lesson-reading'))), findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.text(nbTypo('У него болит поясница.'))), findsOneWidget);
      final listen = find.descendant(of: sheet, matching: find.byType(SessionListenButton));
      expect(tester.widget<SessionListenButton>(listen).size, 44, reason: '«прослушать» 44 в углу листа');

      await tapText(tester, 'Дальше');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b; кадр 32-1 «значение выбрано», наряд CLIENT-CONV-1c §8): тап по
    // значению СТАВИТ его в окно и говорит фразу с ним — так урок показывает, что «эту часть можно менять». Тапнутая
    // плашка — бумага с тонким контуром латуни (1.5), остальные нейтральные; размер плашки тот же. Это не ответ:
    // карточка ничего не пишет до «Дальше» и кончается `passed`, что бы ни трогали. Тап раньше автозапуска отменяет его —
    // звучит то, что в окне; «прослушать» дальше играет тоже то, что в окне.
    // ЛОВИТ: немую плашку, тапнутую плашку без третьего состояния кадра, две выделенные плашки, ответ, отправленный тапом,
    // фразу диалога поверх тапнутой.
    testWidgets('32-1: тап по значению ставит его в окно и говорит фразу с ним — это не ответ', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = fixtureCard(intermediate, SessionKind.phraseIntro);
      final p = card.payload as PhraseIntroPayload;
      final other = p.frame.fillers.firstWhere((f) => f.index != p.said.fillerIndex);
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(frameLine(tester).slot, 'lower back');

      await tester.tap(find.byKey(ValueKey('meaning-${other.index}')));
      await tester.pump();
      expect(frameLine(tester).slot, other.target, reason: 'значение встало в окно');
      expect(voice.played, ['${other.audio!.ref}@1.0'], reason: 'звучит фраза с этим значением — сразу, по тапу');
      expect(voice.fallbacks, ['It hurts in his neck.'], reason: 'без файла телефон читает каркас с этим значением');
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-native'))).data, nbTypo('У него болит шея.'));
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-reading'))).data, 'ит хёртс ин хиз нэк');
      expect(probe.answers, isEmpty, reason: 'тап — не ответ');
      BoxDecoration boxOf(int index) => tester.widget<Container>(find.byKey(ValueKey('meaning-$index'))).decoration! as BoxDecoration;
      final box = boxOf(other.index);
      expect(box.color, AppColors.paper, reason: 'тапнутая плашка — бумага');
      expect(box.border, Border.all(color: AppColors.brassInk, width: 1.5), reason: 'с тонким контуром латуни');
      for (final f in p.frame.fillers.where((f) => f.index != other.index)) {
        expect(boxOf(f.index).border, isNull, reason: 'остальные нейтральные');
        expect(boxOf(f.index).color, AppColors.meaningPlate);
      }
      expect(tester.getSize(find.byKey(ValueKey('meaning-${other.index}'))).height,
          tester.getSize(find.byKey(ValueKey('meaning-${p.said.fillerIndex}'))).height, reason: 'контур не меняет размер плашки');

      await tester.pump(const Duration(seconds: 3));
      expect(voice.played, hasLength(1), reason: 'автозапуск фразы диалога отменён тапом');
      await tester.tap(find.descendant(of: find.byKey(const ValueKey('lesson-sheet')), matching: find.byType(SessionListenButton)));
      await tester.pump();
      expect(voice.played.last, '${other.audio!.ref}@1.0', reason: '«прослушать» играет то, что в окне');

      await tester.tap(find.byKey(ValueKey('meaning-${p.said.fillerIndex}')));
      await tester.pump();
      expect(frameLine(tester).slot, 'lower back');
      expect(voice.played.last, '${p.said.audio!.ref}@1.0', reason: 'значение диалога — своим файлом фразы');
      expect(boxOf(p.said.fillerIndex!).border, isNotNull, reason: 'выбрано то, что тапнули последним');
      expect(boxOf(other.index).border, isNull, reason: 'одна выделенная плашка');

      await tapText(tester, 'Дальше');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 32-1, третье состояние): одно значение — фраза целиком, окна нет, плашек нет,
    // строка-суть говорит это словами, а под ней «В разговоре» — обмен, в котором фраза звучит.
    // ЛОВИТ: окно с одной плашкой под ним; блок «В разговоре», нарисованный без реплики дня.
    testWidgets('32-1: одно значение — фраза целиком, строка-суть и «В разговоре»', (tester) async {
      final one = fixtureCardEdited('day-doctor', 'phrase_intro', (p) {
        final frame = p['frame'] as Map<String, dynamic>;
        final slot = frame['slot'] as Map<String, dynamic>;
        slot['fillers'] = [(slot['fillers'] as List).first];
      });
      await pumpCard(tester, probeEnv(one, CardProbe(), day: intermediate));
      expect(frameLine(tester).window, isFalse, reason: 'окна нет: менять нечего');
      expect(frameLine(tester).before, 'It hurts in his lower back.');
      expect(find.byKey(const ValueKey('meaning-plates')), findsNothing);
      expect(find.text(nbTypo('Эту фразу говорят целиком — в ней ничего не меняется')), findsOneWidget);
      expect(find.text(nbTypo('В РАЗГОВОРЕ')), findsOneWidget);
      expect(find.byKey(const ValueKey('in-talk-partner')), findsOneWidget);
      expect(find.byKey(const ValueKey('in-talk-learner')), findsOneWidget);
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 32-7, приёмка снимков CLIENT-CONV-1a): лист «Скажи целиком» — по кадру: бровь «ФРАЗА», каркас с
    // окном, чтение фразы с этим значением, родное предложение и «прослушать» 44 в правом нижнем углу листа — он
    // играет фразу с этим значением (файл сервера у наполнения). На своём слове окно пустое, чтение держит пробел
    // сервера `___`, как и родное предложение под ним.
    // ЛОВИТ: лист без чтения и без звука — 32-7 до приёмки.
    testWidgets('32-7: лист по кадру — бровь, каркас с окном, чтение, перевод, «прослушать» 44 в углу', (tester) async {
      final voice = QuietVoice();
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final payload = card.payload as PhraseOtherSlotPayload;
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: voice));
      final sheet = find.byKey(const ValueKey('lesson-sheet'));
      expect(find.descendant(of: sheet, matching: find.text('ФРАЗА')), findsOneWidget, reason: 'бровь «Фраза»');
      expect(frameLine(tester).window, isTrue);
      final first = payload.frame.filler(payload.rounds.first.fillerIndex)!;
      expect(frameLine(tester).slot, first.target);
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-reading'))).data, 'ит хёртс ин хиз лоуэр бэк');
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-native'))).data, nt(payload.rounds.first.taskNative));
      final listen = find.descendant(of: sheet, matching: find.byType(SessionListenButton));
      expect(tester.widget<SessionListenButton>(listen).size, 44);
      final box = tester.getRect(sheet);
      expect(box.right - tester.getRect(listen).right, moreOrLessEquals(24, epsilon: 0.5), reason: 'в правом углу листа');
      expect(box.bottom - tester.getRect(listen).bottom, moreOrLessEquals(24, epsilon: 0.5), reason: 'внизу листа');
      await tester.tap(listen);
      await tester.pump();
      expect(voice.played.last, '${first.audio!.ref}@1.0', reason: 'играет фраза с этим значением');

      for (final round in payload.rounds) {
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(find.text(nbTypo('а теперь со своим словом')), findsOneWidget);
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-reading'))).data, payload.frame.framePronunciationNative);
      expect(tester.widget<Text>(find.byKey(const ValueKey('lesson-native'))).data, nt(payload.ownRound!.taskNative));
      expect(find.descendant(of: sheet, matching: find.byType(SessionListenButton)), findsOneWidget, reason: '«прослушать» и на своём слове');
      await settleCard(tester);
    });

    // ПРАВИЛО (SESSION-1b′, п. 11): урок каркаса проигрывает фразу так, как её говорит диалог, ОДИН
    // раз при открытии — дальше только «Прослушать».
    // ЛОВИТ: немой урок и повтор сам по себе.
    testWidgets('32-1: фраза звучит сама один раз, дальше — только «Прослушать»', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseIntro);
      final said = (card.payload as PhraseIntroPayload).said;
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: voice));
      expect(voice.played, isEmpty);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['${said.audio!.ref}@1.0']);
      expect(voice.fallbacks, [said.textTarget], reason: 'без файла фразу читает телефон');
      await tester.pump(const Duration(seconds: 3));
      expect(voice.played, ['${said.audio!.ref}@1.0'], reason: 'само по себе больше ничего не звучит');
      await settleCard(tester);
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1a, кадр 32-4): у вариантов «прослушать» НЕТ — задание здесь стоит
    // переводом над карточкой, и кружок предлагал послушать ответ до того, как его выбрали.
    // ЛОВИТ: возврат «прослушать» на варианты «Вставь в окно».
    testWidgets('32-4: у вариантов нет «прослушать»', (tester) async {
      final beginner = sessionFixture('day-doctor-beginner');
      final card = fixtureCard(beginner, SessionKind.phraseSlot);
      await pumpCard(tester, probeEnv(card, CardProbe(), day: beginner, level: PlanLevel.beginner));
      expect(find.text(nbTypo('Вставь в окно')), findsOneWidget);
      final options = (card.payload as PhraseSlotPayload).options;
      for (final o in options) {
        expect(find.byKey(ValueKey('option-${o.id}')), findsOneWidget);
      }
      expect(find.byType(SessionListenButton), findsNothing, reason: 'звук здесь и есть задание');
      await settleCard(tester);
    });

    // ПРАВИЛО (кадр 32-7): «своё слово» — ПОСЛЕДНЯЯ плашка ряда значений, а не пустое место; ряд
    // показывает, где ты сейчас, во всех кругах. В покое микрофон стоит без кольца.
    // ЛОВИТ: ряд, кончающийся галками, — последний круг без своего места на экране.
    testWidgets('32-7: «своё слово» — последняя плашка, микрофон в покое без кольца', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      await pumpCard(tester, probeEnv(card, CardProbe(), day: intermediate));
      expect(find.byKey(const ValueKey('chip-own')), findsOneWidget);
      expect(chip(tester, 'chip-own').selected, isFalse, reason: 'круг своего слова ещё впереди');

      final rounds = (card.payload as PhraseOtherSlotPayload).rounds;
      for (final r in rounds) {
        await sayDebug(tester, r.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(chip(tester, 'chip-own').selected, isTrue, reason: 'дошли до своего слова — плашка угольная');
      await settleCard(tester);
    });

    testWidgets('phrase_assemble (32-2): words, slot and filler — passed; another filler — failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseAssemble);
      final p = card.payload as PhraseAssemblePayload;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Собери фразу'), findsOneWidget);
      expect(find.text(nbTypo('У него болит шея.')), findsOneWidget);
      Future<void> tray(int i) async {
        await tester.ensureVisible(find.byKey(ValueKey('tray-$i')));
        await tester.tap(find.byKey(ValueKey('tray-$i')));
        await tester.pump();
      }

      Future<void> build(int fillerIndex) async {
        for (final w in p.expectedWords) {
          await tray(p.tiles.indexOf(w));
        }
        await tray(p.tiles.length + p.chips.indexWhere((f) => f.index == fillerIndex));
      }

      await build(1);
      expect(find.text('It'), findsOneWidget, reason: 'the first word of the assembled row is capitalized');
      await tapText(tester, 'Проверить');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.fillerIndex, 1);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await build(0);
      await tapText(tester, 'Проверить');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b): плитка не играет звук. Тап по плитке и по значению только
    // СТАВИТ их в ряд — фраза звучит после «Проверить»: верно — собранная фраза, и карточка уходит, когда она сказана
    // (не раньше обычных 600 мс); неверно — фраза, которую просили, рядом с ошибкой, и «Дальше» руками.
    // ЛОВИТ: фразу, прочитанную вслух в момент, когда значение легло в окно, — ответ звучал до проверки.
    testWidgets('плитка не играет звук: 32-2 — фраза звучит только после «Проверить»', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseAssemble);
      final p = card.payload as PhraseAssemblePayload;
      final voice = QuietVoice();
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      await settleCard(tester);
      expect(voice.played, isEmpty, reason: 'сборка не звучит сама');

      Future<void> tray(int i) async {
        await tester.ensureVisible(find.byKey(ValueKey('tray-$i')));
        await tester.tap(find.byKey(ValueKey('tray-$i')));
        await tester.pump();
      }

      for (final w in p.expectedWords) {
        await tray(p.tiles.indexOf(w));
      }
      await tray(p.tiles.length + p.chips.indexWhere((f) => f.index == 0));
      await tray(p.tiles.length + p.chips.indexWhere((f) => f.index == 1));
      await settleCard(tester);
      expect(voice.played, isEmpty, reason: 'ни плитка, ни значение в окне не звучат');

      await tapText(tester, 'Проверить');
      expect(results(probe), [SessionResult.passed]);
      expect(voice.played, ['p1.f2@1.0'], reason: 'после «Проверить» — фраза с этим значением');
      expect(voice.fallbacks, ['It hurts in his neck.']);
      await tester.pump(const Duration(milliseconds: 300));
      expect(probe.nexts, 0, reason: 'не раньше обычного такта');
      await tester.pump(const Duration(milliseconds: 400));
      expect(probe.nexts, 1, reason: 'фраза сказана — карточка уходит сама');

      final wrongVoice = QuietVoice();
      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong, voice: wrongVoice));
      for (final w in p.expectedWords) {
        await tray(p.tiles.indexOf(w));
      }
      await tray(p.tiles.length + p.chips.indexWhere((f) => f.index == 0));
      expect(wrongVoice.played, isEmpty);
      await tapText(tester, 'Проверить');
      expect(results(wrong), [SessionResult.failed]);
      expect(wrongVoice.played, ['p1.f2@1.0'], reason: 'неверно — звучит фраза, которую просили, а не собранная');
      await settleCard(tester);
      expect(wrong.nexts, 0, reason: '«Дальше» руками');
    });

    testWidgets('phrase_choose_back (32-3): a target phrase — options in the native language', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseChooseBack);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('It started three days ago.'), findsOneWidget);
      expect(find.text('ФРАЗА'), findsOneWidget);
      await tapText(tester, 'Началось три дня назад.');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      // A wrong option is another FRAME's sentence now, never another value of this window (FIX-2 §1).
      await tapText(tester, nbTypo('Температуры у него нет.'));
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    // RULE (SESSION-2a §2): «Choose the translation» plays its phrase by itself when the card opens, like the other
    // phrase trainers; the word's 31-3 plays its word. CATCHES: the owner's 17.09 pass — the line did not play.
    testWidgets('«Choose the translation» plays itself on open: phrase_choose_back and word_choose term_to_native', (tester) async {
      final phrase = QuietVoice();
      final phraseCard = fixtureCard(intermediate, SessionKind.phraseChooseBack);
      await pumpCard(tester, probeEnv(phraseCard, CardProbe(), voice: phrase));
      expect(phrase.played, isEmpty, reason: 'not before the card change has settled');
      await tester.pump(const Duration(milliseconds: 300));
      expect(phrase.played, ['${(phraseCard.payload as PhraseChooseBackPayload).audio?.ref ?? '-'}@1.0']);
      expect(phrase.fallbacks, ['It started three days ago.']);
      await settleCard(tester);
      expect(phrase.played, hasLength(1), reason: 'once');

      final word = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordChoose), CardProbe(), voice: word));
      await tester.pump(const Duration(milliseconds: 300));
      expect(word.fallbacks, ['fever']);
      await settleCard(tester);

      final reverse = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordChoose, skip: 1), CardProbe(), voice: reverse));
      await settleCard(tester);
      expect(reverse.played, isEmpty, reason: '31-4 has no sound on the question');
    });

    // RULE (SESSION-2b §1, кадр 32-4): the translation stands OVER the card in Literata 26 — it is the task; the grey
    // «Перевод» eyebrow under the card is gone.
    // CATCHES: the translation back inside the sheet, the eyebrow coming back, the translation set in the small
    // 15 body type.
    testWidgets('phrase_slot (32-4): a filler into the slot; the translation is the task over the card', (tester) async {
      // The beginner day is the one that deals `phrase_slot` on this scene: its third recognitions fit under the
      // stage's ceiling (DECISIONS п. 354), and the cycle's opener is seeded per frame.
      final card = fixtureCard(beginner, SessionKind.phraseSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe, day: beginner));
      expect(find.text(nbTypo('Вставь в окно')), findsOneWidget);
      expect(find.text('ПЕРЕВОД'), findsNothing);
      final native = tester.widget<Text>(find.byKey(const ValueKey('slot-native')));
      expect(native.data, nbTypo('У него болит плечо.'));
      expect(native.style, AppTextSession.question, reason: 'Literata 26 — the task itself');
      final sheet = tester.getRect(find.byType(SessionSheet).first);
      expect(tester.getRect(find.byKey(const ValueKey('slot-native'))).bottom, lessThan(sheet.top), reason: 'over the card');
      expect(frameLine(tester).slot, isNull);
      await tapText(tester, 'shoulder');
      expect(results(probe), [SessionResult.passed]);
      expect(frameLine(tester).slot, 'shoulder', reason: 'the filler stood in the slot');
      expect(frameLine(tester).look, SlotLook.sage);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong, day: beginner));
      await tapText(tester, 'neck');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    testWidgets('phrase_slot_listen (32-5): sound on open, the options are silent', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseSlotListen);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['p1@1.0']);
      expect(find.text(nbTypo('НА СЛУХ')), findsOneWidget);
      await tapText(tester, 'lower back');
      expect(results(probe), [SessionResult.passed]);
      expect(find.text(nbTypo('У него болит поясница.')), findsOneWidget);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'neck');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    // RULE (FIX-2 §5): `phrase_repeat` is ONE round on a frame WITHOUT a window — there is no second value to say
    // it with, and the second round the phone used to invent went with the rounds becoming the server's. The line is
    // on the screen, so it is said as it stands (`speech_mode: repeat`), and two misses close the card.
    // CATCHES: a round invented on the device, and a line passed with a content word said wrong.
    testWidgets('phrase_repeat (32-6): one round, the sample at 0.85×, the line said as it stands; two misses — skipped', (tester) async {
      final card = fixtureCard(beginner, SessionKind.phraseRepeat);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Скажи фразу вслух'), findsOneWidget);
      expect(find.byKey(const ValueKey('voice-round')), findsNothing, reason: 'one round has no header');
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played.single, endsWith('@0.85'));
      await sayDebug(tester, "he doesn't have a fever");
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await sayDebug(tester, "he doesn't have a favour");
      await sayDebug(tester, 'hello');
      expect(results(miss), [SessionResult.skipped]);
      await settleCard(tester);
    });

    // RULE (SESSION-2b §1, кадр 32-7): the chips only CHOOSE — one is always chosen and stands in the slot, and the
    // microphone is the only action; the native sentence, what is said and what is graded all follow the chosen chip.
    // CATCHES: an empty window on open (the old layout), a «tap to speak» caption under the chips, a card that keeps
    // grading the filler it came with after another chip was chosen.
    // RULE (наряд FIX-1 §6): «Скажи целиком» — ОДИН тренажёр окна. Значения встают в окно по очереди, ученик
    // говорит фразу целиком с каждым, последний круг — своё слово. Плашки не выбирают, а показывают состояние:
    // сказано · сейчас · впереди.
    // CATCHES: возврат выбора плашкой, круг, который не сменился после зачёта, и «своё окно» отдельным экраном.
    testWidgets('phrase_other_slot (32-7): every meaning goes through the window, the own word last', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final payload = card.payload as PhraseOtherSlotPayload;
      final rounds = payload.rounds;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text(nbTypo('Скажи фразу с каждым значением')), findsOneWidget);
      expect(find.text(nbTypo('Выбери, что вставить, и скажи фразу целиком')), findsNothing, reason: 'the chips choose nothing');
      expect(frameLine(tester).slot, 'lower back', reason: 'the first meaning of the card stands in the window');
      expect(chip(tester, 'chip-0').selected, isTrue, reason: '«now» is the chip of this round');
      expect(chip(tester, 'chip-0').onTap, isNull, reason: 'a state, not a button');
      expect(chip(tester, 'chip-1').selected, isFalse);
      expect(find.text(nbTypo('У него болит поясница.')), findsOneWidget);

      // Round 1: the phrase with the first meaning; another meaning is not this round's phrase.
      await sayDebug(tester, 'It hurts in his neck');
      await tester.pump();
      expect(find.text(nbTypo('не расслышал, ещё раз')), findsOneWidget, reason: 'the round is graded on ITS meaning');
      await sayDebug(tester, 'It hurts in his lower back');
      await tester.pump();
      expect(probe.answers, isEmpty, reason: 'a round is not an answer — the card has one');

      // …and the next meaning moves into the window by itself.
      await tester.pump(const Duration(milliseconds: 700));
      expect(frameLine(tester).slot, 'neck');
      expect(chip(tester, 'chip-1').selected, isTrue);
      expect(chip(tester, 'chip-0').trailing, isNotNull, reason: 'the meaning already said is checked');
      expect(find.text(nbTypo('У него болит шея.')), findsOneWidget);

      // …and so on through every round the stage's ceiling left the card (DECISIONS п. 354): the rounds are the
      // server's, and the window follows them.
      for (final round in rounds.skip(1)) {
        expect(frameLine(tester).slot, payload.frame.filler(round.fillerIndex)!.target);
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }

      // The last round — the learner's own word: no chip for it, the window is empty, the task says so.
      expect(find.text(nbTypo('а теперь со своим словом')), findsOneWidget);
      expect(frameLine(tester).slot, isNull);
      expect(frameLine(tester).look, SlotLook.empty);
      for (final round in rounds) {
        expect(chip(tester, 'chip-${round.fillerIndex}').selected, isFalse, reason: 'no meaning is current any more');
      }
      expect(probe.answers, isEmpty);

      await sayDebug(tester, 'It hurts in his elbow');
      await tester.pump();
      expect(results(probe), [SessionResult.passed], reason: 'one answer for the whole card');
      await settleCard(tester);
    });

    // RULE (SESSION-1b′, item 1): step 1 — three WHOLE sentences (`frames[].said`, no «___»), each with «listen» 28
    // (canvas 32-8; the sound came with SESSION-1e); a wrong one — ink outline, sage on the correct one, failed,
    // «Next»; the correct one — sage, and the slot opens in the sheet.
    // Step 2 — an empty brass slot, filler chips under the sheet; a chip substitutes and voices its filler; «Next»
    // becomes active → passed with mode chips and the filler index.
    // CATCHES: bare frames with «___» as options, a pass written on the frame alone, «Next» active before a chip.
    testWidgets('phrase_combine (32-8): whole sentences → the slot opens → a chip; «Next» only after the chip', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseCombine);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice, day: intermediate));
      expect(find.text(nbTypo('Что ты ответишь?')), findsOneWidget);
      // The partner's line as the fixture has it today (наряд CLIENT-22-1 §4) — a target line, printed as it came.
      expect(find.text((card.payload as PhraseCombinePayload).partnerLine!.textTarget), findsOneWidget);
      expect(find.text('РЕГИСТРАТОР · СПРАШИВАЕТ'), findsOneWidget);
      for (final sentence in _combineSentences) {
        expect(find.text(sentence), findsOneWidget, reason: 'the option is a whole sentence');
      }
      expect(find.textContaining('___'), findsNothing);
      await tester.tap(find.descendant(of: find.byKey(const ValueKey('frame-p5')), matching: find.byType(CardListen)));
      await tester.pump();
      expect(voice.played, contains('p5@1.0'), reason: '«listen» 28 plays the whole phrase');
      expect(probe.answers, isEmpty, reason: 'listening is not an answer');
      expect(tester.widget<SessionOption>(find.byKey(const ValueKey('frame-p5'))).look, OptionLook.idle);

      await tapText(tester, 'It hurts in his lower back.');
      expect(probe.answers, isEmpty, reason: 'the sentence alone is not the answer');
      expect(tester.widget<SessionOption>(find.byKey(const ValueKey('frame-p1'))).look, OptionLook.correct);
      expect(find.byKey(const ValueKey('chip-0')), findsNothing, reason: 'the slot opens after the sage beat');
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(frameLine(tester).slot, isNull, reason: 'step 2 — the slot is empty');
      expect(frameLine(tester).look, SlotLook.empty);
      expect(find.byKey(const ValueKey('chip-0')), findsOneWidget);
      expect(dockEnabled(tester, 'Дальше'), isFalse);

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.mode, 'chips');
      expect(probe.answers.single.response?.fillerIndex, 1);
      expect(voice.played.last, 'p1.f2@1.0', reason: 'the chip voices the phrase with its filler');
      expect(frameLine(tester).slot, 'neck');
      expect(dockEnabled(tester, 'Дальше'), isTrue);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong, day: intermediate));
      await tapText(tester, 'He will rest at home.');
      expect(results(wrong), [SessionResult.failed]);
      expect(tester.widget<SessionOption>(find.byKey(const ValueKey('frame-p5'))).look, OptionLook.wrong);
      expect(tester.widget<SessionOption>(find.byKey(const ValueKey('frame-p1'))).look, OptionLook.correct);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
      expect(find.byKey(const ValueKey('chip-0')), findsNothing, reason: 'a wrong sentence never opens the slot');
      expect(wrong.nexts, 0);
    });

    // CATCHES: options that lean on the day when the server already sent the sentence, a day dealt before SESSION-1e
    // (no `frames[].said`) whose options fall back to «___», and a frame with no whole phrase at all shown with an
    // ellipsis instead of not being drawn.
    testWidgets('phrase_combine: `frames[].said` needs no day; without it — the day\'s frame, never «___» or «…»', (tester) async {
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseCombine), CardProbe()));
      for (final sentence in _combineSentences) {
        expect(find.text(sentence), findsOneWidget);
      }
      await settleCard(tester);

      await pumpCard(tester, probeEnv(_withoutSaid(fixtureCard(intermediate, SessionKind.phraseCombine)), CardProbe(), day: intermediate));
      for (final sentence in _combineSentences) {
        expect(find.text(sentence), findsOneWidget, reason: 'the day\'s frame with its dialogue filler');
      }
      expect(find.textContaining('___'), findsNothing);
      await settleCard(tester);

      // Neither `said` nor the day: only the correct frame has a whole phrase (its dialogue chip) — the others are
      // not drawn.
      await pumpCard(tester, probeEnv(_withoutSaid(fixtureCard(intermediate, SessionKind.phraseCombine)), CardProbe()));
      expect(find.text('It hurts in his lower back.'), findsOneWidget);
      expect(find.byType(SessionOption), findsOneWidget);
      expect(find.textContaining('___'), findsNothing);
      expect(find.textContaining('…'), findsNothing);
      await settleCard(tester);
    });

    // RULE (наряды FIX-1 §6 and FIX-2 §5): «Скажи целиком» walks the SERVER'S rounds — the meanings first, the own
    // word last — and only the own word asks `…/judge`.
    // CATCHES: a judge asked on a meaning round, rounds invented on the device instead of read off the payload, and
    // the return of «своё окно» as a screen of its own.
    testWidgets('«Скажи целиком»: the server\'s rounds, and only the own word asks the judge', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'my elbow', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text(nbTypo('Скажи фразу с каждым значением')), findsOneWidget);
      expect(find.text('СВОЁ ОКНО'), findsNothing, reason: 'the eyebrow went with the separate trainer');
      expect(find.text('своё…'), findsNothing, reason: 'the chip with the microphone is gone');
      expect(frameLine(tester).slot, 'lower back', reason: 'the first meaning stands in the window');

      for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        expect(probe.judged, isEmpty, reason: 'a meaning is graded on the phone');
        await tester.pump(const Duration(milliseconds: 700));
      }

      // The own word: the judge — and the card's own answer, because the server does not close it (FIX-2 §5).
      expect(find.text(nbTypo('а теперь со своим словом')), findsOneWidget);
      await sayDebug(tester, 'It hurts in his elbow');
      await tester.pump();
      expect(probe.judged, ['It hurts in his elbow']);
      await settleCard(tester);
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
    });

    // RULE (FIX-2 §5): «круг „со своим словом" — тренировка: промах или „Пропустить" не создают копию и не
    // возвращают единицу». CATCHES: a skip on the own round sent as `skipped`, which the server reads as a lapse of
    // the frame after two attempts and sends the frame back tomorrow over a word the learner was invited to invent.
    testWidgets('«Скажи целиком»: the judge refuses — the reason stands under the microphone, and the card still passes', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про боль.', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      await sayDebug(tester, 'It hurts in his big noise');
      await tester.pump();
      await tester.pump();
      expect(probe.judged, ['It hurts in his big noise']);
      expect(find.text(nbTypo('Ты сказал не про боль.')), findsOneWidget);
      expect(probe.answers, isEmpty);

      await tapText(tester, 'Пропустить');
      expect(results(probe), [SessionResult.passed], reason: 'the value rounds are what the card is graded on');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // RULE (FIX-2 §2, инвариант «клиентская проверка не строже серверной»): круг своего слова спрашивает РОВНО то,
    // что спрашивает сервер перед вызовом модели, — слова каркаса, — и больше ничего. Что стоит в окне и стоит ли
    // там что-нибудь, говорит судья.
    // CATCHES: телефон, который отказывается звать судью, не услышав слов за каркасом: ученик остаётся без причины
    // отказа, а сервер такую попытку принял бы к рассмотрению.
    testWidgets('«Скажи целиком»: the own round asks the judge even when nothing was heard beyond the frame', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты не сказал, где болит.', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      for (final round in (card.payload as PhraseOtherSlotPayload).rounds) {
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      // The frame and nothing else: the window is empty, and the judge is still the one who says so.
      await sayDebug(tester, 'It hurts in his');
      await tester.pump();
      await tester.pump();
      expect(probe.judged, ['It hurts in his']);
      expect(find.text(nbTypo('Ты не сказал, где болит.')), findsOneWidget);
      await settleCard(tester);
    });

    // The other half of the same rule: a miss on a VALUE round is still a lapse of the frame (DECISIONS п. 327).
    testWidgets('«Скажи целиком»: two misses on a VALUE round are still a skip', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      for (var i = 0; i < 2; i++) {
        await sayDebug(tester, 'something else entirely');
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(results(probe), [SessionResult.skipped]);
      await settleCard(tester);
    });
  });

  // RULE (SESSION-1b′, item 5; the owner's sound map of 16.09): «correct» / «miss» on the 30-4 reactions (choice,
  // tiles) and on the verdict of the voice and of the slot judge; «mic_on» when a recording starts; walkthrough
  // cards are silent; «Sounds in the session» off — nothing is registered and nothing sounds.
  // CATCHES: a sound on «Got it», a recording without its start sound, a sound that ignores the switch.
  group('Session sounds (30-4)', () {
    // ЛОВИТ (живой проход 19.09): «возможно, там не один звук на правильный ответ». Ровно один — от нажатия до
    // ухода карточки, включая автопереход: второй звук на том же ответе и слышался бы наложением.
    testWidgets('choice: correct and miss sound once each', (tester) async {
      final sounds = recordSessionSounds(tester);
      final card = fixtureCard(intermediate, SessionKind.wordChoose, skip: 1);
      await pumpCard(tester, probeEnv(card, CardProbe()));
      await tapText(tester, 'X-ray');
      expect(sounds, [SessionSounds.correct], reason: 'один звук на ответ');
      await settleCard(tester);
      expect(sounds, [SessionSounds.correct], reason: 'и автопереход своего не добавляет');

      await pumpCard(tester, probeEnv(card, CardProbe()));
      await tapText(tester, 'heating pad');
      await settleCard(tester);
      expect(sounds, [SessionSounds.correct, SessionSounds.miss]);
    });

    testWidgets('walkthrough cards are silent: word_intro, phrase_intro', (tester) async {
      final sounds = recordSessionSounds(tester);
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordIntro), CardProbe()));
      await tapText(tester, 'Понятно');
      await settleCard(tester);

      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseIntro), CardProbe()));
      await tapText(tester, 'Дальше');
      await settleCard(tester);
      expect(sounds, isEmpty);
    });

    testWidgets('tiles, a recording and its voice pass, the judge — each sounds its own', (tester) async {
      final sounds = recordSessionSounds(tester);
      final assemble = fixtureCard(intermediate, SessionKind.wordAssemble);
      final p = assemble.payload as WordAssemblePayload;
      await pumpCard(tester, probeEnv(assemble, CardProbe()));
      for (final word in p.expected.reversed) {
        final key = ValueKey('tray-${p.tiles.indexOf(word)}');
        await tester.ensureVisible(find.byKey(key));
        await tester.tap(find.byKey(key));
        await tester.pump();
      }
      await tapText(tester, 'Проверить');
      await settleCard(tester);
      expect(sounds, [SessionSounds.miss]);

      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordRepeat), CardProbe()));
      await sayDebug(tester, 'lower back');
      await settleCard(tester);
      expect(sounds, [SessionSounds.miss, SessionSounds.micOn, SessionSounds.correct]);

      final rejected = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время.', attempts: 1);
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.speakAnswer), rejected));
      await sayDebug(tester, 'I have a headache');
      await tester.pump();
      expect(sounds, [SessionSounds.miss, SessionSounds.micOn, SessionSounds.correct, SessionSounds.micOn, SessionSounds.miss]);
      await settleCard(tester);
    });

    testWidgets('the switch off — the cards are silent', (tester) async {
      final sounds = recordSessionSounds(tester);
      SessionSounds.enabled = false;
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordChoose, skip: 1), CardProbe()));
      await tapText(tester, 'X-ray');
      await settleCard(tester);
      expect(sounds, isEmpty);
    });
  });
}

/// The combination's options as the fixture sends them (`frames[].said`), in the shown order.
const _combineSentences = ['He will rest at home.', 'Do we need an X-ray?', 'It hurts in his lower back.'];

/// [card] as a day dealt before SESSION-1e sends it: the combination's frames without `said`.
SessionCard _withoutSaid(SessionCard card) {
  final p = card.payload as PhraseCombinePayload;
  return SessionCard(
    id: card.id,
    stage: card.stage,
    position: card.position,
    kind: card.kind,
    unit: card.unit,
    returned: card.returned,
    payload: PhraseCombinePayload(
      sceneId: p.sceneId,
      exchange: p.exchange,
      partnerLine: p.partnerLine,
      frames: [for (final f in p.frames) CardFrameText(ref: f.ref, frameTarget: f.frameTarget, frameNative: f.frameNative)],
      correctFrame: p.correctFrame,
      chips: p.chips,
      correctFiller: p.correctFiller,
    ),
    attempts: card.attempts,
  );
}
