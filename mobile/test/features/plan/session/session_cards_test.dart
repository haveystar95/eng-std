import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart' show CardListen;
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
    // RULE (SESSION-2b §1, кадр 32-1): a meaning is ALWAYS in the slot and its chip is ink; the reading and the
    // translation change with it; the grey «this part can change» and the «FRAME» eyebrow are gone.
    // CATCHES: a chip that does not darken (the old neutral row), a reading or a translation left at the filler the
    // card opened with, the eyebrow and the caption coming back.
    testWidgets('phrase_intro (32-1): the chosen chip is ink and stands in the slot; reading and translation follow it', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseIntro), probe, voice: voice));
      expect(find.text('Посмотри и послушай'), findsOneWidget);
      expect(find.text('КАРКАС'), findsNothing);
      expect(find.text('эту часть можно менять'), findsNothing);
      expect(frameLine(tester).slot, 'lower back', reason: 'the frame opens with the filler said in the dialogue');
      expect(frameLine(tester).look, SlotLook.filled);
      expect(find.text('У него болит поясница.'), findsOneWidget);
      expect(chip(tester, 'chip-0').selected, isTrue, reason: 'one chip is always chosen');
      for (final key in ['chip-1', 'chip-2']) {
        expect(chip(tester, key).selected, isFalse);
      }

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(voice.played, ['p1.f2@1.0']);
      expect(frameLine(tester).slot, 'neck');
      expect(frameLine(tester).look, SlotLook.filled, reason: 'the slot stays a brass window');
      expect(chip(tester, 'chip-1').selected, isTrue, reason: 'the chosen chip is ink');
      expect(chip(tester, 'chip-0').selected, isFalse);
      expect(find.text('У него болит шея.'), findsOneWidget);
      expect(find.text('ит хёртс ин хиз нэк'), findsOneWidget);

      await tapText(tester, 'Понятно');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    // RULE (SESSION-2b §1, кадр 32-1, third state): one meaning — no slot and no chips, the phrase whole.
    // CATCHES: a window with a single chip under it (there is nothing to choose), an empty window on a frame whose
    // only meaning the day names.
    testWidgets('phrase_intro (32-1): one meaning — the phrase whole, no slot and no chips', (tester) async {
      final one = fixtureCardEdited('day-doctor', 'phrase_intro', (p) {
        final frame = p['frame'] as Map<String, dynamic>;
        final slot = frame['slot'] as Map<String, dynamic>;
        slot['fillers'] = [(slot['fillers'] as List).first];
      });
      await pumpCard(tester, probeEnv(one, CardProbe()));
      expect(find.byType(SessionFrameText), findsOneWidget);
      expect(frameLine(tester).window, isFalse, reason: 'no window: there is nothing to change');
      expect(frameLine(tester).before, 'It hurts in his lower back.', reason: 'the phrase as the dialogue says it');
      expect(find.byKey(const ValueKey('chip-0')), findsNothing);
      expect(find.text('У него болит поясница.'), findsOneWidget);
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
      // A wrong option is another FRAME's sentence now, never another value of this window (FIX-2 §1).
      await tapText(tester, 'Температуры у него нет.');
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
      expect(find.text('Вставь в окно'), findsOneWidget);
      expect(find.text('ПЕРЕВОД'), findsNothing);
      final native = tester.widget<Text>(find.byKey(const ValueKey('slot-native')));
      expect(native.data, 'У него болит плечо.');
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
      expect(find.text('Скажи фразу с каждым значением'), findsOneWidget);
      expect(find.text('Выбери, что вставить, и скажи фразу целиком'), findsNothing, reason: 'the chips choose nothing');
      expect(frameLine(tester).slot, 'lower back', reason: 'the first meaning of the card stands in the window');
      expect(chip(tester, 'chip-0').selected, isTrue, reason: '«now» is the chip of this round');
      expect(chip(tester, 'chip-0').onTap, isNull, reason: 'a state, not a button');
      expect(chip(tester, 'chip-1').selected, isFalse);
      expect(find.text('У него болит поясница.'), findsOneWidget);

      // Round 1: the phrase with the first meaning; another meaning is not this round's phrase.
      await sayDebug(tester, 'It hurts in his neck');
      await tester.pump();
      expect(find.text('не расслышал, ещё раз'), findsOneWidget, reason: 'the round is graded on ITS meaning');
      await sayDebug(tester, 'It hurts in his lower back');
      await tester.pump();
      expect(probe.answers, isEmpty, reason: 'a round is not an answer — the card has one');

      // …and the next meaning moves into the window by itself.
      await tester.pump(const Duration(milliseconds: 700));
      expect(frameLine(tester).slot, 'neck');
      expect(chip(tester, 'chip-1').selected, isTrue);
      expect(chip(tester, 'chip-0').trailing, isNotNull, reason: 'the meaning already said is checked');
      expect(find.text('У него болит шея.'), findsOneWidget);

      // …and so on through every round the stage's ceiling left the card (DECISIONS п. 354): the rounds are the
      // server's, and the window follows them.
      for (final round in rounds.skip(1)) {
        expect(frameLine(tester).slot, payload.frame.filler(round.fillerIndex)!.target);
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 700));
      }

      // The last round — the learner's own word: no chip for it, the window is empty, the task says so.
      expect(find.text('а теперь со своим словом'), findsOneWidget);
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

    // RULE (наряды FIX-1 §6 and FIX-2 §5): «Скажи целиком» walks the SERVER'S rounds — the meanings first, the own
    // word last — and only the own word asks `…/judge`.
    // CATCHES: a judge asked on a meaning round, rounds invented on the device instead of read off the payload, and
    // the return of «своё окно» as a screen of its own.
    testWidgets('«Скажи целиком»: the server\'s rounds, and only the own word asks the judge', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'my elbow', attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Скажи фразу с каждым значением'), findsOneWidget);
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
      expect(find.text('а теперь со своим словом'), findsOneWidget);
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
      expect(find.text('Ты сказал не про боль.'), findsOneWidget);
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
      expect(find.text('Ты не сказал, где болит.'), findsOneWidget);
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
