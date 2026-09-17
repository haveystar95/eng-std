import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart' show CardListen;
import 'package:eng_std/features/plan/session/cards/phrase_cards.dart' show nativeTaskOf;
import 'package:eng_std/features/plan/session/parts/session_bits.dart';
import 'package:eng_std/features/plan/session/parts/session_choice.dart';
import 'package:eng_std/features/plan/session/parts/session_tiles.dart';
import 'package:eng_std/theme/theme.dart';

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
      expect(find.text('В РАЗГОВОРЕ'), findsOneWidget);
      expect(find.text('У него болит поясница.'), findsOneWidget);
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
      expect(find.text('Скажи, как слышишь — регистратор поймёт'), findsOneWidget);
      expect(find.text('тап — говорить'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['v1@0.85']);

      await sayDebug(tester, 'Lower back');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.heard, 'Lower back');
      expect(find.text('услышал — так же, как в записи'), findsOneWidget);
      expect(find.text('услышал'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1, reason: 'after a pass — auto-advance');

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await sayDebug(tester, 'lower');
      expect(miss.answers, isEmpty);
      expect(find.text('не расслышал, ещё раз'), findsOneWidget);
      await sayDebug(tester, 'neck');
      expect(results(miss), [SessionResult.skipped]);
      expect(miss.answers.single.attempts, 2);
      expect(find.text('Дальше'), findsOneWidget);
      await tapText(tester, 'Дальше');
      expect(miss.nexts, 1);
      await settleCard(tester);
    });

    // RULE (SESSION-2a §3): recording until the pause, not until the key — a covered phrase is graded after the 1 s
    // pause like any other, never on the partial result.
    // CATCHES: an early stop on coverage (the owner cut mid-sentence), and a pause that no longer closes a recording.
    testWidgets('recording until the pause, not until the key: word_repeat is graded after 1 s of silence, covered or not', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordRepeat);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      await enterHeard(tester, 'lowerback');
      await tester.pump(const Duration(milliseconds: 950));
      expect(probe.answers, isEmpty, reason: 'covered, but the pause has not run out — still recording');
      await tester.pump(const Duration(milliseconds: 60));
      expect(results(probe), [SessionResult.passed], reason: 'glued «lowerback» covers both words — graded after the pause');
      await settleCard(tester);

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await enterHeard(tester, 'lower');
      await tester.pump(const Duration(milliseconds: 950));
      expect(find.text('не расслышал, ещё раз'), findsNothing);
      expect(miss.answers, isEmpty);
      await tester.pump(const Duration(milliseconds: 60));
      expect(find.text('не расслышал, ещё раз'), findsOneWidget, reason: 'the same 1 s pause closes an uncovered recording');
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
      expect(find.text('Что ты услышал?'), findsOneWidget);
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
      expect(find.text('Собери из частей'), findsOneWidget);
      expect(find.text('по частям'), findsOneWidget);
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
      final card = fixtureCard(intermediate, SessionKind.wordInLine);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Вставь слово в окно'), findsOneWidget);
      expect(find.text('Боль острая или скорее ноющая?'), findsOneWidget);
      await tapText(tester, 'sharp');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'fever');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });
  });

  group('Phrases (32)', () {
    // RULE (SESSION-1b′, item 3): a lesson card — «Look and listen»; the frame opens with the filler said in the
    // dialogue; «this part can change» above neutral chips; a chip substitutes and voices its filler, the chip does
    // not darken — the slot flashes for 600 ms; «Got it» → passed.
    // CATCHES: an empty slot on open, a chip that turns ink as if it were an answer, a flash that never ends.
    testWidgets('phrase_intro (32-1): said filler in the slot, neutral chips substitute and voice, the slot flashes', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseIntro), probe, voice: voice));
      expect(find.text('Посмотри и послушай'), findsOneWidget);
      expect(find.text('КАРКАС'), findsOneWidget);
      expect(find.text('эту часть можно менять'), findsOneWidget);
      expect(frameLine(tester).slot, 'lower back', reason: 'the frame opens with the filler said in the dialogue');
      expect(frameLine(tester).look, SlotLook.filled);
      expect(find.text('У него болит поясница.'), findsOneWidget);
      for (final key in ['chip-0', 'chip-1', 'chip-2']) {
        expect(chip(tester, key).outlined, isTrue, reason: '$key is neutral');
        expect(chip(tester, key).selected, isFalse, reason: 'no chip is selected on open');
      }

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(voice.played, ['p1.f2@1.0']);
      expect(frameLine(tester).slot, 'neck');
      expect(frameLine(tester).look, SlotLook.highlight, reason: 'the slot flashes');
      expect(chip(tester, 'chip-1').selected, isFalse, reason: 'the chip does not darken');
      expect(find.text('У него болит шея.'), findsOneWidget);
      expect(find.text('ит хёртс ин хиз нэк'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(frameLine(tester).look, SlotLook.filled, reason: 'the flash lasts 600 ms');
      expect(frameLine(tester).slot, 'neck');

      await tapText(tester, 'Понятно');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    // RULE (SESSION-1b′, item 11): the frame intro plays the phrase as the dialogue says it once on open, then only by
    // «Listen» and the chips; a chip tapped before the pause is what the learner hears — nothing plays over it.
    // CATCHES: a silent intro, a replay by itself after a chip, an autoplay that talks over the chosen chip.
    testWidgets('phrase_intro: the phrase plays by itself once on open, then only by «Listen» and the chips', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseIntro);
      final said = (card.payload as PhraseIntroPayload).said;
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: voice));
      expect(voice.played, isEmpty);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['${said.audio!.ref}@1.0']);
      expect(voice.fallbacks, [said.textTarget], reason: 'without a file the phone reads the phrase');
      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      await tester.pump(const Duration(seconds: 3));
      expect(voice.played, ['${said.audio!.ref}@1.0', 'p1.f2@1.0'], reason: 'the chip plays; nothing plays by itself again');
      await settleCard(tester);

      final early = QuietVoice();
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: early));
      await tester.tap(find.byKey(const ValueKey('chip-2')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      expect(early.played, ['p1.f3@1.0'], reason: 'a chip before the pause — no autoplay over it');
      await settleCard(tester);
    });

    testWidgets('phrase_assemble (32-2): words, slot and filler — passed; another filler — failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseAssemble);
      final p = card.payload as PhraseAssemblePayload;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Собери фразу'), findsOneWidget);
      expect(find.text('У него болит шея.'), findsOneWidget);
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
      await tapText(tester, 'Началось вчера вечером.');
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

    testWidgets('phrase_slot (32-4): a filler into the slot; correct — the slot in sage', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Вставь в окно'), findsOneWidget);
      expect(find.text('У него болит плечо.'), findsOneWidget);
      expect(frameLine(tester).slot, isNull);
      await tapText(tester, 'shoulder');
      expect(results(probe), [SessionResult.passed]);
      expect(frameLine(tester).slot, 'shoulder', reason: 'the filler stood in the slot');
      expect(frameLine(tester).look, SlotLook.sage);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
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
      expect(find.text('НА СЛУХ'), findsOneWidget);
      await tapText(tester, 'lower back');
      expect(results(probe), [SessionResult.passed]);
      expect(find.text('У него болит поясница.'), findsOneWidget);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'neck');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    testWidgets('phrase_repeat (32-6): sample at 0.85×, coverage by coverage_min; two misses — skipped', (tester) async {
      final card = fixtureCard(beginner, SessionKind.phraseRepeat);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Скажи фразу вслух'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played.single, endsWith('@0.85'));
      await sayDebug(tester, 'it hurts in his back');
      expect(probe.answers, isEmpty, reason: '4 of 5 significant words — round 1 of 2 passed, the answer waits for round 2');
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      await sayDebug(tester, 'it hurts in his shoulder');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await sayDebug(tester, 'hello');
      await sayDebug(tester, 'lower back');
      expect(results(miss), [SessionResult.skipped]);
      await settleCard(tester);
    });

    // RULE (SESSION-1b′, item 2): nothing in the slot — an empty brass window; the whole native sentence under the
    // phrase with the slot's piece in bold ink; no native text inside the English line; the pass is the frame's
    // coverage AND every word of `slot_expected`, and the heard filler then fills the slot in sage.
    // CATCHES: the native value inside the English line (the old native word in the slot), a pass on the frame alone.
    testWidgets('phrase_other_slot (32-7): empty slot, the native sentence below; frame and slot graded together', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Скажи целиком — окно по-русски ниже'), findsOneWidget);
      expect(frameLine(tester).slot, isNull, reason: 'the slot is empty');
      expect(frameLine(tester).look, SlotLook.empty);
      expect(find.text('плечо'), findsNothing, reason: 'no native text inside the English line');
      final task = tester.widget<Text>(find.byKey(const ValueKey('other-slot-task'))).textSpan! as TextSpan;
      expect(task.toPlainText(), 'У него болит плечо.');
      final bold = task.children!.cast<TextSpan>().singleWhere((s) => s.style?.fontWeight == FontWeight.w700);
      expect(bold.text, 'плечо', reason: 'the slot piece is bold');
      expect(bold.style?.color, AppColors.ink);
      expect(task.style?.color, AppColors.tertiary, reason: 'the rest of the sentence is gray');

      await enterHeard(tester, 'it hurts in his neck');
      await tester.pump(const Duration(milliseconds: 950));
      expect(find.text('не расслышал, ещё раз'), findsNothing, reason: 'still recording until the pause');
      await tester.pump(const Duration(milliseconds: 60));
      expect(find.text('не расслышал, ещё раз'), findsOneWidget, reason: 'the pause; the frame alone does not pass');
      expect(probe.answers, isEmpty);

      await enterHeard(tester, 'It hurts in his shoulder');
      await tester.pump(const Duration(milliseconds: 950));
      expect(frameLine(tester).slot, isNull, reason: 'covered, still recording until the pause — nothing graded yet');
      await tester.pump(const Duration(milliseconds: 60));
      await tester.pump();
      expect(frameLine(tester).slot, 'shoulder', reason: 'graded after the pause; the heard filler fills the slot');
      expect(frameLine(tester).look, SlotLook.sage);
      expect(probe.answers, isEmpty, reason: 'round 1 of 2 — the answer waits for round 2');

      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(frameLine(tester).slot, isNull, reason: 'round 2 — the slot is empty again');
      final round2 = tester.widget<Text>(find.byKey(const ValueKey('other-slot-task'))).textSpan! as TextSpan;
      expect(round2.toPlainText(), 'У него болит шея.');
      await enterHeard(tester, 'It hurts in his neck');
      await tester.pump(const Duration(milliseconds: 1010));
      expect(results(probe), [SessionResult.passed]);
      await tester.pump();
      expect(frameLine(tester).slot, 'neck');
      await settleCard(tester);
    });

    test('phrase_other_slot: the native sentence — assembled from the slot value or split out of a whole sentence', () {
      final p = fixtureCard(intermediate, SessionKind.phraseOtherSlot).payload as PhraseOtherSlotPayload;
      expect(p.taskNative, 'плечо', reason: 'the fixture sends only the slot value');
      final assembled = nativeTaskOf(p);
      expect((assembled.before, assembled.piece, assembled.after), ('У него болит ', 'плечо', '.'));

      final whole = PhraseOtherSlotPayload(
        sceneId: p.sceneId,
        frame: p.frame,
        fillerIndex: p.fillerIndex,
        taskNative: 'У него болит плечо.',
        expectedText: p.expectedText,
        slotExpected: p.slotExpected,
        key: p.key,
        coverageMin: p.coverageMin,
      );
      final split = nativeTaskOf(whole);
      expect((split.before, split.piece, split.after), ('У него болит ', 'плечо', '.'));
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
      expect(find.text('Что ты ответишь?'), findsOneWidget);
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsOneWidget);
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

    // RULE (SESSION-1b′, item 4; SESSION-2a §3): the slot is EMPTY when the card opens; a known chip fills the slot
    // without darkening and leaves the microphone waiting for the whole phrase; the judge is asked after the pause.
    // CATCHES: a slot pre-filled on open, a chip that asks the judge by itself, a chip that turns ink.
    testWidgets('phrase_own_slot (32-9): empty slot; a chip fills it and waits for the phrase; the judge after the pause', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOwnSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'last night', result: SessionResult.passed, attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Выбери, что вставить, и скажи фразу целиком'), findsOneWidget);
      expect(find.text('тап — говорить'), findsNothing, reason: 'no caption under the chips (SESSION-2a §5)');
      expect(find.text('СВОЁ ОКНО'), findsOneWidget);
      expect(find.text('Началось ___.'), findsOneWidget);
      expect(find.text('ит стартид ___'), findsOneWidget);
      expect(frameLine(tester).slot, isNull, reason: 'the slot is empty on open');
      expect(frameLine(tester).look, SlotLook.empty);
      expect(find.text('своё…'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(frameLine(tester).slot, 'last night', reason: 'the chip fills the slot');
      expect(chip(tester, 'chip-1').selected, isFalse, reason: 'the chip does not darken');
      expect(probe.judged, isEmpty, reason: 'a chip is not an answer — the microphone waits for the whole phrase');

      await enterHeard(tester, 'It started last night');
      await tester.pump(const Duration(milliseconds: 950));
      expect(probe.judged, isEmpty, reason: 'the judge is asked after the pause');
      await tester.pump(const Duration(milliseconds: 60));
      await tester.pump();
      expect(probe.judged, ['It started last night']);
      expect(probe.answers, isEmpty, reason: 'the server records a judge-graded pass');
      expect(find.text('по смыслу ✓'), findsOneWidget);
      expect(frameLine(tester).look, SlotLook.sage);
      await settleCard(tester);
      expect(probe.nexts, 1);
    });

    testWidgets('phrase_own_slot (32-9): «your own…» — caret while recording, the heard words frozen until the verdict; rejected — «Skip» → skipped', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOwnSlot);
      final probe = CardProbe()
        ..judgeGate = Completer<void>()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время.', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));

      await tester.tap(find.byKey(const ValueKey('chip-own')));
      await tester.pump();
      expect(chip(tester, 'chip-own').selected, isTrue, reason: '«your own…» is selected while recording');
      expect(frameLine(tester).slot, isNull);
      expect(frameLine(tester).caret, isTrue, reason: 'an empty slot with a caret');

      await enterHeard(tester, 'It started yesterday');
      expect(frameLine(tester).slot, 'yesterday', reason: 'the words after the frame fill the slot live');
      await tester.pump(const Duration(milliseconds: 1010));
      await tester.pump();
      expect(probe.judged, ['It started yesterday']);
      expect(frameLine(tester).slot, 'yesterday', reason: 'frozen in the slot while the judge thinks');
      expect(frameLine(tester).caret, isFalse);
      expect(find.text('Ты сказал не про время.'), findsNothing);

      probe.judgeGate!.complete();
      await tester.pump();
      await tester.pump();
      expect(find.text('Ты сказал не про время.'), findsOneWidget);
      expect(find.text('Ещё раз'), findsOneWidget);
      await tapText(tester, 'Пропустить');
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  // RULE (SESSION-1b′, item 5; the owner's sound map of 16.09): «correct» / «miss» on the 30-4 reactions (choice,
  // tiles) and on the verdict of the voice and of the slot judge; «mic_on» when a recording starts; walkthrough
  // cards are silent; «Sounds in the session» off — nothing is registered and nothing sounds.
  // CATCHES: a sound on «Got it», a recording without its start sound, a sound that ignores the switch.
  group('Session sounds (30-4)', () {
    testWidgets('choice: correct and miss sound once each', (tester) async {
      final sounds = recordSessionSounds(tester);
      final card = fixtureCard(intermediate, SessionKind.wordChoose, skip: 1);
      await pumpCard(tester, probeEnv(card, CardProbe()));
      await tapText(tester, 'X-ray');
      await settleCard(tester);
      expect(sounds, [SessionSounds.correct]);

      await pumpCard(tester, probeEnv(card, CardProbe()));
      await tapText(tester, 'heating pad');
      await settleCard(tester);
      expect(sounds, [SessionSounds.correct, SessionSounds.miss]);
    });

    testWidgets('walkthrough cards are silent: word_intro, phrase_intro with a chip', (tester) async {
      final sounds = recordSessionSounds(tester);
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.wordIntro), CardProbe()));
      await tapText(tester, 'Понятно');
      await settleCard(tester);

      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseIntro), CardProbe()));
      await tester.tap(find.byKey(const ValueKey('chip-2')));
      await tester.pump();
      await tapText(tester, 'Понятно');
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
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseOwnSlot), rejected));
      await sayDebug(tester, 'It started a headache');
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
