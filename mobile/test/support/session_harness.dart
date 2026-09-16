/// ХАРНЕСС КАРТОЧЕК СЕССИИ (наряд SESSION-1b): карточка фикстуры сервера → настоящий виджет вида → нажатия
/// человека → что карточка записала. Звук и микрофон — заглушки: голос ничего не играет, распознаватель
/// молчит (голос в тестах идёт через поле «что услышал» debug-сборки — той же дорогой, что финальный
/// транскрипт записи).
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/pronouncer.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/session/cards/card_host.dart';
import 'package:eng_std/features/plan/session/cards/card_kit.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/features/plan/session/session_voice.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

SessionDay sessionFixture(String name) => SessionDay.fromJson(
  jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>,
);

/// Первая карточка вида [kind] этапа [stage] фикстуры.
SessionCard fixtureCard(SessionDay day, SessionKind kind, {int skip = 0}) =>
    day.stages.expand((s) => s.cards).where((c) => c.kind == kind).skip(skip).first;

/// Голос, который ничего не играет и помнит, что его просили сыграть.
class QuietVoice extends SessionVoice {
  QuietVoice() : super(lines: LineAudioCache(directory: Directory.systemTemp), targetLang: 'en', pronouncer: _QuietPronouncer());

  final List<String> played = [];

  @override
  Future<void> warmUp() async {}

  @override
  Future<void> prepare(Iterable<CardAudio> audios) async {}

  @override
  Future<void> play(CardAudio? audio, {required String fallback, double rate = 1.0, Object? key}) async {
    played.add('${audio?.ref ?? '-'}@$rate');
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> release() async {}
}

class _QuietPronouncer extends Pronouncer {
  _QuietPronouncer() : super();

  @override
  Future<void> warmUp({required String targetLang}) async {}

  @override
  Future<void> speakText(String text, {required String targetLang, bool slow = false, bool awaitDone = false}) async {}

  @override
  Future<void> stop() async {}

  @override
  Future<void> release() async {}
}

/// Распознаватель, которого нет: запись не начинается (микрофон на симуляторе и в тестах мёртв).
class SilentRecognizer implements SpeechRecognizer {
  SilentRecognizer({this.available = true});

  final bool available;

  @override
  bool get isReady => available;

  @override
  Future<bool> get hasPermission async => available;

  @override
  Future<bool> prepare() async => available;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async => available ? const SpeechAttempt.silent() : const SpeechAttempt.unavailable();

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}
}

/// Что карточка сделала: ответы, «дальше», вопросы судье.
class CardProbe {
  final List<SessionAnswer> answers = [];
  final List<String> judged = [];
  int nexts = 0;
  bool noMic = false;

  /// Ответ судьи на следующий вопрос.
  SessionJudgeOutcome Function(String heard) verdict = (_) => const SessionJudgeOutcome(accepted: true, attempts: 1);
}

CardEnv probeEnv(SessionCard card, CardProbe probe, {QuietVoice? voice, String role = 'Регистратор', bool micAvailable = true}) => CardEnv(
  card: card,
  voice: voice ?? QuietVoice(),
  targetLang: 'en',
  localeId: 'en_US',
  role: role,
  submit: probe.answers.add,
  next: () async => probe.nexts++,
  judge: (heard) async {
    probe.judged.add(heard);
    return probe.verdict(heard);
  },
  makeMic: (expected, contextual) => SessionMic(
    recognizer: SilentRecognizer(available: micAvailable),
    localeId: 'en_US',
    expected: expected,
    contextualStrings: contextual,
  ),
  reportNoMic: (v) => probe.noMic = v,
  openSettings: () async {},
);

class _Auth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01TEST', name: 'Тест');
}

/// Карточка во весь экран 390 × 1000, анимации выключены, русская локаль. Каждый вызов — НОВАЯ карточка
/// (свой ключ): второй прогон той же карточки в одном тесте не наследует ответ первого — как в сессии, где
/// карточки ключуются порядковым номером.
Future<void> pumpCard(WidgetTester tester, CardEnv env) async {
  tester.view.physicalSize = const Size(390 * 2, 1000 * 2);
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [authControllerProvider.overrideWith(_Auth.new)],
      child: MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
        home: Scaffold(body: KeyedSubtree(key: UniqueKey(), child: Builder(builder: (_) => sessionCardFor(env)))),
      ),
    ),
  );
  await tester.pump();
}

/// Сказать голосом через поле «что услышал» и дождаться, пока запись «замрёт» и уйдёт на зачёт.
Future<void> sayDebug(WidgetTester tester, String text) async {
  await tester.enterText(find.byKey(const ValueKey('session-debug-heard')), text);
  await tester.testTextInput.receiveAction(TextInputAction.done);
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 450));
}

/// Доиграть таймеры карточки (автозвук, автопереход).
Future<void> settleCard(WidgetTester tester) async {
  await tester.pump(const Duration(milliseconds: 700));
  await tester.pump(const Duration(milliseconds: 700));
}

/// Нажать вариант по тексту.
Future<void> tapText(WidgetTester tester, String text) async {
  await tester.ensureVisible(find.text(text).last);
  await tester.tap(find.text(text).last);
  await tester.pump();
}

/// Строка кнопки существует и активна.
bool dockEnabled(WidgetTester tester, String label) {
  final finder = find.ancestor(of: find.text(label), matching: find.byType(GestureDetector));
  if (finder.evaluate().isEmpty) return false;
  return tester.widget<GestureDetector>(finder.first).onTap != null;
}
