import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';

import '../../../support/session_harness.dart';

/// КАРТОЧКИ СЕССИИ 1b ПО ВИДАМ (наряд SESSION-1b, разд. 6): рендер из карточки фикстуры сервера и состояния
/// «верно / неверно / зачёт» — по одному виджету на каждый из 15 видов (слова 6, фразы 9).
void main() {
  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];

  group('Слова (31)', () {
    testWidgets('word_intro (31-1): слово, чтение, перевод, «В разговоре»; «Понятно» → passed', (tester) async {
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
      expect(voice.played, ['v1@1.0'], reason: 'слово звучит само при появлении');

      await tapText(tester, 'Понятно');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
    });

    testWidgets('word_repeat (31-2): образец на 0.85×; услышал — эхо и passed; две мимо — skipped, не failed', (tester) async {
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
      expect(probe.nexts, 1, reason: 'после верного — автопереход');

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

    testWidgets('word_repeat без микрофона: «Нужен микрофон», «Пропустить» → skipped с no_mic', (tester) async {
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

    testWidgets('word_choose native_to_term (31-4): верно — passed и автопереход; неверно — failed и «Дальше»', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordChoose);
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
      expect(wrong.nexts, 0, reason: 'после неверного — «Дальше» вручную');
    });

    testWidgets('word_choose term_to_native (31-3): слово цели со звуком — варианты на родном', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(fixtureCard(beginner, SessionKind.wordChoose), probe));
      expect(find.text('Выбери перевод'), findsOneWidget);
      expect(find.text('СЛОВО'), findsOneWidget);
      expect(find.text('X-ray'), findsOneWidget);
      await tapText(tester, 'рентген');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    testWidgets('word_listen (31-5): звук при открытии, четыре слова молчат; верно / неверно', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordListen);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Выбери, что услышал'), findsOneWidget);
      expect(find.text('Что ты услышал?'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['v2@1.0']);
      await tapText(tester, 'sharp');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'fever');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    testWidgets('word_assemble (31-6): плитки по порядку — passed; не по порядку — failed и «Дальше»', (tester) async {
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

    testWidgets('word_in_line (31-7): реплика с окном; верно — окно шалфеем и passed; неверно — failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.wordInLine);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Вставь слово в окно'), findsOneWidget);
      expect(find.text('Нет, температуры нет.'), findsOneWidget);
      await tapText(tester, 'fever');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'X-ray');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });
  });

  group('Фразы (32)', () {
    testWidgets('phrase_intro (32-1): чип подставляет наполнение и звучит; «Понятно» → passed', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(fixtureCard(intermediate, SessionKind.phraseIntro), probe, voice: voice));
      expect(find.text('Запомни фразу'), findsOneWidget);
      expect(find.text('КАРКАС'), findsOneWidget);
      expect(find.text('У него болит ___.'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(voice.played, ['p1.f2@1.0']);
      expect(find.text('У него болит шея.'), findsOneWidget);
      expect(find.text('ит хёртс ин хиз нэк'), findsOneWidget);
      await tapText(tester, 'Понятно');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    testWidgets('phrase_assemble (32-2): слова, окно и наполнение — passed; другое наполнение — failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseAssemble);
      final p = card.payload as PhraseAssemblePayload;
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Собери фразу'), findsOneWidget);
      expect(find.text('У него болит поясница.'), findsOneWidget);
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

      await build(0);
      expect(find.text('It'), findsOneWidget, reason: 'первое слово собранной строки — с заглавной');
      await tapText(tester, 'Проверить');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.fillerIndex, 0);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await build(1);
      await tapText(tester, 'Проверить');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    testWidgets('phrase_choose_back (32-3): фраза на цели — варианты на родном', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseChooseBack);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('He doesn\'t have a fever.'), findsOneWidget);
      expect(find.text('ФРАЗА'), findsOneWidget);
      await tapText(tester, 'Температуры у него нет.');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'Началось три дня назад.');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    testWidgets('phrase_slot (32-4): наполнение в окно; верно — окно шалфеем', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Вставь в окно'), findsOneWidget);
      expect(find.text('Началось три дня назад.'), findsOneWidget);
      await tapText(tester, 'three days ago');
      expect(results(probe), [SessionResult.passed]);
      expect(find.text('three days ago'), findsNWidgets(2), reason: 'наполнение встало в окно');
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'sharp');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    testWidgets('phrase_slot_listen (32-5): звук при открытии, варианты молчат', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseSlotListen);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['p3@1.0']);
      expect(find.text('НА СЛУХ'), findsOneWidget);
      await tapText(tester, 'sharp');
      expect(results(probe), [SessionResult.passed]);
      expect(find.text('Боль острая, когда он наклоняется.'), findsOneWidget);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'at home');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
    });

    testWidgets('phrase_repeat (32-6): образец 0.85×, покрытие по coverage_min; мимо дважды — skipped', (tester) async {
      final card = fixtureCard(beginner, SessionKind.phraseRepeat);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Скажи фразу вслух'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played.single, endsWith('@0.85'));
      await sayDebug(tester, 'it hurts in his back');
      expect(results(probe), [SessionResult.passed], reason: '4 из 5 значащих слов — выше 0.7');
      await settleCard(tester);

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(card, miss));
      await sayDebug(tester, 'hello');
      await sayDebug(tester, 'lower back');
      expect(results(miss), [SessionResult.skipped]);
      await settleCard(tester);
    });

    testWidgets('phrase_other_slot (32-7): каркас и окно раздельно; окно не прозвучало — не зачёт', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Скажи с другим окном'), findsOneWidget);
      expect(find.text('плечо'), findsOneWidget);
      expect(find.text('У него болит плечо.'), findsOneWidget);
      await sayDebug(tester, 'it hurts in his neck');
      expect(probe.answers, isEmpty);
      await sayDebug(tester, 'It hurts in his shoulder');
      expect(results(probe), [SessionResult.passed]);
      expect(find.text('shoulder'), findsOneWidget, reason: 'окно зачтено — в нём наполнение');
      await settleCard(tester);
    });

    testWidgets('phrase_combine (32-8): каркас → чип; «Дальше» неактивна до обоих выборов; неверный каркас — failed', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseCombine);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Ответь собеседнику'), findsOneWidget);
      expect(find.text('Did it start today, or earlier this week?'), findsOneWidget);
      expect(find.text('РЕГИСТРАТОР · СПРАШИВАЕТ'), findsOneWidget);
      await tapText(tester, 'It started ___.');
      expect(probe.answers, isEmpty);
      expect(dockEnabled(tester, 'Дальше'), isFalse);
      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.fillerIndex, 1);
      expect(dockEnabled(tester, 'Дальше'), isTrue);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(card, wrong));
      await tapText(tester, 'He will rest ___.');
      expect(results(wrong), [SessionResult.failed]);
      expect(find.text('Дальше'), findsOneWidget);
      await settleCard(tester);
    });

    testWidgets('phrase_own_slot (32-9): чип → судья с каркасом; зачтено — «по смыслу ✓»; нет — причина и «Пропустить» → skipped', (tester) async {
      final card = fixtureCard(intermediate, SessionKind.phraseOwnSlot);
      final probe = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: 'last night', result: SessionResult.passed, attempts: 1);
      await pumpCard(tester, probeEnv(card, probe));
      expect(find.text('Скажи своё'), findsOneWidget);
      expect(find.text('своё…'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      await tester.pump();
      expect(probe.judged, ['It started last night.']);
      expect(probe.answers, isEmpty, reason: 'зачёт судейского вида пишет сервер');
      expect(find.text('по смыслу ✓'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1);

      final rejected = CardProbe()
        ..verdict = (_) => const SessionJudgeOutcome(accepted: false, reasonNative: 'Ты сказал не про время.', attempts: 1);
      await pumpCard(tester, probeEnv(card, rejected));
      await sayDebug(tester, 'It started a headache');
      await tester.pump();
      expect(rejected.judged, ['It started a headache']);
      expect(find.text('Ты сказал не про время.'), findsOneWidget);
      expect(find.text('Ещё раз'), findsOneWidget);
      await tapText(tester, 'Пропустить');
      expect(results(rejected), [SessionResult.skipped]);
      expect(rejected.nexts, 1);
      await settleCard(tester);
    });
  });
}
