import 'dart:async';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_grading_config.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/intro_card.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// ХАРНЕСС ТРЁХ КАРТОЧЕК С МИКРОФОНОМ — наряд SPEECH-2, Ч.6.
///
/// На симуляторе микрофона НЕТ по конструкции: `SFSpeechRecognizer` отвечает `error_unknown (300)`,
/// и это не чинится (DAY-GATE-1 назвал причину). Поэтому единственный способ увидеть все три
/// карточки в состояниях «твоя очередь» / «пишу» и все три вердикта — подставить транскрипт вместо
/// голоса. Здесь это делает [_ScriptedRecognizer]: переключатель наверху выбирает, что «услышит»
/// микрофон, дальше всё идёт настоящей дорогой — движок, склейка, тишина, судья.
///
/// РЕНДЕРЯТСЯ НАСТОЯЩИЕ ВИДЖЕТЫ ([SessionIntroCard], [SessionExerciseCard]) — не копии, иначе харнесс показывал бы сам себя.
///
///     flutter run --debug -d <simulator-udid> --target tool/speech_preview.dart
void main() {
  runApp(const _SpeechPreviewApp());
}

/// Что «слышит» микрофон. Три варианта на каждый вердикт — верно, почти, не то.
enum _Say {
  whole('вся реплика'),
  part('две трети'),
  wrong('мимо'),
  abbreviation('аббревиатура по буквам');

  const _Say(this.label);

  final String label;
}

/// Распознаватель, который отвечает тем, что выбрано наверху, и ничего не слышит на самом деле.
class _ScriptedRecognizer implements SpeechRecognizer {
  _ScriptedRecognizer(this.transcriptFor);

  /// Что отдать для конкретной цели — считает галерея, потому что у каждой карточки своя реплика.
  final String Function() transcriptFor;

  @override
  bool get isReady => true;

  @override
  Future<bool> get hasPermission async => true;

  @override
  Future<bool> prepare() async => true;

  /// Человек говорит ОДИН раз за ход. Движок переоткрывает микрофон, пока не набежит тишина, и
  /// плагин, повторяющий уже сказанное на каждом переоткрытии, склеил бы «reservation reservation»
  /// — артефакт харнесса, а не поведение iOS: каждая задача распознавания слышит только свой
  /// отрезок звука.
  bool _spoken = false;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async {
    if (_spoken) {
      await Future<void>.delayed(const Duration(seconds: 1));

      return const SpeechAttempt.silent();
    }
    _spoken = true;
    // Как живой плагин: сначала частичный результат, потом финальный кусок. Закрывает запись
    // движок — тишиной, — а не этот метод.
    final text = transcriptFor();
    await Future<void>.delayed(const Duration(milliseconds: 400));
    onPartial?.call(text);

    return SpeechAttempt.heard(text);
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async => _spoken = false;
}

/// Аутентификация харнесса: QA-аккаунт, чтобы служебная строка микрофона была на экране.
class _QaAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01QA', name: 'QA', qaTools: true);
}

/// Таблица нормализации, как её присылает сервер (`SpeechNormalization`, версия 1) — здесь ровно
/// столько строк, сколько нужно кадру с аббревиатурой.
const _speech = SpeechGradingConfig(
  normalizationVersion: '1',
  normalization: {'sequel': 'sql', 'a p i': 'api', 'h r': 'hr'},
);

const _introTerm = 'reservation';
const _runLine = 'I write SQL queries every day';

class _SpeechPreviewApp extends StatefulWidget {
  const _SpeechPreviewApp();

  @override
  State<_SpeechPreviewApp> createState() => _SpeechPreviewAppState();
}

class _SpeechPreviewAppState extends State<_SpeechPreviewApp> {
  _Say _say = _Say.whole;

  /// Какая карточка сейчас на экране — от неё зависит, что подставлять.
  String _target = _introTerm;

  String _transcript() {
    final words = _target.trim().split(RegExp(r'\s+'));
    switch (_say) {
      case _Say.whole:
        return _target;
      case _Say.part:
        return words.take((words.length * 2 / 3).ceil()).join(' ');
      case _Say.wrong:
        return 'something else entirely';
      case _Say.abbreviation:
        // «sequel» — то, что распознаватель пишет за SQL. Таблица возвращает это к канону.
        return _target.replaceAll('SQL', 'sequel');
    }
  }

  @override
  Widget build(BuildContext context) {
    return ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWith((ref) {
          final db = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(db.close);

          return db;
        }),
        speechRecognizerProvider.overrideWithValue(_ScriptedRecognizer(_transcript)),
        // ДЕВ-ДВЕРЬ QA открыта — иначе служебной строки микрофона (Ч.5) на карточке не увидеть, а
        // она и есть половина того, ради чего харнесс существует. Право приезжает с сервера полем
        // `qa_tools`; здесь оно подставлено, как подставлен и сам микрофон.
        authControllerProvider.overrideWith(_QaAuth.new),
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: DefaultTabController(
          length: 2,
          child: Builder(
            builder: (context) {
              final tabs = DefaultTabController.of(context);
              tabs.addListener(() {
                final target = switch (tabs.index) {
                  0 => _introTerm,
                  _ => _runLine,
                };
                if (target != _target) setState(() => _target = target);
              });

              return Scaffold(
                backgroundColor: AppColors.paper,
                appBar: AppBar(
                  backgroundColor: AppColors.paper,
                  title: const TabBar(
                    labelColor: AppColors.ink,
                    tabs: [
                      Tab(text: 'Повтори вслух'),
                      Tab(text: 'Скажи сам'),
                    ],
                  ),
                  bottom: PreferredSize(
                    preferredSize: const Size.fromHeight(44),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            for (final option in _Say.values)
                              Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: QuietButton(
                                  label: '${_say == option ? '● ' : ''}${option.label}',
                                  onPressed: () => setState(() => _say = option),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                body: const TabBarView(
                  children: [_IntroFrame(), _RunFrame()],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _IntroFrame extends StatelessWidget {
  const _IntroFrame();

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(AppSpacing.screenH),
    child: SessionIntroCard(
      card: SessionCard(
        termId: '01INTRO',
        mode: ExerciseMode.intro,
        type: 'word',
        prompt: 'бронь',
        answer: _introTerm,
        transcription: 'ˌrezərˈveɪʃn',
        example: 'I have a reservation for tonight.',
        exampleTranslation: 'У меня бронь на сегодня.',
        ladderStep: 0,
      ),
      speechLocaleId: 'en_US',
      autoPronounce: false,
      speech: _speech,
      onSpeak: (String text, {bool slow = false}) async {},
    ),
  );
}

class _RunFrame extends StatelessWidget {
  const _RunFrame();

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    padding: const EdgeInsets.all(AppSpacing.screenH),
    child: SessionExerciseCard(
      card: SessionCard(
        termId: '01RUN',
        mode: ExerciseMode.speaking,
        type: 'phrase',
        prompt: 'Я пишу SQL-запросы каждый день',
        answer: _runLine,
        ladderStep: 3,
      ),
      speechLocaleId: 'en_US',
      answerLang: 'en',
      autoPronounce: false,
      showDue: false,
      speech: _speech,
      onAnswered: (_) {},
      onSpeak: (text, {bool slow = false}) async {},
    ),
  );
}
